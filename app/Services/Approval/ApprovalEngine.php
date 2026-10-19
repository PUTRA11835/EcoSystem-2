<?php

namespace App\Services\Approval;

use App\Models\CashAdvance\CashAdvanceApproval;
use App\Models\CashAdvance\CashAdvanceReportApproval;
use App\Models\Overtime\OvertimeRequestApproval;
use App\Models\PurchaseRequest\PurchaseRequestApproval;
use App\Models\Reimbursement\ReimbursementRequestApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one place that answers "who may act on which approval, and what is waiting for whom" — for every module that has a
 * step-by-step approval (Overtime, Reimbursement, Purchase Request, Cash Advance, Cash Advance Report).
 *
 * WHY. A department head, a director or a finance officer is named on a workflow step but should not hold the HR / general menus
 * just to approve. The workflow is the source of truth for who approves; this engine reads it, so:
 *   - a person NAMED on a step (by position, by name, as a supervisor, as a project leader) is a "designated approver" and may open
 *     the review page and the documents that concern them, with no menu slug;
 *   - the Approval Inbox (General → My Approvals) lists, across all modules, what is waiting for exactly that person;
 *   - a designated approver sees only the documents they take part in (see {@see participates()}) — never the whole module list.
 * Who may ACT on one document stays with the module's service (canAct): it also knows locked periods, self-approval and the
 * requester's settings. The engine only opens the door and builds the inbox.
 *
 * The modules keep their own tables and models (their history must not be migrated); their step snapshots share one shape —
 * approver_type / approver_role_id / approver_employee_ids / status / order_seq — and that is all the engine relies on. The
 * registry below says where each module's pieces are.
 */
class ApprovalEngine
{
    /**
     * @var array<string,array{label:string, approval:class-string<Model>, relation:string, fk:string, number:string, route:string, param:string, who:string}>
     *  label     what the inbox calls the module
     *  approval  the per-document step snapshot model
     *  relation  the snapshot's relation to its document
     *  fk        the snapshot's foreign key to its document
     *  number    the document's number column
     *  route     the review page of one document (inbox link); null when the module only has a list page
     *  param     the route parameter that carries the document (for the per-document check of the middleware)
     */
    public const MODULES = [
        'overtime' => [
            'label' => 'Overtime', 'approval' => OvertimeRequestApproval::class, 'relation' => 'request', 'fk' => 'overtime_request_id',
            'number' => 'request_no', 'route' => 'general.overtime.index', 'param' => 'overtimeRequest',
            'document' => \App\Models\Overtime\OvertimeRequest::class, 'controller' => null,
        ],
        'reimbursement' => [
            'label' => 'Reimbursement', 'approval' => ReimbursementRequestApproval::class, 'relation' => 'request', 'fk' => 'reimbursement_request_id',
            'number' => 'request_no', 'route' => 'general.reimbursement.show', 'param' => 'reimbursementRequest',
            'document' => \App\Models\Reimbursement\ReimbursementRequest::class, 'controller' => \App\Http\Controllers\HR_General\ReimbursementController::class,
        ],
        'purchase-request' => [
            'label' => 'Purchase Request', 'approval' => PurchaseRequestApproval::class, 'relation' => 'request', 'fk' => 'purchase_request_id',
            'number' => 'request_no', 'route' => 'general.purchase-request.show', 'param' => 'purchaseRequest',
            'document' => \App\Models\PurchaseRequest\PurchaseRequest::class, 'controller' => \App\Http\Controllers\HR_General\PurchaseRequestController::class,
        ],
        'cash-advance' => [
            'label' => 'Cash Advance', 'approval' => CashAdvanceApproval::class, 'relation' => 'cashAdvance', 'fk' => 'cash_advance_id',
            'number' => 'request_no', 'route' => 'general.cash-advance.show', 'param' => 'cashAdvance',
            'document' => \App\Models\CashAdvance\CashAdvance::class, 'controller' => \App\Http\Controllers\HR_General\CashAdvanceController::class,
        ],
        'cash-advance-report' => [
            'label' => 'Cash Advance Report', 'approval' => CashAdvanceReportApproval::class, 'relation' => 'report', 'fk' => 'cash_advance_report_id',
            'number' => 'report_no', 'route' => 'general.cash-advance-report.show', 'param' => 'cashAdvanceReport',
            'document' => \App\Models\CashAdvance\CashAdvanceReport::class, 'controller' => \App\Http\Controllers\HR_General\CashAdvanceReportController::class,
        ],
    ];

    /** @var array<int,int[]> */
    private array $roles = [];

    /** @var array<string,Collection> module:employee => waiting approvals */
    private array $pending = [];

    public function modules(): array
    {
        return array_keys(self::MODULES);
    }

    public function has(string $module): bool
    {
        return isset(self::MODULES[$module]);
    }

    /** @return int[] the positions (employee_role ids) the employee holds */
    public function roleIdsOf(int $employeeId): array
    {
        return $this->roles[$employeeId] ??= DB::table('employee_role_assignment')
            ->where('employee_id', $employeeId)->pluck('role_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Whether the step snapshot of one document lets this person act. Same rule the snapshot models use in allows(); kept here so
     * a caller holding only an approval row (or a plain object) can ask without knowing the module.
     */
    public function stepAllows(object $step, int $employeeId): bool
    {
        if (!empty($step->chosen_by_requester)) {
            return in_array($employeeId, array_map('intval', $step->approver_employee_ids ?? []), true);
        }

        return match ($step->approver_type) {
            'role'     => $step->approver_role_id !== null && in_array((int) $step->approver_role_id, $this->roleIdsOf($employeeId), true),
            'employee' => in_array($employeeId, array_map('intval', $step->approver_employee_ids ?? []), true),
            default    => false,
        };
    }

    /**
     * The step snapshots that wait for this person NOW — the document is open and the snapshot is its current step.
     *
     * @return Collection<int,Model>
     */
    public function waitingFor(string $module, int $employeeId): Collection
    {
        return $this->pending["$module:$employeeId"] ??= $this->loadWaiting($module, $employeeId);
    }

    /** @return Collection<int,Model> */
    private function loadWaiting(string $module, int $employeeId): Collection
    {
        $cfg = self::MODULES[$module] ?? null;
        if (!$cfg) {
            return collect();
        }

        /** @var class-string<Model> $model */
        $model = $cfg['approval'];

        return $model::query()
            ->where('status', 'waiting')
            ->with($cfg['relation'])
            ->get()
            ->filter(function (Model $step) use ($cfg, $employeeId) {
                $document = $step->{$cfg['relation']};
                if (!$document || !$document->isOpen()) {
                    return false;
                }
                $current = $document->currentApproval();

                return $current && (int) $current->getKey() === (int) $step->getKey() && $this->stepAllows($step, $employeeId);
            })
            ->values();
    }

    /**
     * Whether a document of this module waits for this person right now — the single lookup `menu.approver`, the sidebar entry
     * and the decision buttons all use.
     */
    public function isPending(string $module, int $employeeId): bool
    {
        return $this->hasPending($module, $employeeId);
    }

    /** Whether something waits for this person in this module right now. */
    public function hasPending(string $module, int $employeeId): bool
    {
        return $this->waitingFor($module, $employeeId)->isNotEmpty();
    }

    /**
     * Whether the person is named on the workflow of a module — an active configured step that includes them, or a document
     * waiting on them. This is what opens a review page without a menu slug.
     */
    public function isDesignatedApprover(string $module, int $employeeId): bool
    {
        $cfg = self::MODULES[$module] ?? null;
        if (!$cfg) {
            return false;
        }

        return $this->hasPending($module, $employeeId) || $this->isConfiguredApprover($module, $employeeId);
    }

    /** Named on an ACTIVE step of the module's configured workflow (not on a document yet). */
    public function isConfiguredApprover(string $module, int $employeeId): bool
    {
        $stepModel = $this->stepModel($module);
        if ($stepModel === null) {
            return false;
        }

        $resolver = app(ApproverResolver::class);

        return $stepModel::query()->where('is_active', true)->where('module', $this->stepModuleKey($module))->get()->contains(fn ($step) => match ($step->approver_type) {
            'role'           => $step->approver_role_id !== null && in_array((int) $step->approver_role_id, $this->roleIdsOf($employeeId), true),
            'employee'       => in_array($employeeId, array_map('intval', $step->approver_employee_ids ?? []), true),
            'direct_manager' => $resolver->supervisesAnyone($employeeId),
            'project_lead'   => $resolver->leadsAnyProject($employeeId),
            default          => false,
        });
    }

    /** Named as an approver in ANY module — shows the Approval Inbox entry. */
    public function isApproverAnywhere(int $employeeId): bool
    {
        foreach ($this->modules() as $module) {
            if ($this->isDesignatedApprover($module, $employeeId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this person takes part in one document: its requester, or anyone named on any of its steps (past, present or
     * future). This is what a designated approver without the menu slug may open — the documents they are part of.
     */
    public function participates(string $module, Model $document, int $employeeId): bool
    {
        $cfg = self::MODULES[$module] ?? null;
        if (!$cfg) {
            return false;
        }

        if ((int) ($document->employee_id ?? 0) === $employeeId || (int) ($document->created_by ?? 0) === $employeeId) {
            return true;
        }

        /** @var class-string<Model> $model */
        $model = $cfg['approval'];

        return $model::query()->where($cfg['fk'], $document->getKey())->get()->contains(
            fn (Model $step) => (int) $step->acted_by === $employeeId || $this->stepAllows($step, $employeeId)
        );
    }

    /**
     * The Approval Inbox: every document waiting for this person, oldest first.
     *
     * @return Collection<int,array{module:string,label:string,id:int,number:string,requester:?string,step:string,since:?\Illuminate\Support\Carbon,url:string}>
     */
    public function inbox(int $employeeId): Collection
    {
        $rows = collect();

        foreach (self::MODULES as $module => $cfg) {
            foreach ($this->waitingFor($module, $employeeId) as $step) {
                $document = $step->{$cfg['relation']};
                $requesterId = (int) ($document->employee_id ?? 0);

                $rows->push([
                    'module'    => $module,
                    'label'     => $cfg['label'],
                    'id'        => (int) $document->getKey(),
                    'number'    => (string) ($document->{$cfg['number']} ?? '#' . $document->getKey()),
                    'requester' => $requesterId ? $this->nameOf($requesterId) : null,
                    'step'      => (string) $step->step_name,
                    'since'     => $step->updated_at ?? $step->created_at ?? $document->created_at,
                    'url'       => $this->urlFor($module, $document),
                ]);
            }
        }

        return $rows->sortBy(fn ($r) => $r['since']?->timestamp ?? 0)->values();
    }

    /**
     * The review link of one document: the approver-only page (General → My Approvals → document), which has no HR layout and
     * does not depend on any module menu. The modules' own detail pages stay for HR.
     */
    public function urlFor(string $module, Model $document): string
    {
        return route('general.approvals.show', ['module' => $module, 'id' => $document->getKey()]);
    }

    /** @return class-string<Model>|null the module's configured-step model */
    private function stepModel(string $module): ?string
    {
        return match ($module) {
            'overtime'            => \App\Models\Overtime\OvertimeApprovalStep::class,
            'reimbursement'       => \App\Models\Reimbursement\ReimbursementApprovalStep::class,
            'purchase-request'    => \App\Models\PurchaseRequest\PurchaseRequestApprovalStep::class,
            'cash-advance',
            'cash-advance-report' => \App\Models\CashAdvance\CashAdvanceApprovalStep::class,
            default               => null,
        };
    }

    /** The `module` value stored on that module's configured steps (several modules may share one steps table). */
    private function stepModuleKey(string $module): string
    {
        return match ($module) {
            'purchase-request'    => 'purchase_request',
            'cash-advance'        => 'cash_advance',
            'cash-advance-report' => 'cash_advance_report',
            default               => $module,
        };
    }

    private function nameOf(int $employeeId): ?string
    {
        return \App\Models\Employee::with('basicData:basic_data_id,employee_id,nick_name,first_name,last_name')->find($employeeId)?->fullNameLabel();
    }
}
