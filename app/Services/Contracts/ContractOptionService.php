<?php

namespace App\Services\Contracts;

use App\Models\ContractOption;
use App\Models\ContractTemplate;
use App\Models\EmployeeContract;
use App\Services\HrProfile\EngagementRules;
use App\Support\Contracts\ContractRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dropdown data of the contract forms, kept per menu (scope) in HR → Contract → Settings and External → External Contract Settings.
 *
 *   contract types   built-in ones (the engine's: numbering, date rules and templates follow their code) that HR renames, describes,
 *                    switches on / off, reorders and marks as default — and custom ones HR creates and deletes: a custom type is an
 *                    ALIAS of a built-in one ("Probation", based on PKWT), so every rule keeps working.
 *   position types   HR's own list (Sales, Internship, Management Trainee …): a template and a contract can follow one.
 *
 * Reads fall back to the built-in lists when the table is missing or empty, so the forms never end up without options.
 */
class ContractOptionService
{
    private const CACHE = 'contract_options_v2';

    // ── Reads for the forms ───────────────────────────────────────────────

    /**
     * code => label of the ACTIVE options of a scope and kind, plus $keep when that one was switched off since
     * (so editing an old contract still shows its own value).
     *
     * @return array<string,string>
     */
    public function options(string $scope, string $kind, ?string $keep = null): array
    {
        $rows = $this->all()->where('scope', $scope)->where('kind', $kind);
        $out = $rows->where('is_active', true)->mapWithKeys(fn ($r) => [$r['code'] => $r['label']])->all();

        if ($out === [] && $kind === ContractOption::KIND_CONTRACT_TYPE) {
            $out = $scope === ContractOption::SCOPE_EXTERNAL
                ? EngagementRules::SCHEMES
                : array_intersect_key(ContractRules::TYPES, array_flip(ContractRules::INTERNAL_TYPES));
        }
        if ($keep !== null && $keep !== '' && !isset($out[$keep])) {
            $row = $rows->firstWhere('code', $keep);
            $out[$keep] = $row['label'] ?? (ContractRules::TYPES[$keep] ?? $keep);
        }

        return $out;
    }

    /**
     * Position-type names of a scope, for the searchable dropdowns (codes are an internal detail; the name is what is stored).
     *
     * @return string[]
     */
    public function positionTypes(string $scope, ?string $keep = null): array
    {
        $names = array_values($this->options($scope, ContractOption::KIND_POSITION_TYPE));
        if ($keep !== null && $keep !== '' && !in_array($keep, $names, true)) {
            $names[] = $keep;
        }

        return $names;
    }

    /** The code the form preselects (an active option marked as default), or null. */
    public function defaultCode(string $scope, string $kind): ?string
    {
        $row = $this->all()->where('scope', $scope)->where('kind', $kind)->where('is_active', true)->firstWhere('is_default', true);

        return $row['code'] ?? null;
    }

    /** The default POSITION TYPE name (positions are stored by name). */
    public function defaultPositionType(string $scope): ?string
    {
        $row = $this->all()->where('scope', $scope)->where('kind', ContractOption::KIND_POSITION_TYPE)->where('is_active', true)->firstWhere('is_default', true);

        return $row['label'] ?? null;
    }

    /**
     * What a form value stands for: the built-in type to run the rules with, and the custom type's code when it is one.
     *
     * @return array{base:string,kind:?string}
     */
    public function resolve(string $scope, string $code): array
    {
        $row = $this->all()->where('scope', $scope)->where('kind', ContractOption::KIND_CONTRACT_TYPE)->firstWhere('code', $code);
        if (!$row || $row['is_system']) {
            return ['base' => $code, 'kind' => null];
        }

        return ['base' => $row['base_type'] ?: $code, 'kind' => $code];
    }

    /** code => label of every contract type of a scope, switched on or off, for showing what a saved contract holds. @return array<string,string> */
    public function labels(string $scope): array
    {
        return $this->all()->where('scope', $scope)->where('kind', ContractOption::KIND_CONTRACT_TYPE)->mapWithKeys(fn ($r) => [$r['code'] => $r['label']])->all();
    }

    // ── Settings page ─────────────────────────────────────────────────────

    /** Every row of one scope and kind (active or not). @return Collection<int,ContractOption> */
    public function rows(string $scope, string $kind): Collection
    {
        return ContractOption::where('scope', $scope)->where('kind', $kind)->orderBy('sort_order')->orderBy('label')->get();
    }

    /**
     * How many contracts and templates hold each option — what decides whether it can be deleted.
     *
     * @return array<int,array{contracts:int,templates:int}> option id => counts
     */
    public function usage(string $scope): array
    {
        $out = [];
        $rows = ContractOption::where('scope', $scope)->get();

        $contractsByKind = EmployeeContract::whereNotNull('contract_kind')->selectRaw('contract_kind, count(*) as n')->groupBy('contract_kind')->pluck('n', 'contract_kind');
        $contractsByPosition = EmployeeContract::whereNotNull('position_type')->selectRaw('position_type, count(*) as n')->groupBy('position_type')->pluck('n', 'position_type');
        $templatesByPosition = ContractTemplate::whereNotNull('position_type')->selectRaw('position_type, count(*) as n')->groupBy('position_type')->pluck('n', 'position_type');
        $templatesByType = ContractTemplate::selectRaw('contract_type, count(*) as n')->groupBy('contract_type')->pluck('n', 'contract_type');

        foreach ($rows as $row) {
            if ($row->kind === ContractOption::KIND_POSITION_TYPE) {
                $out[$row->id] = ['contracts' => (int) ($contractsByPosition[$row->label] ?? 0), 'templates' => (int) ($templatesByPosition[$row->label] ?? 0)];
                continue;
            }
            if ($row->is_system) {
                // A built-in type: every contract of that type that is not filed under a custom alias.
                $out[$row->id] = ['contracts' => $this->builtInTypeCount($scope, $row->code), 'templates' => $scope === ContractOption::SCOPE_EXTERNAL
                    ? 0 : (int) ($templatesByType[$row->code] ?? 0)];
                continue;
            }
            $out[$row->id] = ['contracts' => (int) ($contractsByKind[$row->code] ?? 0), 'templates' => 0];
        }

        return $out;
    }

    /** @throws \DomainException */
    public function addPositionType(string $scope, string $name, ?string $description = null): ContractOption
    {
        $name = $this->cleanName($name);
        $code = $this->codeOf($name);
        $this->assertFree($scope, ContractOption::KIND_POSITION_TYPE, $code, $name);

        $row = ContractOption::create([
            'scope' => $scope, 'kind' => ContractOption::KIND_POSITION_TYPE, 'code' => $code, 'label' => $name,
            'description' => $this->cleanText($description),
            'sort_order' => $this->nextOrder($scope, ContractOption::KIND_POSITION_TYPE), 'is_active' => true, 'is_system' => false,
        ]);
        $this->forget();

        return $row;
    }

    /**
     * Several position types at once, one per line. Lines already in the list are skipped, not an error.
     *
     * @return array{added:string[],skipped:string[]}
     * @throws \DomainException
     */
    public function addPositionTypes(string $scope, string $lines): array
    {
        $names = collect(preg_split('/\R/', $lines))->map(fn ($l) => trim((string) preg_replace('/\s+/', ' ', $l)))->filter()->unique()->take(50)->values();
        if ($names->isEmpty()) {
            throw new \DomainException('Type at least one name, one per line.');
        }

        $added = [];
        $skipped = [];
        foreach ($names as $name) {
            try {
                $this->addPositionType($scope, $name);
                $added[] = $name;
            } catch (\DomainException) {
                $skipped[] = $name;
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * A custom contract type: an alias of a built-in type of the same menu.
     *
     * @throws \DomainException
     */
    public function addContractType(string $scope, string $name, string $baseCode, ?string $description = null): ContractOption
    {
        $base = ContractOption::where('scope', $scope)->where('kind', ContractOption::KIND_CONTRACT_TYPE)->where('is_system', true)->where('code', $baseCode)->first();
        if (!$base) {
            throw new \DomainException('Choose which built-in type the new type behaves like.');
        }

        $name = $this->cleanName($name);
        $code = 'CUSTOM_' . $this->codeOf($name);
        $this->assertFree($scope, ContractOption::KIND_CONTRACT_TYPE, $code, $name);

        $row = ContractOption::create([
            'scope' => $scope, 'kind' => ContractOption::KIND_CONTRACT_TYPE, 'code' => mb_substr($code, 0, 60), 'base_type' => $base->code,
            'label' => $name, 'description' => $this->cleanText($description),
            'sort_order' => $this->nextOrder($scope, ContractOption::KIND_CONTRACT_TYPE), 'is_active' => true, 'is_system' => false,
        ]);
        $this->forget();

        return $row;
    }

    /**
     * Rename, describe, switch and mark as default. Renaming a position type moves the contracts and templates that carry
     * its old name; the last active contract type cannot be switched off.
     *
     * @throws \DomainException
     */
    public function update(ContractOption $option, string $label, bool $active, ?string $description = null, bool $default = false): ContractOption
    {
        $label = $this->cleanName($label);
        $old = $option->label;

        if ($option->kind === ContractOption::KIND_POSITION_TYPE && $label !== $old) {
            $code = $this->codeOf($label);
            $clash = ContractOption::where('scope', $option->scope)->where('kind', $option->kind)->where('code', $code)->where('id', '!=', $option->id)->exists();
            if ($clash) {
                throw new \DomainException("“{$label}” is already in the list.");
            }
            $option->code = $code;
        }

        if (!$active && $option->kind === ContractOption::KIND_CONTRACT_TYPE) {
            $others = ContractOption::where('scope', $option->scope)->where('kind', $option->kind)->where('is_active', true)->where('id', '!=', $option->id)->exists();
            if (!$others) {
                throw new \DomainException('At least one contract type has to stay on, or the form has nothing to offer.');
            }
        }
        if ($default && !$active) {
            throw new \DomainException('A type that is switched off cannot be the default.');
        }

        DB::transaction(function () use ($option, $label, $active, $description, $default, $old) {
            if ($default) {
                ContractOption::where('scope', $option->scope)->where('kind', $option->kind)->where('id', '!=', $option->id)->update(['is_default' => false]);
            }
            $option->label = $label;
            $option->is_active = $active;
            $option->description = $this->cleanText($description);
            $option->is_default = $default && $active;
            $option->save();

            if ($option->kind === ContractOption::KIND_POSITION_TYPE && $label !== $old) {
                EmployeeContract::where('position_type', $old)->update(['position_type' => $label]);
                ContractTemplate::where('position_type', $old)->update(['position_type' => $label]);
            }
        });
        $this->forget();

        return $option;
    }

    /** Swap an option with its neighbour so the form lists it earlier or later. */
    public function move(ContractOption $option, string $direction): void
    {
        $siblings = $this->rows($option->scope, $option->kind)->values();
        $i = $siblings->search(fn ($r) => $r->id === $option->id);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i === false || !isset($siblings[$j])) {
            return;
        }

        // Re-number the whole list so equal sort values never make a move do nothing.
        $order = $siblings->pluck('id')->all();
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        DB::transaction(function () use ($order) {
            foreach ($order as $position => $id) {
                ContractOption::whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });
        $this->forget();
    }

    /**
     * Delete an option nobody holds. Built-in contract types are the engine's and stay; switch them off instead.
     *
     * @throws \DomainException
     */
    public function delete(ContractOption $option): void
    {
        if ($option->kind === ContractOption::KIND_CONTRACT_TYPE && $option->is_system) {
            throw new \DomainException('A built-in contract type cannot be deleted: numbering, date rules and templates follow it. Switch it off instead.');
        }

        $usage = $this->usage($option->scope)[$option->id] ?? ['contracts' => 0, 'templates' => 0];
        $used = $usage['contracts'] + $usage['templates'];
        if ($used > 0) {
            throw new \DomainException("“{$option->label}” is used by {$usage['contracts']} contract(s) and {$usage['templates']} template(s). Switch it off instead of deleting it.");
        }

        $option->delete();
        $this->forget();
    }

    // ── internals ─────────────────────────────────────────────────────────

    private function builtInTypeCount(string $scope, string $code): int
    {
        if ($scope === ContractOption::SCOPE_EXTERNAL) {
            $aliases = \App\Support\External\ExternalRules::schemeAliases($code);
            $aliases[] = $code;

            return (int) DB::table('external_contract_terms as t')->join('employee_contract as c', 'c.contract_id', '=', 't.contract_id')
                ->whereNull('c.contract_kind')->whereIn(DB::raw("LOWER(REPLACE(REPLACE(t.engagement_scheme, ' ', ''), '_', ''))"), array_map(fn ($a) => strtolower(str_replace('_', '', $a)), $aliases))->count();
        }

        return (int) EmployeeContract::whereNull('contract_kind')->whereIn(DB::raw('UPPER(contract_type)'), ContractRules::aliasKeysOf($code))->count();
    }

    /** @throws \DomainException */
    private function assertFree(string $scope, string $kind, string $code, string $name): void
    {
        $taken = ContractOption::where('scope', $scope)->where('kind', $kind)
            ->where(fn ($q) => $q->where('code', $code)->orWhere('label', $name))->exists();
        if ($taken) {
            throw new \DomainException("“{$name}” is already in the list.");
        }
    }

    private function nextOrder(string $scope, string $kind): int
    {
        return (int) ContractOption::where('scope', $scope)->where('kind', $kind)->max('sort_order') + 10;
    }

    private function all(): Collection
    {
        if (!Schema::hasTable('contract_options')) {
            return collect();
        }

        return Cache::remember(self::CACHE, 300, fn () => ContractOption::orderBy('sort_order')->orderBy('label')->get()->map->toArray());
    }

    private function forget(): void
    {
        Cache::forget(self::CACHE);
    }

    /** @throws \DomainException */
    private function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        if ($name === '') {
            throw new \DomainException('The name cannot be empty.');
        }

        return mb_substr($name, 0, 100);
    }

    private function cleanText(?string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));

        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private function codeOf(string $name): string
    {
        $code = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_'));

        return mb_substr($code !== '' ? $code : 'OPTION', 0, 50);
    }
}
