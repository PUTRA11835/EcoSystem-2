<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Services\Management\EmployeeMasterData;
use App\Support\Management\EmployeeMasterSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Management → Employee → <tab> (slugs management.employee.*): the settings of what each tab of the employee profile offers and accepts —
 * its dropdown lists and its rules (App\Support\Management\EmployeeMasterSettings). The profile forms and the employee API read them
 * through App\Services\Management\EmployeeMasterData, so a change here is live at once.
 *
 *   V  open the tab        C  add an option to a list
 *   E  rename / switch an option on or off / move it / change the rules        D  delete an option nobody uses
 *
 * The tab a route belongs to is the literal prefix of its route group, never a request value.
 */
class EmployeeSettingsController extends Controller
{
    /**
     * Everything the settings panel shows for one tab: its lists with their rows and how many records use each option, and its rules
     * with their current values.
     */
    public static function panel(string $section): array
    {
        $def = EmployeeMasterSettings::get($section);

        $lists = [];
        foreach ($def['lists'] as $field => $list) {
            $usage = self::usage($list['column']);
            $rows = DB::table('employee_field_options')->where('section', $section)->where('field', $field)->orderBy('sort_order')->orderBy('id')->get();

            $lists[$field] = $list + [
                'field'    => $field,
                'restrict' => EmployeeMasterData::restricts($section, $field),
                'rows'     => $rows->map(fn ($r) => (object) [
                    'id' => $r->id, 'value' => $r->value, 'label' => $r->label, 'is_active' => (bool) $r->is_active,
                    'internal_only' => (bool) $r->internal_only, 'used' => (int) ($usage[$r->value] ?? 0),
                ])->all(),
            ];
        }

        $rules = [];
        foreach ($def['rules'] as $key => $rule) {
            $rules[$key] = $rule + ['key' => $key, 'value' => EmployeeMasterData::rule($section, $key)];
        }

        return ['section' => $section, 'title' => $def['title'], 'about' => $def['about'], 'applies' => $def['applies'], 'lists' => $lists, 'rules' => $rules];
    }

    // ── lists ────────────────────────────────────────────────────────────────

    public function storeOption(Request $request)
    {
        $section = $this->section($request);
        $lists = EmployeeMasterSettings::get($section)['lists'];

        $data = $request->validate([
            'field' => ['required', Rule::in(array_keys($lists))],
            'label' => 'required|string|max:150',
            'value' => 'nullable|string|max:150',
        ], [], ['label' => 'name', 'value' => 'saved value']);

        $label = trim($data['label']);
        $value = trim((string) ($data['value'] ?? '')) !== '' ? trim($data['value']) : $label;

        if (DB::table('employee_field_options')->where('section', $section)->where('field', $data['field'])->where('value', $value)->exists()) {
            return $this->back($section)->withInput()->withErrors(['label' => "“{$value}” is already on this list."]);
        }

        DB::table('employee_field_options')->insert([
            'section' => $section, 'field' => $data['field'], 'value' => $value, 'label' => $label,
            'sort_order' => 1 + (int) DB::table('employee_field_options')->where('section', $section)->where('field', $data['field'])->max('sort_order'),
            'is_active' => true, 'internal_only' => !empty($lists[$data['field']]['internal']) && $request->boolean('internal_only'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        EmployeeMasterData::forget();

        return $this->back($section)->with('success', "“{$label}” added to {$lists[$data['field']]['label']}. It is on the form now.");
    }

    public function updateOption(Request $request, int $optionId)
    {
        $section = $this->section($request);
        $option = $this->option($section, $optionId);
        $list = EmployeeMasterSettings::get($section)['lists'][$option->field];

        $data = $request->validate(['label' => 'required|string|max:150'], [], ['label' => 'name']);

        DB::table('employee_field_options')->where('id', $option->id)->update([
            'label' => trim($data['label']), 'is_active' => $request->boolean('is_active'),
            'internal_only' => !empty($list['internal']) && $request->boolean('internal_only'), 'updated_at' => now(),
        ]);
        EmployeeMasterData::forget();

        return $this->back($section)->with('success', "“{$data['label']}” saved.");
    }

    public function moveOption(Request $request, int $optionId)
    {
        $section = $this->section($request);
        $option = $this->option($section, $optionId);
        $up = $request->input('direction') === 'up';

        $rows = DB::table('employee_field_options')->where('section', $section)->where('field', $option->field)->orderBy('sort_order')->orderBy('id')->get(['id'])->pluck('id')->all();
        $at = array_search($option->id, $rows, true);
        $to = $up ? $at - 1 : $at + 1;

        if ($to >= 0 && $to < count($rows)) {
            [$rows[$at], $rows[$to]] = [$rows[$to], $rows[$at]];
            foreach ($rows as $i => $id) {            // renumber the whole list: no gaps, no ties
                DB::table('employee_field_options')->where('id', $id)->update(['sort_order' => $i + 1]);
            }
            EmployeeMasterData::forget();
        }

        return $this->back($section);
    }

    public function destroyOption(Request $request, int $optionId)
    {
        $section = $this->section($request);
        $option = $this->option($section, $optionId);
        $list = EmployeeMasterSettings::get($section)['lists'][$option->field];

        $used = (int) (self::usage($list['column'])[$option->value] ?? 0);
        if ($used > 0) {
            return $this->back($section)->withErrors(['label' => "“{$option->label}” is used by {$used} record(s), so it is kept. Switch it off instead — it leaves the form and the records keep their value."]);
        }

        DB::table('employee_field_options')->where('id', $option->id)->delete();
        EmployeeMasterData::forget();

        return $this->back($section)->with('success', "“{$option->label}” deleted.");
    }

    // ── rules & list switches ────────────────────────────────────────────────

    public function updateSettings(Request $request)
    {
        $section = $this->section($request);
        $def = EmployeeMasterSettings::get($section);

        $rules = [];
        foreach ($def['rules'] as $key => $rule) {
            $rules["rules.{$key}"] = match ($rule['type']) {
                'int'   => ['required', 'integer', 'min:' . $rule['min'], 'max:' . $rule['max']],
                'bool'  => ['nullable'],
                default => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9 ,.]+$/'],
            };
        }
        $attributes = [];
        foreach ($def['rules'] as $key => $rule) {
            $attributes["rules.{$key}"] = mb_strtolower($rule['label']);
        }
        $request->validate($rules, ['regex' => 'Use letters, digits and commas only.'], $attributes);

        // Education: the earliest year cannot come after the latest.
        if ($section === 'education' && (int) $request->input('rules.year_min') > (int) $request->input('rules.year_max')) {
            return $this->back($section)->withInput()->withErrors(['rules.year_min' => 'The earliest year cannot be after the latest year.']);
        }

        DB::transaction(function () use ($request, $section, $def) {
            $save = function (string $key, ?string $value) use ($section) {
                DB::table('employee_section_settings')->updateOrInsert(['section' => $section, 'key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
            };

            foreach ($def['rules'] as $key => $rule) {
                $save($key, match ($rule['type']) {
                    'int'   => (string) (int) $request->input("rules.{$key}"),
                    'bool'  => $request->boolean("rules.{$key}") ? '1' : '0',
                    default => implode(',', array_filter(array_map('trim', explode(',', (string) $request->input("rules.{$key}"))))),
                });
            }
            foreach (array_keys($def['lists']) as $field) {
                $save("restrict.{$field}", $request->boolean("restrict.{$field}") ? '1' : '0');
            }
        });
        EmployeeMasterData::forget();

        return $this->back($section)->with('success', $def['title'] . ' settings saved.');
    }

    // ── internal ─────────────────────────────────────────────────────────────

    private function section(Request $request): string
    {
        // The tab is the last segment of the route group's literal prefix (management/employee/address) — fixed by routes/web.php, so it
        // cannot be chosen by the request; the menu slug that guards the route is that same tab's.
        $section = basename((string) $request->route()->getPrefix());
        abort_unless(EmployeeMasterSettings::exists($section), 404);

        return $section;
    }

    private function option(string $section, int $id): object
    {
        $option = DB::table('employee_field_options')->where('id', $id)->where('section', $section)->first();
        abort_unless($option, 404);

        return $option;
    }

    private function back(string $section)
    {
        return redirect()->route("management.employee.{$section}.index");
    }

    /** @param array{0: string, 1: string}|null $column @return array<string, int> how many records hold each value */
    private static function usage(?array $column): array
    {
        if (!$column || !Schema::hasTable($column[0]) || !Schema::hasColumn($column[0], $column[1])) {
            return [];
        }

        return DB::table($column[0])->whereNotNull($column[1])->selectRaw("`{$column[1]}` as v, COUNT(*) as c")->groupBy($column[1])->pluck('c', 'v')->map(fn ($c) => (int) $c)->all();
    }
}
