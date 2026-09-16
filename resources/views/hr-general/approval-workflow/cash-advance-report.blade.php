@extends('dashboard')

@section('title', 'Approval Workflow — Cash Advance Report')
@section('page-title', 'Approval Workflow')
@section('page-subtitle', 'Configure the approval chain for each module — separate from its rules')

@section('content')
@php
    use App\Models\CashAdvance\CashAdvanceApprovalStep;
@endphp

@include('hr-general.approval-workflow.hub-tabs-approval-workflow')

<div class="w-full space-y-5">
    @include('hr-general.approval-workflow._cash-advance-step-editor', [
        'module'    => CashAdvanceApprovalStep::MODULE_CAR,
        'steps'     => $steps,
        'openCount' => $openCount,
        'heading'   => 'Cash Advance Report — Approval Workflow',
        'blurb'     => 'Approval for settlement reports. Approving the last step closes the cash advance.',
        'roles'     => $roles,
        'employees' => $employees,
    ])
</div>
@endsection

@push('scripts')
<script>
    // 🔴 showConfirm(), bukan confirm() bawaan peramban — permintaan eksplisit
    // pemilik sistem, dan pola yang sudah dipakai seluruh Purchase Request.
    document.querySelectorAll('.js-ca-delete-step').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;
            event.preventDefault();

            const ok = await showConfirm(
                `Delete the approval step "${form.dataset.name}"? Documents already in progress keep `
                + `their own copy of the workflow and are not affected.`,
                'Delete approval step',
                'danger',
                { okText: 'Delete', cancelText: 'Cancel' }
            );

            if (!ok) return;
            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });

    function caSyncReference(scope) {
        const typeSelect = scope.querySelector('[data-ca-type-select]');
        if (!typeSelect) return;

        const type = typeSelect.value;

        scope.querySelectorAll('[data-ref-for]').forEach(function (box) {
            box.hidden = box.dataset.refFor !== type;
        });

        caSyncRoleHint(scope);
    }

    function caSyncRoleHint(scope) {
        const roleSelect = scope.querySelector('[data-ca-role-select]');
        const hint       = scope.querySelector('[data-ca-role-hint]');
        if (!roleSelect || !hint) return;

        const option  = roleSelect.selectedOptions[0];
        const holders = option ? parseInt(option.dataset.holders || '0', 10) : 0;

        if (!roleSelect.value) {
            hint.textContent = 'No position chosen yet.';
            hint.className   = 'text-xs mt-1 text-gray-400';
            return;
        }

        if (holders === 0) {
            hint.textContent = 'This position has no employee assigned — a document at this step '
                             + 'would wait for nobody.';
            hint.className   = 'text-xs mt-1 text-red-600';
            return;
        }

        hint.textContent = holders === 1
            ? 'One employee holds this position; the approver is fixed.'
            : holders + ' employees hold this position — any one of them can act.';
        hint.className = 'text-xs mt-1 text-gray-500';
    }

    document.querySelectorAll('[data-ca-step-row]').forEach(function (scope) {
        caSyncReference(scope);

        const typeSelect = scope.querySelector('[data-ca-type-select]');
        if (typeSelect) typeSelect.addEventListener('change', () => caSyncReference(scope));

        const roleSelect = scope.querySelector('[data-ca-role-select]');
        if (roleSelect) roleSelect.addEventListener('change', () => caSyncRoleHint(scope));
    });
</script>
@endpush
