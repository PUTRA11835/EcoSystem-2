<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\OnboardingProgressService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Onboarding — progres kelengkapan data master employee (sisi HR).
 *
 * v1 TANPA tabel baru (HC-D14): daftar = SEMUA karyawan aktif beserta progresnya,
 * dihitung OnboardingProgressService dari data master. Halaman ini hanya MEMBACA;
 * melengkapi data dilakukan di halaman master employee (HR) atau My Profile
 * (pemilik data) lewat tautan "Open" pada tiap butir yang kurang.
 *
 * Dijaga slug `general.onboarding` (halaman ini menampilkan progres SEMUA
 * karyawan). Karyawan biasa melihat progres DIRINYA SENDIRI lewat banner di
 * My Profile, yang tidak memerlukan slug ini.
 */
class OnboardingController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request, OnboardingProgressService $progress)
    {
        $data    = $progress->all();
        $filters = $this->filters($request);

        $rows = collect($data['employees'])
            ->filter(fn (array $e) => $this->matches($e, $filters))
            ->sort(fn (array $a, array $b) => $this->compare($a, $b, $filters['sort']))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('hr-general.onboarding.index', [
            'rows'    => $paginator,
            'summary' => $data['summary'],
            'filters' => $filters,
            'groups'  => config('hc_onboarding.groups'),
        ]);
    }

    public function show(int $employeeId, OnboardingProgressService $progress)
    {
        $row = $progress->forEmployee($employeeId);

        // Karyawan nonaktif / ditandai hapus tidak masuk perhitungan (lihat service).
        abort_if($row === null, 404, 'Employee not found or not active.');

        return view('hr-general.onboarding.show', [
            'row'    => $row,
            'groups' => config('hc_onboarding.groups'),
        ]);
    }

    // ── Penyaringan & pengurutan ────────────────────────────────────────────

    /** @return array{search:string,status:string,type:string,missing:string,sort:string} */
    private function filters(Request $request): array
    {
        $groups = array_keys(config('hc_onboarding.groups'));

        $status = (string) $request->query('status', 'in_progress');
        $type   = (string) $request->query('type', 'all');
        $missing = (string) $request->query('missing', 'all');
        $sort   = (string) $request->query('sort', 'progress_asc');

        return [
            'search'  => trim((string) $request->query('search', '')),
            'status'  => in_array($status, ['all', 'in_progress', 'complete'], true) ? $status : 'in_progress',
            'type'    => in_array($type, ['all', 'Internal', 'External'], true) ? $type : 'all',
            'missing' => in_array($missing, $groups, true) ? $missing : 'all',
            'sort'    => in_array($sort, ['progress_asc', 'progress_desc', 'name', 'join_date'], true) ? $sort : 'progress_asc',
        ];
    }

    private function matches(array $e, array $f): bool
    {
        if ($f['status'] !== 'all' && $e['status'] !== $f['status']) {
            return false;
        }
        if ($f['type'] !== 'all' && $e['type'] !== $f['type']) {
            return false;
        }
        if ($f['missing'] !== 'all' && empty($e['groups'][$f['missing']]['missing'])) {
            return false;
        }
        if ($f['search'] !== '') {
            $haystack = mb_strtolower(implode(' ', [$e['name'], $e['eci'], $e['position'], $e['department']]));
            if (!str_contains($haystack, mb_strtolower($f['search']))) {
                return false;
            }
        }

        return true;
    }

    private function compare(array $a, array $b, string $sort): int
    {
        return match ($sort) {
            'progress_desc' => [$b['percent'], $a['name']] <=> [$a['percent'], $b['name']],
            'name'          => strcasecmp($a['name'], $b['name']),
            // Tanggal kosong ditaruh paling akhir.
            'join_date'     => [$a['join_date'] === null, $a['join_date']] <=> [$b['join_date'] === null, $b['join_date']],
            default         => [$a['percent'], $a['name']] <=> [$b['percent'], $b['name']],
        };
    }
}
