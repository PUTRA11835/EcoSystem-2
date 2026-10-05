{{-- A custom letter: the subject, recipient and body HR wrote, in the frame of every letter. --}}
@extends('hr-general.letters.pdf.layout')

@section('title', mb_strtoupper($letter->subject))

@section('body')
    <table class="meta" style="margin-bottom: 12pt;">
        <tr><td style="width: 70pt;">{{ __('letters.subject') }}</td><td style="width: 12pt;">:</td><td>{{ $letter->subject }}</td></tr>
    </table>

    @if($letter->counterparty)
        <p style="text-align: left;">
            {{ __('letters.to') }}<br>
            <strong>{{ $letter->counterparty }}</strong>
            @if(__('letters.place'))<br>{{ __('letters.place') }}@endif
        </p>
    @endif

    {{-- One paragraph per blank-line block; single line breaks are kept. --}}
    @foreach(preg_split('/\R{2,}/', trim((string) ($f['body'] ?? ''))) as $paragraph)
        @if(trim($paragraph) !== '')
            <p>{!! nl2br(e(trim($paragraph))) !!}</p>
        @endif
    @endforeach
@endsection
