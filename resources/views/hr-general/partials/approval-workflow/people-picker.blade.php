{{--
    Multi-choice name picker for one workflow step: a searchable checklist that
    opens under its summary line. Several names can be ticked and the ticked
    ones stay at the TOP of the list. None ticked = everyone holding the chosen
    position.

    Parameters: $people [{id, name}] · $ticked int[] · $fieldName (e.g. steps[k][approver_employee_ids][])
--}}
@php
    // Ticked first, then alphabetical (the list already arrives sorted by name).
    $ordered = collect($people)->sortBy(fn ($p) => in_array($p['id'], $ticked, true) ? 0 : 1)->values();
@endphp
<details class="group border border-gray-300 rounded-lg bg-white" data-pp>
    <summary class="list-none cursor-pointer px-2 py-1.5 text-sm flex items-center justify-between gap-2">
        @php $tickedNames = $ordered->filter(fn ($p) => in_array($p['id'], $ticked, true))->pluck('name')->implode(', '); @endphp
        <span data-pp-count class="truncate {{ $tickedNames === '' ? 'text-gray-400' : 'text-gray-800' }}"
              title="{{ $tickedNames }}">{{ $tickedNames !== '' ? $tickedNames : 'Everyone in the position' }}</span>
        <i class="fas fa-chevron-down text-[10px] text-gray-400 group-open:rotate-180 transition-transform"></i>
    </summary>

    <div class="border-t border-gray-100 p-2">
        <div class="relative">
            <input type="text" data-pp-search autocomplete="off" placeholder="Search name…"
                   class="w-full bg-gray-50 border border-gray-200 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-red-800">
            <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>

        <div class="mt-2 max-h-44 overflow-y-auto space-y-0.5" data-pp-list>
            @foreach($ordered as $person)
                <label class="flex items-center gap-2 px-1.5 py-1 text-xs text-gray-700 rounded hover:bg-gray-50 cursor-pointer"
                       data-pp-item data-name="{{ mb_strtolower($person['name']) }}" data-label="{{ $person['name'] }}" data-order="{{ $loop->index }}">
                    <input type="checkbox" name="{{ $fieldName }}" value="{{ $person['id'] }}"
                           @checked(in_array($person['id'], $ticked, true))
                           class="rounded border-gray-300 text-red-800 focus:ring-red-800">
                    <span class="truncate">{{ $person['name'] }}</span>
                </label>
            @endforeach
        </div>
    </div>
</details>
