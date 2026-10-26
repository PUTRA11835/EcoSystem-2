<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Management → Employee → (Address, Identification, Family, Education, Bank, Payment, Attachment): daftar seluruh rekaman satu seksi.
 *
 * Dulu tiap halaman meminta daftar karyawan lalu SATU permintaan per karyawan (±165 permintaan paralel) dan membaca nama kolom yang
 * keliru (mis. rute `/address` padahal yang ada `/addresses`) → loading lama dan "No data" walau datanya ada. Kini satu kueri
 * bergabung (join) dengan pagination dan pencarian di sisi server.
 *
 * Izin: sama dengan sebelumnya — halaman dijaga slug menu `management.employee.<seksi>`, dan data hanya dikirim bila pembuka juga
 * memegang `employee.section.<seksi>.view` (syarat yang dulu berlaku lewat API per karyawan). Cakupan: karyawan INTERNAL
 * (konsultan External dikelola di External → Consultants).
 */
class ManagementEmployeeRecordsController extends Controller
{
    /** seksi => tabel, kunci utama, kolom yang dikirim, kolom yang ikut dicari, urutan */
    private const SECTIONS = [
        'address' => [
            'table' => 'employee_address', 'pk' => 'address_id',
            'select' => ['address_type', 'street', 'house_number', 'city', 'region', 'district', 'postal_code', 'is_primary'],
            'search' => ['address_type', 'street', 'city', 'region', 'district', 'postal_code'],
        ],
        'identification' => [
            'table' => 'employee_identification', 'pk' => 'identification_id',
            'select' => ['identification_type', 'identification_number', 'valid_from', 'valid_to'],
            'search' => ['identification_type', 'identification_number'],
        ],
        'family' => [
            'table' => 'employee_family', 'pk' => 'family_id',
            'select' => ['name', 'relation', 'gender', 'birth_date', 'occupation'],
            'search' => ['name', 'relation', 'occupation'],
        ],
        'education' => [
            'table' => 'employee_education', 'pk' => 'education_id',
            'select' => ['education_type', 'institute_place', 'branch_of_study', 'degree', 'graduation_year'],
            'search' => ['education_type', 'institute_place', 'branch_of_study', 'degree'],
        ],
        'bank' => [
            'table' => 'employee_bank', 'pk' => 'bank_id',
            'select' => ['bank_name', 'account_number', 'account_holder', 'valid_from'],
            'search' => ['bank_name', 'account_number', 'account_holder'],
        ],
        'payment' => [
            'table' => 'employee_payment', 'pk' => 'payment_id',
            'select' => ['payment_method', 'amount', 'payment_status', 'paid_at', 'reference_number'],
            'search' => ['payment_method', 'payment_status', 'reference_number'],
        ],
        'attachment' => [
            'table' => 'employee_attachment', 'pk' => 'attachment_id',
            'select' => ['document_type', 'document_title', 'file_name', 'file_size', 'uploaded_by', 'created_at'],
            'search' => ['document_type', 'document_title', 'file_name', 'uploaded_by'],
        ],
    ];

    public const PER_PAGE_OPTIONS = [25, 50, 100];

    public function data(Request $request, string $section): JsonResponse
    {
        $def = self::SECTIONS[$section] ?? null;
        if ($def === null) {
            return response()->json(['success' => false, 'message' => 'Unknown section.'], 404);
        }

        $viewer = Employee::find(session('user.id'));
        if (!$viewer || !$viewer->canAccessMenu("employee.section.{$section}.view")) {
            return response()->json(['success' => false, 'message' => "You do not have permission to view the employee {$section} data (needs employee.section.{$section}.view)."], 403);
        }

        try {
            $perPage = (int) $request->query('per_page', 25);
            $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;
            $page = max(1, (int) $request->query('page', 1));
            $term = trim((string) $request->query('q', ''));

            $query = DB::table("{$def['table']} as r")
                ->join('employee as e', 'e.employee_id', '=', 'r.employee_id')
                ->leftJoin('employee_basic_data as eb', 'eb.employee_id', '=', 'e.employee_id')
                // Master Employee hanya memuat karyawan INTERNAL (sama dengan daftar utama).
                ->where(fn ($q) => $q->whereNull('eb.employee_type')->orWhere('eb.employee_type', '!=', 'External'));

            foreach (preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($word, 0, 60)) . '%';
                $query->where(function ($q) use ($like, $def) {
                    $q->where('e.eci', 'like', $like)->orWhere('eb.first_name', 'like', $like)->orWhere('eb.last_name', 'like', $like)->orWhere('eb.nick_name', 'like', $like);
                    foreach ($def['search'] as $col) {
                        $q->orWhere("r.{$col}", 'like', $like);
                    }
                });
            }

            $total = (clone $query)->count();
            $select = array_merge(
                ["r.{$def['pk']} as record_id", 'e.employee_id', 'e.eci', 'eb.first_name', 'eb.last_name'],
                array_map(fn ($c) => "r.{$c}", $def['select'])
            );
            $rows = $query->select($select)
                ->orderByRaw("TRIM(CONCAT(COALESCE(eb.first_name,''), ' ', COALESCE(eb.last_name,''))) = '' asc")
                ->orderByRaw("TRIM(CONCAT(COALESCE(eb.first_name,''), ' ', COALESCE(eb.last_name,''))) asc")
                ->orderBy('e.eci')->orderBy("r.{$def['pk']}")
                ->forPage($page, $perPage)->get()
                ->map(function ($r) {
                    $r->name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: ($r->eci ?? '-');
                    unset($r->first_name, $r->last_name);

                    return $r;
                });

            return response()->json([
                'success' => true, 'data' => $rows,
                'pagination' => [
                    'total' => $total, 'per_page' => $perPage, 'current_page' => $page, 'last_page' => max(1, (int) ceil($total / $perPage)),
                    'from' => $total ? ($page - 1) * $perPage + 1 : 0, 'to' => min($total, $page * $perPage),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('ManagementEmployeeRecords: gagal memuat', ['section' => $section, 'error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);

            return response()->json(['success' => false, 'message' => 'The list could not be loaded. Please try again.'], 500);
        }
    }
}
