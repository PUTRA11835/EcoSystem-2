<?php

namespace App\Services\Management;

use App\Support\Management\EmployeeMasterSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Reads what Management → Employee configures (see App\Support\Management\EmployeeMasterSettings): the options of a dropdown and the
 * rules of a tab. The employee forms ask it what to offer; the API controllers ask it what to accept.
 *
 * Without the tables (a fresh install, unit tests) every answer is the default the definition carries, so nothing depends on the
 * settings having been saved first.
 */
class EmployeeMasterData
{
    /** @var array<string, array<int, object>> "section.field" → option rows, in order */
    private static array $options = [];

    /** @var array<string, array<string, string|null>>|null section → [key → value] */
    private static ?array $settings = null;

    public static function forget(): void
    {
        self::$options = [];
        self::$settings = null;
    }

    // ── lists ────────────────────────────────────────────────────────────────

    /**
     * The rows of a list, in order. Without saved rows: the defaults.
     *
     * @return array<int, object{value: string, label: string, is_active: bool, internal_only: bool, sort_order: int}>
     */
    public static function rows(string $section, string $field): array
    {
        $key = "{$section}.{$field}";
        if (isset(self::$options[$key])) {
            return self::$options[$key];
        }

        $rows = [];
        try {
            $rows = DB::table('employee_field_options')->where('section', $section)->where('field', $field)
                ->orderBy('sort_order')->orderBy('id')->get(['value', 'label', 'is_active', 'internal_only', 'sort_order'])->all();
        } catch (\Throwable) {
            // no table yet
        }

        if ($rows === [] && !self::tableHolds($section, $field)) {
            $order = 0;
            foreach ((EmployeeMasterSettings::get($section)['lists'][$field]['defaults'] ?? []) as $value => $label) {
                $rows[] = (object) ['value' => (string) $value, 'label' => $label, 'is_active' => true, 'internal_only' => false, 'sort_order' => ++$order];
            }
        }

        return self::$options[$key] = $rows;
    }

    /** True when the list was emptied on purpose (rows were saved once) rather than never saved. */
    private static function tableHolds(string $section, string $field): bool
    {
        try {
            return DB::table('employee_field_options')->where('section', $section)->where('field', $field)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** What a dropdown offers: [value => label] of the options that are on. */
    public static function options(string $section, string $field): array
    {
        $out = [];
        foreach (self::rows($section, $field) as $row) {
            if ($row->is_active) {
                $out[$row->value] = $row->label;
            }
        }

        return $out;
    }

    /** Values only External consultants do not get. @return string[] */
    public static function internalOnly(string $section, string $field): array
    {
        return array_values(array_map(fn ($r) => $r->value, array_filter(self::rows($section, $field), fn ($r) => $r->is_active && $r->internal_only)));
    }

    /** The values a save may carry: what is on, plus (when editing) the value the record already holds. @return string[] */
    public static function values(string $section, string $field, ?string $current = null): array
    {
        $values = array_keys(self::options($section, $field));
        if ($current !== null && $current !== '' && !in_array($current, $values, true)) {
            $values[] = $current;
        }

        return array_map('strval', $values);
    }

    /** Does the API refuse a value that is not on the list? */
    public static function restricts(string $section, string $field): bool
    {
        $saved = self::setting($section, "restrict.{$field}");

        return $saved !== null ? $saved === '1' : (bool) (EmployeeMasterSettings::get($section)['lists'][$field]['restrict'] ?? false);
    }

    /**
     * `in:` rule for a field of a save, or null when the list does not restrict. $current: the value the record already has
     * (an option switched off since stays valid for the record that holds it).
     */
    public static function inRule(string $section, string $field, ?string $current = null): ?In
    {
        return self::restricts($section, $field) ? Rule::in(self::values($section, $field, $current)) : null;
    }

    // ── rules ────────────────────────────────────────────────────────────────

    public static function setting(string $section, string $key): ?string
    {
        if (self::$settings === null) {
            self::$settings = [];
            try {
                foreach (DB::table('employee_section_settings')->get(['section', 'key', 'value']) as $row) {
                    self::$settings[$row->section][$row->key] = $row->value;
                }
            } catch (\Throwable) {
                // no table yet
            }
        }

        return self::$settings[$section][$key] ?? null;
    }

    /** A rule's value, typed, inside the range its definition allows. @return int|bool|string */
    public static function rule(string $section, string $key): int|bool|string
    {
        $def = EmployeeMasterSettings::get($section)['rules'][$key] ?? null;
        if ($def === null) {
            throw new \InvalidArgumentException("Unknown rule {$section}.{$key}");
        }

        $saved = self::setting($section, $key);

        return match ($def['type']) {
            'int'  => max($def['min'], min($def['max'], $saved === null || $saved === '' ? $def['default'] : (int) $saved)),
            'bool' => $saved === null ? (bool) $def['default'] : $saved === '1',
            default => $saved === null || trim($saved) === '' ? (string) $def['default'] : trim($saved),
        };
    }

    /** A length limit as a `max:` rule value — never above what the database column holds (the definition's max). */
    public static function maxLength(string $section, string $key): int
    {
        return (int) self::rule($section, $key);
    }

    /** A "require" rule as validation words: `required` or `nullable`. */
    public static function presence(string $section, string $key, bool $alwaysRequired = false): string
    {
        return $alwaysRequired || self::rule($section, $key) ? 'required' : 'nullable';
    }

    /** @return string[] lower-case file extensions the attachment tab accepts */
    public static function allowedExtensions(): array
    {
        $list = array_filter(array_map(fn ($e) => strtolower(trim(ltrim($e, '. '))), explode(',', (string) self::rule('attachment', 'allowed_extensions'))));

        return array_values(array_unique($list)) ?: ['pdf'];
    }
}
