<?php

namespace App\Services\External;

use App\Models\Employee;
use App\Models\Letterhead;
use App\Models\Letters\Letter;
use App\Models\Letters\LetterSetting;
use App\Models\LetterTypeSetting;
use App\Services\Letters\LetterNumberService;
use App\Services\Letters\LetterService;
use App\Support\External\ExternalRules;
use App\Support\Letters\LetterTemplates;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * External → Assignment Letter / Employment Certificate / Reference Letter.
 *
 * Tiga surat ini adalah jenis surat Letter Templates (ext_*), sehingga SATU mesin dipakai bersama: nomor dari urutan surat keluar,
 * kop surat, PDF DomPDF, tanda tangan master, dan pencatatan otomatis di Letter Register. Layanan ini hanya membungkusnya:
 * arsip per jenis (daftar surat), nilai awal formulir, dan pembuatan surat konsultan.
 */
class ExternalDocumentService
{
    public function __construct(private LetterService $letters, private LetterNumberService $numbers) {}

    // ── Arsip ─────────────────────────────────────────────────────────────

    /** Jumlah surat per jenis (kunci assignment/certificate/reference) — surat yang belum di-void. */
    public function counts(): array
    {
        $byTemplate = Letter::where('direction', Letter::DIRECTION_OUTGOING)->whereIn('template_key', ExternalRules::templateKeys())
            ->where('status', '!=', 'void')->selectRaw('template_key, COUNT(*) as n')->groupBy('template_key')->pluck('n', 'template_key');

        $out = [];
        foreach (ExternalRules::DOCUMENTS as $key => $doc) {
            $out[$key] = (int) ($byTemplate[$doc['template']] ?? 0);
        }

        return $out;
    }

    /**
     * @param  array{search?:string,status?:string}  $filters
     */
    public function listing(string $docKey, array $filters, int $perPage): LengthAwarePaginator
    {
        $template = ExternalRules::DOCUMENTS[$docKey]['template'];
        $search = (string) ($filters['search'] ?? '');
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

        return Letter::with(['employee.basicData'])
            ->where('direction', Letter::DIRECTION_OUTGOING)
            ->where('template_key', $template)
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('letter_number', 'like', $like)
                ->orWhere('counterparty', 'like', $like)
                ->orWhere('fields->contract_no', 'like', $like)
                ->orWhere('fields->purpose', 'like', $like)
                ->orWhereHas('employee', fn ($e) => $e->where('eci', 'like', $like))))
            ->when(in_array($filters['status'] ?? '', ['draft', 'signed', 'sent', 'void'], true), fn ($q) => $q->withStatus($filters['status']))
            ->orderByDesc('letter_date')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();
    }

    /** @param  int[]  $ids  @return array<int,string> id karyawan → nama (pembuat surat) */
    public function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return [];
        }

        return DB::table('employee_basic_data')->whereIn('employee_id', $ids)
            ->selectRaw("employee_id, CONCAT_WS(' ', first_name, last_name) as n")->pluck('n', 'employee_id')
            ->map(fn ($n) => trim((string) $n))->all();
    }

    // ── Nilai awal formulir ───────────────────────────────────────────────

    /**
     * Bahasa & kode surat awal tiap jenis (Letter Templates → Settings), dan penanda tangan awal = orang yang membuat bila ia
     * boleh menandatangani (Settings → Signers), jika tidak penanda tangan pertama.
     *
     * @return array{languages:array,defaults:array,signatories:\Illuminate\Support\Collection,signer:?array}
     */
    public function formOptions(int $actorId): array
    {
        $signatories = LetterService::signatoryOptions();
        $signer = $signatories->firstWhere('id', $actorId) ?? $signatories->first();

        $defaults = [];
        foreach (ExternalRules::DOCUMENTS as $key => $doc) {
            $setting = LetterTypeSetting::for($doc['template']);
            $defaults[$key] = ['language' => $setting->language ?? LetterTypeSetting::LANGUAGE_INDONESIAN, 'code' => $setting->letter_code_id];
        }

        return ['languages' => LetterTypeSetting::LANGUAGES, 'defaults' => $defaults, 'signatories' => $signatories, 'signer' => $signer];
    }

    /**
     * Kontrak konsultan untuk pemilih "Related EXT Contract" — per konsultan.
     *
     * @return array<int,array<int,array{no:string,label:string,start:?string,end:?string}>>
     */
    public function contractsByConsultant(): array
    {
        $today = now()->toDateString();

        return DB::table('employee_contract')->whereIn(DB::raw('UPPER(contract_type)'), ['EXTERNAL', 'EXT', 'PKS'])
            ->where(fn ($q) => $q->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', 'draft'))
            ->orderByDesc('start_date')->get(['employee_id', 'contract_number', 'start_date', 'end_date', 'is_active', 'lifecycle_status'])
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->map(function ($r) use ($today) {
                $status = \App\Support\Contracts\ContractRules::effectiveStatus($r->lifecycle_status, (bool) $r->is_active, $r->end_date, $today);

                return ['no' => $r->contract_number, 'label' => $r->contract_number . ' (' . (\App\Support\Contracts\ContractRules::STATUS_LABELS[$status] ?? $status) . ')',
                    'start' => $r->start_date, 'end' => $r->end_date];
            })->values()->all())
            ->all();
    }

    // ── Buat surat ────────────────────────────────────────────────────────

    /**
     * Terbitkan surat konsultan: nomor (diketik atau urutan berikutnya), tersimpan di Letter Register dengan data konsultan
     * dan engagement-nya SEPERTI SAAT INI (dibekukan bersama surat, jadi cetak ulang tampil sama).
     *
     * @param  array<string,mixed>  $in  data formulir yang sudah divalidasi controller
     * @return array{letter:Letter,signed:bool,sign_problem:?string}
     */
    public function create(string $docKey, array $in, int $actorId): array
    {
        $doc = ExternalRules::DOCUMENTS[$docKey];
        $key = $doc['template'];
        $template = LetterTemplates::get($key);

        $employee = Employee::with('basicData')->findOrFail((int) $in['employee_id']);
        $snapshot = LetterTemplates::employeeSnapshot($employee);
        $settings = LetterSetting::current();
        $signer = LetterService::signatoryOptions()->firstWhere('id', (int) $in['signatory_employee_id']);
        $language = $in['language'];

        $engagement = DB::table('employee_engagement')->where('employee_id', $employee->employee_id)->first();

        // Satu kolom isian "tujuan" di formulir → kolom milik tiap jenis surat.
        $purposeField = ['assignment' => 'task', 'certificate' => 'purpose', 'reference' => 'remarks'][$docKey];
        $fields = LetterTemplates::cleanFields($key, [
            'addressed_to' => $in['addressed_to'] ?? null,
            'contract_no'  => $in['contract_no'] ?? null,
            $purposeField  => $in['purpose'] ?? null,
            'location'     => $in['location'] ?? null,
            'period_from'  => $in['period_from'] ?? null,
            'period_to'    => $in['period_to'] ?? null,
        ]);

        $letter = $this->letters->generate([
            'language'              => $language,
            'letter_date'           => $in['letter_date'],
            'letter_number'         => $in['letter_number'] ?? null,
            'letter_code_id'        => LetterTypeSetting::for($key)->letter_code_id,
            'signatory_employee_id' => $signer['id'],
            'signatory_name'        => $signer['name'],
            'signatory_title'       => $in['signatory_title'],
            'recipient_email'       => Letter::workEmailOf($employee->employee_id),
            'notes'                 => $in['notes'] ?? null,
            'source'                => Letter::SOURCE_TEMPLATE,
            'template_key'          => $key,
            'employee_id'           => $employee->employee_id,
            'subject'               => $language === LetterTypeSetting::LANGUAGE_ENGLISH ? $template['label_en'] : $template['label'],
            'counterparty'          => $snapshot['name'] ?? null,
            'use_letterhead'        => true,
            // Kop surat jenis ini; bila belum dipasang, memakai kop jenis surat internal yang sepadan.
            'letterhead_id'         => (Letterhead::forLetter($key) ?? Letterhead::forLetter(self::COUNTERPART[$key]))?->id,
            'letter_request_id'     => null,
            'fields'                => [
                ...$fields,
                'employee'   => $snapshot,
                'engagement' => $engagement ? array_filter([
                    'role'   => $engagement->assignment_role,
                    'client' => $engagement->client_company,
                    'vendor' => $engagement->vendor_partner,
                    'scheme' => ExternalRules::schemeKey($engagement->engagement_scheme) ? ExternalRules::schemeLabel($engagement->engagement_scheme) : null,
                ]) : [],
                'company'    => $settings->company_name,
                'city'       => $settings->signing_city,
            ],
        ], $actorId);

        $signed = false;
        $problem = null;
        if (!empty($in['sign_now'])) {
            $problem = $this->letters->signingProblem($letter);
            if ($problem === null) {
                $this->letters->sign($letter, $actorId);
                $signed = true;
            }
        }

        return ['letter' => $letter->refresh(), 'signed' => $signed, 'sign_problem' => $problem];
    }

    /** jenis surat konsultan → jenis surat internal yang kop suratnya dipinjam bila belum ada kop sendiri */
    private const COUNTERPART = [
        'ext_assignment_letter'      => 'assignment_letter',
        'ext_employment_certificate' => 'employment_certificate',
        'ext_reference_letter'       => 'employment_reference',
    ];
}
