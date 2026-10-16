{{--
    Approval workflow of Purchase Request (slug `general.approval-workflow.purchase-request`): the shared editor
    (partials/approval-workflow) with step name, position, names, Mandatory (D126 — a step the requester must
    choose the approver for) and Active.

    Variables: $steps · $openCount · $roles · $employees · $roleMembers · $employeeRoles
--}}
@include('hr-general.partials.approval-workflow.editor', ['wf' => [
    'uid'              => 'purchase-request',
    'action'           => route('general.purchase-request.settings.steps.sync'),
    'title'            => 'Approval Workflow',
    'blurb'            => 'A request moves to the next step only after the current one is approved.',
    'name'             => true,
    'name_placeholder' => 'e.g. Department Head',
    'mandatory'        => true,
    'mandatory_hint'   => 'checked: the person here always acts and the requester only sees it. Unchecked: the requester chooses the approver of this step from them (shown as "Name (chosen by requester)").',
    'open_count'       => $openCount,
    'open_label'       => 'request(s)',
    'note'             => '<span class="font-semibold">The signature block of the printed form is built from these steps</span> — Requester first, then one column per active step, signed by whoever actually approved it.',
]])
