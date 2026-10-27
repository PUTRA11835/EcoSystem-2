{{-- One field of a letter template (Template Designs). $i row index, $row [key,label,type,required,help,empty_id,empty_en], $cell input classes, $fieldTypes. --}}
<tr data-field-row class="align-top">
    <td class="py-1 pr-2"><input data-f="key" name="fields[{{ $i }}][key]" value="{{ $row['key'] }}" maxlength="40" class="{{ $cell }} font-mono" placeholder="purpose"></td>
    <td class="py-1 pr-2"><input data-f="label" name="fields[{{ $i }}][label]" value="{{ $row['label'] }}" maxlength="150" class="{{ $cell }}" placeholder="Purpose of the letter"></td>
    <td class="py-1 pr-2">
        <select data-f="type" name="fields[{{ $i }}][type]" data-no-enhance class="{{ $cell }}">
            @foreach($fieldTypes as $value => $name)<option value="{{ $value }}" @selected($row['type'] === $value)>{{ $name }}</option>@endforeach
        </select>
    </td>
    <td class="py-1 pr-2 text-center pt-2.5"><input data-f="required" type="checkbox" name="fields[{{ $i }}][required]" value="1" class="rounded border-gray-300" @checked($row['required'])></td>
    <td class="py-1 pr-2"><input data-f="help" name="fields[{{ $i }}][help]" value="{{ $row['help'] }}" maxlength="255" class="{{ $cell }}" placeholder="optional"></td>
    <td class="py-1 pr-2"><input data-f="empty_id" name="fields[{{ $i }}][empty_id]" value="{{ $row['empty_id'] }}" maxlength="255" class="{{ $cell }}"></td>
    <td class="py-1 pr-2"><input data-f="empty_en" name="fields[{{ $i }}][empty_en]" value="{{ $row['empty_en'] }}" maxlength="255" class="{{ $cell }}"></td>
    <td class="py-1 text-center pt-2"><button type="button" data-remove-row class="text-red-500 hover:text-red-700" title="Remove this field" aria-label="Remove this field"><i class="fas fa-trash text-xs"></i></button></td>
</tr>
