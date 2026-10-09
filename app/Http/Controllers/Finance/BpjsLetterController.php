<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Letterhead;
use App\Models\LetterTypeSetting;
use App\Models\Letters\Letter;
use App\Services\Letters\LetterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Finance → BPJS → Letters: membuat SATU surat penonaktifan BPJS Kesehatan (PDF 2 halaman: surat + lampiran) untuk
 * banyak karyawan sekaligus. Surat disimpan lewat modul Letters yang sudah ada (LetterService::generate): nomor surat
 * diambil dari penomoran keluar yang sama, kop surat mengikuti Settings → letterhead untuk jenis `bpjs_deactivation`,
 * dan surat muncul di Letter Register. Data karyawan disalin ke surat (snapshot).
 *
 * Kandidat = karyawan yang punya nomor BPJS Kesehatan di Master → Employee → Identification (aktif maupun tidak aktif;
 * yang tidak aktif ditampilkan lebih dulu karena umumnya merekalah yang dinonaktifkan).
 * Izin: `menu:finance.bpjs.letters`; buat = `…,create`; batalkan (void) = `…,delete`.
 */
class BpjsLetterController extends Controller
{
    public const TEMPLATE = 'bpjs_deactivation';

    /** key => [Indonesian, English] */
    public const REASONS = [
        'resignation' => ['Mengundurkan diri', 'Resignation'],
        'contract_end' => ['Berakhirnya kontrak kerja', 'End of contract'],
        'termination' => ['Pemutusan hubungan kerja', 'Termination'],
        'retirement' => ['Pensiun', 'Retirement'],
        'death' => ['Meninggal dunia', 'Death'],
        'other' => ['Lainnya', 'Other'],
    ];

    public function __construct(private readonly LetterService $letters)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $branch = (string) $request->query('branch', '');
        $status = in_array($request->query('status'), ['inactive', 'active'], true) ? $request->query('status') : '';

        $branches = DB::table('branches')->where('is_active', 1)->whereNull('deleted_at')->orderBy('name')->get(['name', 'home_base_key']);
        $branchNameByBase = $branches->filter(fn ($b) => $b->home_base_key)->groupBy('home_base_key')->map(fn ($g) => $g->first()->name);

        $rows = DB::table('employee as e')
            ->join('employee_identification as i', function ($j) {
                $j->on('i.employee_id', '=', 'e.employee_id')->where('i.identification_type', 'BPJS_KESEHATAN');
            })
            ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->leftJoin('employee_address as a', function ($j) {
                $j->on('a.employee_id', '=', 'e.employee_id')->where('a.is_primary', 1);
            })
            ->whereNotNull('i.identification_number')->where('i.identification_number', '!=', '')
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $x->where('b.first_name', 'like', $like)->orWhere('b.last_name', 'like', $like)->orWhere('e.eci', 'like', $like)
                    ->orWhere('i.identification_number', 'like', $like);
            }))
            ->when($branch !== '', fn ($w) => $w->where('b.home_base', $branch))
            ->when($status === 'inactive', fn ($w) => $w->where('e.is_active', 0))
            ->when($status === 'active', fn ($w) => $w->where('e.is_active', 1))
            ->orderBy('e.is_active')->orderBy('b.first_name')->orderBy('e.employee_id')
            ->limit(500)
            ->get(['e.employee_id', 'e.eci', 'e.is_active', 'b.first_name', 'b.last_name', 'b.employee_type', 'b.home_base',
                   'i.identification_number as bpjs_no', 'a.cell_phone_country', 'a.cell_phone']);

        $candidates = $rows->map(fn ($r) => [
            'id' => (int) $r->employee_id, 'eci' => $r->eci, 'active' => (bool) $r->is_active,
            'name' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: ('Employee #' . $r->employee_id),
            'type' => $r->employee_type, 'bpjs_no' => $r->bpjs_no,
            'phone' => trim(($r->cell_phone ? ($r->cell_phone_country ? '+' . ltrim((string) $r->cell_phone_country, '+') . ' ' : '') . $r->cell_phone : '')),
            'branch' => $branchNameByBase[$r->home_base] ?? $r->home_base,
        ])->all();

        $history = Letter::where('template_key', self::TEMPLATE)->orderByDesc('id')->limit(25)->get();

        $me = Employee::find(session('user.id'));
        $signer = session('user');

        return view('finance.bpjs.letters', [
            'candidates' => $candidates, 'capped' => $rows->count() >= 500,
            'filters' => ['q' => $q, 'branch' => $branch, 'status' => $status],
            'bases' => DB::table('employee_basic_data')->whereNotNull('home_base')->where('home_base', '!=', '')->distinct()->orderBy('home_base')->pluck('home_base')->map(fn ($hb) => ['key' => $hb, 'label' => $branchNameByBase[$hb] ?? $hb])->all(),
            'reasons' => array_map(fn ($r) => $r[1], self::REASONS),
            'history' => $history,
            'defaults' => [
                'letter_date' => now()->toDateString(), 'signer_name' => (string) ($signer['name'] ?? ''), 'signer_position' => (string) ($signer['position'] ?? ''),
                'city' => (string) (\App\Models\Letters\LetterSetting::current()->signing_city ?? ''),
            ],
            'canCreate' => (bool) $me?->hasMenuPermission('finance.bpjs.letters', 'can_create'),
            'canDelete' => (bool) $me?->hasMenuPermission('finance.bpjs.letters', 'can_delete'),
            'hasLetterhead' => (bool) Letterhead::forLetter(self::TEMPLATE),
            'hasLetterCode' => (bool) LetterTypeSetting::where('letter_type', self::TEMPLATE)->value('letter_code_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'letter_date' => ['required', 'date_format:Y-m-d'],
            'signer_name' => ['required', 'string', 'max:150'],
            'signer_position' => ['required', 'string', 'max:150'],
            'staff_name' => ['nullable', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'employee_ids' => ['required', 'array', 'min:1', 'max:200'],
            'employee_ids.*' => ['integer'],
            'reasons' => ['required', 'array'],
        ], [
            'employee_ids.required' => 'Select at least one employee.',
            'employee_ids.min' => 'Select at least one employee.',
        ]);

        $errors = [];
        $employees = [];
        foreach (array_unique(array_map('intval', $data['employee_ids'])) as $id) {
            $reason = (string) ($data['reasons'][$id] ?? '');
            $info = DB::table('employee as e')
                ->join('employee_identification as i', function ($j) {
                    $j->on('i.employee_id', '=', 'e.employee_id')->where('i.identification_type', 'BPJS_KESEHATAN');
                })
                ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
                ->where('e.employee_id', $id)->first(['e.eci', 'b.first_name', 'b.last_name', 'i.identification_number as bpjs_no']);
            if (!$info || trim((string) $info->bpjs_no) === '') {
                $errors[] = "Employee #{$id} has no BPJS Health number.";
                continue;
            }
            $name = trim(($info->first_name ?? '') . ' ' . ($info->last_name ?? '')) ?: $info->eci;
            if (!isset(self::REASONS[$reason])) {
                $errors[] = "Choose a reason for {$name}.";
                continue;
            }
            $nik = DB::table('employee_identification')->where('employee_id', $id)->where('identification_type', 'KTP')->value('identification_number');
            $employees[] = ['employee_id' => $id, 'name' => $name, 'eci' => $info->eci, 'nik' => (string) $nik, 'bpjs_no' => (string) $info->bpjs_no,
                'reason' => $reason, 'reason_id' => self::REASONS[$reason][0], 'reason_en' => self::REASONS[$reason][1]];
        }
        if ($errors) {
            return redirect()->route('finance.bpjs.letters')->withErrors($errors)->withInput();
        }

        $letterhead = Letterhead::forLetter(self::TEMPLATE);
        $typeSetting = LetterTypeSetting::where('letter_type', self::TEMPLATE)->first();
        $letter = $this->letters->generate([
            'source' => Letter::SOURCE_TEMPLATE, 'template_key' => self::TEMPLATE,
            'subject' => 'Penonaktifan Kepesertaan BPJS Kesehatan', 'counterparty' => 'BPJS Kesehatan',
            'letter_date' => $data['letter_date'], 'language' => 'id', 'letter_code_id' => $typeSetting?->letter_code_id,
            'signatory_name' => $data['signer_name'], 'signatory_title' => $data['signer_position'],
            'use_letterhead' => (bool) $letterhead, 'letterhead_id' => $letterhead?->id,
            'fields' => ['employees' => $employees, 'staff_name' => $data['staff_name'] ?? null, 'contact' => $data['contact'] ?? null, 'city' => $data['city']],
        ], (int) session('user.id'));

        return redirect()->route('finance.bpjs.letters')
            ->with('success', 'BPJS letter ' . $letter->letter_number . ' created for ' . count($employees) . ' employee(s).')
            ->with('bpjs_letter_id', $letter->id);
    }

    /** ?download=1 → langsung terunduh (dipakai otomatis setelah Generate); tanpa itu dibuka di tab. */
    public function pdf(Request $request, int $letter)
    {
        $l = Letter::where('template_key', self::TEMPLATE)->findOrFail($letter);

        return $request->boolean('download') ? $l->toPdf()->download($l->fileName()) : $l->toPdf()->stream($l->fileName());
    }

    public function void(Request $request, int $letter): RedirectResponse
    {
        $l = Letter::where('template_key', self::TEMPLATE)->findOrFail($letter);
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return redirect()->route('finance.bpjs.letters')->withErrors(['Enter a reason to void the letter.']);
        }
        $this->letters->void($l, $reason, (int) session('user.id'));

        return redirect()->route('finance.bpjs.letters')->with('success', 'Letter ' . $l->letter_number . ' voided. Its number stays used.');
    }
}
