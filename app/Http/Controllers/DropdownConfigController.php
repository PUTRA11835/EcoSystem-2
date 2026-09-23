<?php

namespace App\Http\Controllers;

use App\Models\DropdownConfig;
use App\Models\DropdownConfigValue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD for the generic Employee Information dropdown master data
 * (Position, Department, Division, Personnel Area, Personnel Subarea,
 * Employee Group, Employee Subgroup, and any new dropdown type an admin
 * adds later). See App\Models\DropdownConfig and the migration
 * create_dropdown_configs_tables for the design.
 */
class DropdownConfigController extends Controller
{
    // ── Configs ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $configs = DropdownConfig::withCount('values')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id'           => $c->id,
                'code'         => $c->code,
                'name'         => $c->name,
                'description'  => $c->description,
                'is_active'    => $c->is_active,
                'values_count' => $c->values_count,
            ]);

        return response()->json(['success' => true, 'data' => $configs]);
    }

    public function store(Request $request)
    {
        $data = $this->validateConfig($request);
        $config = DropdownConfig::create($data);

        return response()->json(['success' => true, 'data' => $config], 201);
    }

    public function update(Request $request, $id)
    {
        $config = DropdownConfig::findOrFail($id);
        $data   = $this->validateConfig($request, $config->id);
        $config->update($data);

        return response()->json(['success' => true, 'data' => $config]);
    }

    public function destroy($id)
    {
        $config = DropdownConfig::findOrFail($id);
        $config->delete(); // values cascade via FK

        return response()->json(['success' => true, 'message' => 'Dropdown config deleted successfully.']);
    }

    // ── Values ───────────────────────────────────────────────────────────────────

    public function values($id)
    {
        $config = DropdownConfig::findOrFail($id);
        $values = $config->values()->orderBy('sort_order')->orderBy('value')->get();

        return response()->json(['success' => true, 'data' => $values]);
    }

    public function storeValue(Request $request, $id)
    {
        $config = DropdownConfig::findOrFail($id);
        $data   = $this->validateValue($request, $config);

        $data['dropdown_config_id'] = $config->id;
        $data['sort_order']         = $data['sort_order'] ?? ((int) $config->values()->max('sort_order') + 1);

        $value = DropdownConfigValue::create($data);

        return response()->json(['success' => true, 'data' => $value], 201);
    }

    public function updateValue(Request $request, $id, $valueId)
    {
        $config = DropdownConfig::findOrFail($id);
        $value  = $config->values()->findOrFail($valueId);
        $data   = $this->validateValue($request, $config, $value->id);

        $value->update($data);

        return response()->json(['success' => true, 'data' => $value]);
    }

    public function destroyValue($id, $valueId)
    {
        $config = DropdownConfig::findOrFail($id);
        $value  = $config->values()->findOrFail($valueId);
        $value->delete();

        return response()->json(['success' => true, 'message' => 'Value deleted successfully.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function validateConfig(Request $request, $ignoreId = null): array
    {
        return $request->validate([
            'code'        => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('dropdown_configs', 'code')->ignore($ignoreId),
            ],
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'sometimes|boolean',
        ], [
            'code.regex' => 'Code may only contain lowercase letters, numbers, and underscores.',
        ]);
    }

    private function validateValue(Request $request, DropdownConfig $config, $ignoreId = null): array
    {
        return $request->validate([
            'value'      => [
                'required', 'string', 'max:255',
                Rule::unique('dropdown_config_values', 'value')
                    ->where('dropdown_config_id', $config->id)
                    ->ignore($ignoreId),
            ],
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'sometimes|boolean',
        ]);
    }
}
