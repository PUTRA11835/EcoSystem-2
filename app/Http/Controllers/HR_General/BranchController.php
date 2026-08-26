<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Attendance\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Master cabang / kantor beserta titik geofence-nya.
 *
 * Halaman ini adalah satu-satunya tempat koordinat kantor diisi, dan karena
 * itu menentukan apakah seluruh modul Attendance punya data untuk bekerja.
 * Formnya menyediakan peta agar HR dapat menentukan titiknya sendiri tanpa
 * alat bantu di luar aplikasi.
 */
class BranchController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');

        $branches = Branch::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%")
                      ->orWhere('province', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_head_office')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('HR_General.settings.branches.index', compact('branches', 'search', 'status'));
    }

    public function create()
    {
        return view('HR_General.settings.branches.form', [
            'branch'    => new Branch(['radius_meters' => 100, 'is_active' => true]),
            'isEditing' => false,
        ]);
    }

    public function edit(Branch $branch)
    {
        return view('HR_General.settings.branches.form', [
            'branch'    => $branch,
            'isEditing' => true,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $branch = DB::transaction(function () use ($data) {
            $this->clearOtherHeadOffices($data);

            $data['created_by'] = session('user.id');

            return Branch::create($data);
        });

        return redirect()
            ->route('general.settings.branches.index')
            ->with('success', "Branch \"{$branch->name}\" has been added.");
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $this->validatePayload($request, $branch->id);

        DB::transaction(function () use ($data, $branch) {
            $this->clearOtherHeadOffices($data, $branch->id);

            $branch->update($data);
        });

        return redirect()
            ->route('general.settings.branches.index')
            ->with('success', "Branch \"{$branch->name}\" has been updated.");
    }

    /**
     * Hapus lembut. Riwayat presensi menyimpan branch_id, jadi menghapus
     * permanen akan membuat catatan lama menunjuk ke cabang yang hilang dan
     * kolom lokasinya kosong tanpa penjelasan.
     */
    public function destroy(Branch $branch)
    {
        $name = $branch->name;
        $branch->delete();

        return redirect()
            ->route('general.settings.branches.index')
            ->with('success', "Branch \"{$name}\" has been deleted. Related attendance records are kept.");
    }

    /**
     * Hanya boleh ada satu kantor pusat. Ditegakkan di sini karena MariaDB
     * tidak mendukung partial unique index (UNIQUE hanya pada baris bernilai
     * true).
     */
    private function clearOtherHeadOffices(array $data, ?int $exceptId = null): void
    {
        if (empty($data['is_head_office'])) {
            return;
        }

        $affected = Branch::where('is_head_office', true)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_head_office' => false]);

        if ($affected > 0) {
            Log::info('Head office flag moved to another branch.', [
                'cleared_from' => $affected,
                'actor_id'     => session('user.id'),
            ]);
        }
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('branches', 'code')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'name'              => ['required', 'string', 'max:150'],
            'city'              => ['nullable', 'string', 'max:100'],
            'province'          => ['nullable', 'string', 'max:100'],
            'address'           => ['nullable', 'string', 'max:1000'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'latitude'          => ['required', 'numeric', 'between:-90,90'],
            'longitude'         => ['required', 'numeric', 'between:-180,180'],
            'radius_meters'     => ['required', 'integer', 'between:' . Branch::RADIUS_MIN . ',' . Branch::RADIUS_MAX],
            'geofence_override' => ['nullable', Rule::in(Branch::GEOFENCE_OVERRIDES)],
            'home_base_key'     => ['nullable', 'string', 'max:100'],
            'is_head_office'    => ['nullable', 'boolean'],
            'is_active'         => ['nullable', 'boolean'],
        ], [
            'latitude.required'      => 'Latitude is required. Pick a point on the map or type the coordinate.',
            'longitude.required'     => 'Longitude is required. Pick a point on the map or type the coordinate.',
            'latitude.between'       => 'Latitude must be between -90 and 90.',
            'longitude.between'      => 'Longitude must be between -180 and 180.',
            'radius_meters.between'  => 'Radius must be between ' . Branch::RADIUS_MIN . ' and ' . Branch::RADIUS_MAX . ' meters. Below ' . Branch::RADIUS_MIN . ' m, normal GPS drift makes a successful check-in almost impossible.',
        ]);

        // validate() hanya mengembalikan kunci yang benar-benar dikirim, jadi
        // field opsional yang tidak ada di form harus diberi nilai eksplisit.
        $validated['is_head_office']    = $request->boolean('is_head_office');
        $validated['is_active']         = $request->boolean('is_active');
        $validated['geofence_override'] = ($validated['geofence_override'] ?? null) ?: null;
        $validated['home_base_key']     = ($validated['home_base_key'] ?? null) ?: null;

        return $validated;
    }
}
