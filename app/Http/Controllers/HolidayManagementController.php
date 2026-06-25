<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayManagementController extends Controller
{
    // ── Page ────────────────────────────────────────────────────────────────────

    public function page()
    {
        return view('management.holidays.index');
    }

    // ── CRUD (JSON API) ───────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Holiday::query();

        if ($request->filled('year')) {
            $year = (int) $request->query('year');
            $query->whereBetween('date', [
                sprintf('%04d-01-01', $year),
                sprintf('%04d-12-31', $year),
            ]);
        }

        $holidays = $query->orderBy('date')->get()->map(fn($h) => [
            'id'        => $h->id,
            'date'      => $h->date->format('Y-m-d'),
            'name'      => $h->name,
            'type'      => $h->type,
            'is_active' => $h->is_active,
        ]);

        // Daftar tahun yang punya data — untuk dropdown filter
        $years = Holiday::query()
            ->selectRaw('DISTINCT YEAR(date) as y')
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn($y) => (int) $y)
            ->all();

        return response()->json(['success' => true, 'data' => $holidays, 'years' => $years]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        if ($this->duplicateExists($data['date'], $data['name'])) {
            return response()->json([
                'success' => false,
                'message' => 'A holiday with the same date and name already exists.',
            ], 422);
        }

        $holiday = Holiday::create($data);
        return response()->json(['success' => true, 'data' => $holiday], 201);
    }

    public function update(Request $request, $id)
    {
        $holiday = Holiday::findOrFail($id);
        $data = $this->validateData($request);

        if ($this->duplicateExists($data['date'], $data['name'], $id)) {
            return response()->json([
                'success' => false,
                'message' => 'A holiday with the same date and name already exists.',
            ], 422);
        }

        $holiday->update($data);
        return response()->json(['success' => true, 'data' => $holiday]);
    }

    public function destroy($id)
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->delete();
        return response()->json(['success' => true, 'message' => 'Holiday deleted successfully.']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function validateData(Request $request): array
    {
        return $request->validate([
            'date'      => 'required|date',
            'name'      => 'required|string|max:255',
            'type'      => ['required', Rule::in(Holiday::TYPES)],
            'is_active' => 'sometimes|boolean',
        ]);
    }

    private function duplicateExists(string $date, string $name, $ignoreId = null): bool
    {
        return Holiday::where('date', $date)
            ->where('name', $name)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
