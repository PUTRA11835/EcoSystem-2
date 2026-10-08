{{--
    The two dialogs the letter tabs share, opened from icon-action buttons:

      openLetterSendModal({ action, number, name, email, subject, body, title, again, pdf })
          email a signed letter with a subject and message HR can adjust, next to the PDF (`pdf` = its URL)
          that is attached, so it is read before it goes out
      openReasonModal({ action, title, intro, label, button })
          void a letter / reject a request — a reason is required

    After a failed check the dialog opens again with what was typed
    (old('_modal') = 'letterSend' | 'reason').
--}}
@php $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200'; @endphp

<div id="letterSendModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl max-h-[92vh] flex flex-col">
        <form id="letterSendForm" method="POST" class="flex flex-col min-h-0">
            @csrf
            <input type="hidden" name="_modal" value="letterSend">
            <input type="hidden" name="_action" id="letterSendAction">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800" id="letterSendTitle">Send Letter</h3>
                <button type="button" onclick="closeLetterModal('letterSendModal')" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="flex flex-col lg:flex-row flex-1 min-h-0 overflow-y-auto lg:overflow-hidden">
            <div class="lg:w-5/12 px-5 py-4 space-y-3 lg:overflow-y-auto">
                <p id="letterSendAgain" class="hidden text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-3 py-2">
                    The last email failed. Sending again retries it — a letter that was delivered is never emailed twice.
                </p>
                <div>
                    <span class="block text-xs font-semibold text-gray-600 mb-1">To</span>
                    <p class="text-sm text-gray-800"><span id="letterSendName" class="font-semibold"></span> &lt;<span id="letterSendEmail"></span>&gt;</p>
                </div>
                <div>
                    <label for="letterSendSubject" class="block text-xs font-semibold text-gray-600 mb-1">Subject <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" id="letterSendSubject" required maxlength="255" class="{{ $input }}">
                </div>
                <div>
                    <label for="letterSendBody" class="block text-xs font-semibold text-gray-600 mb-1">Message <span class="text-red-500">*</span></label>
                    <textarea name="body" id="letterSendBody" required rows="9" maxlength="10000" class="{{ $input }} leading-relaxed"></textarea>
                    <p class="text-[11px] text-gray-400 mt-1">Starts from the email text in the letter's language — adjust it as needed. A blank line starts a new paragraph.</p>
                </div>
                <p class="text-xs text-gray-500"><i class="fas fa-paperclip mr-1"></i> The signed letter <strong id="letterSendNumber"></strong> is attached as a PDF.</p>
                <label class="flex items-start gap-2 text-xs text-gray-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 cursor-pointer">
                    <input type="checkbox" id="letterSendReviewed" required class="mt-0.5 rounded border-gray-300">
                    <span>I have read the letter on the right and this message, and the details are correct.</span>
                </label>
            </div>
            <div class="lg:w-7/12 border-t lg:border-t-0 lg:border-l border-gray-100 bg-gray-50 flex flex-col min-h-[26rem] lg:min-h-0">
                <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between gap-3">
                    <span class="text-xs font-semibold text-gray-700"><i class="fas fa-file-pdf text-red-600 mr-1"></i> The letter as it is attached</span>
                    <a id="letterSendPreviewLink" href="#" target="_blank" rel="noopener" class="text-[11px] font-semibold text-gray-500 hover:text-gray-800">Open in a new tab <i class="fas fa-arrow-up-right-from-square text-[9px]"></i></a>
                </div>
                <iframe id="letterSendPreview" title="Preview of the letter PDF" class="flex-1 w-full min-h-[24rem] bg-white" loading="lazy"></iframe>
            </div>
            </div>
            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeLetterModal('letterSendModal')" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">
                    <i class="fas fa-paper-plane text-[10px]"></i> <span id="letterSendButton">Send</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="reasonModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <form id="reasonForm" method="POST">
            @csrf
            <input type="hidden" name="_modal" value="reason">
            <input type="hidden" name="_action" id="reasonAction">
            <input type="hidden" name="_title" id="reasonTitleInput">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800" id="reasonTitle"></h3>
                <button type="button" onclick="closeLetterModal('reasonModal')" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-5 py-4 space-y-3">
                <p class="text-xs text-gray-500" id="reasonIntro"></p>
                <div>
                    <label for="reasonText" class="block text-xs font-semibold text-gray-600 mb-1"><span id="reasonLabel">Reason</span> <span class="text-red-500">*</span></label>
                    <textarea id="reasonText" rows="3" required maxlength="1000" class="{{ $input }}"></textarea>
                </div>
            </div>
            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeLetterModal('reasonModal')" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold text-white bg-red-600 rounded-lg hover:opacity-90" id="reasonButton">Confirm</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const byId = id => document.getElementById(id);

        function show(id) { const modal = byId(id); modal.classList.remove('hidden'); modal.style.display = 'flex'; }
        window.closeLetterModal = function (id) {
            const modal = byId(id); modal.classList.add('hidden'); modal.style.display = 'none';
            if (id === 'letterSendModal') byId('letterSendPreview').src = 'about:blank';   // stop loading the PDF while it is hidden
        };

        window.openLetterSendModal = function (letter) {
            byId('letterSendForm').action = letter.action;
            byId('letterSendAction').value = letter.action;
            byId('letterSendTitle').textContent = letter.title || 'Send Letter';
            byId('letterSendName').textContent = letter.name || '';
            byId('letterSendEmail').textContent = letter.email || '(no email address)';
            byId('letterSendNumber').textContent = letter.number || '';
            byId('letterSendSubject').value = letter.subject ?? '';
            byId('letterSendBody').value = letter.body ?? '';
            byId('letterSendAgain').classList.toggle('hidden', !letter.again);
            byId('letterSendButton').textContent = letter.button || (letter.again ? 'Send again' : 'Send');
            // The PDF is the very file that gets attached: HR reads it next to the email before sending.
            byId('letterSendReviewed').checked = false;
            byId('letterSendPreview').src = letter.pdf ? letter.pdf + '#view=FitH' : 'about:blank';
            byId('letterSendPreviewLink').href = letter.pdf || '#';
            show('letterSendModal');
        };

        // The reason goes out under the name the route expects (void_reason / reject_reason).
        window.openReasonModal = function (options) {
            byId('reasonForm').action = options.action;
            byId('reasonAction').value = options.action;
            byId('reasonTitle').textContent = options.title || 'Confirm';
            byId('reasonTitleInput').value = options.title || '';
            byId('reasonIntro').textContent = options.intro || '';
            byId('reasonLabel').textContent = options.label || 'Reason';
            byId('reasonText').name = options.field || 'void_reason';
            byId('reasonText').value = options.value || '';
            byId('reasonButton').textContent = options.button || 'Confirm';
            show('reasonModal');
        };

        document.addEventListener('DOMContentLoaded', function () {
            @if(old('_modal') === 'letterSend' && old('_action'))
                openLetterSendModal({{ Js::from(['action' => old('_action'), 'subject' => old('subject'), 'body' => old('body')]) }});
            @elseif(old('_modal') === 'reason' && old('_action'))
                openReasonModal({{ Js::from([
                    'action' => old('_action'), 'title' => old('_title'),
                    'field'  => old('reject_reason') !== null ? 'reject_reason' : 'void_reason',
                    'value'  => old('reject_reason') ?? old('void_reason'),
                ]) }});
            @endif
        });
    })();
</script>
@endpush
