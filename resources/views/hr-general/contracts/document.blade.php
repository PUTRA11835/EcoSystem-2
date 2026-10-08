{{--
    The contract as it is printed. The sheet is the real PDF (DomPDF) shown in a frame, so every page carries the
    letterhead of the letter type "Employment Contract" (Letter Templates → Settings) exactly as it will print or download.
    Print prints that frame; PDF downloads it. Someone without "View Salary" gets a plain text view with the salary hidden
    (no letterhead, no print, no PDF) — a copy with hidden amounts must never be printed as a contract.
--}}
@extends('dashboard')
@section('title', $doc['number'])
@section('page-title', 'Contract')
@section('page-subtitle', $doc['title'] . ' — ' . $doc['number'])

@php
    $hasLetterhead = (bool) $doc['letterhead']?->backgroundPath();
    $hasSigned = !empty($contract->signed_file_path);
    $canEditList = $canDo('general.contracts.list', 'edit');
    $canUpload = $canEditList && $contract->lifecycle_status !== 'draft' && !$masked;
    $confirmUpload = $hasSigned ? 'Replace the stamped copy of ' . $doc['number'] . ' with this file?' : null;
    $warnText = '{{' . implode('}}, {{', $doc['warnings']) . '}}';
@endphp

@push('styles')
<style>
    .contract-frame { width: 100%; height: calc(100vh - 260px); min-height: 640px; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; }
    .contract-text { width: 210mm; max-width: 100%; margin: 0 auto; background: #fff; box-shadow: 0 1px 12px rgba(0,0,0,.12); padding: 20mm;
        font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.45; color: #000; }
    @include('hr-general.contracts.components.paper-css', ['sel' => '.contract-text'])
</style>
@endpush

@section('content')
<div class="w-full space-y-4 px-1 lg:px-2">
    @include('hr-general.contracts.components.tabs')

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2 text-xs text-gray-500">
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusTone }}">{{ $statusLabel }}</span>
            <span>{{ $doc['number'] }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $back }}" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i> Back</a>
            @if($canUpload)
                <form id="signedForm" method="POST" action="{{ route('general.contracts.signed.upload', $contract->contract_id) }}" enctype="multipart/form-data" data-unsaved-ignore>@csrf
                    <input type="file" name="signed_file" id="signedFile" accept="application/pdf" class="hidden">
                    <button type="button" id="signedPick" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"><i class="fas fa-stamp mr-1"></i> {{ $hasSigned ? 'Replace stamped copy' : 'Upload stamped copy' }}</button>
                </form>
            @endif
            @unless($masked)
                <a href="{{ $pdfUrl }}?download=1" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"><i class="fas fa-file-pdf mr-1"></i> PDF</a>
                <button type="button" id="printContract" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90"><i class="fas fa-print mr-1"></i> Print Contract</button>
            @endunless
        </div>
    </div>

    @if($masked)
        <div class="text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><i class="fas fa-lock mr-1"></i>
            Salary amounts are hidden because you do not hold the “View Salary” permission, so this copy cannot be printed or downloaded.</div>
    @endif
    @if(!$hasLetterhead && $doc['use_letterhead'])
        <div class="text-xs bg-sky-50 border border-sky-200 text-sky-900 rounded-lg px-4 py-3"><i class="fas fa-circle-info mr-1"></i>
            No letterhead is set for “Employment Contract” yet. Add one in Letter Templates → Settings and tick this letter type.
            @if($canDo('general.letter-templates'))<a href="{{ route('general.letters.settings.index', ['section' => 'letterheads']) }}" class="underline font-semibold">Open letterheads</a>@endif</div>
    @endif
    @if(!$contract->signatory_name)
        <div class="text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><i class="fas fa-pen-nib mr-1"></i>
            No company signatory is chosen for this contract yet, so the signatory name and title print as “-”. Edit the contract and pick a signer.
            @if($canDo('general.letter-templates'))<a href="{{ route('general.letters.settings.index', ['section' => 'signers']) }}" class="underline font-semibold">Manage signers</a>@endif</div>
    @endif
    @if($doc['warnings'])
        <div class="text-xs bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3"><i class="fas fa-triangle-exclamation mr-1"></i>
            The template contains placeholders this contract type does not have: {{ $warnText }}. They are printed as typed — fix the template.</div>
    @endif

    @error('contract')
        <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3" role="alert"><i class="fas fa-circle-exclamation mr-1"></i> {{ $message }}</div>
    @enderror
    @error('signed_file')
        <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3" role="alert"><i class="fas fa-circle-exclamation mr-1"></i> {{ $message }}</div>
    @enderror

    @unless($masked)
        {{-- Stamp duty (meterai). Method 1: download the PDF, sign it and stick the stamp, upload the PDF here. --}}
        <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 text-xs flex flex-wrap items-center justify-between gap-3">
            @if($hasSigned)
                <div class="flex items-center gap-2 text-gray-700">
                    <span class="w-8 h-8 rounded-lg tone-primary flex items-center justify-center"><i class="fas fa-stamp"></i></span>
                    <div><p class="font-semibold">Stamped copy on file <span class="font-normal text-gray-400">· {{ $contract->signed_file_name }}</span></p>
                        <p class="text-[11px] text-gray-400">Uploaded {{ $contract->signed_file_at?->format('d M Y H:i') }}@if($signedBy) by {{ $signedBy }}@endif</p></div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="seg">
                        <button type="button" data-doc-view="signed" class="seg-print">Stamped copy</button>
                        <button type="button" data-doc-view="generated" class="seg-view">Generated</button>
                    </div>
                    <a href="{{ $signedUrl }}?download=1" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-50"><i class="fas fa-download mr-1"></i> Download</a>
                    @if($canEditList)
                        <form method="POST" action="{{ route('general.contracts.signed.remove', $contract->contract_id) }}" data-confirm="Remove the stamped copy of {{ $doc['number'] }}? The contract itself is not affected." data-confirm-title="Remove Stamped Copy" data-confirm-ok="Remove" data-unsaved-ignore>@csrf
                            <button type="submit" class="px-3 py-1.5 border border-red-300 text-red-600 font-semibold rounded-lg hover:bg-red-50">Remove</button>
                        </form>
                    @endif
                </div>
            @else
                <div class="flex items-center gap-2 text-gray-600">
                    <span class="w-8 h-8 rounded-lg bg-gray-50 text-gray-400 flex items-center justify-center"><i class="fas fa-stamp"></i></span>
                    <p><span class="font-semibold text-gray-700">No stamped copy yet.</span> Download the PDF, sign it and stick the meterai on the dashed box, then upload the PDF here.
                        @unless($canUpload) <span class="text-gray-400">(A Draft cannot have one; uploading needs the Edit permission.)</span>@endunless</p>
                </div>
            @endif
        </div>
    @endunless

    @if($masked)
        <div class="overflow-x-auto pb-6"><div class="contract-text">{!! $doc['html'] !!}</div></div>
    @else
        <iframe id="contractFrame" class="contract-frame" src="{{ $hasSigned ? $signedUrl : $pdfUrl }}#view=FitH" title="Contract {{ $doc['number'] }}"></iframe>
        <p class="text-[11px] text-gray-400">This is the PDF as it prints. If the sheet does not show, <a href="{{ $pdfUrl }}" target="_blank" class="underline font-semibold">open the PDF</a>.</p>
    @endif
</div>

@unless($masked)
@push('scripts')
<script>
    const frame = document.getElementById('contractFrame');
    // Stamp: choose the file, confirm a replacement, then send it.
    const pick = document.getElementById('signedPick'), file = document.getElementById('signedFile'), form = document.getElementById('signedForm');
    pick?.addEventListener('click', () => file.click());
    file?.addEventListener('change', async () => {
        if (!file.files.length) return;
        @if($confirmUpload)
        if (!(await window.showConfirm(@json($confirmUpload), 'Replace Stamped Copy', 'primary', { okText: 'Replace', cancelText: 'Cancel' }))) { file.value = ''; return; }
        @endif
        form.submit();
    });
    // Switch the frame between the stamped copy and the generated contract.
    document.querySelectorAll('[data-doc-view]').forEach(b => b.addEventListener('click', () => {
        frame.src = (b.dataset.docView === 'signed' ? @json($signedUrl) : @json($pdfUrl)) + '#view=FitH';
    }));
    // "Print" in the list opens this page with ?print=1: print once the sheet has loaded.
    if (new URLSearchParams(location.search).get('print') === '1') {
        frame.addEventListener('load', () => setTimeout(() => document.getElementById('printContract').click(), 700), { once: true });
    }
    document.getElementById('printContract')?.addEventListener('click', function () {
        try { frame.contentWindow.focus(); frame.contentWindow.print(); }
        catch (e) { window.open(@json($pdfUrl), '_blank'); } // the browser would not print the frame: open the PDF to print from there
    });
</script>
@endpush
@endunless
@endsection
