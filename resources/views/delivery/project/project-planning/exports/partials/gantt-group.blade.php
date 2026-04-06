{{-- Group Row --}}
<tr class="group-row">
    <td class="indent-{{ $level }}">
        📁 {{ $group['name'] }}
    </td>
    <td>{{ $group['start'] ?? '-' }}</td>
    <td>{{ $group['end'] ?? '-' }}</td>
    <td>{{ number_format($group['progress'], 1) }}%</td>
    <td class="timeline-cell">
        @if($group['start'] && $group['end'])
            <div class="gantt-bar bar-group" style="width: {{ $group['progress'] }}%;"></div>
        @endif
    </td>
</tr>

{{-- Sub-groups --}}
@if(!empty($group['sub_groups']))
    @foreach($group['sub_groups'] as $subGroup)
        @include('project-planning.exports.partials.gantt-group', ['group' => $subGroup, 'level' => $level + 1])
    @endforeach
@endif

{{-- Stages --}}
@if(!empty($group['stages']))
    @foreach($group['stages'] as $stage)
        <tr class="stage-row">
            <td class="indent-{{ $level + 1 }}">
                ⭐ {{ $stage['name'] }}
            </td>
            <td>{{ $stage['planned_start_date'] ?? '-' }}</td>
            <td>{{ $stage['planned_end_date'] ?? '-' }}</td>
            <td>{{ number_format($stage['progress'], 1) }}%</td>
            <td class="timeline-cell">
                @if($stage['planned_start_date'] && $stage['planned_end_date'])
                    <div class="gantt-bar bar-stage" style="width: {{ $stage['progress'] }}%;"></div>
                @endif
            </td>
        </tr>
    @endforeach
@endif