{{--
    The app's calendar (flatpickr, the library Delivery and Inventory use) in
    place of the browser's native date box, for the Reimbursement pages.

    Every <input type="text" data-date> shows "09 Oct 2026" and still submits
    Y-m-d. Optional attributes: data-min / data-max (Y-m-d).

    Inputs that appear later (a new item row, a filter popover) are wired by a
    MutationObserver, so nothing has to call this by hand.
--}}
@once
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
    .flatpickr-calendar { font-family: inherit; border-radius: .75rem; border: 1px solid #e5e7eb; box-shadow: 0 10px 30px rgba(15, 23, 42, .15); z-index: 10050 !important; }
    .flatpickr-calendar:before, .flatpickr-calendar:after { display: none; }
    .flatpickr-current-month { font-size: .85rem; font-weight: 700; padding-top: .55rem; }
    .flatpickr-months .flatpickr-month { height: 2.75rem; }
    .flatpickr-day.selected, .flatpickr-day.selected:hover { background: var(--primary-color); border-color: var(--primary-color); }
    .flatpickr-day.today:not(.selected) { border-color: var(--primary-color); }
    input.rb-date-alt { cursor: pointer; background-color: #fff; }
</style>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
(function () {
    function wire(root) {
        if (typeof flatpickr === 'undefined') return;

        (root || document).querySelectorAll('input[data-date]').forEach(function (el) {
            if (el._flatpickr || el.dataset.dateWired) return;
            el.dataset.dateWired = '1';

            flatpickr(el, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd M Y',
                altInputClass: (el.className + ' rb-date-alt').trim(),
                allowInput: false,
                disableMobile: true,
                monthSelectorType: 'static',
                locale: { firstDayOfWeek: 1 },
                minDate: el.dataset.min || null,
                maxDate: el.dataset.max || null,
            });
        });
    }

    window.rbDates = wire;

    document.addEventListener('DOMContentLoaded', function () {
        wire(document);

        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) wire(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    });
})();
</script>
@endonce
