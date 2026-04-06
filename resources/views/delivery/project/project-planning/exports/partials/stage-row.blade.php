{{-- Stage Row --}}
<tr class="stage-row">
    <td class="indent-{{ $level }}">
        ⭐ {{ $stage['name'] }}
        @if(!empty($stage['activities']))
            <small>({{ count($stage['activities']) }} activities)</small>
        @endif
    </td>
    <td style="text-align: center;">{{ number_format($stage['weight'], 1) }}%</td>
    <td colspan="2">{{ $stage['description'] ?? 'Stage' }}</td>
    <td>{{ $stage['start_date'] }}</td>
    <td>{{ $stage['end_date'] }}</td>
    <td style="text-align: center;">{{ $stage['duration_in_days'] ?? '-' }}</td>
    <td>
        <span class="status-badge status-{{ $stage['status'] }}">
            {{ $stage['status_text'] }}
        </span>
    </td>
</tr>

{{-- Activities --}}
@if(!empty($stage['activities']))
    @foreach($stage['activities'] as $activity)
        @include('project-planning.exports.partials.activity-row', ['activity' => $activity, 'level' => $level + 1])
    @endforeach
@endif