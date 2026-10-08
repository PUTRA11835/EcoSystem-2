{{--
    BPJS Health deactivation letter — page 1: the statement to BPJS Kesehatan (signature block from the layout);
    page 2: the attachment list of employees. Created by App\Http\Controllers\Finance\BpjsLetterController through
    LetterService::generate(); everything printed is in the letter's saved fields (a snapshot, so later changes to
    employee data do not alter a letter already issued):
      $f['employees'] = [['name','eci','nik','bpjs_no','reason_id','reason_en'], …] · $f['staff_name'] (optional)
--}}
@extends('hr-general.letters.pdf.layout')

@section('title', __('letters.titles.bpjs_deactivation'))

@php $rows = $f['employees'] ?? []; $count = count($rows); @endphp

@section('body')
    @if($lang === 'en')
        <p>To:<br><strong>BPJS Kesehatan</strong><br>Branch Office</p>
        <p>Dear Sir / Madam,</p>
        <p>Due to changes in the employment status of our staff, <strong>{{ $company }}</strong> hereby requests the deactivation of the BPJS Kesehatan membership of the {{ $count }} employee(s) listed in the attachment to this letter.</p>
        <p>The employment relationship of the employee(s) concerned with the company has ended or changed, so they are no longer registered under the company's membership from the date of this letter. All contributions until their last working period have been or will be settled by the company.</p>
        <p>@if(!empty($f['staff_name']))For further information please contact {{ $f['staff_name'] }}.@endif Thank you for your attention and cooperation.</p>
    @else
        <p>Kepada Yth.<br><strong>BPJS Kesehatan</strong><br>Di Tempat</p>
        <p>Dengan hormat,</p>
        <p>Sehubungan dengan perubahan status kepegawaian di perusahaan kami, <strong>{{ $company }}</strong> dengan ini mengajukan permohonan penonaktifan kepesertaan BPJS Kesehatan atas {{ $count }} (jumlah) karyawan yang tercantum pada lampiran surat ini.</p>
        <p>Hubungan kerja karyawan yang bersangkutan dengan perusahaan telah berakhir atau berubah, sehingga sejak tanggal surat ini tidak lagi terdaftar dalam kepesertaan badan usaha kami. Seluruh iuran sampai dengan masa kerja terakhir telah atau akan diselesaikan oleh perusahaan.</p>
        <p>@if(!empty($f['staff_name']))Untuk informasi lebih lanjut dapat menghubungi {{ $f['staff_name'] }}.@endif Demikian surat ini kami sampaikan, atas perhatian dan kerja samanya kami ucapkan terima kasih.</p>
    @endif
@endsection

@section('after')
    <div style="clear: both; page-break-before: always;"></div>
    <p style="text-align:left;"><strong>{{ $lang === 'en' ? 'ATTACHMENT' : 'LAMPIRAN' }}</strong><br>
        {{ $lang === 'en' ? 'Employees whose BPJS Kesehatan membership is to be deactivated' : 'Daftar karyawan yang kepesertaan BPJS Kesehatan-nya dinonaktifkan' }}<br>
        {{ $lang === 'en' ? 'Letter number' : 'Nomor surat' }}: {{ $number }}</p>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:24pt">{{ $lang === 'en' ? 'No' : 'No' }}</th>
                <th>{{ __('letters.name') }}</th>
                <th>{{ __('letters.employee_id') }}</th>
                <th>{{ $lang === 'en' ? 'ID card no. (NIK)' : 'No. KTP (NIK)' }}</th>
                <th>{{ $lang === 'en' ? 'BPJS Health no.' : 'No. BPJS Kesehatan' }}</th>
                <th>{{ $lang === 'en' ? 'Reason' : 'Alasan' }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['name'] ?? '' }}</td>
                    <td>{{ $row['eci'] ?? '' }}</td>
                    <td>{{ $row['nik'] ?? '' }}</td>
                    <td>{{ $row['bpjs_no'] ?? '' }}</td>
                    <td>{{ $lang === 'en' ? ($row['reason_en'] ?? '') : ($row['reason_id'] ?? '') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
