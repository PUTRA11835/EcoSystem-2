<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Reimbursement\ReimbursementSetting;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Impor reimbursement dari berkas Excel.
 *
 * 🔴 GAGAL UTUH, BUKAN SEBAGIAN. Seluruh impor berjalan di dalam SATU transaksi:
 * bila ada satu baris saja yang tidak sah, tidak ada satu dokumen pun yang
 * tersimpan. Impor yang berhasil sebagian jauh lebih mahal daripada impor yang
 * gagal — memisahkan mana yang sudah masuk dan mana yang belum, di tengah data
 * keuangan, adalah pekerjaan manual yang tidak punya jalan pintas.
 *
 * Dikerjakan SETELAH alur manual terbukti (langkah R7), dengan alasan yang sama:
 * impor memasukkan data rusak dalam jumlah besar sekaligus.
 *
 * Satu berkas dapat memuat BEBERAPA dokumen. Baris dikelompokkan menurut kolom
 * `document`, sehingga dua baris dengan nilai `document` yang sama menjadi dua
 * item pada satu dokumen — bentuk yang sama dengan cara orang menyusunnya di
 * spreadsheet.
 *
 * Pembuatan dokumen memakai ReimbursementService::submit() apa adanya, tanpa
 * jalur khusus: aturan yang menolak pengajuan lewat layar juga menolaknya lewat
 * impor. Itulah gunanya service dibuat agnostik terhadap transport.
 */
class ReimbursementImportController extends Controller
{
    /** Kolom yang wajib ada pada berkas. Namanya harus persis. */
    private const COLUMNS = [
        'document', 'employee_eci', 'request_date', 'title', 'supporting_url',
        'item_description', 'branch_code', 'receipt_no',
        'receipt_date_from', 'receipt_date_to', 'amount',
    ];

    public function form()
    {
        return view('hr-general.reimbursement.import', [
            'columns'  => self::COLUMNS,
            'settings' => ReimbursementSetting::current(),
        ]);
    }

    /**
     * Berkas contoh berisi kepala kolom + dua baris pada satu dokumen.
     *
     * Diberikan sebagai unduhan, bukan sekadar didokumentasikan di layar: orang
     * menyalin bentuk yang ada di tangannya, bukan bentuk yang dibacanya.
     */
    public function template()
    {
        $sample = [
            self::COLUMNS,
            [
                'RB-1', 'ESS-TRIAL-001', now()->toDateString(), 'Operational expenses',
                'https://drive.google.com/file/d/example',
                'Taxi to client site', 'EC-JOGJA', '001',
                now()->toDateString(), now()->toDateString(), 150000,
            ],
            [
                'RB-1', 'ESS-TRIAL-001', now()->toDateString(), 'Operational expenses',
                'https://drive.google.com/file/d/example',
                'Team lunch', 'EC-JOGJA', '002',
                now()->toDateString(), now()->toDateString(), 250000,
            ],
        ];

        return Excel::download(new class ($sample) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\ShouldAutoSize {
            public function __construct(private array $rows)
            {
            }

            public function array(): array
            {
                return $this->rows;
            }
        }, 'reimbursement_import_template.xlsx');
    }

    public function store(Request $request, ReimbursementService $reimbursement)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:4096'],
        ], [
            'file.required' => 'Choose an Excel file to import.',
            'file.mimes'    => 'The file must be .xlsx, .xls, or .csv.',
        ]);

        $rows = $this->readRows($request->file('file'));

        if ($rows === []) {
            return back()->with('error', 'The file has no data rows.');
        }

        $missing = array_diff(self::COLUMNS, array_keys($rows[0]));
        if ($missing !== []) {
            return back()->with('error',
                'These columns are missing from the file: ' . implode(', ', $missing)
                . '. Download the template to see the expected format.');
        }

        $prepared = $this->groupRows($rows);

        if ($prepared['errors'] !== []) {
            return back()->with('error',
                'Nothing was imported. ' . count($prepared['errors']) . ' problem(s) found — '
                . implode(' · ', array_slice($prepared['errors'], 0, 5))
                . (count($prepared['errors']) > 5 ? ' …' : ''));
        }

        $actorId = (int) session('user.id');

        try {
            $created = DB::transaction(function () use ($prepared, $reimbursement, $actorId) {
                $numbers = [];

                foreach ($prepared['documents'] as $key => $document) {
                    $result = $reimbursement->submit($document['employee_id'], $document, $actorId);

                    // Satu penolakan membatalkan SELURUH impor. Melempar dari
                    // dalam transaksi adalah caranya — bukan mengumpulkan galat
                    // lalu melanjutkan, yang akan meninggalkan sebagian data
                    // masuk dan sebagian tidak.
                    if (!$result['allowed']) {
                        throw new \RuntimeException("Document \"{$key}\": " . $result['reason']);
                    }

                    $numbers[] = $result['request']->request_no;
                }

                return $numbers;
            });
        } catch (\Throwable $e) {
            Log::error('Reimbursement import failed and was rolled back.', [
                'actor_id' => $actorId,
                'message'  => $e->getMessage(),
            ]);

            return back()->with('error', 'Nothing was imported — ' . $e->getMessage());
        }

        return redirect()
            ->route('general.reimbursement.index')
            ->with('success', count($created) . ' reimbursement document(s) imported: ' . implode(', ', $created));
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Baca berkas menjadi array asosiatif berdasarkan baris kepala.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readRows($file): array
    {
        $sheets = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
            public function array(array $array)
            {
                return $array;
            }
        }, $file);

        $rows = $sheets[0] ?? [];

        if (count($rows) < 2) {
            return [];
        }

        $headings = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            array_shift($rows)
        );

        $out = [];

        foreach ($rows as $row) {
            $assoc = [];

            foreach ($headings as $i => $heading) {
                if ($heading !== '') {
                    $assoc[$heading] = $row[$i] ?? null;
                }
            }

            // Baris yang seluruh selnya kosong dilewati tanpa protes — berkas
            // Excel hampir selalu membawa baris kosong di bawah datanya.
            if (collect($assoc)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()) {
                $out[] = $assoc;
            }
        }

        return $out;
    }

    /**
     * Kelompokkan baris menjadi dokumen, sekaligus kumpulkan galat per baris.
     *
     * @return array{documents: array<string, array>, errors: array<string>}
     */
    private function groupRows(array $rows): array
    {
        $employees = Employee::where('is_active', 1)->pluck('employee_id', 'eci');
        $branches  = DB::table('branches')->whereNull('deleted_at')->pluck('id', 'code');

        $documents = [];
        $errors    = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;                       // +1 kepala, +1 basis-1
            $key  = trim((string) ($row['document'] ?? ''));

            if ($key === '') {
                $errors[] = "row {$line}: the document column is empty";
                continue;
            }

            $eci = trim((string) ($row['employee_eci'] ?? ''));
            if (!$employees->has($eci)) {
                $errors[] = "row {$line}: employee \"{$eci}\" not found or inactive";
                continue;
            }

            $branchCode = trim((string) ($row['branch_code'] ?? ''));
            if (!$branches->has($branchCode)) {
                $errors[] = "row {$line}: branch \"{$branchCode}\" not found";
                continue;
            }

            $amount = (float) str_replace([',', ' '], '', (string) ($row['amount'] ?? 0));
            if ($amount <= 0) {
                $errors[] = "row {$line}: amount must be greater than zero";
                continue;
            }

            $documents[$key] ??= [
                'employee_id'    => (int) $employees[$eci],
                'request_date'   => $this->date($row['request_date'] ?? null),
                'title'          => trim((string) ($row['title'] ?? '')),
                'supporting_url' => trim((string) ($row['supporting_url'] ?? '')) ?: null,
                'items'          => [],
            ];

            // Baris kedua dan seterusnya pada dokumen yang sama harus menyebut
            // karyawan yang sama. Membiarkannya berbeda berarti satu dokumen
            // berisi biaya milik dua orang.
            if ($documents[$key]['employee_id'] !== (int) $employees[$eci]) {
                $errors[] = "row {$line}: document \"{$key}\" already belongs to another employee";
                continue;
            }

            $from = $this->date($row['receipt_date_from'] ?? null);
            $to   = $this->date($row['receipt_date_to'] ?? null);

            $documents[$key]['items']['imp' . $line] = [
                'description'       => trim((string) ($row['item_description'] ?? '')),
                'branch_id'         => (int) $branches[$branchCode],
                'receipt_no'        => trim((string) ($row['receipt_no'] ?? '')) ?: null,
                'receipt_date_from' => $from,
                'receipt_date_to'   => $to ?: $from,
                'amount'            => $amount,
            ];
        }

        return ['documents' => $documents, 'errors' => $errors];
    }

    /**
     * Tanggal dari sel Excel.
     *
     * Excel menyimpan tanggal sebagai angka seri; sel yang diketik manusia
     * datang sebagai teks. Keduanya harus diterima, kalau tidak berkas yang
     * dibuat di Excel dan yang dibuat di Google Sheets berperilaku berbeda.
     */
    private function date($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $time = strtotime((string) $value);

        return $time ? date('Y-m-d', $time) : '';
    }
}
