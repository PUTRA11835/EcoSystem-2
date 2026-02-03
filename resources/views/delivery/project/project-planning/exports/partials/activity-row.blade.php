{{-- Activity Row --}}
<tr class="activity-row">
    <td class="indent-{{ $level }}">
        📄 {{ $activity['name'] }}
    </td>
    <td style="text-align: center;">{{ number_format($activity['weight'], 1) }}%</td>
    <td>{{ $activity['module'] ?? '-' }}</td>
    <td>{{ $activity['tcode'] ?? '-' }}</td>
    <td>{{ $activity['start_date'] }}</td>
    <td>{{ $activity['end_date'] }}</td>
    <td style="text-align: center;">{{ $activity['duration_in_days'] ?? '-' }}</td>
    <td>
        <span class="status-badge status-{{ $activity['status'] }}">
            {{ $activity['status_text'] }}
        </span>
    </td>
</tr>

{{-- Child Activities (recursive) --}}
@if(!empty($activity['children']))
    @foreach($activity['children'] as $child)
        @include('project-planning.exports.partials.activity-row', ['activity' => $child, 'level' => $level + 1])
    @endforeach
@endif