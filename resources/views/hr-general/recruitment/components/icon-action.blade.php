{{--
    An icon-only action button, colour-coded by what it does — the same look
    as the action column of the Branches and Shifts tables. The label is not
    shown; it is the tooltip and the accessible name.

    Colour code:  blue = edit · gray = view · green = save / confirm ·
                  indigo = reschedule · amber = close · red = delete / cancel

    Parameters:
      $icon   — Font Awesome name without the "fa-" prefix
      $tone   — blue | gray | green | indigo | amber | red
      $label  — tooltip / accessible name
    and exactly one of:
      $href     — renders a link
      $onclick  — renders a button running this JavaScript
      $post     — renders a one-button POST form to this URL; add $confirm
                  (with optional $confirmTitle, $confirmOk) to ask first —
                  the page must then include components/confirm-forms
      $submit   — true: a submit button for the form it sits in
--}}
@php
    $tones = [
        'blue'   => 'border-blue-200 text-blue-600 hover:bg-blue-50',
        'gray'   => 'border-gray-200 text-gray-600 hover:bg-gray-50',
        'green'  => 'border-green-200 text-green-600 hover:bg-green-50',
        'indigo' => 'border-indigo-200 text-indigo-600 hover:bg-indigo-50',
        'amber'  => 'border-amber-200 text-amber-600 hover:bg-amber-50',
        'red'    => 'border-red-200 text-red-600 hover:bg-red-50',
    ];
    $class = 'w-8 h-8 inline-flex items-center justify-center rounded-lg border bg-white transition-all shrink-0 ' . $tones[$tone];
    $iconHtml = '<i class="fas fa-' . e($icon) . ' text-xs"></i>';
@endphp
@if(!empty($href))
    <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" class="{{ $class }}" @if(!empty($newTab)) target="_blank" rel="noopener" @endif>{!! $iconHtml !!}</a>
@elseif(!empty($post))
    <form action="{{ $post }}" method="POST" class="inline-flex"
        @if(!empty($confirm)) data-confirm="{{ $confirm }}" data-confirm-title="{{ $confirmTitle ?? $label }}" data-confirm-ok="{{ $confirmOk ?? $label }}" @endif>
        @csrf
        <button type="submit" title="{{ $label }}" aria-label="{{ $label }}" class="{{ $class }}">{!! $iconHtml !!}</button>
    </form>
@elseif(!empty($submit))
    <button type="submit" title="{{ $label }}" aria-label="{{ $label }}" class="{{ $class }}">{!! $iconHtml !!}</button>
@else
    <button type="button" title="{{ $label }}" aria-label="{{ $label }}" class="{{ $class }}"
        @if(!empty($data)) data-payload='@json($data)' @endif onclick="{{ $onclick }}">{!! $iconHtml !!}</button>
@endif

