{{--
    Compact table of Legal Basis register rows (read-only). Parameters: $rows (decorated register rows: label, title, article, effective_date,
    last_amended_date, status, confidence, reviewed_at).
--}}
@php
    $badges = \App\Services\Legal\LegalBasisService::BADGES;
    $statuses = \App\Services\Legal\LegalBasisService::STATUSES;
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-xs text-left">
        <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
            <tr><th class="px-4 py-2.5">Rule</th><th class="px-4 py-2.5">Regulation</th><th class="px-4 py-2.5">In force since</th><th class="px-4 py-2.5">Last amended</th><th class="px-4 py-2.5">Status</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-gray-700">
            @forelse($rows as $r)
                <tr class="align-top">
                    <td class="px-4 py-2.5 font-mono text-[11px] text-gray-600 whitespace-nowrap">{{ $r->rule_key }}</td>
                    <td class="px-4 py-2.5"><span class="font-semibold text-gray-800">{{ $r->label }}</span><span class="block text-[11px] text-gray-500">{{ $r->title }}</span>@if($r->article)<span class="block text-[10px] text-gray-400">{{ $r->article }}</span>@endif</td>
                    <td class="px-4 py-2.5 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($r->effective_date)->format('d M Y') }}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap">{{ $r->last_amended_date ? \Illuminate\Support\Carbon::parse($r->last_amended_date)->format('d M Y') : '—' }}@if($r->amended_by)<span class="block text-[10px] text-gray-400 max-w-[14rem] whitespace-normal">{{ $r->amended_by }}</span>@endif</td>
                    <td class="px-4 py-2.5 whitespace-nowrap"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badges[$r->status] ?? '' }}">{{ $statuses[$r->status] ?? $r->status }}</span>
                        @if($r->reviewed_at)<span class="block text-[10px] text-gray-400">{{ \Illuminate\Support\Carbon::parse($r->reviewed_at)->format('d M Y') }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No legal basis registered.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
