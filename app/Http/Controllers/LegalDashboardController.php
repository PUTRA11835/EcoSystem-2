<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Letterhead;
use App\Models\Letters\LetterSignatory;
use App\Services\Contracts\ContractService;
use App\Services\External\ExternalContractService;
use App\Support\Contracts\SigningRules;
use Illuminate\Support\Facades\DB;

/**
 * Legal → dashboard: the summary of what the Legal sub menus hold — counts only, each card from ONE source and shown only when the
 * viewer's slug for that source allows it (a card never reveals data of a page the person could not open). Every card links to the
 * existing page; this page changes nothing.
 *
 *   Contracts          general.contracts.list            by status, ending in 30 days, awaiting signature
 *   External contracts general.external.contracts        by status
 *   Document Identity  general.letters.settings.*        letterheads and signers (shared with HR letters)
 */
class LegalDashboardController extends Controller
{
    public function index(ContractService $contracts, ExternalContractService $external)
    {
        $employee = Employee::find((int) session('user.id'));
        $can = fn (string $slug): bool => (bool) $employee?->canAccessMenu($slug);

        $cards = [];

        if ($can('general.contracts.list')) {
            $s = $contracts->summary();
            $cards['contracts'] = [
                'title' => 'Contracts', 'icon' => 'file-contract', 'link' => route('general.contracts.index'),
                'stats' => [
                    ['Active', $s['active'], 'green'],
                    ['Draft', $s['draft'], 'amber'],
                    ['Ending in ' . (int) config('hc_contract.expiring_soon_days', 30) . ' days', $s['ending'], 'red'],
                    ['Employees without a contract', $s['none'], 'gray'],
                ],
            ];

            $stages = DB::table('employee_contract')->whereIn('sign_stage', [SigningRules::WITH_EMPLOYEE, SigningRules::WITH_LEGAL, SigningRules::WITH_APPROVER])
                ->selectRaw('sign_stage, COUNT(*) as n')->groupBy('sign_stage')->pluck('n', 'sign_stage');
            $cards['signing'] = [
                'title' => 'Awaiting signature', 'icon' => 'signature', 'link' => route('general.contracts.index'),
                'stats' => [
                    ['With the employee', (int) ($stages[SigningRules::WITH_EMPLOYEE] ?? 0), 'amber'],
                    ['With Legal', (int) ($stages[SigningRules::WITH_LEGAL] ?? 0), 'red'],
                    ['With the approver', (int) ($stages[SigningRules::WITH_APPROVER] ?? 0), 'amber'],
                ],
            ];
        }

        if ($can('general.external.contracts')) {
            $s = $external->summary();
            $cards['external'] = [
                'title' => 'External contracts', 'icon' => 'user-tie', 'link' => route('general.external.contracts.index'),
                'stats' => [
                    ['All contracts', $s['total'], 'gray'],
                    ['Active', $s['active'], 'green'],
                    ['Draft', $s['draft'], 'amber'],
                ],
            ];
        }

        $identity = [];
        if ($can('general.letters.settings.letterheads')) {
            $identity[] = ['Letterheads', Letterhead::count(), route('general.document-identity.index', ['section' => 'letterheads'])];
        }
        if ($can('general.letters.settings.signers')) {
            $identity[] = ['Active signers', LetterSignatory::where('is_active', true)->count(), route('general.document-identity.index', ['section' => 'signers'])];
        }
        if ($identity) {
            $cards['identity'] = [
                'title' => 'Document Identity', 'icon' => 'id-card', 'link' => $identity[0][2],
                'stats' => array_map(fn ($i) => [$i[0], $i[1], 'gray', $i[2]], $identity),
            ];
        }

        return view('legal.legal', ['cards' => $cards]);
    }
}
