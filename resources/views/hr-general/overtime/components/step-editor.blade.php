{{--
    Approval workflow of Overtime (slug `general.approval-workflow.overtime`, separate from the Rules): the shared
    editor (partials/approval-workflow) with the columns Overtime needs — step name, position, names, Active.
    Overtime has no "chosen by requester" and no signature role, so those columns are off.

    Variables: $steps · $roles · $employees · $roleMembers · $employeeRoles
--}}
@include('hr-general.partials.approval-workflow.editor', ['wf' => [
    'uid'              => 'overtime',
    'action'           => route('general.overtime.settings.steps.sync'),
    'title'            => 'Approval Workflow',
    'blurb'            => 'Requests move through these steps in order; each step needs only one approver to act. A step can name a position or people, or follow the requester: their supervisor, or the leader of their project.',
    'name'             => true,
    'dynamic'          => true,
    'name_placeholder' => 'e.g. Team Lead',
    'note'             => '<span class="font-semibold">Changes apply to new requests only.</span> Requests already in progress keep the steps they were created with, so editing this list never rewrites an approval that already happened.',
]])
