{{--
    The approval participants of a new document, from the workflow:

      · a step marked Mandatory in Settings is shown as read-only — what it
        signs as, the position and the name(s). The requester cannot change it.
      · a step that is NOT mandatory is a required dropdown: the requester
        must choose who acts, from that step's position holders / names.

    Parameters:
      $fixedSteps  — Collection of ['signs_as', 'position', 'names']
      $choiceSteps — Collection of ['step' => ReimbursementApprovalStep, 'options' => Collection<{id,label}>]
--}}
@foreach(($fixedSteps ?? collect()) as $fixed)
    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ $fixed['signs_as'] }}</label>
        <div class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-700 min-h-[2.375rem]">
            @if($fixed['names'])
                {{ implode(', ', $fixed['names']) }}
            @else
                <span class="text-gray-500">Anyone holding the position</span>
            @endif
        </div>
        <p class="text-xs text-gray-400 mt-1">
            Signs as {{ $fixed['signs_as'] }}@if($fixed['position']) · Position: {{ $fixed['position'] }}@endif
        </p>
    </div>
@endforeach

@foreach($choiceSteps as $choice)
    @php
        $step   = $choice['step'];
        $signs  = \App\Models\Reimbursement\ReimbursementApprovalStep::SIGNS_AS_LABELS[$step->signs_as] ?? $step->name;
        $field  = 'step_approver.' . $step->id;
    @endphp
    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
            {{ $signs }} <span class="text-red-600">*</span>
        </label>
        <select name="step_approver[{{ $step->id }}]" required data-searchable="true" data-search-placeholder="Search name…"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <option value="">— choose a person —</option>
            @foreach($choice['options'] as $option)
                <option value="{{ $option['id'] }}" @selected((string) old('step_approver.' . $step->id) === (string) $option['id'])>
                    {{ $option['label'] }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-400 mt-1">
            Required — signs as {{ $signs }}@if($step->role) · Position: {{ $step->role->name }}@endif
        </p>
        @error($field)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
@endforeach
