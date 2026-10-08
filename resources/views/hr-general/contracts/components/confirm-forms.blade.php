{{--
    The app's own confirm dialog (window.showConfirm, partials/confirm-modal) for forms of the Contract pages, in place of
    the browser's confirm() box. A form opts in with attributes:

      data-confirm="Delete draft SPKWT/…?"   the question (required)
      data-confirm-title="Delete Draft"       title (default "Confirm")
      data-confirm-ok="Delete"                text of the OK button (default "OK")
      data-confirm-variant="danger"           default | primary | danger (default "danger")

    The form is submitted for real only after the person agrees. Without JavaScript the form still submits.
--}}
@once
@push('scripts')
<script>
    document.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') return;

        event.preventDefault();
        const ok = await window.showConfirm(
            form.getAttribute('data-confirm'),
            form.getAttribute('data-confirm-title') || 'Confirm',
            form.getAttribute('data-confirm-variant') || 'danger',
            { okText: form.getAttribute('data-confirm-ok') || 'OK', cancelText: 'Cancel' }
        );
        if (ok) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    }, true);
</script>
@endpush
@endonce
