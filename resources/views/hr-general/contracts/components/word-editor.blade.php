{{--
    A Word-like page editor (ribbon, ruler, tables, find & replace, preview) — public/js/word-editor.js + public/css/word-editor.css.

    Parameters:
      $weHtml        — the HTML to start from (already sanitised)
      $weInput       — id of the hidden input that receives the HTML on submit
      $weForm        — id of the form
      $weFields      — ['employee' => [key => meaning], 'external' => [...]] for the "Fields" menu
      $weTypeSelect  — id of the contract type <select> (decides which fields are offered)
      $weLhToggle    — CSS selector of the "enable letterhead" checkbox
      $weLetterhead  — data URI of the letterhead image, or null
      $wePreviewUrl  — POST url that answers with the PDF of the unsaved text
      $wePage        — ['size' => 'A4', 'orientation' => 'portrait', 'margins' => ['top' => cm|null, 'right' => …]] (null margin = default)
      $wePageDefaults — ['plain' => [top,right,bottom,left in cm], 'letterhead' => [...]] default margins with / without letterhead
--}}
@php
    $pageSizes = \App\Support\Contracts\PageSettings::SIZES;
@endphp
@php
    $btn = 'we-btn';
    $icon = fn (string $cmd, string $fa, string $title, string $extra = '') =>
        '<button type="button" class="' . $btn . ' ' . $extra . '" data-cmd="' . $cmd . '" title="' . e($title) . '" aria-label="' . e($title) . '"><i class="fas fa-' . $fa . '"></i></button>';
@endphp
<link rel="stylesheet" href="{{ asset('css/word-editor.css') }}?v={{ @filemtime(public_path('css/word-editor.css')) }}">
<style>
    @include('hr-general.contracts.components.paper-css', ['sel' => '.we-body'])
</style>

<div class="we" data-word-editor data-form="{{ $weForm }}" data-input="{{ $weInput }}" data-type-select="{{ $weTypeSelect }}"
     data-lh-toggle="{{ $weLhToggle }}" data-preview-url="{{ $wePreviewUrl }}" @if($weLetterhead) data-letterhead="{{ $weLetterhead }}" @endif
     data-fields="{{ json_encode($weFields) }}" data-sizes="{{ json_encode($pageSizes) }}" data-page-defaults="{{ json_encode($wePageDefaults) }}">

    {{-- Paper settings travel with the form (empty margin = default). --}}
    <input type="hidden" name="page_size" value="{{ $wePage['size'] }}">
    <input type="hidden" name="page_orientation" value="{{ $wePage['orientation'] }}">
    @foreach(['top', 'right', 'bottom', 'left'] as $side)<input type="hidden" name="margin_{{ $side }}" value="{{ $wePage['margins'][$side] }}">@endforeach

    <div class="we-ribbon">
        <div class="we-row">
            {!! $icon('undo', 'rotate-left', 'Undo (Ctrl+Z)') !!}{!! $icon('redo', 'rotate-right', 'Redo (Ctrl+Y)') !!}
            <span class="we-sep"></span>
            <select data-ctl="block" data-no-enhance class="we-sel w-style" title="Paragraph style" aria-label="Paragraph style">
                <option value="p">Normal</option><option value="title">Title</option><option value="h1">Heading 1</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option>
            </select>
            <select data-ctl="font" data-no-enhance class="we-sel w-font" title="Font" aria-label="Font">
                <option value="">Font…</option>
                @foreach(['Times New Roman', 'Arial', 'Courier New', 'DejaVu Sans'] as $f)<option value="{{ $f }}" style="font-family: '{{ $f }}'">{{ $f }}</option>@endforeach
            </select>
            <input data-ctl="size" class="we-size" list="we-sizes" inputmode="decimal" title="Font size (pt)" aria-label="Font size" value="11">
            <datalist id="we-sizes">@foreach([8, 9, 10, 10.5, 11, 12, 14, 16, 18, 20, 24, 28, 36] as $s)<option value="{{ $s }}">@endforeach</datalist>
            {!! $icon('size-up', 'arrow-up-a-z', 'Increase font size (Ctrl+])') !!}{!! $icon('size-down', 'arrow-down-a-z', 'Decrease font size (Ctrl+[)') !!}
            <span class="we-sep"></span>
            {!! $icon('bold', 'bold', 'Bold (Ctrl+B)') !!}{!! $icon('italic', 'italic', 'Italic (Ctrl+I)') !!}{!! $icon('underline', 'underline', 'Underline (Ctrl+U)') !!}
            {!! $icon('strike', 'strikethrough', 'Strikethrough') !!}{!! $icon('sub', 'subscript', 'Subscript (Ctrl+=)') !!}{!! $icon('sup', 'superscript', 'Superscript (Ctrl+Shift+=)') !!}
            <span class="we-sep"></span>
            @foreach(['text' => ['Text colour', 'font', '#000000'], 'highlight' => ['Highlight colour', 'highlighter', '#ffff00']] as $kind => [$title, $fa, $dflt])
                <span class="we-pop-wrap">
                    <button type="button" class="we-btn we-colorbtn" data-apply="{{ $kind }}" title="{{ $title }}" aria-label="{{ $title }}"><i class="fas fa-{{ $fa }}"></i><span class="we-colorbar" data-bar="{{ $kind }}" style="background: {{ $dflt }}"></span></button>
                    <button type="button" class="we-btn" style="min-width:16px;padding:0 3px" data-pop-toggle="{{ $kind }}" title="{{ $title }}…" aria-label="{{ $title }} palette"><i class="fas fa-caret-down" style="font-size:9px"></i></button>
                    <div class="we-pop" data-pop="{{ $kind }}" data-palette="{{ $kind }}">
                        <div class="we-swatches"></div>
                        <div class="we-pop-title">Other</div>
                        <button type="button" class="we-pop-item" data-none="1"><i class="fas fa-ban"></i> {{ $kind === 'text' ? 'Automatic (black)' : 'No highlight' }}</button>
                        <label class="we-pop-item" style="cursor:pointer"><i class="fas fa-palette"></i> More colours… <input type="color" style="width:0;height:0;opacity:0;position:absolute"></label>
                    </div>
                </span>
            @endforeach
            {!! $icon('clear', 'eraser', 'Clear formatting (Ctrl+Space)') !!}
        </div>

        <div class="we-row">
            {!! $icon('left', 'align-left', 'Align left (Ctrl+L)') !!}{!! $icon('center', 'align-center', 'Centre (Ctrl+E)') !!}{!! $icon('right', 'align-right', 'Align right (Ctrl+R)') !!}{!! $icon('justify', 'align-justify', 'Justify (Ctrl+J)') !!}
            <select data-ctl="spacing" data-no-enhance class="we-sel" title="Line spacing" aria-label="Line spacing">
                <option value="default">Spacing: default</option><option value="1">1.0</option><option value="1.15">1.15</option><option value="1.5">1.5</option><option value="2">2.0</option><option value="2.5">2.5</option>
            </select>
            <span class="we-sep"></span>
            {!! $icon('ul', 'list-ul', 'Bullets (Ctrl+Shift+L)') !!}{!! $icon('ol', 'list-ol', 'Numbering') !!}
            <select data-ctl="liststyle" data-no-enhance class="we-sel" title="Numbering / bullet style" aria-label="List style">
                <option value="">List style…</option><option value="decimal">1. 2. 3.</option><option value="lower-alpha">a. b. c.</option><option value="upper-alpha">A. B. C.</option>
                <option value="lower-roman">i. ii. iii.</option><option value="upper-roman">I. II. III.</option><option value="disc">● Bullets</option><option value="circle">○ Circles</option><option value="square">■ Squares</option>
            </select>
            {!! $icon('outdent', 'outdent', 'Decrease indent (Ctrl+Shift+M)') !!}{!! $icon('indent', 'indent', 'Increase indent (Ctrl+M)') !!}
            <span class="we-sep"></span>
            <span class="we-pop-wrap">
                <button type="button" class="we-btn" data-pop-toggle="table" title="Table"><i class="fas fa-table"></i> Table <i class="fas fa-caret-down" style="font-size:9px"></i></button>
                <div class="we-pop" data-pop="table" style="min-width:210px">
                    <div class="we-pop-title">Insert table <span class="we-grid-label" style="float:right;text-transform:none">&nbsp;</span></div>
                    <div class="we-grid"></div>
                    <div class="we-pop-title">Edit (cursor in a cell)</div>
                    <button type="button" class="we-pop-item" data-table="row-above"><i class="fas fa-arrow-up"></i> Insert row above</button>
                    <button type="button" class="we-pop-item" data-table="row-below"><i class="fas fa-arrow-down"></i> Insert row below</button>
                    <button type="button" class="we-pop-item" data-table="col-left"><i class="fas fa-arrow-left"></i> Insert column left</button>
                    <button type="button" class="we-pop-item" data-table="col-right"><i class="fas fa-arrow-right"></i> Insert column right</button>
                    <button type="button" class="we-pop-item" data-table="merge-right"><i class="fas fa-object-group"></i> Merge with the cell on the right</button>
                    <button type="button" class="we-pop-item" data-table="borders"><i class="fas fa-border-all"></i> Borders on / off</button>
                    <button type="button" class="we-pop-item" data-table="del-row"><i class="fas fa-minus"></i> Delete row</button>
                    <button type="button" class="we-pop-item" data-table="del-col"><i class="fas fa-minus"></i> Delete column</button>
                    <button type="button" class="we-pop-item" data-table="del-table"><i class="fas fa-trash"></i> Delete table</button>
                </div>
            </span>
            {!! $icon('pagebreak', 'file-circle-plus', 'Page break (Ctrl+Enter)') !!}
            <span class="we-pop-wrap">
                <button type="button" class="we-btn we-btn-txt" data-pop-toggle="fields" title="Insert a field that is filled in from the employee / contract"><i class="fas fa-code"></i> Fields <i class="fas fa-caret-down" style="font-size:9px"></i></button>
                <div class="we-pop we-fields" data-pop="fields">
                    <input type="text" placeholder="Search a field…" aria-label="Search a field" autocomplete="off">
                    <div class="we-field-list"></div>
                </div>
            </span>
            <span class="we-sep"></span>
            <select data-ctl="papersize" data-no-enhance class="we-sel" title="Paper size" aria-label="Paper size">
                @foreach($pageSizes as $key => $s)<option value="{{ $key }}">{{ $s[2] }}</option>@endforeach
            </select>
            {!! $icon('orient', 'rotate', 'Portrait / landscape') !!}
            <select data-ctl="marginpreset" data-no-enhance class="we-sel" title="Margins" aria-label="Margins">
                <option value="default">Margins: default</option><option value="normal">Normal (2.54 cm)</option><option value="narrow">Narrow (1.27 cm)</option>
                <option value="moderate">Moderate</option><option value="wide">Wide</option><option value="custom" hidden>Custom</option>
            </select>
            <button type="button" class="we-btn we-btn-txt" data-cmd="pagesetup" title="Page setup: paper size, orientation, margins"><i class="fas fa-file-lines"></i> Page setup</button>
            {!! $icon('ruler', 'ruler-horizontal', 'Show / hide the ruler', 'on') !!}
            <span class="we-sep"></span>
            <button type="button" class="we-btn we-btn-txt" data-cmd="find" title="Find & replace (Ctrl+F / Ctrl+H)"><i class="fas fa-magnifying-glass"></i> Find</button>
            <button type="button" class="we-btn we-btn-txt" data-cmd="preview" title="Preview as PDF (Ctrl+P)"><i class="fas fa-file-pdf"></i> Preview</button>
            {!! $icon('keys', 'keyboard', 'Keyboard shortcuts (F1)') !!}{!! $icon('full', 'expand', 'Full screen') !!}
        </div>
    </div>

    <div class="we-stage">
        <div class="we-zoom">
            <div class="we-rulerbar"><div class="we-ruler" aria-label="Ruler: drag the markers to set the indents of the selected paragraphs, the grey edges to set the page margins"></div></div>
            <div class="we-page">
                <div class="we-sheets" aria-hidden="true"></div>
                <div class="we-vruler" aria-label="Vertical ruler: drag the grey edges to set the top and bottom margins"></div>
                <div class="we-body" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Contract text" spellcheck="false">{!! $weHtml !!}</div>
            </div>
        </div>

        <div class="we-find" role="dialog" aria-label="Find and replace">
            <input type="text" data-f="find" placeholder="Find" aria-label="Find" autocomplete="off">
            <input type="text" data-f="replace" placeholder="Replace with" aria-label="Replace with" autocomplete="off">
            <div><label><input type="checkbox" data-f="case"> Match case</label><label><input type="checkbox" data-f="word"> Whole word</label><span class="we-cnt"></span></div>
            <div class="we-frow">
                <button type="button" class="we-btn" data-f="prev" title="Previous (Shift+Enter)"><i class="fas fa-chevron-up"></i></button>
                <button type="button" class="we-btn" data-f="next" title="Next (Enter)"><i class="fas fa-chevron-down"></i></button>
                <button type="button" class="we-btn we-btn-txt" data-f="one">Replace</button>
                <button type="button" class="we-btn we-btn-txt" data-f="all">Replace all</button>
                <button type="button" class="we-btn" data-f="close" title="Close (Esc)" style="margin-left:auto"><i class="fas fa-xmark"></i></button>
            </div>
        </div>
    </div>

    <div class="we-status">
        <span class="we-status-info"></span>
        <span class="we-status-page" role="button" tabindex="0" title="Page setup" data-cmd="pagesetup"></span>
        <span><button type="button" class="we-btn we-btn-txt" data-cmd="fit" title="Fit the page width in the window" style="height:20px">Fit width</button> <i class="fas fa-magnifying-glass-minus"></i> <input type="range" data-ctl="zoom" min="50" max="200" step="10" value="100" aria-label="Zoom"> <i class="fas fa-magnifying-glass-plus"></i> <b class="we-zoom-label">100%</b></span>
    </div>

    <div class="we-modal" data-modal="preview">
        <div class="we-modal-box">
            <div class="we-modal-head"><span><i class="fas fa-file-pdf"></i> Preview — sample values, as it prints</span><button type="button" class="we-btn" data-modal-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
            <p class="we-prev-msg" style="padding:18px;font-size:13px;color:#6b7280"></p>
            <iframe title="Preview"></iframe>
        </div>
    </div>

    <div class="we-modal" data-modal="pagesetup">
        <div class="we-modal-box sm">
            <div class="we-modal-head"><span><i class="fas fa-file-lines"></i> Page setup</span><button type="button" class="we-btn" data-modal-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
            <div class="we-ps">
                <label>Paper size
                    <select data-ps="size" data-no-enhance class="we-sel">@foreach($pageSizes as $key => $s)<option value="{{ $key }}">{{ $s[2] }}</option>@endforeach</select></label>
                <div class="we-ps-orient">Orientation
                    <label><input type="radio" name="we-ps-orient" value="portrait"> <i class="fas fa-file"></i> Portrait</label>
                    <label><input type="radio" name="we-ps-orient" value="landscape"> <i class="fas fa-file fa-rotate-270"></i> Landscape</label></div>
                <div class="we-ps-margins">
                    <div class="we-ps-title">Margins (cm)</div>
                    @foreach(['top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right'] as $side => $label)
                        <label>{{ $label }} <input type="number" data-ps="{{ $side }}" min="0.5" max="8" step="0.01" inputmode="decimal"></label>
                    @endforeach
                </div>
                <p class="we-ps-note" data-ps="note"></p>
                <div class="we-ps-actions">
                    <button type="button" class="we-btn we-btn-txt" data-ps="reset" title="Back to the default margins (clear of the letterhead when it is on)">Default margins</button>
                    <span style="flex:1"></span>
                    <button type="button" class="we-btn we-btn-txt" data-modal-close>Cancel</button>
                    <button type="button" class="we-btn we-btn-txt we-primary" data-ps="ok">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="we-modal" data-modal="keys">
        <div class="we-modal-box sm">
            <div class="we-modal-head"><span><i class="fas fa-keyboard"></i> Keyboard shortcuts</span><button type="button" class="we-btn" data-modal-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
            <table class="we-keys">
                @foreach([
                    ['Ctrl+C / X / V', 'Copy / cut / paste (a paste from Word keeps bold, lists, tables and colours)'],
                    ['Ctrl+Z / Ctrl+Y', 'Undo / redo (Ctrl+Shift+Z also redoes)'],
                    ['Ctrl+A', 'Select all text'],
                    ['Ctrl+F / Ctrl+H', 'Find / find and replace'],
                    ['Ctrl+S', 'Save the template'],
                    ['Ctrl+P', 'Preview as PDF'],
                    ['Ctrl+B / I / U', 'Bold / italic / underline'],
                    ['Ctrl+L / E / R / J', 'Align left / centre / right / justify'],
                    ['Ctrl+Shift+L', 'Bullet list'],
                    ['Ctrl+M / Ctrl+Shift+M', 'Increase / decrease indent'],
                    ['Tab / Shift+Tab', 'In a list: indent / outdent · in a table: next / previous cell · otherwise: indent the first line'],
                    ['Ctrl+Alt+1 / 2 / 3', 'Heading 1 / 2 / 3 (Ctrl+Alt+0 = Normal)'],
                    ['Ctrl+] / Ctrl+[', 'Bigger / smaller font'],
                    ['Ctrl+= / Ctrl+Shift+=', 'Subscript / superscript'],
                    ['Ctrl+Space', 'Clear formatting'],
                    ['Ctrl+Enter', 'Page break'],
                    ['Ctrl + mouse wheel', 'Zoom'],
                    ['Esc', 'Close the open panel · leave full screen'],
                    ['F1', 'This list'],
                ] as [$k, $d])
                    <tr><td>@foreach(explode(' / ', $k) as $i => $part)@if($i) / @endif<kbd>{{ $part }}</kbd>@endforeach</td><td>{{ $d }}</td></tr>
                @endforeach
            </table>
            <p style="padding:0 14px 14px;font-size:11px;color:#6b7280">The top ruler sets the indents of the selected paragraphs: the top marker is the first line, the lower triangle the hanging indent, the small block under it moves both, the right triangle the right indent (hold Alt to move freely). Its grey ends, and the top and bottom grey of the ruler on the left, are the page margins: drag them. Numbers count in centimetres from the left margin. Paper size, orientation and margins are under <b>Page setup</b>. The page boundaries drawn here are close to the printed ones, not identical: the exact page breaks are in Preview. Fonts are limited to the four the PDF can print.</p>
        </div>
    </div>
</div>

<script src="{{ asset('js/word-editor.js') }}?v={{ @filemtime(public_path('js/word-editor.js')) }}" defer></script>
