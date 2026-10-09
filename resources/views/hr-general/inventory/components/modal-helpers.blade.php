{{--
    What the Inventory, Assets and Settings pages share (include once per page; the modals call these):

      invOpen(modalId, data)   open the modal. No `data` = a new record (fields back to the markup's defaults);
                               with `data._id` = edit that record. Every key of `data` that matches a field name
                               fills it; `photo_url` fills the photo tile; `_reopen` keeps the server's error list.
                               A <select> whose list no longer offers the record's value (an option switched off in
                               Settings) gets that value added for now, so saving does not silently change it.
      invEdit(modalId, button) invOpen() with the row's data-payload
      invClose(modalId)        the ONLY way out of a modal (the X, and Cancel): it asks first when something was typed
                               (the form differs from how it opened), and never closes on a click outside or Esc

    With ['dates' => true] the page also gets the app's calendar (flatpickr, the library Delivery uses) in place of the
    browser's native date box: every <input type="text" data-date> shows "09 Oct 2026" with a static month title, Today
    and Clear, and still sends Y-m-d. invDates(root) wires any such input that is not wired yet.

    Field behaviours, by attribute:
      [data-money]              shows 15000000 as 15.000.000 while typing (the server keeps only the digits)
      [data-photo-field]        the square photo tile (see photo-field)
      [data-date]               a calendar box (with ['dates' => true])

    A form carries data-store-action and data-update-action (with __ID__); its modal carries data-title-new /
    data-title-edit / data-submit-new, and holds [data-modal-title] / [data-submit-label].
--}}
@once
@if($dates ?? false)
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
    .flatpickr-calendar { font-family: inherit; border-radius: .75rem; border: 1px solid #e5e7eb; box-shadow: 0 10px 30px rgba(15, 23, 42, .15); z-index: 10050 !important; }
    .flatpickr-calendar:before, .flatpickr-calendar:after { display: none; }
    .flatpickr-months .flatpickr-month { height: 2.75rem; }
    .flatpickr-current-month { font-size: .85rem; font-weight: 700; padding-top: .55rem; }
    .flatpickr-weekday { font-size: .7rem; font-weight: 700; color: #9ca3af; }
    .flatpickr-day { border-radius: .5rem; font-size: .8rem; }
    .flatpickr-day.today:not(.selected) { border-color: var(--primary-color); }
    .flatpickr-day.selected, .flatpickr-day.selected:hover { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
    .flatpickr-day:hover { background: #f3f4f6; border-color: transparent; }
    .fp-actions { display: flex; justify-content: space-between; padding: .45rem .75rem .6rem; border-top: 1px solid #f3f4f6; }
    .fp-actions button { font-size: .72rem; font-weight: 600; color: var(--primary-color); }
    .fp-actions button:hover { text-decoration: underline; }
    /* the visible box looks like every other input of the forms */
    input.flatpickr-input[readonly] { cursor: pointer; }
</style>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
(function () {
    window.invDates = function (root) {
        if (typeof flatpickr === 'undefined') return;
        (root || document).querySelectorAll('input[data-date]').forEach(el => {
            if (el._flatpickr) return;
            flatpickr(el, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd M Y',
                allowInput: false,
                disableMobile: true,
                monthSelectorType: 'static',
                locale: { firstDayOfWeek: 1 },
                onReady(selected, str, fp) {
                    const bar = document.createElement('div');
                    bar.className = 'fp-actions';
                    bar.innerHTML = '<button type="button" data-fp-today>Today</button><button type="button" data-fp-clear>Clear</button>';
                    bar.querySelector('[data-fp-today]').addEventListener('click', () => { fp.setDate(new Date(), true); fp.close(); });
                    bar.querySelector('[data-fp-clear]').addEventListener('click', () => { fp.clear(); fp.close(); });
                    fp.calendarContainer.appendChild(bar);
                },
            });
        });
    };
    document.addEventListener('DOMContentLoaded', () => invDates());
})();
</script>
@endif
@push('scripts')
<script>
(function () {
    const group = v => { const d = String(v ?? '').replace(/\D/g, ''); return d.replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
    const PHOTO_MAX = 2 * 1024 * 1024;

    // ── money ──
    document.addEventListener('input', e => { if (e.target.matches('[data-money]')) e.target.value = group(e.target.value); });

    // ── square photo tile ──
    function photoSet(field, url) {
        field.querySelector('[data-photo-input]').value = '';
        field.querySelector('[data-photo-remove]').value = '0';
        field.querySelector('[data-photo-error]').textContent = '';
        field.dataset.had = url ? '1' : '';
        photoShow(field, url || '');
    }
    function photoShow(field, src) {
        const img = field.querySelector('[data-photo-img]');
        img.src = src;
        img.classList.toggle('hidden', !src);
        field.querySelector('[data-photo-empty]').classList.toggle('hidden', !!src);
        field.querySelector('[data-photo-clear]').classList.toggle('hidden', !src);
        field.querySelector('[data-photo-clear]').classList.toggle('flex', !!src);
    }
    document.addEventListener('change', e => {
        if (!e.target.matches('[data-photo-input]')) return;
        const field = e.target.closest('[data-photo-field]');
        const file = e.target.files[0];
        const error = field.querySelector('[data-photo-error]');
        error.textContent = '';
        if (!file) return;
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { error.textContent = 'Use a JPG, PNG or WebP image.'; e.target.value = ''; return; }
        if (file.size > PHOTO_MAX) { error.textContent = 'The photo is over 2 MB.'; e.target.value = ''; return; }
        field.querySelector('[data-photo-remove]').value = '0';
        photoShow(field, URL.createObjectURL(file));
    });
    document.addEventListener('click', e => {
        const clear = e.target.closest('[data-photo-clear]');
        if (!clear) return;
        e.preventDefault(); e.stopPropagation(); // the tile is a <label>: do not open the file dialog
        const field = clear.closest('[data-photo-field]');
        field.querySelector('[data-photo-input]').value = '';
        field.querySelector('[data-photo-remove]').value = field.dataset.had ? '1' : '0';
        field.querySelector('[data-photo-error]').textContent = '';
        photoShow(field, '');
    });

    // ── modal ──
    function setField(el, value) {
        if (el.type === 'file') return;
        if (el.matches('[data-date]')) {
            if (el._flatpickr) el._flatpickr.setDate(value || null, false); else el.value = value || '';
            return;
        }
        if (el.type === 'checkbox') { el.checked = value === true || value === 1 || value === '1'; return; }
        if (el.tagName === 'SELECT') {
            el.querySelectorAll('option[data-temp]').forEach(o => o.remove());
            const text = String(value ?? '');
            if (text !== '' && !Array.from(el.options).some(o => o.value === text)) {
                const extra = new Option(text + ' (not offered any more)', text);
                extra.dataset.temp = '1';
                el.add(extra);
            }
            el.value = text;
            el.dispatchEvent(new Event('change', { bubbles: true })); // keeps the select enhancer in step
            return;
        }
        el.value = el.matches('[data-money]') ? group(value) : (value ?? '');
    }

    window.invOpen = function (modalId, data) {
        const modal = document.getElementById(modalId);
        const form = modal.querySelector('form');
        data = data || {};
        const id = data._id ? String(data._id) : '';

        // A reopened edit has lost the photo URL (a failed save keeps no files): take it from the row's edit button.
        if (data._reopen && id && !data.photo_url) {
            for (const b of document.querySelectorAll('[data-payload]')) {
                const row = JSON.parse(b.dataset.payload);
                if (String(row._id) === id) { data.photo_url = row.photo_url; break; }
            }
        }

        window.invDates?.(form);
        form.querySelectorAll('select option[data-temp]').forEach(o => o.remove());
        form.reset();
        form.querySelectorAll('[data-date]').forEach(el => el._flatpickr?.clear(false));
        if (!data._reopen) modal.querySelector('[data-form-errors]')?.remove();
        form.querySelectorAll('[data-photo-field]').forEach(f => photoSet(f, data.photo_url || ''));

        Object.entries(data).forEach(([key, value]) => {
            if (['_token', 'photo_url', '_reopen', 'photo', 'remove_photo'].includes(key)) return;
            const el = form.elements[key];
            if (el) setField(el, value);
        });
        // A reopened form was posted without its unticked boxes: show them unticked, not at their default.
        if (data._reopen) form.querySelectorAll('input[type="checkbox"]').forEach(c => { if (!(c.name in data)) c.checked = false; });
        form.elements['_id'].value = id;

        form.action = id ? form.dataset.updateAction.replace('__ID__', id) : form.dataset.storeAction;
        modal.querySelector('[data-modal-title]').textContent = id ? modal.dataset.titleEdit : modal.dataset.titleNew;
        modal.querySelector('[data-submit-label]').textContent = id ? 'Save changes' : modal.dataset.submitNew;

        // What the form holds as it opens; closing with anything different asks first. A form reopened after a failed
        // save counts as changed from the start: what was typed has not been saved.
        modal._snapshot = snapshot(form);
        modal._changed = !!data._reopen;

        modal.classList.remove('hidden');
        modal.dispatchEvent(new CustomEvent('inv:open', { detail: data }));
        setTimeout(() => form.querySelector('input[type="text"]:not([disabled]):not([data-money])')?.focus(), 50);
    };
    window.invEdit = (modalId, button) => invOpen(modalId, JSON.parse(button.dataset.payload));
    // The form's values (files by name and size) without the fields the page fills itself.
    function snapshot(form) {
        const skip = ['_token', '_modal', '_id'];
        return JSON.stringify(Array.from(new FormData(form).entries())
            .filter(([key]) => !skip.includes(key))
            .map(([key, value]) => [key, value instanceof File ? (value.name ? 'file:' + value.name + ':' + value.size : '') : value]));
    }

    window.invClose = async function (modalId) {
        const modal = document.getElementById(modalId);
        const form = modal.querySelector('form');
        if (modal._changed || snapshot(form) !== modal._snapshot) {
            const message = 'You have filled in data that is not saved yet. If you close now, it will be lost.';
            const leave = window.showConfirm
                ? await window.showConfirm(message, 'Discard changes?', 'danger', { okText: 'Discard', cancelText: 'Keep editing' })
                : window.confirm(message);
            if (!leave) return;
        }
        modal.classList.add('hidden');
    };
})();
</script>
@endpush
@endonce
