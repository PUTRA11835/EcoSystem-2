<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\InventoryOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * General Affairs → Inventory & Assets → Settings (general.inventory.settings): the lists behind the dropdowns of the
 * Inventory and Assets forms (category, unit, location, asset condition and status).
 *
 * V see the lists · C add an option · E rename / recolour / re-order / switch off · D delete an option nobody uses.
 * Built-in condition / status rows (is_system) are never deleted or switched off: the app's rules rely on them.
 */
class InventorySettingController extends Controller
{
    public function index(Request $request)
    {
        $field = array_key_exists((string) $request->query('field'), InventoryOption::FIELDS) ? (string) $request->query('field') : array_key_first(InventoryOption::FIELDS);

        return view('hr-general.inventory.settings', [
            'field'   => $field,
            'config'  => InventoryOption::FIELDS[$field],
            'options' => InventoryOption::forField($field),
            'usage'   => InventoryOption::usage($field),
            'fields'  => InventoryOption::FIELDS,
        ]);
    }

    public function store(Request $request)
    {
        $field = $request->validate(['field' => ['required', Rule::in(array_keys(InventoryOption::FIELDS))]])['field'];
        $keyed = InventoryOption::FIELDS[$field]['keyed'];
        $data = $this->validated($request, $field);

        $value = $data['label'];
        if ($keyed) {
            // Condition / status records store a fixed key made once from the first label.
            $base = Str::slug($data['label'], '_') ?: 'option';
            $value = $base;
            for ($i = 2; InventoryOption::where('field', $field)->where('value', $value)->exists(); $i++) {
                $value = "{$base}_{$i}";
            }
        }

        InventoryOption::create([
            'field'      => $field,
            'value'      => $value,
            'label'      => $data['label'],
            'tone'       => $keyed ? ($data['tone'] ?? 'gray') : null,
            'sort_order' => (int) InventoryOption::where('field', $field)->max('sort_order') + 1,
        ]);

        return $this->back($field, 'Option added.');
    }

    public function update(Request $request, InventoryOption $option)
    {
        $field = $option->field;
        $keyed = InventoryOption::FIELDS[$field]['keyed'];
        $data = $this->validated($request, $field, $option);

        DB::transaction(function () use ($option, $data, $keyed, $request, $field) {
            // A name-valued option is stored by its name on the records, so a rename moves them along.
            if (!$keyed && $data['label'] !== $option->value) {
                foreach (InventoryOption::FIELDS[$field]['columns'] as [$table, $column]) {
                    DB::table($table)->where($column, $option->value)->update([$column => $data['label']]);
                }
                $option->value = $data['label'];
            }

            $option->label = $data['label'];
            if ($keyed) {
                $option->tone = $data['tone'] ?? $option->tone;
            }
            $option->is_active = $option->is_system ? true : $request->boolean('is_active');
            $option->save();
        });

        return $this->back($field, 'Option updated.');
    }

    /** The on / off switch of a row: offered in the forms or not. The page calls it in the background (JSON). */
    public function toggle(Request $request, InventoryOption $option)
    {
        if ($option->is_system) {
            $message = 'A built-in option stays on: the app\'s rules rely on it.';

            return $request->expectsJson() ? response()->json(['message' => $message], 422) : $this->back($option->field, null, $message);
        }

        $option->update(['is_active' => $request->boolean('is_active')]);

        $used = InventoryOption::usage($option->field)[$option->value] ?? 0;
        $message = $option->is_active
            ? "{$option->label} is offered in the forms again."
            : "{$option->label} is switched off." . ($used ? " The {$used} record(s) that use it keep it." : '');

        return $request->expectsJson() ? response()->json(['active' => $option->is_active, 'message' => $message]) : $this->back($option->field, $message);
    }

    public function move(InventoryOption $option, string $direction)
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $ids = InventoryOption::forField($option->field)->pluck('id')->all();
        $at = array_search($option->id, $ids, true);
        $to = $direction === 'up' ? $at - 1 : $at + 1;

        if (isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            DB::transaction(function () use ($ids) {
                foreach ($ids as $position => $id) {
                    InventoryOption::whereKey($id)->update(['sort_order' => $position + 1]);
                }
            });
        }

        return $this->back($option->field, null);
    }

    public function destroy(InventoryOption $option)
    {
        $field = $option->field;
        if ($option->is_system) {
            return $this->back($field, null, 'A built-in option cannot be deleted; the app relies on it.');
        }

        $used = InventoryOption::usage($field)[$option->value] ?? 0;
        if ($used > 0) {
            return $this->back($field, null, "{$option->label} is used by {$used} record(s). Switch it off instead, or move those records to another option first.");
        }

        $option->delete();

        return $this->back($field, 'Option deleted.');
    }

    private function validated(Request $request, string $field, ?InventoryOption $option = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'tone'  => ['nullable', Rule::in(array_keys(InventoryOption::TONES))],
        ]);
        $data['label'] = trim($data['label']);

        // One name once per list, whatever the letter case.
        $clash = InventoryOption::where('field', $field)->whereRaw('LOWER(label) = ?', [mb_strtolower($data['label'])])
            ->when($option, fn ($q) => $q->whereKeyNot($option->id))->exists();
        if ($clash) {
            throw ValidationException::withMessages(['label' => "\"{$data['label']}\" is already in this list."]);
        }

        return $data;
    }

    private function back(string $field, ?string $success, ?string $error = null)
    {
        $redirect = redirect()->route('general.inventory.settings.index', ['field' => $field]);

        if ($error) {
            return $redirect->with('error', $error);
        }

        return $success ? $redirect->with('success', $success) : $redirect;
    }
}
