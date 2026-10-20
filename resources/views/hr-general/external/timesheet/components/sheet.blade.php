{{--
    One timesheet, from its summary cards down to its invoice — shared by the HR / RPMO / Finance review page (show) and the
    consultant's own page (my), so both look and behave the same.

    Parameters:
      $sheet        ExternalTimesheetService::find() (entries, pricing, invoice, actor_names)
      $self         true on the consultant's own page
      $amounts      may the viewer see rate / gross / invoice amount
      $canEdit      may the viewer fill the rows (the consultant on their own page; HR/RPMO with the Edit box)
      $canApprove / $canVerify / $canUploadFor   (staff page) what the viewer may do now
      $approvers    ['timesheet_approval' => names[], 'invoice_verification' => names[]]
      $projects     [['id','label']] read-only project list
      $verification print verification code
      $u            urls: save, submit, print, invoiceFile, invoiceUpload, approve, return, verify, reject, paid
--}}
@php
    use App\Support\External\ExternalTimesheetRules as R;

    $p = $sheet->pricing;
    $inv = $sheet->invoice;
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $dt = fn ($v, $f = 'd M Y H:i') => $v ? \Illuminate\Support\Carbon::parse($v)->format($f) : null;
    $who = fn ($id) => $id ? ($sheet->actor_names[$id] ?? ('#' . $id)) : null;
    $unitNames = ['mandays' => 'mandays', 'monthly' => 'month', 'hourly' => 'hours', 'fixed_price' => 'of the fixed price'];
    $qtyText = number_format($p['qty'], 2, ',', '.') . ' ' . ($unitNames[$p['scheme']] ?? $p['unit']);
    $money = fn ($v) => \App\Support\External\ExternalRules::money($v, $p['currency']);
    $editorOpen = $sheet->editable && $canEdit;
    $oldEntries = old('entries');
    $rows = $oldEntries !== null ? $oldEntries : $sheet->entries->map(fn ($e) => [
        'work_date' => $e->work_date, 'project_id' => $e->project_id, 'project_label' => $e->project_label,
        'start_time' => $e->start_time ? substr($e->start_time, 0, 5) : '', 'end_time' => $e->end_time ? substr($e->end_time, 0, 5) : '',
        'hours' => $e->start_time ? '' : $e->hours, 'location' => $e->location, 'activity' => $e->activity,
    ])->all();
    $minDate = max(\App\Support\External\ExternalTimesheetRules::periodBounds((int) $sheet->period_year, (int) $sheet->period_month)[0], substr((string) $sheet->contract_start, 0, 10));
    $maxDate = min(\App\Support\External\ExternalTimesheetRules::periodBounds((int) $sheet->period_year, (int) $sheet->period_month)[1], $sheet->contract_end ? substr((string) $sheet->contract_end, 0, 10) : '9999-12-31', now()->toDateString());
@endphp

{{-- Summary cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4"><p class="text-[11px] text-gray-500">Total hours</p><p class="text-2xl font-bold text-gray-800 leading-tight mt-1" id="sumHours">{{ number_format($editorOpen ? $sheet->live_hours : $p['hours'], 2, ',', '.') }}</p></div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4"><p class="text-[11px] text-gray-500">Billable quantity</p><p class="text-2xl font-bold text-gray-800 leading-tight mt-1" id="sumQty">{{ number_format($p['qty'], 2, ',', '.') }}</p><p class="text-[10px] text-gray-400">{{ $unitNames[$p['scheme']] ?? $p['unit'] }}</p></div>
    @if($amounts)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4"><p class="text-[11px] text-gray-500">Rate</p>
            <p class="text-2xl font-bold text-gray-800 leading-tight mt-1">{{ $p['rate'] !== null ? $money($p['rate']) : '—' }}</p>
            <p class="text-[10px] text-gray-400">{{ \App\Support\External\ExternalRules::rateUnit($p['scheme']) }} · {{ $p['estimate'] ? 'from the contract' : 'frozen at submit' }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4"><p class="text-[11px] text-gray-500">{{ $p['estimate'] ? 'Estimated gross' : 'Gross amount' }}</p>
            <p class="text-2xl font-bold primary-text leading-tight mt-1" id="sumGross">{{ $p['gross'] !== null ? $money($p['gross']) : '—' }}</p><p class="text-[10px] text-gray-400">before tax</p></div>
    @else
        <div class="col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-2 text-xs text-gray-500"><i class="fas fa-lock"></i> Rate and amounts need the “View Amounts” permission.</div>
    @endif
</div>

@if($p['missing_rate'] && !$sheet->frozen)
    <div class="text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><i class="fas fa-triangle-exclamation mr-1"></i>
        This contract has no rate yet, so the timesheet cannot be submitted. @unless($self) Open External Contract → edit the contract and save its rate and billing terms. @else Please ask HR to complete the contract. @endunless</div>
@endif

@if($sheet->status === R::RETURNED && $sheet->return_reason)
    <div class="text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><i class="fas fa-rotate-left mr-1"></i>
        <strong>Returned{{ $who($sheet->returned_by) ? ' by ' . $who($sheet->returned_by) : '' }}:</strong> {{ $sheet->return_reason }} @if($self) Correct the rows and submit again. @endif</div>
@endif

{{-- Rows --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
        <div><h3 class="text-sm font-bold text-gray-800">Working hours — {{ $sheet->period_label }}</h3>
            <p class="text-[11px] text-gray-500 mt-0.5">{{ $sheet->contract_number }} · {{ \App\Support\External\ExternalRules::schemeLabel($p['scheme']) }}@if($p['scheme'] === 'mandays') · {{ rtrim(rtrim(number_format($p['hpm'], 1, ',', '.'), '0'), ',') }} h = 1 manday @endif</p></div>
        @if($editorOpen)<button type="button" id="addRow" class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-plus mr-1"></i> Add row</button>@endif
    </div>

    @if($editorOpen)
        <form method="POST" action="{{ $u['save'] }}" id="sheetForm" data-unsaved-ignore>
            @csrf
            @if($p['scheme'] === 'fixed_price')
                <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
                    <label class="{{ $label }} mb-0" for="milestonePct">Share of the fixed price billed in this timesheet (%)</label>
                    <input id="milestonePct" name="milestone_pct" type="number" min="1" max="100" step="0.01" value="{{ old('milestone_pct', rtrim(rtrim((string) $p['milestone'], '0'), '.')) }}" class="{{ $input }} w-28">
                    <span class="text-[11px] text-gray-400">A fixed-price contract is billed in parts; the approver checks the share.</span>
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left" id="entriesTable">
                    <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                        <tr><th class="px-3 py-3">Date</th><th class="px-3 py-3">Project</th><th class="px-3 py-3">Start</th><th class="px-3 py-3">End</th><th class="px-3 py-3">Hours</th><th class="px-3 py-3">Location</th><th class="px-3 py-3 w-full">Activity</th><th class="px-3 py-3"></th></tr>
                    </thead>
                    <tbody id="entriesBody" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
            <p id="entriesEmpty" class="hidden px-5 py-8 text-center text-xs text-gray-400">No rows yet. Click “Add row” to log your first day.</p>
            <div class="px-5 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <p class="text-[11px] text-gray-400">Dates must be inside {{ $sheet->period_label }}, inside the contract period, and not in the future. Start/end time fills the hours; without them type the hours.</p>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"><i class="far fa-floppy-disk mr-1"></i> Save draft</button>
                    <button type="submit" formaction="{{ $u['submit'] }}" id="submitBtn" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-paper-plane mr-1"></i> Submit for approval</button>
                </div>
            </div>
        </form>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                    <tr><th class="px-4 py-3 w-10">No</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Project</th><th class="px-4 py-3">Time</th><th class="px-4 py-3">Location</th><th class="px-4 py-3">Activity</th><th class="px-4 py-3 text-right">Hours</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($sheet->entries as $e)
                        <tr>
                            <td class="px-4 py-2.5 text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($e->work_date)->format('d M Y') }}</td>
                            <td class="px-4 py-2.5">{{ $e->project_label ?: '—' }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap">{{ $e->start_time ? substr($e->start_time, 0, 5) . '–' . substr($e->end_time, 0, 5) : '—' }}</td>
                            <td class="px-4 py-2.5">{{ $e->location ? R::LOCATIONS[$e->location] ?? $e->location : '—' }}</td>
                            <td class="px-4 py-2.5">{{ $e->activity }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ number_format($e->hours, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No rows.</td></tr>
                    @endforelse
                </tbody>
                @if($sheet->entries->isNotEmpty())
                    <tfoot class="bg-gray-50 text-xs"><tr><td colspan="6" class="px-4 py-2.5 text-right font-semibold text-gray-600">Total hours</td><td class="px-4 py-2.5 text-right font-bold text-gray-800">{{ number_format($sheet->live_hours, 2, ',', '.') }}</td></tr>
                        <tr><td colspan="6" class="px-4 py-2.5 text-right font-semibold text-gray-600">Billable quantity</td><td class="px-4 py-2.5 text-right font-bold text-gray-800">{{ $qtyText }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    @endif
</div>

{{-- Approval (staff) --}}
@if(!$self && ($canApprove ?? false))
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="text-sm font-bold text-green-700">Approve timesheet</h3>
            <p class="text-[11px] text-gray-500 mt-1">Approval locks the timesheet and lets the consultant upload the invoice.</p>
            <form method="POST" action="{{ $u['approve'] }}" class="mt-3" data-confirm="Approve {{ $sheet->sheet_no }} for {{ $sheet->name }}? The rows are locked and the consultant can upload the invoice." data-confirm-title="Approve Timesheet" data-confirm-ok="Approve" data-confirm-variant="primary" data-unsaved-ignore>@csrf
                <button class="px-4 py-2 text-xs font-semibold text-white bg-green-700 rounded-lg hover:opacity-90"><i class="fas fa-check mr-1"></i> Approve</button></form>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="text-sm font-bold text-amber-700">Return for correction</h3>
            <form method="POST" action="{{ $u['return'] }}" class="mt-3 flex gap-2" data-unsaved-ignore>@csrf
                <input name="reason" required maxlength="2000" placeholder="Reason (required)" class="{{ $input }}">
                <button class="px-4 py-2 text-xs font-semibold text-white bg-amber-500 rounded-lg hover:opacity-90 whitespace-nowrap">Return</button></form>
        </div>
    </div>
@endif

{{-- Invoice & payment --}}
@if($sheet->status === R::APPROVED || $inv)
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-2">
            <h3 class="text-sm font-bold text-gray-800">Invoice &amp; payment</h3>
            @if($inv)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ R::INVOICE_BADGES[$inv->status] }}">{{ R::INVOICE_LABELS[$inv->status] }}</span>@endif
        </div>
        <div class="p-5 space-y-4">
            @if($inv)
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div><p class="text-[11px] text-gray-400">Invoice number</p><p class="text-sm font-semibold text-gray-800">{{ $inv->invoice_no }}</p></div>
                    <div><p class="text-[11px] text-gray-400">Invoice date</p><p class="text-sm font-semibold text-gray-800">{{ $dt($inv->invoice_date, 'd M Y') }}</p></div>
                    @if($amounts)
                        <div><p class="text-[11px] text-gray-400">Amount</p><p class="text-sm font-semibold text-gray-800">{{ $money($inv->amount) }}
                            @if($sheet->gross_amount !== null && abs((float) $inv->amount - (float) $sheet->gross_amount) > 0.009)<span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-bold" title="Approved gross {{ $money($sheet->gross_amount) }}">differs</span>@endif</p></div>
                    @endif
                    <div><p class="text-[11px] text-gray-400">Due date</p><p class="text-sm font-semibold text-gray-800">{{ $dt($inv->due_date, 'd M Y') ?? '—' }}</p></div>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    @if($inv->file_path)
                        <a href="{{ $u['invoiceFile'] }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50"><i class="far fa-eye mr-1"></i> View invoice</a>
                        <a href="{{ $u['invoiceFile'] }}?download=1" class="px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50"><i class="fas fa-download mr-1"></i> {{ $inv->file_name }}</a>
                    @endif
                </div>
                @if($inv->status === R::INV_REJECTED)
                    <div class="text-xs bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3"><i class="fas fa-circle-exclamation mr-1"></i> <strong>Rejected{{ $who($inv->rejected_by) ? ' by ' . $who($inv->rejected_by) : '' }}:</strong> {{ $inv->reject_reason }}</div>
                @endif
                @if($inv->status === R::INV_PAID)
                    <div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> Paid on {{ $dt($inv->paid_date, 'd M Y') }}@if($inv->payment_ref) · reference {{ $inv->payment_ref }}@endif.</div>
                @endif
            @endif

            {{-- Upload / replace (consultant, or HR with the Edit box) --}}
            @if(($self || ($canUploadFor ?? false)) && $sheet->status === R::APPROVED && (!$inv || $inv->status === R::INV_REJECTED))
                <form method="POST" action="{{ $u['invoiceUpload'] }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end border-t border-gray-100 pt-4" data-unsaved-ignore>@csrf
                    <div><label class="{{ $label }}" for="invNo">Invoice number</label><input id="invNo" name="invoice_no" required maxlength="100" value="{{ old('invoice_no') }}" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}" for="invDate">Invoice date</label><input type="date" id="invDate" name="invoice_date" required value="{{ old('invoice_date', now()->toDateString()) }}" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}" for="invAmount">Amount ({{ $p['currency'] }})</label><input id="invAmount" name="amount" required inputmode="decimal" value="{{ old('amount', $amounts && $p['gross'] !== null ? number_format($p['gross'], 2, ',', '.') : '') }}" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}" for="invFile">Invoice (PDF)</label><input type="file" id="invFile" name="invoice_file" required accept="application/pdf" class="{{ $input }} text-xs"></div>
                    <div class="md:col-span-4 flex justify-end"><button class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90"><i class="fas fa-upload mr-1"></i> {{ $inv ? 'Upload corrected invoice' : 'Upload invoice' }}</button></div>
                </form>
            @elseif($sheet->status === R::APPROVED && !$inv)
                <p class="text-xs text-gray-500"><i class="far fa-clock mr-1"></i> Waiting for the consultant to upload the invoice.</p>
            @endif

            {{-- Verify / reject / paid (staff) --}}
            @if(!$self && ($canVerify ?? false) && $inv)
                @if($inv->status === R::INV_UPLOADED)
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 border-t border-gray-100 pt-4">
                        <form method="POST" action="{{ $u['verify'] }}" data-confirm="Verify invoice {{ $inv->invoice_no }}?" data-confirm-title="Verify Invoice" data-confirm-ok="Verify" data-confirm-variant="primary" data-unsaved-ignore>@csrf
                            <button class="px-4 py-2 text-xs font-semibold text-white bg-green-700 rounded-lg hover:opacity-90"><i class="fas fa-check mr-1"></i> Verify invoice</button></form>
                        <form method="POST" action="{{ $u['reject'] }}" class="flex gap-2" data-unsaved-ignore>@csrf
                            <input name="reason" required maxlength="2000" placeholder="Reason for rejecting (required)" class="{{ $input }}">
                            <button class="px-4 py-2 text-xs font-semibold text-white bg-red-600 rounded-lg hover:opacity-90 whitespace-nowrap">Reject</button></form>
                    </div>
                @elseif($inv->status === R::INV_VERIFIED)
                    <form method="POST" action="{{ $u['paid'] }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-t border-gray-100 pt-4" data-unsaved-ignore>@csrf
                        <div><label class="{{ $label }}" for="paidDate">Payment date</label><input type="date" id="paidDate" name="paid_date" required value="{{ now()->toDateString() }}" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="paidRef">Payment reference</label><input id="paidRef" name="payment_ref" maxlength="150" placeholder="e.g. transfer number" class="{{ $input }}"></div>
                        <div class="flex justify-end"><button class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90"><i class="fas fa-money-check-dollar mr-1"></i> Mark as paid</button></div>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endif

{{-- Timeline --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">History</h3></div>
    <ol class="p-5 space-y-2.5 text-xs text-gray-700">
        @foreach(array_filter([
            [$dt($sheet->created_at), 'Timesheet created' . ($who($sheet->created_by) && (int) $sheet->created_by !== (int) $sheet->employee_id ? ' by ' . $who($sheet->created_by) . ' on behalf of the consultant' : '')],
            $sheet->submitted_at ? [$dt($sheet->submitted_at), 'Submitted' . ($who($sheet->submitted_by) ? ' by ' . $who($sheet->submitted_by) : '') . ' — approvers: ' . (implode(', ', $approvers[R::STAGE_APPROVAL] ?? []) ?: 'none set')] : null,
            $sheet->returned_at ? [$dt($sheet->returned_at), 'Returned' . ($who($sheet->returned_by) ? ' by ' . $who($sheet->returned_by) : '')] : null,
            $sheet->approved_at ? [$dt($sheet->approved_at), 'Approved' . ($who($sheet->approved_by) ? ' by ' . $who($sheet->approved_by) : '')] : null,
            $inv ? [$dt($inv->created_at), 'Invoice ' . $inv->invoice_no . ' uploaded' . ($who($inv->uploaded_by) ? ' by ' . $who($inv->uploaded_by) : '') . ' — verifiers: ' . (implode(', ', $approvers[R::STAGE_INVOICE] ?? []) ?: 'none set')] : null,
            $inv && $inv->rejected_at ? [$dt($inv->rejected_at), 'Invoice rejected' . ($who($inv->rejected_by) ? ' by ' . $who($inv->rejected_by) : '')] : null,
            $inv && $inv->verified_at ? [$dt($inv->verified_at), 'Invoice verified' . ($who($inv->verified_by) ? ' by ' . $who($inv->verified_by) : '')] : null,
            $inv && $inv->paid_date ? [$dt($inv->paid_date, 'd M Y'), 'Paid' . ($who($inv->paid_by) ? ' (recorded by ' . $who($inv->paid_by) . ')' : '')] : null,
        ]) as [$when, $what])
            <li class="flex gap-3"><span class="w-32 shrink-0 text-gray-400">{{ $when }}</span><span>{{ $what }}</span></li>
        @endforeach
    </ol>
</div>

@if($editorOpen)
@push('scripts')
<script>
(function () {
    const body = document.getElementById('entriesBody'), empty = document.getElementById('entriesEmpty');
    const rows = @json($rows), projects = @json($projects), price = @json($amounts ? $p : array_merge($p, ['rate' => null, 'gross' => null])), amounts = @json((bool) $amounts);
    const locations = @json(R::LOCATIONS), min = @json($minDate), max = @json($maxDate);
    const cls = 'w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200';
    let index = 0;

    function esc(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
    function addRow(r = {}) {
        const i = index++;
        const tr = document.createElement('tr');
        tr.className = 'align-top';
        tr.innerHTML = `
            <td class="px-3 py-2"><input type="date" name="entries[${i}][work_date]" value="${esc(r.work_date)}" min="${min}" max="${max}" class="${cls}"></td>
            <td class="px-3 py-2 min-w-[11rem]"><input name="entries[${i}][project_label]" list="projectList" value="${esc(r.project_label)}" maxlength="255" placeholder="Project (optional)" class="${cls}"><input type="hidden" name="entries[${i}][project_id]" value="${esc(r.project_id)}"></td>
            <td class="px-3 py-2"><input type="time" name="entries[${i}][start_time]" value="${esc(r.start_time)}" class="${cls}"></td>
            <td class="px-3 py-2"><input type="time" name="entries[${i}][end_time]" value="${esc(r.end_time)}" class="${cls}"></td>
            <td class="px-3 py-2 w-28"><input name="entries[${i}][hours]" inputmode="decimal" value="${esc(r.hours)}" placeholder="auto" class="${cls}" data-hours></td>
            <td class="px-3 py-2"><select name="entries[${i}][location]" class="${cls}"><option value="">—</option>${Object.entries(locations).map(([k, v]) => `<option value="${k}" ${r.location === k ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
            <td class="px-3 py-2 min-w-[14rem]"><input name="entries[${i}][activity]" value="${esc(r.activity)}" maxlength="500" placeholder="What did you work on?" class="${cls}"></td>
            <td class="px-3 py-2"><button type="button" class="text-red-500 hover:text-red-700 px-1" aria-label="Remove row">&times;</button></td>`;
        tr.querySelector('button').onclick = () => { tr.remove(); refresh(); };
        tr.addEventListener('input', e => {
            // The project text matches a known project → keep its id; free text clears it.
            if (e.target.name && e.target.name.endsWith('[project_label]')) {
                const hit = projects.find(p => p.label === e.target.value);
                tr.querySelector('[name$="[project_id]"]').value = hit ? hit.id : '';
            }
            refresh();
        });
        body.appendChild(tr);
    }

    function hoursOf(tr) {
        const s = tr.querySelector('[name$="[start_time]"]').value, e = tr.querySelector('[name$="[end_time]"]').value;
        if (s && e) {
            const [a, b] = [s, e].map(t => { const [h, m] = t.split(':'); return +h * 60 + +m; });
            return b > a ? (b - a) / 60 : 0;
        }
        const v = parseFloat(String(tr.querySelector('[data-hours]').value).replace(',', '.'));
        return isNaN(v) ? 0 : v;
    }

    function refresh() {
        const trs = [...body.children];
        empty.classList.toggle('hidden', trs.length > 0);
        // With start and end time the hours field follows them; typed hours stay for rows without times.
        trs.forEach(tr => {
            const s = tr.querySelector('[name$="[start_time]"]').value, e = tr.querySelector('[name$="[end_time]"]').value, h = tr.querySelector('[data-hours]');
            h.readOnly = !!(s && e);
            if (s && e) h.value = hoursOf(tr) ? hoursOf(tr).toFixed(2) : '';
        });
        const total = trs.reduce((n, tr) => n + hoursOf(tr), 0);
        const hpm = price.hpm || 8;
        const qty = { mandays: total / hpm, hourly: total, monthly: price.factor, fixed_price: (price.milestone || 100) / 100 }[price.scheme] ?? total / hpm;
        const fmt = (n, d = 2) => n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
        document.getElementById('sumHours').textContent = fmt(total);
        document.getElementById('sumQty').textContent = fmt(qty);
        const gross = document.getElementById('sumGross');
        if (gross && amounts && price.rate !== null) gross.textContent = (price.currency === 'IDR' ? 'Rp' : price.currency) + ' ' + fmt(price.rate * qty, Number.isInteger(price.rate * qty) ? 0 : 2);
    }

    const list = document.createElement('datalist'); list.id = 'projectList';
    list.innerHTML = projects.map(p => `<option value="${esc(p.label)}"></option>`).join('');
    document.body.appendChild(list);

    document.getElementById('milestonePct')?.addEventListener('input', e => { price.milestone = parseFloat(e.target.value) || 0; refresh(); });
    document.getElementById('addRow').onclick = () => { addRow({ work_date: max < min ? '' : max }); refresh(); };
    (rows.length ? rows : []).forEach(r => addRow(r));
    refresh();

    // Submitting freezes the rows: ask first (the app's own confirm dialog).
    const form = document.getElementById('sheetForm');
    form.addEventListener('submit', async e => {
        if (e.submitter?.id !== 'submitBtn' || form.dataset.confirmed === '1') return;
        e.preventDefault();
        if (await window.showConfirm('Submit this timesheet for approval? You cannot change the rows after submitting unless it is returned.', 'Submit Timesheet', 'primary', { okText: 'Submit', cancelText: 'Cancel' })) {
            form.dataset.confirmed = '1';
            const hidden = document.createElement('input'); hidden.type = 'hidden';
            form.action = e.submitter.getAttribute('formaction'); form.submit();
        }
    });
})();
</script>
@endpush
@endif
