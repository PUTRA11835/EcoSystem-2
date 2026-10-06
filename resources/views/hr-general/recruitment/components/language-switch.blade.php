{{--
    Bahasa Indonesia | English switch for the language a letter is written in
    — radio buttons styled as one segmented control, so the choice is always
    visible instead of hidden in a dropdown.

    Parameters:
      $switchName      — input name (e.g. "language", "languages[offering_letter]")
      $switchId        — prefix for the radio ids (unique on the page)
      $switchValue     — the language code that is selected (a key of $languages)
      $languages       — [code => label], LetterTypeSetting::LANGUAGES
      $switchDisabled  — optional, true = shown but not changeable
--}}
<div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5" role="radiogroup">
    @foreach($languages as $code => $languageLabel)
        <label for="{{ $switchId }}-{{ $code }}" class="cursor-pointer">
            <input type="radio" name="{{ $switchName }}" id="{{ $switchId }}-{{ $code }}" value="{{ $code }}" class="sr-only peer"
                @checked($switchValue === $code) @disabled(!empty($switchDisabled)) required>
            <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500 transition-colors
                         hover:text-gray-800 peer-checked:bg-white peer-checked:text-indigo-700 peer-checked:shadow-sm
                         peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-200 peer-disabled:cursor-not-allowed">
                <span class="px-1 rounded bg-gray-200/70 text-[9px] font-bold tracking-wider">{{ strtoupper($code) }}</span>
                {{ $languageLabel }}
            </span>
        </label>
    @endforeach
</div>
