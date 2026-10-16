<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\ContractOption;
use App\Services\Contracts\ContractOptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Settings tab of the contract menus — dropdown data of the contract forms, kept apart for each menu:
 *
 *   scope internal   HR & General → Contract → Settings            (slug general.contracts.settings)
 *   scope external   External → External Contract → Settings       (slug general.external.contracts.settings)
 *
 * Contract types: the built-in ones are renamed, described, reordered, switched on / off and marked as default; custom ones (an
 * alias of a built-in type) are created and deleted. Position types: created (one or several at once), edited, reordered, deleted.
 * The scope comes from the route (`->defaults('scope', …)`), never from the request, so one menu cannot edit the other's list.
 *
 * C add · E edit / reorder / switch / default · D delete an option nobody uses. Only GET and POST (production blocks DELETE).
 */
class ContractSettingController extends Controller
{
    private const SLUGS = [
        ContractOption::SCOPE_INTERNAL => 'general.contracts.settings',
        ContractOption::SCOPE_EXTERNAL => 'general.external.contracts.settings',
    ];

    public function __construct(private ContractOptionService $options) {}

    public function index(string $scope)
    {
        $this->assertScope($scope);

        $contractTypes = $this->options->rows($scope, ContractOption::KIND_CONTRACT_TYPE);

        return view('hr-general.contracts.settings', [
            'scope'         => $scope,
            'external'      => $scope === ContractOption::SCOPE_EXTERNAL,
            'contractTypes' => $contractTypes,
            'positionTypes' => $this->options->rows($scope, ContractOption::KIND_POSITION_TYPE),
            'usage'         => $this->options->usage($scope),
            // What a custom type can be an alias of: the built-in types of this menu.
            'baseChoices'   => $contractTypes->where('is_system', true)->pluck('label', 'code')->all(),
            'baseLabels'    => $contractTypes->pluck('label', 'code')->all(),
            'routes'        => $this->routes($scope),
            'slug'          => self::SLUGS[$scope],
        ]);
    }

    /** One endpoint for the three ways to add: a contract type, a position type, or several position types at once. */
    public function store(Request $request, string $scope)
    {
        $this->assertScope($scope);
        $kind = $request->validate(['kind' => ['required', Rule::in(['position_type', 'position_bulk', 'contract_type'])]])['kind'];

        try {
            $message = match ($kind) {
                'contract_type' => $this->addContractType($request, $scope),
                'position_bulk' => $this->addSeveral($request, $scope),
                default         => $this->addPositionType($request, $scope),
            };
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['option' => $e->getMessage()])->with('open_add', $kind);
        }

        return redirect()->route($this->routes($scope)['index'])->with('success', $message);
    }

    public function update(Request $request, string $scope, int $optionId)
    {
        $this->assertScope($scope);
        $option = $this->ownOption($scope, $optionId);
        $data = $request->validate([
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'nullable|boolean',
            'is_default'  => 'nullable|boolean',
        ]);

        try {
            $this->options->update($option, $data['label'], (bool) ($data['is_active'] ?? false), $data['description'] ?? null, (bool) ($data['is_default'] ?? false));
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['option' => $e->getMessage()]);
        }

        return redirect()->route($this->routes($scope)['index'])->with('success', "“{$option->label}” saved.");
    }

    /** Earlier or later in the form's dropdown. */
    public function move(Request $request, string $scope, int $optionId)
    {
        $this->assertScope($scope);
        $option = $this->ownOption($scope, $optionId);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $this->options->move($option, $direction);

        return redirect()->route($this->routes($scope)['index']);
    }

    public function destroy(string $scope, int $optionId)
    {
        $this->assertScope($scope);
        $option = $this->ownOption($scope, $optionId);

        try {
            $this->options->delete($option);
        } catch (\DomainException $e) {
            return back()->withErrors(['option' => $e->getMessage()]);
        }

        return redirect()->route($this->routes($scope)['index'])->with('success', "“{$option->label}” deleted.");
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function addPositionType(Request $request, string $scope): string
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:255']);
        $row = $this->options->addPositionType($scope, $data['name'], $data['description'] ?? null);

        return "Position type “{$row->label}” added.";
    }

    private function addSeveral(Request $request, string $scope): string
    {
        $data = $request->validate(['names' => 'required|string|max:5000']);
        $result = $this->options->addPositionTypes($scope, $data['names']);

        $text = count($result['added']) . ' position type(s) added.';
        if ($result['skipped']) {
            $text .= ' Skipped (already in the list): ' . implode(', ', $result['skipped']) . '.';
        }

        return $text;
    }

    private function addContractType(Request $request, string $scope): string
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'base' => 'required|string|max:30', 'description' => 'nullable|string|max:255']);
        $row = $this->options->addContractType($scope, $data['name'], $data['base'], $data['description'] ?? null);

        return "Contract type “{$row->label}” added.";
    }

    private function assertScope(string $scope): void
    {
        abort_unless(isset(self::SLUGS[$scope]), 404);
    }

    /** An option can only be reached through the menu (scope) it belongs to. */
    private function ownOption(string $scope, int $optionId): ContractOption
    {
        return ContractOption::where('scope', $scope)->findOrFail($optionId);
    }

    /** @return array{index:string,store:string,update:string,destroy:string,move:string} */
    private function routes(string $scope): array
    {
        $base = $scope === ContractOption::SCOPE_EXTERNAL ? 'general.external.contracts.settings.' : 'general.contracts.settings.';

        return ['index' => $base . 'index', 'store' => $base . 'store', 'update' => $base . 'update', 'destroy' => $base . 'destroy', 'move' => $base . 'move'];
    }
}
