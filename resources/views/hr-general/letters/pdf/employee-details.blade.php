{{-- The employee a letter is about, as saved with it: name, employee ID, position, department (and join date). --}}
<table class="details">
    @foreach(array_filter([
        'name'        => $employee['name'] ?? null,
        'employee_id' => $employee['eci'] ?? null,
        'position'    => $employee['position'] ?? null,
        'department'  => $employee['department'] ?? null,
        'join_date'   => ($withJoinDate ?? false) && !empty($employee['join_date']) ? $date($employee['join_date']) : null,
    ]) as $key => $value)
        <tr>
            <td class="label">{{ __("letters.{$key}") }}</td>
            <td class="colon">:</td>
            <td>{{ $value }}</td>
        </tr>
    @endforeach
</table>
