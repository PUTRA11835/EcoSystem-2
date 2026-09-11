<?php

namespace App\Http\Controllers\HR_General\Concerns;

use App\Models\CashAdvance\CashAdvanceSetting;
use App\Models\Employee;
use Illuminate\Http\Request;

/**
 * Bagian yang dipakai BERSAMA oleh sisi ESS dan sisi HR sebuah dokumen CA.
 *
 * 🔴 KENAPA TRAIT, BUKAN DISALIN.
 *
 * Form Cash Advance sekarang punya TIGA pintu masuk — "Submit CA" (ESS),
 * "New CA" (HR, atas nama karyawan), dan "Edit CA" — tetapi hanya SATU
 * aturan. Sub-modul Purchase Request menyalin helper yang setara ke dua
 * controller dan menuliskannya apa adanya di komentarnya sendiri
 * ("bentuknya sama dengan ..."). Salinan seperti itu tidak salah pada hari
 * ia ditulis; ia salah enam bulan kemudian, ketika satu sisi diperbaiki dan
 * sisi lain tidak — dan yang menyimpang di sini adalah validasi dokumen yang
 * mengeluarkan uang perusahaan.
 *
 * Yang TIDAK ada di sini: apa pun yang jawabannya berbeda antara ESS dan HR.
 * Kepemilikan dokumen, hak `.manage`, dan `employee_id` dari sesi tetap
 * tinggal di controllernya masing-masing, karena di situlah perbedaannya
 * memang nyata.
 */
trait BuildsCashAdvanceForms
{
    /**
     * Data dropdown "Approver" pada langkah PERTAMA (Keputusan D138).
     *
     * 🔴 Ini perbaikan atas cacat aplikasi acuan: di sana server menuntut
     * `approver_employee_id` sementara formnya tidak pernah merender
     * kontrolnya, sehingga pengguna menerima galat yang tidak punya jalan
     * keluar. Di sini kontrolnya dirender HANYA bila langkahnya memang
     * menawarkan pilihan — kalau tidak, fieldnya tidak dikirim dan tidak
     * divalidasi.
     *
     * Hanya langkah pertama yang ditanyakan. Langkah kedua dan seterusnya
     * tidak: pemohon tidak dapat menilai siapa yang pantas meninjau pada tahap
     * yang belum ia capai, dan service mengisinya dari kandidat langkahnya.
     *
     * @return array{firstStep: ?object, chooseApprover: bool, approverCandidates: array}
     */
    private function approverChoice($steps): array
    {
        $first = $steps->first();

        if (! $first || ! $first->offersChoice()) {
            return [
                'firstStep'          => $first,
                'chooseApprover'     => false,
                'approverCandidates' => [],
            ];
        }

        $candidates = Employee::with('basicData:basic_data_id,employee_id,nick_name')
            ->whereIn('employee_id', $first->candidateEmployeeIds())
            ->where('is_active', 1)
            ->get(['employee_id', 'eci'])
            ->map(fn (Employee $e) => [
                'id'   => (int) $e->employee_id,
                'name' => $e->basicData?->nick_name ?? $e->eci,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        // Kandidat yang seluruhnya sudah non-aktif membuat dropdown lahir
        // kosong — dan dokumen yang lahir dari situ tidak punya jalan keluar.
        // Jatuh kembali ke perilaku non-pilihan; service tetap menolak bila
        // memang tidak ada kandidat, dengan pesan yang menyebut langkahnya.
        return [
            'firstStep'          => $first,
            'chooseApprover'     => $candidates !== [],
            'approverCandidates' => $candidates,
        ];
    }

    private function nameOf(int $employeeId): string
    {
        $employee = Employee::with('basicData')->find($employeeId);

        return $employee?->basicData?->nick_name ?? $employee?->eci ?? (session('user.name') ?? '—');
    }

    /**
     * Karyawan aktif untuk dropdown "New CA" / "New CAR".
     *
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function employeeOptions()
    {
        return Employee::with('basicData:basic_data_id,employee_id,nick_name,department')
            ->where('is_active', 1)
            ->get(['employee_id', 'eci'])
            ->sortBy(fn (Employee $e) => $e->basicData?->nick_name ?? $e->eci)
            ->values();
    }

    /**
     * Validasi badan request form CA — SATU aturan untuk ketiga pintu masuk.
     *
     * @param  bool  $needsEmployee  true hanya pada "New CA" sisi HR
     * @return array<string, mixed>
     */
    private function validateCashAdvancePayload(Request $request, bool $needsEmployee = false): array
    {
        $settings = CashAdvanceSetting::current();
        $minDesc  = (int) $settings->require_description_min_chars;

        $rules = [
            'request_date'    => ['required', 'date'],
            'request_date_to' => ['nullable', 'date'],
            'description'     => ['required', 'string', 'min:' . $minDesc, 'max:255'],

            // 🔴 `amount` divalidasi sebagai STRING, bukan `numeric`.
            //
            // Form acuan menampilkan "100.000", dan aturan `numeric` menolaknya
            // mentah-mentah. Penguraiannya dikerjakan
            // CashAdvanceAmountService::parseAmount() yang membaca titik sebagai
            // pemisah ribuan; batas nominalnya ditegakkan SERVICE, bukan di
            // sini, supaya aturan yang sama berlaku untuk pengajuan mandiri,
            // "New CA", dan Edit.
            'amount'          => ['required', 'string', 'max:30'],

            'detail_url'      => ['nullable', 'url', 'max:500'],
            'notes'           => ['nullable', 'string', 'max:2000'],

            'cost_center_type'   => ['nullable', 'string', 'max:20'],
            'charged_branch_id'  => ['nullable', 'integer'],
            'charged_project_id' => ['nullable', 'integer'],

            // Dikirim HANYA bila langkah pertama menawarkan pilihan.
            'approver_ids'   => ['nullable', 'array'],
            'approver_ids.*' => ['integer', 'exists:employee,employee_id'],
        ];

        // 🔴 Hanya sisi HR. Pada sisi ESS, `employee_id` TIDAK PERNAH dibaca
        // dari badan request — selalu dari sesi. Memvalidasinya di sana justru
        // menyiratkan field itu boleh dikirim.
        if ($needsEmployee) {
            $rules['employee_id'] = ['required', 'integer', 'exists:employee,employee_id'];
        }

        return $request->validate($rules, [
            'description.min'      => "Please describe the request in at least {$minDesc} characters.",
            'amount.required'      => 'Please enter the amount.',
            'detail_url.url'       => 'The supporting link must be a valid URL.',
            'employee_id.required' => 'Please choose the employee this cash advance is for.',
        ]);
    }

    /**
     * Validasi badan request form CAR — SATU aturan untuk ketiga pintu masuk.
     *
     * Sengaja tinggal di trait yang sama dengan versi CA-nya: keduanya dipakai
     * empat controller sekaligus, dan `approverChoice()` serta `nameOf()` yang
     * mereka pakai bersama hanya boleh ada satu kali. Memecahnya jadi dua trait
     * akan menabrakkan nama method yang sama.
     *
     * @return array<string, mixed>
     */
    private function validateCashAdvanceReportPayload(Request $request): array
    {
        $rules = [
            'report_date'  => ['required', 'date'],
            'description'  => ['required', 'string', 'min:5', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:2000'],

            // 🔴 Baris realisasi memakai KUNCI ACAK (`items[<uuid>][amount]`),
            // bukan indeks berurutan: baris ditambah/dihapus lewat JavaScript,
            // dan dengan indeks, menghapus baris di tengah membuat sisanya
            // bergeser sehingga pesan validasi menunjuk baris yang salah.
            // Karena kuncinya acak, aturannya memakai wildcard.
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.expense_date'     => ['nullable', 'date'],
            'items.*.description'      => ['nullable', 'string', 'max:200'],
            'items.*.receipt_no'       => ['nullable', 'string', 'max:60'],
            // Nominal per baris juga dibaca parseAmount() di service — jadi
            // divalidasi sebagai string, bukan numeric.
            'items.*.amount'           => ['nullable', 'string', 'max:30'],
            'items.*.receipt_url'      => ['nullable', 'string', 'max:500'],
            'items.*.cost_center_type' => ['nullable', 'string', 'max:20'],
            'items.*.branch_id'            => ['nullable', 'integer'],
            'items.*.delivery_project_id'  => ['nullable', 'integer'],

            'approver_ids'   => ['nullable', 'array'],
            'approver_ids.*' => ['integer', 'exists:employee,employee_id'],
        ];

        return $request->validate($rules, [
            'items.required'   => 'Add at least one expense line.',
            'description.min'  => 'Please describe the report in at least 5 characters.',
        ]);
    }
}
