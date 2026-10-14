<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeRole;
use App\Models\Reimbursement\ReimbursementApprovalStep;
use App\Models\Reimbursement\ReimbursementSetting;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Pengaturan sub-modul Reimbursement: aturan dokumen + alur persetujuan.
 *
 * Halaman ini adalah KATUP PENGAMAN modul. Setiap kebijakan yang dapat MENOLAK
 * pengajuan — batas mundur, batas jumlah item, batas nominal bermode `block`,
 * penguncian periode — harus dapat dilonggarkan dari sini tanpa menunggu
 * perubahan kode. Pelajaran Keputusan D52: satu setelan yang salah pernah
 * mengunci modul Attendance dan hanya bisa dibuka lewat perubahan kode, karena
 * halaman pengaturannya belum pernah dibuat.
 *
 * Bagian alur persetujuan bekerja SUNGGUHAN, bukan gambaran. Menambah langkah
 * kedua di sini langsung berlaku pada dokumen berikutnya — tanpa migrasi dan
 * tanpa penyesuaian kode. Dokumen yang sedang berjalan tidak terpengaruh, karena
 * langkahnya sudah disalin ke masing-masing dokumen saat dibuat.
 */
class ReimbursementSettingController extends Controller
{
    /**
     * Halaman Settings memuat DUA bagian dengan hak akses terpisah (D180):
     *   - Approval Workflow  — slug `general.approval-workflow.reimbursement`
     *   - Reimbursement Rules — slug `general.settings.reimbursement`
     * Keduanya kini satu halaman (tab Settings pada hub Reimbursement); view
     * hanya merender bagian yang slug-nya dipegang pengguna.
     */
    public function edit(ReimbursementService $reimbursement)
    {
        $steps = ReimbursementApprovalStep::forReimbursement()
            ->with('role')
            ->orderBy('order_seq')
            ->get();

        return view('hr-general.reimbursement.settings', [
            'settings' => ReimbursementSetting::current(),
            'steps'    => $steps,
            'roles'    => EmployeeRole::orderBy('name')->get(['id', 'name']),

            // Berapa dokumen berjalan yang akan terkena bila langkah baru
            // ditambahkan — disebut SEBELUM tombolnya ditekan.
            'openCount' => $reimbursement->countOpenRequestsBefore(
                (int) $steps->max('order_seq') + 1
            ),

            // Kop surat dari menu Letter Templates, untuk cetakan dokumen.
            'letterheads' => \App\Models\Letterhead::orderBy('name')->get(['id', 'name']),

            // karyawan => nama posisinya; ditampilkan di bawah kolom Position
            // untuk nama yang dipilih.
            'employeeRoles' => DB::table('employee_role_assignment')
                ->join('employee_role', 'employee_role.id', '=', 'employee_role_assignment.role_id')
                ->select('employee_role_assignment.employee_id', 'employee_role.name')
                ->get()
                ->groupBy('employee_id')
                ->map(fn ($rows) => $rows->pluck('name')->unique()->values()->all())
                ->all(),

            // posisi => id karyawan pemegangnya; dipakai daftar nama langkah.
            'roleMembers' => DB::table('employee_role_assignment')
                ->select('role_id', 'employee_id')
                ->get()
                ->groupBy('role_id')
                ->map(fn ($rows) => $rows->pluck('employee_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
                ->all(),

            // Hanya karyawan aktif, dan hanya kolom yang benar-benar dipakai —
            // 209 baris berisi seluruh kolom membuat halaman berat tanpa manfaat.
            // Nama LENGKAP (first_name + last_name), bukan nama panggilan.
            'employees' => Employee::with('basicData:basic_data_id,employee_id,nick_name,first_name,last_name,department')
                ->where('is_active', 1)
                ->get(['employee_id', 'eci'])
                ->sortBy(fn ($e) => mb_strtolower($e->fullNameLabel()))
                ->values(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $this->validatePayload($request);

        // Gerbang yang tidak dapat diungkapkan lewat aturan validasi biasa:
        // menuntut tautan bukti sementara daftar host-nya kosong berarti TIDAK
        // ADA tautan yang pernah lolos — modul terkunci oleh setelannya sendiri.
        // Persis kegagalan yang halaman ini ada untuk mencegahnya (D52).
        if ($data['require_supporting_url'] && $data['supporting_url_allowed_hosts'] === '') {
            return back()->withInput()->with('error',
                'Supporting documents are required, so the allowed host list cannot be empty — '
                . 'no link would ever pass. Add at least one host, or turn the requirement off.');
        }

        $settings = ReimbursementSetting::current();
        $before   = $settings->only(array_keys($data));

        $data['updated_by'] = session('user.id');

        $settings->update($data);

        // Cache-nya per-request. Tanpa ini, pembacaan berikutnya DI REQUEST YANG
        // SAMA — termasuk saat me-render ulang halaman — masih memakai nilai lama.
        ReimbursementSetting::forgetCache();

        // Kebijakan reimbursement menentukan apakah karyawan dapat mengajukan
        // hari itu. Perubahannya dicatat supaya bila besok ada keluhan,
        // penyebabnya dapat ditelusuri ke perubahan ini (pola Keputusan D53).
        Log::info('Reimbursement settings updated.', [
            'actor_id' => session('user.id'),
            'before'   => $before,
            'after'    => $settings->fresh()->only(array_keys($before)),
        ]);

        return back()->with('success', 'Reimbursement settings saved.');
    }

    // ── Alur persetujuan ────────────────────────────────────────────────────

    /**
     * Simpan SELURUH alur sekaligus: baris yang ada diperbarui, baris baru
     * dibuat, baris yang hilang dari form dihapus, dan urutannya mengikuti
     * urutan baris di layar.
     *
     * Satu tombol Save untuk semuanya — bukan satu per baris — supaya yang
     * tersimpan selalu satu keadaan utuh. Dokumen yang sedang berjalan tidak
     * terpengaruh: langkahnya sudah disalin ke dokumennya saat dibuat. Langkah
     * BARU hanya menyentuhnya bila `apply_to_open` dicentang
     * (ReimbursementService::applyStepToOpenRequests).
     */
    public function syncSteps(Request $request, ReimbursementService $reimbursement)
    {
        $rows = array_values((array) $request->input('steps', []));

        if ($rows === []) {
            return back()->withInput()->with('error', 'The workflow needs at least one step.');
        }

        $errors = [];
        $built  = [];

        foreach ($rows as $i => $row) {
            $result = $this->stepData((array) $row);

            if (is_string($result)) {
                $errors['steps.' . $i] = 'Step ' . ($i + 1) . ': ' . $result;
                continue;
            }

            $built[] = ['id' => (int) ($row['id'] ?? 0), 'data' => $result];
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        if (!collect($built)->contains(fn ($b) => $b['data']['is_active'])) {
            return back()->withInput()->with('error',
                'At least one approval step must stay active, otherwise new documents cannot be reviewed by anyone.');
        }

        $created = collect();

        DB::transaction(function () use ($built, &$created) {
            $existing = ReimbursementApprovalStep::forReimbursement()->get()->keyBy('id');

            // Pindahkan dulu ke rentang sementara supaya urutan baru tidak
            // bentrok. Kolomnya TINYINT (maks. 255), jadi nilainya dibuat
            // dari 200 ke atas — bukan dari id langkah.
            $temp = 200;
            foreach ($existing as $step) {
                $step->update(['order_seq' => $temp++]);
            }

            $keep = [];

            foreach ($built as $index => $item) {
                $payload = $item['data'] + ['order_seq' => $index + 1];

                if ($item['id'] && $existing->has($item['id'])) {
                    $existing[$item['id']]->update($payload);
                    $keep[] = $item['id'];
                } else {
                    $created->push(ReimbursementApprovalStep::create($payload + [
                        'module' => ReimbursementApprovalStep::MODULE_REIMBURSEMENT,
                    ]));
                }
            }

            // Baris yang dibuang dari form dihapus. Dokumen berjalan memegang
            // salinannya sendiri, jadi tidak terpengaruh.
            $existing->keys()->diff($keep)->each(fn ($id) => $existing[$id]->delete());
        });

        $note = '';

        if ($request->boolean('apply_to_open') && $created->isNotEmpty()) {
            $applied = 0;

            foreach ($created as $step) {
                $applied += $reimbursement->applyStepToOpenRequests($step, (int) session('user.id'))['applied'];
            }

            $note = $applied > 0 ? " The new step was also applied to {$applied} document(s) in progress." : '';
        }

        return redirect()
            ->route('general.reimbursement.settings.edit')
            ->with('success', 'Approval workflow saved successfully.' . $note)
            ->with('workflow_saved', true);
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'company_name'                  => ['required', 'string', 'max:150'],
            'max_backdate_days'             => ['required', 'integer', 'between:0,3650'],
            'max_items_per_request'         => ['required', 'integer', 'between:0,255'],
            'min_item_amount'               => ['required', 'numeric', 'between:0,999999999999'],
            'max_request_amount'            => ['required', 'numeric', 'between:0,999999999999'],
            'over_limit_policy'             => ['required', Rule::in(ReimbursementSetting::OVER_LIMIT_POLICIES)],
            'require_title_min_chars'       => ['required', 'integer', 'between:0,200'],
            'supporting_url_allowed_hosts'  => ['nullable', 'string', 'max:255'],
            'locked_period_policy'          => ['required', Rule::in(ReimbursementSetting::LOCK_POLICIES)],
            'receipt_max_age_days'          => ['required', 'integer', 'between:0,3650'],
            'receipt_age_policy'            => ['required', Rule::in(ReimbursementSetting::OVER_LIMIT_POLICIES)],
            'letterhead_id'                 => ['nullable', 'integer', 'exists:letterheads,id'],
            'self_approval_fallback_role_id' => ['nullable', 'integer', 'exists:employee_role,id'],
        ], [
            'max_backdate_days.between'      => 'The backdate window must be between 0 (unlimited) and 3650 days.',
            'max_items_per_request.between'  => 'The item limit must be between 0 (unlimited) and 255.',
            'max_request_amount.between'     => 'The amount limit must be 0 (unlimited) or a positive number.',
            'require_title_min_chars.between' => 'The title length must be between 0 and 200 characters.',
        ]);

        // Checkbox yang tidak dicentang TIDAK dikirim browser sama sekali, jadi
        // nilainya harus ditetapkan di sini — bukan diambil dari $validated.
        $validated['use_branch_name_in_header']    = $request->boolean('use_branch_name_in_header');
        $validated['allow_future_date']            = $request->boolean('allow_future_date');
        $validated['require_supporting_url']       = $request->boolean('require_supporting_url');
        $validated['require_receipt_no']           = $request->boolean('require_receipt_no');
        $validated['allow_self_approval']          = $request->boolean('allow_self_approval');
        $validated['allow_approver_adjust_amount'] = $request->boolean('allow_approver_adjust_amount');

        $validated['supporting_url_allowed_hosts'] = $this->normaliseHosts(
            $validated['supporting_url_allowed_hosts'] ?? ''
        );

        return $validated;
    }

    /**
     * Rapikan daftar host yang boleh dipakai pada tautan bukti.
     *
     * Orang menempelkan URL utuh ("https://drive.google.com/file/d/..."), bukan
     * nama host. Membiarkannya apa adanya membuat pencocokan host tidak pernah
     * cocok, dan pengguna tidak punya cara menebak apa yang salah — halamannya
     * menerima masukannya tanpa keluhan. Jadi dibersihkan di sini: skema, jalur,
     * `www.`, dan huruf besar dibuang; duplikat dihilangkan.
     */
    private function normaliseHosts(string $raw): string
    {
        return collect(explode(',', $raw))
            ->map(function ($host) {
                $host = strtolower(trim($host));
                $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);   // buang skema
                $host = explode('/', $host)[0];                                // buang jalur
                $host = preg_replace('#^www\.#', '', $host);

                return trim($host);
            })
            ->filter()
            ->unique()
            ->implode(',');
    }

    /**
     * Satu baris form menjadi atribut langkah, atau pesan galat (string).
     *
     * `mandatory` dicentang = orangnya TETAP (nama/posisi di baris ini yang
     * bertindak, pemohon tidak memilih). Tidak dicentang = pemohon WAJIB memilih
     * dari kandidat langkah ini. Disimpan sebagai `requester_selectable`.
     *
     * Orangnya: bila ada nama yang dicentang, langkah itu untuk nama-nama itu;
     * bila tidak, untuk SEMUA pemegang posisi yang dipilih.
     *
     * @return array<string, mixed>|string
     */
    private function stepData(array $row)
    {
        $signsAs = $row['signs_as'] ?? '';

        if (!array_key_exists($signsAs, ReimbursementApprovalStep::SIGNS_AS_LABELS)) {
            return 'choose what it signs as — Accounting, Cashier or Approver.';
        }

        $names = array_values(array_unique(array_filter(array_map('intval', (array) ($row['approver_employee_ids'] ?? [])))));
        $role  = (int) ($row['approver_role_id'] ?? 0);

        if ($names !== [] && Employee::whereIn('employee_id', $names)->count() !== count($names)) {
            return 'one of the chosen names no longer exists.';
        }

        if ($role && !EmployeeRole::whereKey($role)->exists()) {
            return 'the chosen position no longer exists.';
        }

        if ($names === [] && !$role) {
            return 'choose a position, or at least one name.';
        }

        // Nama dipilih tanpa posisi: bila semua orangnya berbagi TEPAT satu
        // posisi, itulah posisi langkah ini — supaya kolom Position terisi.
        if (!$role && $names !== []) {
            $shared = null;

            foreach ($names as $id) {
                $mine   = DB::table('employee_role_assignment')->where('employee_id', $id)->pluck('role_id')->all();
                $shared = $shared === null ? $mine : array_values(array_intersect($shared, $mine));
            }

            if (is_array($shared) && count($shared) === 1) {
                $role = (int) $shared[0];
            }
        }

        $selectable = empty($row['mandatory']);

        if ($selectable && $names === []
            && !DB::table('employee_role_assignment')->where('role_id', $role)->exists()) {

            return 'nobody holds that position, so the requester would have nobody to choose.';
        }

        return [
            'name'                  => ReimbursementApprovalStep::SIGNS_AS_LABELS[$signsAs],
            'signs_as'              => $signsAs,
            'requester_selectable'  => $selectable,
            'approver_type'         => $names !== [] ? ReimbursementApprovalStep::TYPE_EMPLOYEE : ReimbursementApprovalStep::TYPE_ROLE,
            // Posisi tetap disimpan walau nama dipilih, supaya layar Settings
            // menampilkan kembali pilihan yang tersimpan.
            'approver_role_id'      => $role ?: null,
            'approver_employee_ids' => $names !== [] ? $names : null,
            'is_active'             => !empty($row['is_active']),
        ];
    }
}
