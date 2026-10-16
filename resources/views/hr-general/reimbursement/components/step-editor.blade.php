{{--
    Approval workflow of Reimbursement: the shared editor (partials/approval-workflow) with the reimbursement columns —
    what the step signs as (Accounting · Cashier · Approver), position, names, Mandatory, Active.

    Variables: $steps · $openCount · $roles · $employees · $roleMembers · $employeeRoles
--}}
@include('hr-general.partials.approval-workflow.editor', ['wf' => [
    'uid'        => 'reimbursement',
    'action'     => route('general.reimbursement.settings.steps.sync'),
    'title'      => 'Approval Workflow',
    'blurb'      => 'Steps run top to bottom; one person per step is enough.',
    'select'     => [
        'field'   => 'signs_as',
        'label'   => 'Signs as',
        'options' => \App\Models\Reimbursement\ReimbursementApprovalStep::SIGNS_AS_LABELS,
    ],
    'mandatory'      => true,
    'mandatory_hint' => 'checked: the person here always acts; New RB only shows signs as, position and name. Unchecked: the requester must choose from them.',
    'open_count'     => $openCount,
]])
