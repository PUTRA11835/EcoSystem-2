@extends('dashboard')

@section('title', 'AI Settings')
@section('page-title', 'AI Settings')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">

    {{-- Flash session sengaja TIDAK dirender di sini: dashboard.blade.php sudah
         menampilkannya lewat showToast(). Merendernya ulang membuat toast dobel. --}}
    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Penjelasan singkat: halaman ini menyentuh tagihan, jadi konsekuensinya
         ditulis di layar, bukan diserahkan ke ingatan admin. --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                <i class="fas fa-microchip text-indigo-600"></i>
            </div>
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-gray-800">Model &amp; token ceiling</h2>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                    These settings decide which Claude model backs each assistant, and how many tokens a single
                    answer may spend. Model choice changes the <strong>price per token</strong>; the token ceiling
                    changes <strong>how many tokens</strong> one answer can burn. Both apply to the next message
                    anyone sends &mdash; no deploy needed.
                </p>
                <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                    Users do not choose a model. Everyone gets the preset you mark as <strong>Active</strong>;
                    the others are kept here as ready-made settings you can switch to.
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.ai-settings.update') }}" class="space-y-5">
        @csrf

        @foreach ($assistants as $assistantKey => $assistantName)
            @php
                $config = $settings[$assistantKey];
                $isResearch = $assistantKey === \App\Support\AiModelSettings::RESEARCH;
                $allowed = $isResearch
                    ? array_filter($catalog, fn ($m) => $m['server_tools'])
                    : $catalog;
            @endphp

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl {{ $isResearch ? 'bg-indigo-50' : 'bg-emerald-50' }} flex items-center justify-center">
                        <i class="fas {{ $isResearch ? 'fa-magnifying-glass-chart text-indigo-600' : 'fa-robot text-emerald-600' }} text-sm"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $assistantName }}</h3>
                        <p class="text-xs text-gray-400">
                            {{ $isResearch
                                ? 'External lookup on the web. Each answer may also trigger billable web searches.'
                                : 'Internal EcoSystem data. Uses local tools only.' }}
                        </p>
                    </div>
                </div>

                @if ($isResearch)
                    <div class="px-5 pt-4">
                        <div class="rounded-xl bg-amber-50 border border-amber-200 px-3 py-2.5 flex items-start gap-2">
                            <i class="fas fa-triangle-exclamation text-amber-500 text-xs mt-0.5"></i>
                            <p class="text-xs text-amber-800 leading-relaxed">
                                Haiku is not offered here. Web search and web fetch need Sonnet 5 or Opus 5 &mdash;
                                on Haiku the page would fail on every question.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="p-5 space-y-3">
                    @foreach ($config['tiers'] as $tierKey => $tier)
                        @php $isActive = $config['active'] === $tierKey; @endphp

                        <div class="rounded-xl border {{ $isActive ? 'border-indigo-300 bg-indigo-50/40' : 'border-gray-200' }} p-4"
                             data-tier-card="{{ $assistantKey }}.{{ $tierKey }}">

                            <label class="flex items-center gap-2.5 cursor-pointer mb-3">
                                <input type="radio"
                                       name="assistants[{{ $assistantKey }}][active]"
                                       value="{{ $tierKey }}"
                                       {{ $isActive ? 'checked' : '' }}
                                       class="w-4 h-4 text-indigo-600 focus:ring-indigo-400">
                                <span class="text-sm font-semibold text-gray-800">{{ $tier['label'] }}</span>
                                @if ($isActive)
                                    <span class="px-2 py-0.5 rounded-full bg-indigo-600 text-white text-[10px] font-semibold">In use</span>
                                @endif
                            </label>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-500 mb-1">Model</label>
                                    <select name="assistants[{{ $assistantKey }}][tiers][{{ $tierKey }}][model]"
                                            data-ai-model
                                            data-target="{{ $assistantKey }}.{{ $tierKey }}"
                                            class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400">
                                        @foreach ($allowed as $modelId => $model)
                                            <option value="{{ $modelId }}" {{ $tier['model'] === $modelId ? 'selected' : '' }}>
                                                {{ $model['label'] }} &mdash; ${{ rtrim(rtrim(number_format($model['price_in'], 2), '0'), '.') }}/${{ rtrim(rtrim(number_format($model['price_out'], 2), '0'), '.') }} per 1M
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-500 mb-1">
                                        Max tokens per answer
                                    </label>
                                    <input type="number"
                                           name="assistants[{{ $assistantKey }}][tiers][{{ $tierKey }}][max_tokens]"
                                           value="{{ $tier['max_tokens'] }}"
                                           min="512"
                                           step="256"
                                           data-ai-maxtokens
                                           data-target="{{ $assistantKey }}.{{ $tierKey }}"
                                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400">
                                    <p class="text-[10px] text-gray-400 mt-1" data-ai-maxnote="{{ $assistantKey }}.{{ $tierKey }}"></p>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-500 mb-1">Thinking effort</label>
                                    <select name="assistants[{{ $assistantKey }}][tiers][{{ $tierKey }}][effort]"
                                            data-ai-effort
                                            data-target="{{ $assistantKey }}.{{ $tierKey }}"
                                            class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 disabled:bg-gray-100 disabled:text-gray-400">
                                        <option value="">Off</option>
                                        @foreach ($efforts as $effort)
                                            <option value="{{ $effort }}" {{ $tier['effort'] === $effort ? 'selected' : '' }}>
                                                {{ ucfirst($effort) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="text-[10px] text-gray-400 mt-1" data-ai-effortnote="{{ $assistantKey }}.{{ $tierKey }}"></p>
                                </div>
                            </div>

                            <p class="text-[11px] text-gray-500 mt-2.5" data-ai-modelnote="{{ $assistantKey }}.{{ $tierKey }}"></p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex items-center justify-end gap-2 pb-2">
            <a href="{{ route('admin.index') }}"
               class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                Cancel
            </a>
            <button type="submit"
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">
                Save settings
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
/*
 * Katalog dikirim ke browser supaya batasan per-model terlihat SEBELUM submit:
 * plafon max_tokens milik model, dan model mana yang menolak `effort`
 * (Haiku 4.5). Ini murni bantuan tampilan — penegakan sebenarnya tetap di
 * AiModelSettings::sanitize(), yang jalan di server pada setiap penyimpanan
 * DAN setiap pembacaan.
 */
const AI_CATALOG = @json($catalog);

function aiApplyModelLimits(target) {
    const modelSelect = document.querySelector(`[data-ai-model][data-target="${target}"]`);
    if (!modelSelect) return;

    const model = AI_CATALOG[modelSelect.value];
    if (!model) return;

    const maxInput   = document.querySelector(`[data-ai-maxtokens][data-target="${target}"]`);
    const maxNote    = document.querySelector(`[data-ai-maxnote="${target}"]`);
    const effort     = document.querySelector(`[data-ai-effort][data-target="${target}"]`);
    const effortNote = document.querySelector(`[data-ai-effortnote="${target}"]`);
    const modelNote  = document.querySelector(`[data-ai-modelnote="${target}"]`);

    if (maxInput) {
        maxInput.max = model.max_output;
        if (Number(maxInput.value) > model.max_output) {
            maxInput.value = model.max_output;
        }
    }
    if (maxNote) {
        maxNote.textContent = 'Ceiling for this model: ' + model.max_output.toLocaleString() + ' tokens.';
    }

    if (effort) {
        effort.disabled = !model.effort;
        if (!model.effort) effort.value = '';
    }
    if (effortNote) {
        effortNote.textContent = model.effort
            ? 'Higher effort means deeper thinking — and more tokens.'
            : 'This model does not accept an effort setting.';
    }

    if (modelNote) {
        modelNote.textContent = model.context + ' context. ' + model.note;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-ai-model]').forEach(function (select) {
        const target = select.dataset.target;
        aiApplyModelLimits(target);
        select.addEventListener('change', function () { aiApplyModelLimits(target); });
    });
});
</script>
@endpush
