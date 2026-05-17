@php
    $uniqueId = 'existing_' . $item->id;
    $indentClass = 'pl-' . (4 + $level * 4);
@endphp

@if($item->is_group)
    <tr class="hover:bg-gray-50 transition-colors" data-group-id="{{ $uniqueId }}">
        <td class="px-3 py-4 max-w-64 {{ $indentClass }}">
            <div class="text-sm font-medium text-gray-900">
                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium 
                    @if($level == 0) bg-purple-100 text-purple-800 @else bg-indigo-100 text-indigo-800 @endif mr-2">
                    @if($level == 0) GROUP @else SUB-GROUP @endif
                </span>
                <input type="text" name="activities[{{ $uniqueId }}][notes]" value="{{ $item->notes ?? '' }}" 
                       class="mt-1 w-auto text-sm rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500" 
                       placeholder="Group name...">
            </div>
        </td>
        @for($i = 0; $i < 5; $i++)
            <td class="px-3 py-4 whitespace-nowrap"></td>
        @endfor
        <td class="px-3 py-4">
            <div class="flex items-center">
                <div class="flex-1 max-w-32">
                    <div class="bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $item->calculateProgress() }}%"></div>
                    </div>
                </div>
                <span class="ml-2 text-sm font-medium text-gray-700">{{ $item->calculateProgress() }}%</span>
            </div>
        </td>
        <td class="px-3 py-4"></td>
        <td class="px-3 py-4 whitespace-nowrap text-center text-sm font-medium">
            <div class="flex flex-col space-y-1">
                <button type="button" onclick="addActivity({{ $phaseId }}, '{{ $uniqueId }}')" 
                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-md transition-colors">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Aktivitas
                </button>
                <button type="button" onclick="addGroup({{ $phaseId }}, '{{ $uniqueId }}')" 
                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-purple-700 bg-purple-100 hover:bg-purple-200 rounded-md transition-colors">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    Sub-Group
                </button>
                <button type="button" onclick="showDeleteConfirmation('{{ $uniqueId }}', '{{ $item->notes ?? 'Group ini' }}', true)" 
                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-md transition-colors mt-1">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete
                </button>
            </div>
        </td>
    </tr>
    <tr class="hidden">
        <td colspan="9">
            <input type="hidden" name="activities[{{ $uniqueId }}][activity_type]" value="standard">
            <input type="hidden" name="activities[{{ $uniqueId }}][is_group]" value="1">
            <input type="hidden" name="activities[{{ $uniqueId }}][parent_id]" value="{{ $item->parent_id ? 'existing_' . $item->parent_id : '' }}">
            <input type="hidden" name="activities[{{ $uniqueId }}][phase_id]" value="{{ $phaseId }}">
            <input type="hidden" name="activities[{{ $uniqueId }}][id]" value="{{ $item->id }}">
        </td>
    </tr>
    @foreach($item->children as $child)
        @include('project-planning.partials.edit-row', ['item' => $child, 'level' => $level + 1, 'phaseId' => $phaseId])
    @endforeach
@else
    <tr class="hover:bg-gray-50 transition-colors {{ $item->is_overdue ? 'bg-red-50' : '' }}" data-activity-id="{{ $uniqueId }}">
        <td class="px-3 py-4 max-w-64 {{ $indentClass }}">
            <div class="text-sm font-medium text-gray-900">
                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-2">Aktivitas</span>
                {{ $item->activity_name ?? $item->activity->name ?? $item->name }}
            </div>
            @if($item->description)
                <div class="text-xs text-gray-500 mt-1">{{ $item->description }}</div>
            @endif
        </td>
        <td class="px-3 py-4 whitespace-nowrap">
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                {{ $item->activity ? 'Standard' : 'Custom' }}
            </span>
        </td>
        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
            <input type="text" name="activities[{{ $uniqueId }}][start_date]" value="{{ $item->start_date->format('d/m/Y') }}"
                   placeholder="dd/mm/yyyy" pattern="\d{2}/\d{2}/\d{4}"
                   class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        </td>
        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
            <input type="text" name="activities[{{ $uniqueId }}][end_date]" value="{{ $item->end_date->format('d/m/Y') }}"
                   placeholder="dd/mm/yyyy" pattern="\d{2}/\d{2}/\d{4}"
                   class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @if($item->is_overdue)
                <span class="text-xs font-semibold text-red-600 mt-1 block">Overdue</span>
            @endif
        </td>
        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item->duration_in_days }} days</td>
        <td class="px-3 py-4 whitespace-nowrap">
            <select name="activities[{{ $uniqueId }}][status]" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach(['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'delayed' => 'Delayed'] as $value => $label)
                    <option value="{{ $value }}" {{ $item->status == $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </td>
        <td class="px-3 py-4">
            <div class="flex items-center">
                <input type="number" name="activities[{{ $uniqueId }}][progress_percentage]" min="0" max="100" value="{{ $item->progress_percentage }}" 
                       class="w-20 text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 mr-2">
                <span class="text-sm font-medium text-gray-700">%</span>
            </div>
        </td>
        <td class="px-3 py-4">
            <textarea name="activities[{{ $uniqueId }}][notes]" rows="1" 
                      class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      placeholder="Notes...">{{ $item->notes ?? '' }}</textarea>
        </td>
        <td class="px-3 py-4 whitespace-nowrap text-center text-sm font-medium">
            <button type="button" onclick="showDeleteConfirmation('{{ $uniqueId }}', '{{ $item->activity_name ?? $item->activity->name ?? $item->name }}', false)" 
                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-md transition-colors">
                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Hapus
            </button>
        </td>
    </tr>
    <tr class="hidden">
        <td colspan="9">
            <input type="hidden" name="activities[{{ $uniqueId }}][activity_type]" value="{{ $item->activity ? 'standard' : 'custom' }}">
            <input type="hidden" name="activities[{{ $uniqueId }}][is_group]" value="0">
            <input type="hidden" name="activities[{{ $uniqueId }}][parent_id]" value="{{ $item->parent_id ? 'existing_' . $item->parent_id : '' }}">
            <input type="hidden" name="activities[{{ $uniqueId }}][phase_id]" value="{{ $phaseId }}">
            <input type="hidden" name="activities[{{ $uniqueId }}][id]" value="{{ $item->id }}">
            @if($item->activity)
                <input type="hidden" name="activities[{{ $uniqueId }}][activity_id]" value="{{ $item->activity->id }}">
            @endif
        </td>
    </tr>
@endif