<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<title>EcoSystem User Guide</title>
<style>
    @page { margin: 22mm 16mm 20mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.5; color: #1f2937; }
    h1, h2, h3, h4 { color: #111827; margin: 0; }
    .cover { text-align: center; padding-top: 70mm; page-break-after: always; }
    .cover .brand { font-size: 11pt; letter-spacing: 3px; text-transform: uppercase; color: #991b1b; font-weight: bold; }
    .cover h1 { font-size: 30pt; margin: 8mm 0 3mm; }
    .cover .sub { font-size: 11pt; color: #4b5563; margin: 0 20mm; }
    .cover .rule { width: 40mm; height: 3px; background: #991b1b; margin: 10mm auto; }
    .cover .meta { font-size: 9.5pt; color: #6b7280; line-height: 1.9; }
    .toc { page-break-after: always; }
    .toc h2 { font-size: 16pt; border-bottom: 2px solid #991b1b; padding-bottom: 3mm; margin-bottom: 4mm; }
    .toc .tc { font-weight: bold; font-size: 10.5pt; margin-top: 4mm; color: #111827; text-transform: uppercase; letter-spacing: .5px; }
    .toc .tt { margin: 1mm 0 0 5mm; font-size: 9.5pt; }
    .toc a { color: #374151; text-decoration: none; }
    .chapter-title { font-size: 18pt; font-weight: bold; color: #111827; border-bottom: 2px solid #991b1b; padding-bottom: 2mm; margin: 0 0 5mm; page-break-before: always; }
    .chapter-title.first { page-break-before: auto; }
    .topic { margin-bottom: 8mm; }
    .topic-title { font-size: 13.5pt; font-weight: bold; color: #7f1d1d; margin: 6mm 0 1mm; }
    .summary { color: #6b7280; font-size: 9.5pt; margin: 0 0 2mm; }
    .body h2 { font-size: 11.5pt; font-weight: bold; margin: 4mm 0 1mm; color: #111827; }
    .body h3 { font-size: 10.5pt; font-weight: bold; margin: 3mm 0 1mm; color: #374151; }
    .body p { margin: 1.5mm 0; }
    .body ul, .body ol { margin: 1.5mm 0 1.5mm 6mm; padding-left: 4mm; }
    .body li { margin: .8mm 0; }
    /* Nama menu/tombol/kolom: kotak abu-abu tebal agar menonjol saat dicetak */
    .body code { background: #f3f4f6; border: 1px solid #d1d5db; padding: 0 1.2mm; font-weight: bold; font-size: 9pt; color: #111827; font-family: DejaVu Sans, sans-serif; }
    .body strong { color: #111827; }
    .body img { max-width: 100%; border: 1px solid #d1d5db; margin: 2mm 0; page-break-inside: avoid; }
    .body a { color: #991b1b; text-decoration: none; }
    .g-table { margin: 2mm 0; }
    .body table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    .body th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 1.5mm 2mm; text-align: left; }
    .body td { border: 1px solid #e5e7eb; padding: 1.5mm 2mm; vertical-align: top; }
    .callout { border-left: 3px solid; padding: 2mm 3mm; margin: 3mm 0; page-break-inside: avoid; }
    .callout p { margin: .8mm 0; }
    .callout-title { font-weight: bold; font-size: 8pt; text-transform: uppercase; letter-spacing: .6px; }
    .callout-note { background: #eff6ff; border-color: #3b82f6; color: #1e3a8a; }
    .callout-tip { background: #ecfdf5; border-color: #10b981; color: #064e3b; }
    .callout-important { background: #fffbeb; border-color: #f59e0b; color: #78350f; }
    .callout-warning { background: #fef2f2; border-color: #ef4444; color: #7f1d1d; }
    .upd { font-size: 8pt; color: #9ca3af; margin-top: 2mm; }
</style>
</head>
<body>
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $w = $pdf->get_width(); $h = $pdf->get_height();
        $pdf->page_text(40, $h - 28, 'EcoSystem User Guide', $font, 7.5, [0.42, 0.45, 0.5]);
        $pdf->page_text($w - 120, $h - 28, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 7.5, [0.42, 0.45, 0.5]);
    }
</script>

<div class="cover">
    <div class="brand">EcoSystem</div>
    <h1>{{ $ui['title'] }}</h1>
    <p class="sub">{{ $ui['subtitle'] }}</p>
    <div class="rule"></div>
    <div class="meta">
        {{ $locales[$locale] }}<br>
        @if($updated){{ $ui['updated'] }}: {{ \Carbon\Carbon::parse($updated)->locale($locale === 'id' ? 'id' : 'en')->translatedFormat('d F Y') }}<br>@endif
        @if($who !== ''){{ $ui['cover_for'] }}: {{ $who }}<br>@endif
        {{ $ui['tailored'] }} · {{ $visibleCount }} {{ $ui['topics_of'] }}
    </div>
</div>

<div class="toc">
    <h2>{{ $ui['toc'] }}</h2>
    @foreach($chapters as $chapter)
        <div class="tc">{{ $chapter['no'] }}. {{ $chapter['title'] }}</div>
        @foreach($chapter['topics'] as $key => $topic)<div class="tt"><a href="#topic-{{ $key }}">{{ $topic['no'] }}&nbsp;&nbsp;{{ $topic['title'] }}</a></div>@endforeach
    @endforeach
</div>

@foreach($chapters as $chapter)
    <div class="chapter-title {{ $loop->first ? 'first' : '' }}" id="chapter-{{ $chapter['id'] }}">{{ $chapter['no'] }}. {{ $chapter['title'] }}</div>
    @foreach($chapter['topics'] as $key => $topic)
        <div class="topic" id="topic-{{ $key }}">
            <div class="topic-title">{{ $topic['no'] }}&nbsp;&nbsp;{{ $topic['title'] }}</div>
            @if($topic['summary'])<p class="summary">{{ $topic['summary'] }}</p>@endif
            <div class="body">{!! $topic['html'] !!}</div>
            @if($topic['updated'])<div class="upd">{{ $ui['topic_updated'] }}: {{ \Carbon\Carbon::parse($topic['updated'])->locale($locale === 'id' ? 'id' : 'en')->translatedFormat('d M Y') }}</div>@endif
        </div>
    @endforeach
@endforeach
</body>
</html>
