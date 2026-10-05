{{--
    An on / off switch drawn with the Font Awesome toggle icons, in place of a
    bare checkbox. The checkbox is still there (visually hidden, keyboard
    focusable), so forms, `change` listeners and `disabled` fieldsets treat it
    as the checkbox it is.

    An @include sees every variable of the page that includes it, so the
    parameters carry a `toggle` prefix: a page's own $label, $value or $title
    (a CSS class string, a @foreach leftover) must never end up here.

    Parameters:
      $toggleName     — the checkbox's name
      $toggleChecked  — whether it starts on
      $toggleValue    — submitted value when on (default 1)
      $toggleText     — text next to the switch, the same either way; without
                        it the switch says "Active" / "Inactive"
      $toggleTitle    — tooltip (optional)
--}}
@php
    $toggleValue = $toggleValue ?? 1;
    $toggleText = $toggleText ?? null;
    $toggleTitle = $toggleTitle ?? null;
@endphp
<label class="inline-flex items-center gap-1.5 cursor-pointer select-none text-[11px] font-semibold" @if($toggleTitle) title="{{ $toggleTitle }}" @endif>
    <input type="checkbox" name="{{ $toggleName }}" value="{{ $toggleValue }}" @checked($toggleChecked) class="sr-only peer">
    <i class="fas fa-toggle-on hidden peer-checked:inline-block text-xl leading-none text-green-600 rounded peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-200 peer-disabled:opacity-50" aria-hidden="true"></i>
    <i class="fas fa-toggle-off peer-checked:hidden text-xl leading-none text-gray-300 rounded peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-200 peer-disabled:opacity-50" aria-hidden="true"></i>
    @if($toggleText)
        <span class="text-sm font-normal text-gray-500 peer-checked:text-gray-800">{{ $toggleText }}</span>
    @else
        <span class="text-gray-400 peer-checked:hidden">Inactive</span>
        <span class="hidden peer-checked:inline text-green-700">Active</span>
    @endif
</label>
