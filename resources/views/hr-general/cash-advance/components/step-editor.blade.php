{{--
    Approval workflow of Cash Advance — used TWICE on the Settings tab, for the module `cash_advance` (CA) and
    `cash_advance_report` (CAR), one route serving both (D136). The shared editor (partials/approval-workflow)
    with step name, ACTOR (Verificator · Approver), position, names, Mandatory and Active.

    Variables: $module · $steps · $openCount · $heading · $blurb · $roles · $employees · $roleMembers · $employeeRoles
--}}
@include('hr-general.partials.approval-workflow.editor', ['wf' => [
    'uid'              => str_replace('_', '-', $module),
    'action'           => route('management.cash-advance-settings.steps.sync'),
    'module'           => $module,
    'title'            => $heading,
    'blurb'            => $blurb,
    'name'             => true,
    'name_placeholder' => 'e.g. Finance Verification',
    'select'           => [
        'field'   => 'actor_role',
        'label'   => 'Actor',
        'options' => \App\Models\CashAdvance\CashAdvanceApprovalStep::ACTOR_LABELS,
    ],
    'mandatory'        => true,
    'mandatory_hint'   => 'checked: the person here always acts and the requester only sees it. Unchecked: the requester chooses from them.',
    'open_count'       => $openCount,
    'open_label'       => 'document(s)',
    'note'             => 'Adding a step can be applied to documents already in progress; removing or changing one never is — a document waiting at a deleted step could otherwise jump to approved unreviewed. At least one active step must have the actor <strong>Approver</strong>: the printed "Approved by" column takes its name from it.',
]])
