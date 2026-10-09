@extends('dashboard')

@section('title', 'AI Usage')
@section('page-title', 'AI Usage')
@section('page-subtitle', 'Token usage and estimated remaining balance for Claude and OpenAI')

@section('content')
@php
    $fmt = fn ($n) => number_format((int) $n);
    $usd = fn ($n) => '$' . number_format((float) $n, 2);
    $compact = function ($n) {
        $n = (int) $n;
        if ($n >= 1_000_000) return number_format($n / 1_000_000, 2) . 'M';
        if ($n >= 1_000) return number_format($n / 1_000, 1) . 'K';
        return (string) $n;
    };
    $allTokens = fn ($r) => (int) $r->input_tokens + (int) $r->cache_read_tokens + (int) $r->cache_write_tokens + (int) $r->output_tokens;
    $providerTone = ['anthropic' => 'bg-amber-50 text-amber-600', 'openai' => 'bg-emerald-50 text-emerald-600'];
    $maxDaily = max(0.000001, (float) $daily->max('cost_usd'));
@endphp

<div class="space-y-6">

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

    <div class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3">
        <i class="fas fa-circle-info mt-0.5 text-xs text-indigo-500"></i>
        <p class="text-xs leading-relaxed text-indigo-900">
            Token counts come from what each provider returns per request. Costs are <strong>estimates</strong>
            from the model price list in AI Settings and exclude web search and code execution fees.
            Anthropic and OpenAI do not expose prepaid balance through their API, so the remaining balance below is
            <strong>the balance you enter minus estimated spend since that date</strong>. The provider console remains the official figure.
            @if ($trackingSince)
                Tracking started {{ \Illuminate\Support\Carbon::parse($trackingSince)->format('d M Y H:i') }}.
            @else
                No usage recorded yet; numbers appear after the next AI request.
            @endif
        </p>
    </div>

    {{-- Remaining balance --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach ($providers as $key => $label)
            @php $b = $balances[$key]; @endphp
            <section class="overflow-hidden rounded-xl border bg-white shadow-sm {{ $b['is_low'] ? 'border-red-300' : 'border-gray-200' }}">
                <header class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $providerTone[$key] }}">
                        <i class="fas fa-wallet text-sm"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-semibold text-gray-900">{{ $label }}</h2>
                        <p class="text-xs text-gray-500">Estimated remaining balance</p>
                    </div>
                    @if ($b['is_low'])
                        <span class="rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-semibold text-red-700">Low balance</span>
                    @endif
                </header>

                <div class="p-5">
                    @if ($b['remaining'] === null)
                        <p class="text-sm text-gray-500">No balance set. Enter your current balance below to start tracking.</p>
                    @else
                        <p class="text-3xl font-semibold {{ $b['is_low'] || $b['remaining'] < 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $usd($b['remaining']) }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $usd($b['balance_usd']) }} entered, {{ $usd($b['spent']) }} spent since
                            {{ \Illuminate\Support\Carbon::parse($b['since'])->format('d M Y') }}
                        </p>
                        @if ($b['balance_usd'] > 0)
                            @php $pct = max(0, min(100, ($b['remaining'] / $b['balance_usd']) * 100)); @endphp
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full {{ $b['is_low'] ? 'bg-red-500' : 'bg-indigo-500' }}" style="width: {{ $pct }}%"></div>
                            </div>
                        @endif
                    @endif

                    <form method="POST" action="{{ route('admin.ai-usage.balance', ['range' => $range]) }}" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @csrf
                        <input type="hidden" name="provider" value="{{ $key }}">
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Balance (USD)</label>
                            <input type="number" step="0.01" min="0" name="balance_usd" required
                                   value="{{ old('provider') === $key ? old('balance_usd') : ($b['balance_usd'] ?? '') }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Balance as of</label>
                            <input type="date" name="since" required max="{{ now()->toDateString() }}"
                                   value="{{ old('provider') === $key ? old('since') : ($b['since'] ? \Illuminate\Support\Carbon::parse($b['since'])->toDateString() : now()->toDateString()) }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Warn below (USD)</label>
                            <input type="number" step="0.01" min="0" name="low_threshold_usd"
                                   value="{{ old('provider') === $key ? old('low_threshold_usd') : ($b['low_threshold_usd'] ?? '') }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                        </div>
                        <div class="sm:col-span-3">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">Save balance</button>
                        </div>
                    </form>
                </div>
            </section>
        @endforeach
    </div>

    {{-- Range filter --}}
    <div class="flex flex-wrap gap-2">
        @foreach ($ranges as $key => $label)
            <a href="{{ route('admin.ai-usage', ['range' => $key]) }}"
               class="rounded-full border px-3.5 py-1.5 text-xs font-medium transition {{ $range === $key ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Totals --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        @foreach ([
            ['Requests', $fmt($totals->calls ?? 0), null],
            ['Estimated cost', $usd($totals->cost_usd ?? 0), null],
            ['Input tokens', $compact($totals->input_tokens ?? 0), $fmt($totals->input_tokens ?? 0)],
            ['Output tokens', $compact($totals->output_tokens ?? 0), $fmt($totals->output_tokens ?? 0)],
            ['Cached tokens', $compact(($totals->cache_read_tokens ?? 0) + ($totals->cache_write_tokens ?? 0)), 'read ' . $fmt($totals->cache_read_tokens ?? 0) . ' / write ' . $fmt($totals->cache_write_tokens ?? 0)],
        ] as [$title, $value, $hint])
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $title }}</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ $value }}</p>
                @if ($hint)<p class="mt-0.5 truncate text-[11px] text-gray-400">{{ $hint }}</p>@endif
            </div>
        @endforeach
    </div>

    {{-- Daily cost --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">Daily estimated cost</h2>
        @if ($daily->isEmpty())
            <p class="mt-3 text-sm text-gray-500">No usage in this range.</p>
        @else
            <div class="mt-4 flex h-40 items-end gap-1 overflow-x-auto">
                @foreach ($daily as $d)
                    <div class="group relative flex h-full min-w-[14px] flex-1 items-end" title="{{ \Illuminate\Support\Carbon::parse($d->day)->format('d M') }}: {{ $usd($d->cost_usd) }}, {{ $fmt($d->tokens) }} tokens">
                        <div class="w-full rounded-t bg-indigo-500 transition group-hover:bg-indigo-600"
                             style="height: {{ max(2, ((float) $d->cost_usd / $maxDaily) * 100) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[11px] text-gray-400">
                <span>{{ \Illuminate\Support\Carbon::parse($daily->first()->day)->format('d M Y') }}</span>
                <span>Peak {{ $usd($maxDaily) }}/day</span>
                <span>{{ \Illuminate\Support\Carbon::parse($daily->last()->day)->format('d M Y') }}</span>
            </div>
        @endif
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        {{-- By model --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-900">By model</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <tr><th class="px-5 py-2">Model</th><th class="px-3 py-2 text-right">Requests</th><th class="px-3 py-2 text-right">Tokens</th><th class="px-5 py-2 text-right">Est. cost</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($byModel as $r)
                            <tr>
                                <td class="px-5 py-2 font-medium text-gray-800">{{ $catalog[$r->model]['label'] ?? $r->model }}<span class="ml-1 text-gray-400">{{ $providers[$r->provider] ?? $r->provider }}</span></td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ $fmt($r->calls) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ $compact($allTokens($r)) }}</td>
                                <td class="px-5 py-2 text-right font-medium text-gray-800">{{ $usd($r->cost_usd) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-gray-400">No usage in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- By feature --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-900">By feature</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <tr><th class="px-5 py-2">Feature</th><th class="px-3 py-2 text-right">Requests</th><th class="px-3 py-2 text-right">Tokens</th><th class="px-5 py-2 text-right">Est. cost</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($bySource as $r)
                            <tr>
                                <td class="px-5 py-2 font-medium text-gray-800">{{ $sources[$r->source] ?? $r->source }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ $fmt($r->calls) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ $compact($allTokens($r)) }}</td>
                                <td class="px-5 py-2 text-right font-medium text-gray-800">{{ $usd($r->cost_usd) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-gray-400">No usage in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Top users --}}
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-900">Top users</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr><th class="px-5 py-2">Employee</th><th class="px-3 py-2 text-right">Requests</th><th class="px-3 py-2 text-right">Tokens</th><th class="px-5 py-2 text-right">Est. cost</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($byUser as $r)
                        <tr>
                            <td class="px-5 py-2 font-medium text-gray-800">{{ $names[$r->employee_id] ?? ('Employee #' . $r->employee_id) }}</td>
                            <td class="px-3 py-2 text-right text-gray-600">{{ $fmt($r->calls) }}</td>
                            <td class="px-3 py-2 text-right text-gray-600">{{ $compact($allTokens($r)) }}</td>
                            <td class="px-5 py-2 text-right font-medium text-gray-800">{{ $usd($r->cost_usd) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-6 text-center text-gray-400">No user-attributed usage in this range. Background jobs are not attributed to a user.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Recent requests --}}
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-900">Latest 25 requests</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-2">Time</th><th class="px-3 py-2">Model</th><th class="px-3 py-2">Feature</th>
                        <th class="px-3 py-2 text-right">Input</th><th class="px-3 py-2 text-right">Cached</th>
                        <th class="px-3 py-2 text-right">Output</th><th class="px-5 py-2 text-right">Est. cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recent as $r)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-2 text-gray-600">{{ $r->created_at->format('d M H:i:s') }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $catalog[$r->model]['label'] ?? $r->model }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $sources[$r->source] ?? $r->source }}</td>
                            <td class="px-3 py-2 text-right text-gray-600">{{ $fmt($r->input_tokens) }}</td>
                            <td class="px-3 py-2 text-right text-gray-600">{{ $fmt($r->cache_read_tokens + $r->cache_write_tokens) }}</td>
                            <td class="px-3 py-2 text-right text-gray-600">{{ $fmt($r->output_tokens) }}</td>
                            <td class="px-5 py-2 text-right font-medium text-gray-800">${{ number_format($r->cost_usd, 4) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-6 text-center text-gray-400">Nothing recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
