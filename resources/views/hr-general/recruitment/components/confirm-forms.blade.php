{{--
    Makes every <form data-confirm="…"> on the page ask through the app's
    confirm dialog before it posts (optional data-confirm-title and
    data-confirm-ok). Include once on a page that has such forms — the
    icon-action component renders them for its `post` actions.

    The listener is delegated, so it also covers table rows that a live
    filter re-renders after the page has loaded.
--}}
@once
@push('scripts')
<script>
    document.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!form.matches || !form.matches('form[data-confirm]') || event.defaultPrevented) return;

        event.preventDefault();
        const confirmed = await showConfirm(form.dataset.confirm, form.dataset.confirmTitle || 'Confirm', 'danger', {
            okText: form.dataset.confirmOk || 'OK', cancelText: 'Back',
        });
        if (confirmed) form.submit();
    });
</script>
@endpush
@endonce
