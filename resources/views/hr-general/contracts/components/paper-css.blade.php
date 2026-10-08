{{--
    Typography of contract text on screen — the sheet in document / preview and the template editor. The page's
    Tailwind reset removes list markers and heading sizes, which a contract needs (numbered clauses, headings).
    Parameter: $sel — the CSS selector of the element that holds the contract text.
--}}
{{ $sel }} p { margin: 0 0 8pt; }
{{ $sel }} ol { list-style: decimal; margin: 0 0 8pt 18pt; padding-left: 12pt; }
{{ $sel }} ul { list-style: disc; margin: 0 0 8pt 18pt; padding-left: 12pt; }
{{ $sel }} li { margin: 0 0 4pt; }
{{ $sel }} blockquote { margin: 0 0 8pt 18pt; padding-left: 10pt; border-left: 2px solid #bbb; }
{{ $sel }} h1 { font-size: 14pt; font-weight: bold; margin: 0 0 8pt; }
{{ $sel }} h2 { font-size: 12.5pt; font-weight: bold; margin: 0 0 8pt; }
{{ $sel }} h3 { font-size: 11.5pt; font-weight: bold; margin: 0 0 8pt; }
{{ $sel }} table { border-collapse: collapse; margin: 0 0 8pt; }
{{ $sel }} td, {{ $sel }} th { vertical-align: top; padding: 1pt 2pt; }
{{ $sel }} strong, {{ $sel }} b { font-weight: bold; }
{{ $sel }} u { text-decoration: underline; }
