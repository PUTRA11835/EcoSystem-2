{{-- Group Row --}}
<tr class="group-row">
    <td class="indent-{{ $level }}">
        📁 {{ $group['name'] }}
        @if(!empty($group['sub_groups']))
            <small>({{ count($group['sub_groups']) }} sub-groups)</small>
        @endif
        @if(!empty($group['stages']))
            <small>({{ count($group['stages']) }} stages)</small>
        @endif
    </td>
    <td style="text-align: center;">{{ number_format($group['weight'], 1) }}%</td>
    <td colspan="2">{{ $group['notes'] ?? 'Group' }}</td>
    <td>{{ $group['start_date'] }}</td>
    <td>{{ $group['end_date'] }}</td>
    <td style="text-align: center;">{{ $group['duration_in_days'] ?? '-' }}</td>
    <td>
        <span class="status-badge status-{{ $group['status'] }}">
            {{ $group['status_text'] }}
        </span>
    </td>
</tr>

{{-- Sub-groups (recursive) --}}
@if(!empty($group['sub_groups']))
    @foreach($group['sub_groups'] as $subGroup)
        @include('project-planning.exports.partials.group-row', ['group' => $subGroup, 'level' => $level + 1])
    @endforeach
@endif

{{-- Stages --}}
@if(!empty($group['stages']))
    @foreach($group['stages'] as $stage)
        @include('project-planning.exports.partials.stage-row', ['stage' => $stage, 'level' => $level + 1])
    @endforeach
@endif