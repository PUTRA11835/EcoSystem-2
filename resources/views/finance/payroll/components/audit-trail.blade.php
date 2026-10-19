{{--
    Activity log of one payroll period (payroll_audit_logs): every manual step — who did it, what, when. Insert-only; this card only reads.

    Parameters:
      $trail — Collection of rows from PayrollAudit::forPeriod() (action, summary, details[], actor_name, created_at)
--}}
@php
    $labels = \App\Services\Payroll\PayrollAudit::LABELS;
    $tones = [
        'period.locked' => 'bg-gray-800 text-white', 'period.deleted' => 'bg-red-100 text-red-700',
        'correction.added' => 'bg-green-100 text-green-700', 'correction.removed' => 'bg-red-100 text-red-700',
        'employee.switches' => 'bg-amber-100 text-amber-800',
    ];
    $on = fn ($v) => $v ? 'on' : 'off';
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-900">Activity log</h3>
        <p class="text-xs text-gray-500 mt-0.5">Every manual step on this period — who did it and when. The log cannot be edited or deleted.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                <th class="px-5 py-3 whitespace-nowrap">When</th><th class="px-3 py-3">Who</th><th class="px-3 py-3">Action</th><th class="px-3 py-3">Detail</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($trail as $row)
                    <tr class="align-top">
                        <td class="px-5 py-2.5 text-xs text-gray-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                        <td class="px-3 py-2.5 text-gray-900 whitespace-nowrap">{{ $row->actor_name }}</td>
                        <td class="px-3 py-2.5 whitespace-nowrap"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded {{ $tones[$row->action] ?? 'bg-indigo-50 text-indigo-700' }}">{{ $labels[$row->action] ?? $row->action }}</span></td>
                        <td class="px-3 py-2.5 text-gray-700">
                            {{ $row->summary }}
                            @if($row->action === 'employee.switches' && !empty($row->details['after']))
                                @php $b = $row->details['before'] ?? null; $a = $row->details['after']; @endphp
                                <p class="text-xs text-gray-500 mt-0.5">
                                    BPJS Kes {{ $b ? $on($b['bpjs_health'] ?? false) . ' → ' : '' }}{{ $on($a['bpjs_health']) }} ·
                                    BPJS TK {{ $b ? $on($b['bpjs_employment'] ?? false) . ' → ' : '' }}{{ $on($a['bpjs_employment']) }} ·
                                    PPh 21 {{ $b ? $on($b['pph21'] ?? false) . ' → ' : '' }}{{ $on($a['pph21']) }}
                                </p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No activity recorded for this period yet. From now on every step is listed here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
