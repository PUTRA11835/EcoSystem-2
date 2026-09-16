@extends('dashboard')

@section('title', 'Approval Workflow — Purchase Request')
@section('page-title', 'Approval Workflow')
@section('page-subtitle', 'Configure the approval chain for each module — separate from its rules')

@section('content')
@php
    use App\Models\PurchaseRequest\PurchaseRequestApprovalStep;
@endphp

@include('hr-general.approval-workflow.hub-tabs-approval-workflow')

<div class="w-full space-y-5">
    @include('hr-general.approval-workflow._purchase-request-step-editor')
</div>
@endsection

@push('scripts')
<script>
    // Field penyetuju mengikuti tipe yang dipilih. Menampilkan keduanya
    // sekaligus membuat pengguna mengisi field yang akan diabaikan.
    function bindTypeToggle(select, roleBox, employeeBox) {
        function apply() {
            const isRole = select.value === @json(PurchaseRequestApprovalStep::TYPE_ROLE);
            roleBox.classList.toggle('hidden', !isRole);
            employeeBox.classList.toggle('hidden', isRole);
        }

        select.addEventListener('change', apply);
        apply();
    }

    document.querySelectorAll('.js-type').forEach(function (select) {
        const id = select.dataset.step;
        bindTypeToggle(
            select,
            document.querySelector('.js-role-box[data-step="' + id + '"]'),
            document.querySelector('.js-employee-box[data-step="' + id + '"]')
        );
    });

    bindTypeToggle(
        document.getElementById('newStepType'),
        document.getElementById('newRoleBox'),
        document.getElementById('newEmployeeBox')
    );

    document.querySelectorAll('.js-delete-step').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;

            event.preventDefault();

            const ok = await showConfirm(
                `Delete the approval step "${form.dataset.name}"? Requests already in progress keep their own copy and are not affected.`,
                'Delete Approval Step',
                'danger',
                { okText: 'Delete', cancelText: 'Cancel' }
            );

            if (!ok) return;

            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });
</script>
@endpush
