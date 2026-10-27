{{-- Peringatan: penandatangan yang diwajibkan config (config/hc_external.php) belum terdaftar di Settings → Signers, sehingga tidak bisa dipilih.
     Parameter: $signerScope ('letters' | 'contract'). Tanpa ini daftar tampil tanpa nama itu dan penyebabnya tak terlihat. --}}
@php
    $missingSigners = \App\Support\External\ExternalSigners::missing($signerScope);
    $canOpenSigners = ($canDo ?? null) ? $canDo('general.letters.settings.signers') : false;
@endphp
@if($missingSigners)
    <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-xs text-amber-900 mt-2" role="alert">
        <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i> {{ count($missingSigners) === 1 ? 'A required signer is' : 'Required signers are' }} not available yet</p>
        <ul class="mt-1 space-y-0.5 list-disc list-inside">
            @foreach($missingSigners as $m)
                <li><strong>{{ $m['name'] ?: $m['eci'] }}</strong> ({{ $m['eci'] }}) —
                    {{ $m['exists'] ? 'is not registered as a signer.' : 'was not found in the employee data (check the ECI in config/hc_external.php).' }}</li>
            @endforeach
        </ul>
        <p class="mt-1.5">An administrator registers a signer in <strong>Document identity → Signers</strong>.
            @if($canOpenSigners)<a href="{{ route('general.document-identity.index') }}" target="_blank" rel="noopener" class="font-semibold underline">Open it now <i class="fas fa-arrow-up-right-from-square text-[10px]"></i></a>@endif
        Until then other registered signers are offered instead.</p>
    </div>
@endif
