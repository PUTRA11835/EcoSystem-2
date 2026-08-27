@extends('dashboard')

@section('title', 'AI Settings')
@section('page-title', 'AI Settings')
@section('page-subtitle', 'Choose the AI provider and model powering each assistant')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Flash session sengaja TIDAK dirender di sini: dashboard.blade.php sudah
         menampilkannya lewat showToast(). Merendernya ulang membuat toast dobel. --}}
    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <i class="fas fa-circle-exclamation mt-0.5 text-red-500"></i>
            <ul class="list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Ringkas: halaman ini menyentuh tagihan, jadi konsekuensinya ditulis di
         layar, bukan diserahkan ke ingatan admin. Page header (dashboard.blade.php)
         sudah menjelaskan konteksnya, jadi ini cukup satu baris peringatan. --}}
    <div class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3">
        <i class="fas fa-circle-info mt-0.5 text-xs text-indigo-500"></i>
        <p class="text-xs leading-relaxed text-indigo-900">
            Model choice sets both the <strong>provider being billed</strong> and the <strong>price per token</strong>;
            the token ceiling sets how much a single answer can spend. Changes apply to the next message immediately,
            with no deploy required. Users never pick a model themselves; everyone gets the model configured below.
        </p>
    </div>

    @php
        /*
         * Ikon, warna, dan satu kalimat penjelas per asisten. Dulu ini cuma
         * percabangan dua arah (Research vs "yang lain"), sehingga setiap
         * asisten baru — Ticket Analyzer, AI Summarize, dua fase Word Report —
         * ikut dilabeli "Internal EcoSystem data only", yang untuk sebagian
         * besar dari mereka justru salah: mereka memang mencari ke web dan
         * ikut menagih biaya pencarian.
         *
         * Kelas warna ditulis LENGKAP (bukan "bg-{$tone}-50" hasil sambungan
         * string) supaya tetap terbaca sebagai kelas utuh oleh Tailwind.
         */
        $assistantMeta = [
            \App\Support\AiModelSettings::RESEARCH => [
                'icon' => 'fa-magnifying-glass-chart',
                'tone' => 'bg-indigo-50 text-indigo-600',
                'desc' => 'External lookup on the web. Each answer may also trigger billable web searches.',
            ],
            \App\Support\AiModelSettings::INTERNAL => [
                'icon' => 'fa-robot',
                'tone' => 'bg-emerald-50 text-emerald-600',
                'desc' => 'Internal EcoSystem data only, via local tools.',
            ],
            \App\Support\AiModelSettings::TICKET_ANALYZER => [
                'icon' => 'fa-clipboard-check',
                'tone' => 'bg-amber-50 text-amber-600',
                'desc' => 'Staging ticket validation. One structured analysis per ticket reviewed.',
            ],
            \App\Support\AiModelSettings::TICKET_SUMMARY => [
                'icon' => 'fa-wand-magic-sparkles',
                'tone' => 'bg-sky-50 text-sky-600',
                'desc' => 'The AI Summarize button on the ticket list. Reads the ticket internally, then searches vendor documentation for the fix — the highest-volume assistant here, so model price matters most on this row.',
            ],
            \App\Support\AiModelSettings::WORD_REPORT => [
                'icon' => 'fa-file-word',
                'tone' => 'bg-blue-50 text-blue-600',
                'desc' => 'Word report generator, phases 1-2: reads the template structure and collects the data.',
            ],
            \App\Support\AiModelSettings::WORD_REPORT_DOCUMENT => [
                'icon' => 'fa-file-arrow-down',
                'tone' => 'bg-violet-50 text-violet-600',
                'desc' => 'Word report generator, phase 3: assembles the document via code execution. Heaviest phase; kept on its own model so it does not share a rate-limit pool with the phases above.',
            ],
        ];

        // Asisten yang ditambahkan di AiModelSettings tapi belum diberi entri di
        // atas tetap tampil dan tetap bisa diatur — tanpa ini halamannya mati
        // dengan undefined index hanya karena kurang label.
        $assistantMetaFallback = [
            'icon' => 'fa-robot',
            'tone' => 'bg-gray-100 text-gray-600',
            'desc' => 'AI assistant.',
        ];
    @endphp

    <form method="POST" action="{{ route('admin.ai-settings.update') }}" class="space-y-6">
        @csrf

        @foreach ($assistants as $assistantKey => $assistantName)
            @php
                $config = $settings[$assistantKey];

                // Penyaringan model TIDAK lagi dihitung di sini: controller
                // sudah mengirim daftar per asisten dari AiModelSettings::catalogFor(),
                // satu-satunya sumber yang sama dengan yang menegakkan di sisi simpan.
                $allowed = $allowedByAssistant[$assistantKey];
                $needsWeb = $requiresWebByAssistant[$assistantKey];
                $meta = $assistantMeta[$assistantKey] ?? $assistantMetaFallback;

                // Dikelompokkan per provider untuk <optgroup>: bukan cuma kosmetik,
                // provider menentukan driver mana yang benar-benar dipanggil
                // (App\Services\Ai\Drivers\AiDriverFactory), jadi admin perlu melihat
                // dengan jelas API mana yang akan menagih.
                $providerLabels = ['anthropic' => 'Claude (Anthropic)', 'openai' => 'OpenAI GPT'];
                $groupedAllowed = [];
                foreach ($allowed as $modelId => $model) {
                    $groupedAllowed[$model['provider']][$modelId] = $model;
                }
            @endphp

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <header class="flex items-center gap-3 border-b border-gray-100 px-6 py-4">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $meta['tone'] }}">
                        <i class="fas {{ $meta['icon'] }} text-sm"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-semibold text-gray-900">{{ $assistantName }}</h2>
                        <p class="text-xs text-gray-500">{{ $meta['desc'] }}</p>
                    </div>
                </header>

                @if ($needsWeb)
                    <div class="flex items-start gap-2.5 px-6 pt-4">
                        <i class="fas fa-triangle-exclamation mt-0.5 text-xs text-amber-500"></i>
                        <p class="text-xs leading-relaxed text-amber-700">
                            Only models with built-in web search and fetch are listed here; Claude Haiku 4.5 is
                            excluded because it does not support them.
                        </p>
                    </div>
                @endif

                {{-- Satu model per asisten: tidak ada lagi radio "Active" atau beberapa
                     preset untuk dipilih. Field-field ini langsung mengikat ke
                     assistants[$assistantKey][...], bukan ke tiers bersarang. --}}
                <div class="p-6">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Model</label>
                            <select name="assistants[{{ $assistantKey }}][model]"
                                    data-ai-model
                                    data-target="{{ $assistantKey }}"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                @foreach ($groupedAllowed as $providerKey => $models)
                                    <optgroup label="{{ $providerLabels[$providerKey] ?? ucfirst($providerKey) }}">
                                        @foreach ($models as $modelId => $model)
                                            <option value="{{ $modelId }}" {{ $config['model'] === $modelId ? 'selected' : '' }}>
                                                {{ $model['label'] }} (${{ rtrim(rtrim(number_format($model['price_in'], 2), '0'), '.') }}/${{ rtrim(rtrim(number_format($model['price_out'], 2), '0'), '.') }} per 1M)
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                Max tokens per answer
                            </label>
                            <input type="number"
                                   name="assistants[{{ $assistantKey }}][max_tokens]"
                                   value="{{ $config['max_tokens'] }}"
                                   min="512"
                                   step="256"
                                   data-ai-maxtokens
                                   data-target="{{ $assistantKey }}"
                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            <p class="mt-1 text-[10px] text-gray-400" data-ai-maxnote="{{ $assistantKey }}"></p>
                        </div>

                        <div>
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Thinking / reasoning effort</label>
                            @php $modelEfforts = $catalog[$config['model']]['efforts'] ?? []; @endphp
                            <select name="assistants[{{ $assistantKey }}][effort]"
                                    data-ai-effort
                                    data-target="{{ $assistantKey }}"
                                    {{ empty($modelEfforts) ? 'disabled' : '' }}
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="">Off</option>
                                @foreach ($modelEfforts as $effort)
                                    <option value="{{ $effort }}" {{ $config['effort'] === $effort ? 'selected' : '' }}>
                                        {{ ucfirst($effort) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[10px] text-gray-400" data-ai-effortnote="{{ $assistantKey }}"></p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 text-[11px] text-gray-500"
                         data-ai-modelnote="{{ $assistantKey }}"></div>
                </div>
            </section>
        @endforeach

        <div class="flex items-center justify-end gap-3 pb-2">
            <a href="{{ route('admin.index') }}"
               class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
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
 * plafon max_tokens milik model, dan daftar effort yang model itu terima
 * (kosakata Claude dan OpenAI berbeda, lihat AiModelSettings::CATALOG). Ini
 * murni bantuan tampilan; penegakan sebenarnya tetap di
 * AiModelSettings::sanitize(), yang jalan di server pada setiap penyimpanan
 * DAN setiap pembacaan.
 */
const AI_CATALOG = @json($catalog);
const AI_PROVIDER_LABELS = @json($providerLabels);

// Warna badge provider di baris keterangan model, murni tampilan.
const AI_PROVIDER_BADGE = {
    anthropic: 'bg-indigo-50 text-indigo-700',
    openai: 'bg-teal-50 text-teal-700',
};

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

    // Daftar effort dibangun ulang dari nol tiap kali model berganti: Claude dan
    // OpenAI tidak berbagi kosakata (low/medium/high/xhigh/max vs.
    // minimal/low/medium/high), jadi opsi lama harus dibuang, bukan sekadar
    // diaktif/nonaktifkan seperti dulu ketika modelnya cuma sesama Claude.
    if (effort) {
        const previousValue = effort.value;
        const efforts = model.efforts || [];

        effort.innerHTML = '';
        const offOption = document.createElement('option');
        offOption.value = '';
        offOption.textContent = 'Off';
        effort.appendChild(offOption);

        efforts.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value.charAt(0).toUpperCase() + value.slice(1);
            effort.appendChild(option);
        });

        effort.value = efforts.includes(previousValue) ? previousValue : '';
        effort.disabled = efforts.length === 0;
    }
    if (effortNote) {
        effortNote.textContent = (model.efforts || []).length > 0
            ? 'Higher effort means deeper thinking and higher token usage.'
            : 'This model does not accept an effort setting.';
    }

    if (modelNote) {
        const providerLabel = AI_PROVIDER_LABELS[model.provider] || model.provider;
        const badgeClass = AI_PROVIDER_BADGE[model.provider] || 'bg-gray-100 text-gray-600';

        modelNote.innerHTML = [
            `<span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold ${badgeClass}">${providerLabel}</span>`,
            `<span class="text-gray-300">&bull;</span>`,
            `<span>${model.context} context</span>`,
            `<span class="text-gray-300">&bull;</span>`,
            `<span>${model.note}</span>`,
        ].join('');
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
