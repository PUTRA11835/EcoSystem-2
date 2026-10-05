{{--
    Unsaved-changes guard for the Recruitment, Offering Letter and Letter
    Templates pages (included by their tab components and by My Letter
    Requests). Warns before what was typed in a form is lost:

      · leaving the page — closing the tab, refreshing, typing an address:
        the browser's own "Leave site?" dialog
      · following a link of the app — sidebar, tabs, pagination, "Back":
        the app's confirm dialog, naming the forms with changes
      · closing a modal (Cancel / ✕) that has changes
      · saving another form, which reloads the page and loses these changes
      · switching a step of a page with steps — UnsavedGuard.confirmLeave(panel)

    A form counts when it POSTs and has a field someone can change; filters
    (GET), one-button delete forms and fields locked by a disabled fieldset
    do not. "Changed" means different from how the form was when the person
    first touched it — typing something and changing it back is not a change.
    A form that saves itself in the background calls UnsavedGuard.markSaved(form).
    A form opts out with data-unsaved-ignore.

    While something is unsaved the form is outlined amber and a notice at the
    bottom of the screen names it, with a button that brings it into view.
--}}
@once
<div id="unsavedNotice" class="hidden fixed bottom-4 right-4 z-[90] max-w-sm bg-amber-50 border border-amber-300 text-amber-900 rounded-xl shadow-lg px-4 py-3 text-xs" role="status" aria-live="polite">
    <div class="flex items-start gap-2.5">
        <i class="fas fa-triangle-exclamation text-amber-500 mt-0.5"></i>
        <div class="min-w-0">
            <p class="font-bold">Unsaved changes</p>
            <p class="mt-0.5 text-amber-800" id="unsavedNoticeText"></p>
        </div>
        <button type="button" id="unsavedNoticeShow" class="ml-1 shrink-0 px-2.5 py-1 rounded-lg bg-white border border-amber-300 font-semibold hover:bg-amber-100">Show me</button>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        if (window.UnsavedGuard) return;

        const baselines = new Map(); // form → how it was when first touched
        const outlined = new Set();   // forms currently outlined as unsaved
        let leaving = false;          // a navigation the person already confirmed
        let saving = null;            // the form being submitted (its changes are being saved)

        const FIELD_TAGS = ['INPUT', 'SELECT', 'TEXTAREA'];
        const NOT_FIELDS = ['submit', 'button', 'reset', 'image'];

        const fieldsOf = form => Array.from(form.elements).filter(el => FIELD_TAGS.includes(el.tagName) && el.name && !NOT_FIELDS.includes(el.type));

        function isGuarded(form) {
            return form instanceof HTMLFormElement
                && (form.getAttribute('method') || 'get').toLowerCase() === 'post'
                && !form.hasAttribute('data-unsaved-ignore')
                && (fieldsOf(form).some(el => el.type !== 'hidden' && !el.matches(':disabled')) || form.querySelector('[contenteditable="true"]'));
        }

        // Everything someone could change, as one string. Hidden "_…" fields are form bookkeeping, not data.
        function snapshot(form) {
            const parts = fieldsOf(form)
                .filter(el => !(el.type === 'hidden' && el.name.startsWith('_')))
                .map(el => {
                    if (el.type === 'checkbox' || el.type === 'radio') return el.name + '=' + el.value + ':' + el.checked;
                    if (el.type === 'file') return el.name + '=' + Array.from(el.files).map(f => f.name + '/' + f.size).join(',');
                    if (el.tagName === 'SELECT' && el.multiple) return el.name + '=' + Array.from(el.selectedOptions).map(o => o.value).join(',');
                    return el.name + '=' + el.value;
                });
            form.querySelectorAll('[contenteditable="true"]').forEach((el, i) => parts.push('editable' + i + '=' + el.innerHTML));
            return parts.join('\n');
        }

        // The form an event happened in: the element's own form, or the one a `form="…"` field belongs to.
        const formOf = target => target instanceof Element ? (target.form || target.closest('form')) : null;

        function touch(event) {
            const form = formOf(event.target);
            if (form && !baselines.has(form) && isGuarded(form)) baselines.set(form, snapshot(form));
        }

        function dirtyForms(scope) {
            return Array.from(baselines.entries())
                .filter(([form, before]) => form.isConnected && (!scope || scope.contains(form)) && snapshot(form) !== before)
                .map(([form]) => form);
        }

        function labelOf(form) {
            if (form.dataset.unsavedLabel) return form.dataset.unsavedLabel;
            for (let el = form; el && el !== document.body; el = el.parentElement) {
                const heading = el.querySelector('h3, h2');
                if (heading && heading.textContent.trim()) return heading.textContent.trim();
            }
            return 'a form';
        }

        const describe = forms => Array.from(new Set(forms.map(labelOf))).join(', ');

        // Discarding: forget what was typed — a page form goes back to how it was loaded.
        function discard(forms) {
            forms.forEach(form => {
                baselines.delete(form);
                if (!form.closest('.fixed')) {
                    form.reset();
                    form.querySelectorAll('select').forEach(select => select.dispatchEvent(new Event('change', { bubbles: true })));
                    baselines.delete(form);
                }
            });
            refresh();
        }

        async function ask(forms, action) {
            const message = 'You have unsaved changes in: ' + describe(forms) + '. ' + action + ' without saving? What you changed will be lost.';
            const confirmed = typeof window.showConfirm === 'function'
                ? await window.showConfirm(message, 'Unsaved Changes', 'danger', { okText: 'Discard changes', cancelText: 'Keep editing' })
                : window.confirm(message);
            if (!confirmed) showForm(forms[0]);
            return confirmed;
        }

        // Brings a form into view: opens its step if it is on a hidden one, then scrolls and focuses it.
        function showForm(form) {
            if (!form) return;
            const panel = form.closest('[data-panel].hidden');
            if (panel && typeof window.showSettingsStep === 'function') window.showSettingsStep(panel.dataset.panel, { force: true });
            setTimeout(() => {
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const field = fieldsOf(form).find(el => el.type !== 'hidden' && !el.matches(':disabled'));
                field?.focus({ preventScroll: true });
            }, 50);
        }

        // ── The notice and the outline ──
        let refreshQueued = false;
        function refresh() {
            if (refreshQueued) return;
            refreshQueued = true;
            requestAnimationFrame(() => {
                refreshQueued = false;
                const dirty = dirtyForms();
                // Outline what is unsaved; clear forms that are saved, discarded or no longer watched.
                new Set([...outlined, ...dirty]).forEach(form => {
                    const outline = dirty.includes(form) ? '2px solid #fcd34d' : '';
                    // Only when it differs: the observer below watches style changes.
                    if (form.style.outline !== outline) {
                        form.style.outline = outline;
                        form.style.outlineOffset = outline ? '3px' : '';
                    }
                    outline ? outlined.add(form) : outlined.delete(form);
                });
                const notice = document.getElementById('unsavedNotice');
                if (!notice) return;
                notice.classList.toggle('hidden', dirty.length === 0);
                document.getElementById('unsavedNoticeText').textContent = dirty.length ? 'In ' + describe(dirty) + ' — save before you leave this page.' : '';
            });
        }

        document.addEventListener('pointerdown', touch, true);
        document.addEventListener('keydown', touch, true);
        document.addEventListener('focusin', touch, true);
        document.addEventListener('input', refresh, true);
        document.addEventListener('change', refresh, true);
        document.getElementById('unsavedNoticeShow')?.addEventListener('click', () => showForm(dirtyForms()[0]));

        // A modal or a step that is opened or closed starts afresh: a modal is filled in by script when
        // it opens, and what was typed in one that closed is gone.
        new MutationObserver(mutations => {
            let reset = false;
            mutations.forEach(({ target }) => {
                if (!(target instanceof Element) || !target.matches('.fixed, [data-panel]')) return;
                target.querySelectorAll('form').forEach(form => { reset = baselines.delete(form) || reset; });
            });
            if (reset) refresh();
        }).observe(document.body, { attributes: true, attributeFilter: ['class', 'style'], subtree: true });

        // ── Leaving the page: the browser asks ──
        window.addEventListener('beforeunload', event => {
            if (leaving) return;
            if (dirtyForms().some(form => form !== saving)) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        // ── A link of the app: the app asks, naming what is unsaved ──
        document.addEventListener('click', async event => {
            const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if ((link.target && link.target !== '_self') || link.hasAttribute('download')) return;
            const href = link.getAttribute('href') || '';
            if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

            const dirty = dirtyForms();
            if (!dirty.length) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            if (await ask(dirty, 'Leave this page')) {
                leaving = true;
                window.location.href = link.href;
            }
        }, true);

        // ── Closing a modal that has changes ──
        document.addEventListener('click', async event => {
            const button = event.target instanceof Element ? event.target.closest('button') : null;
            if (!button || button.type === 'submit') return;
            if (button.dataset.unsavedBypass) { delete button.dataset.unsavedBypass; return; }
            const modal = button.closest('.fixed');
            if (!modal || modal.id === 'globalConfirmModal') return;

            const handler = button.getAttribute('onclick') || '';
            const closes = button.getAttribute('aria-label') === 'Close'
                || /close|classList\.add\(\s*['"]hidden['"]\s*\)|display\s*=\s*['"]none['"]/i.test(handler)
                || /^(cancel|batal)$/i.test(button.textContent.trim());
            if (!closes) return;

            const dirty = dirtyForms(modal);
            if (!dirty.length) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            if (await ask(dirty, 'Close')) {
                discard(dirty);
                button.dataset.unsavedBypass = '1';
                button.click();
            }
        }, true);

        // ── Saving one form while another has changes: the reload would lose them ──
        document.addEventListener('submit', async event => {
            const form = event.target;
            if (form.dataset.unsavedBypass) { delete form.dataset.unsavedBypass; saving = form; return; }

            const target = (event.submitter?.getAttribute('formtarget') || form.getAttribute('target') || '').toLowerCase();
            if (target === '_blank') return; // a preview in a new tab: nothing here is left

            const others = dirtyForms().filter(other => other !== form);
            if (!others.length) { saving = form; return; }

            event.preventDefault();
            event.stopImmediatePropagation();
            if (await ask(others, 'Continue')) {
                discard(others);
                form.dataset.unsavedBypass = '1';
                form.requestSubmit(event.submitter && event.submitter.form === form ? event.submitter : undefined);
            }
        }, true);

        window.UnsavedGuard = {
            /** Whether anything (inside scope, if given) has unsaved changes. */
            isDirty: scope => dirtyForms(scope).length > 0,
            /**
             * Before switching away from part of a page: true when nothing there is unsaved,
             * or the person chose to discard it; false to stay.
             */
            async confirmLeave(scope, action = 'Leave this step') {
                const dirty = dirtyForms(scope);
                if (!dirty.length) return true;
                if (!(await ask(dirty, action))) return false;
                discard(dirty);
                return true;
            },
            /** A form saved in the background: what it shows now is what is saved. */
            markSaved(form) {
                if (baselines.has(form)) baselines.set(form, snapshot(form));
                refresh();
            },
        };
    })();
</script>
@endpush
@endonce
