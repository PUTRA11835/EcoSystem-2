{{-- Tanda Terima / Goods Receipt — Bahasa Indonesia and English side by side. --}}
@extends('hr-general.letters.pdf.layout')

@section('title', __('letters.titles.goods_receipt'))

@section('closing', $lang === 'en' ? 'Received by,' : 'Yang menerima,')

@section('body')
    @php $items = $f['items'] ?? []; @endphp
    <p>
        @if($lang === 'en')
            The following items have been received from <strong>{{ $f['received_from'] ?? '' }}</strong>:
        @else
            Telah diterima dari <strong>{{ $f['received_from'] ?? '' }}</strong> barang-barang sebagai berikut:
        @endif
    </p>

    <table class="grid">
        <tr>
            <th style="width: 24pt;">No.</th>
            <th>{{ $lang === 'en' ? 'Item' : 'Nama Barang' }}</th>
            <th style="width: 70pt;">{{ $lang === 'en' ? 'Quantity' : 'Jumlah' }}</th>
            <th>{{ $lang === 'en' ? 'Notes' : 'Keterangan' }}</th>
        </tr>
        @foreach($items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item['name'] ?? '' }}</td>
                <td>{{ $item['qty'] ?? '' }}</td>
                <td>{{ $item['note'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>

    @if($employee)
        <p>{{ $lang === 'en' ? 'Employee concerned' : 'Karyawan terkait' }}: {{ $employee['name'] }}@if(!empty($employee['position'])) ({{ $employee['position'] }})@endif</p>
    @endif

    <p>
        {{ $lang === 'en'
            ? 'This receipt is made as proof that the items above were received in good condition.'
            : 'Demikian tanda terima ini dibuat sebagai bukti bahwa barang-barang di atas telah diterima dengan baik.' }}
    </p>
@endsection
