{{--
    The fields of every template, each group hidden unless it is the active one (the page script shows the chosen one and
    enables only its inputs). Used by the template form and by the custom letter's "Start from" template.
    Needs: $templates, $fields (what the form starts with), $activeKey, and the page's $input / $label / $help classes.
--}}
{{-- The fields of each template; only the chosen template's are enabled and sent. --}}
@foreach($templates as $key => $template)
    <div data-template-fields="{{ $key }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ $activeKey === $key ? '' : 'hidden' }}">
        @foreach($template['fields'] as $name => $field)
            @php $fieldId = "f-{$key}-{$name}"; $current = $activeKey === $key ? ($fields[$name] ?? null) : null; @endphp
            <div class="{{ in_array($field['type'], ['textarea', 'items'], true) ? 'md:col-span-2' : '' }}">
                <label for="{{ $fieldId }}" class="{{ $label }}">{{ $field['label'] }} @if($field['required'])<span class="text-red-500">*</span>@endif</label>
                @switch($field['type'])
                    @case('textarea')
                        <textarea name="fields[{{ $name }}]" id="{{ $fieldId }}" rows="3" maxlength="5000" @required($field['required']) class="{{ $input }}">{{ $current }}</textarea>
                        @break
                    @case('date')
                        <input type="date" name="fields[{{ $name }}]" id="{{ $fieldId }}" value="{{ $current }}" @required($field['required']) class="{{ $input }}">
                        @break
                    @case('money')
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                            <input type="text" inputmode="numeric" name="fields[{{ $name }}]" id="{{ $fieldId }}" data-money
                                value="{{ $current !== null && $current !== '' ? number_format((float) preg_replace('/\D/', '', (string) $current), 0, ',', '.') : '' }}"
                                @required($field['required']) class="{{ $input }} pl-9 text-right">
                        </div>
                        @break
                    @case('lines')
                        <div data-items="{{ $key }}" class="space-y-2">
                            @foreach(($current ?: [['name' => '', 'amount' => '']]) as $i => $line)
                                @php $amountText = ($line['amount'] ?? '') !== '' ? number_format((float) preg_replace('/\D/', '', (string) $line['amount']), 0, ',', '.') : ''; @endphp
                                <div data-item-row class="grid grid-cols-12 gap-2">
                                    <input type="text" name="fields[{{ $name }}][{{ $i }}][name]" value="{{ $line['name'] ?? '' }}" placeholder="Component, e.g. Base salary" maxlength="255" list="offerComponentNames" class="{{ $input }} col-span-7">
                                    <div class="relative col-span-4">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                                        <input type="text" inputmode="numeric" data-money name="fields[{{ $name }}][{{ $i }}][amount]" value="{{ $amountText }}" placeholder="0" class="{{ $input }} pl-9 text-right">
                                    </div>
                                    <button type="button" data-item-remove class="col-span-1 text-red-500 hover:text-red-700" title="Remove this line" aria-label="Remove this line"><i class="fas fa-trash text-xs"></i></button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" data-item-add="{{ $key }}" data-item-name="{{ $name }}" class="mt-2 text-xs font-semibold primary-text">
                            <i class="fas fa-plus text-[10px]"></i> Add a line
                        </button>
                        @once
                            {{-- The compensation components of Offering Letter → Settings, offered as names to pick from. --}}
                            <datalist id="offerComponentNames">@foreach($offerComponentNames ?? [] as $componentName)<option value="{{ $componentName }}">@endforeach</datalist>
                        @endonce
                        @break
                    @case('items')
                        <div data-items="{{ $key }}" class="space-y-2">
                            @foreach(($current ?: [['name' => '', 'qty' => '', 'note' => '']]) as $i => $item)
                                <div data-item-row class="grid grid-cols-12 gap-2">
                                    <input type="text" name="fields[{{ $name }}][{{ $i }}][name]" value="{{ $item['name'] ?? '' }}" placeholder="Item" maxlength="255" class="{{ $input }} col-span-5">
                                    <input type="text" name="fields[{{ $name }}][{{ $i }}][qty]" value="{{ $item['qty'] ?? '' }}" placeholder="Qty" maxlength="50" class="{{ $input }} col-span-2">
                                    <input type="text" name="fields[{{ $name }}][{{ $i }}][note]" value="{{ $item['note'] ?? '' }}" placeholder="Notes" maxlength="255" class="{{ $input }} col-span-4">
                                    <button type="button" data-item-remove class="col-span-1 text-red-500 hover:text-red-700" title="Remove this item" aria-label="Remove this item"><i class="fas fa-trash text-xs"></i></button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" data-item-add="{{ $key }}" data-item-name="{{ $name }}" class="mt-2 text-xs font-semibold primary-text">
                            <i class="fas fa-plus text-[10px]"></i> Add an item
                        </button>
                        @break
                    @default
                        <input type="text" name="fields[{{ $name }}]" id="{{ $fieldId }}" value="{{ $current }}" maxlength="255"
                            placeholder="{{ $field['placeholder'] ?? '' }}" @required($field['required']) class="{{ $input }}">
                @endswitch
                @if(!empty($field['help']))<p class="{{ $help }}">{{ $field['help'] }}</p>@endif
            </div>
        @endforeach
    </div>
@endforeach
