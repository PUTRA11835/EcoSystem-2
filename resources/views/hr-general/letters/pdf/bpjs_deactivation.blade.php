{{--
    BPJS Health deactivation letter — page 1: the statement to BPJS Kesehatan (signature block from the layout);
    page 2: the attachment list of employees. Created by App\Http\Controllers\Finance\BpjsLetterController through
    LetterService::generate(); everything printed is in the letter's saved fields (a snapshot, so later changes to
    employee data do not alter a letter already issued):
      $f['employees'] = [['name','eci','nik','bpjs_no','reason_id','reason_en'], …] · $f['staff_name'] (optional)
--}}
@extends('hr-general.letters.pdf.layout')

@section('title', __('letters.titles.bpjs_deactivation'))

@php $rows = $f['employees'] ?? []; $count = count($rows); $en = $lang === 'en'; @endphp

@section('header')
    <table class="meta" style="margin-bottom:10pt;">
        <tr><td style="width:60pt">{{ $en ? 'Number' : 'Nomor' }}</td><td>: {{ $number }}</td></tr>
        <tr><td>{{ $en ? 'Attachment' : 'Lampiran' }}</td><td>: 1 {{ $en ? '(one) list of employees' : '(satu) daftar karyawan' }}</td></tr>
    </table>
    <p style="text-align:left;margin-bottom:14pt;">
        {{ $en ? 'Absolute Statement of Responsibility' : 'Surat Pernyataan Tanggung Jawab Mutlak' }}<br>
        {{ $en ? 'Reporting of Termination (PHK) by the Employer' : 'Pelaporan PHK dari Badan Usaha' }}
    </p>
    <p style="text-align:center;font-weight:bold;">{{ $en ? 'ABSOLUTE STATEMENT OF RESPONSIBILITY BY COMPANY MANAGEMENT' : 'SURAT PERNYATAAN TANGGUNG JAWAB MUTLAK PIMPINAN PERUSAHAAN' }}</p>
@endsection

@section('closing', '')

@section('body')
    <table class="details" style="margin-left:0;">
        <tr><td class="label">{{ $en ? 'Full name' : 'Nama Lengkap' }}</td><td class="colon">:</td><td>{{ $letter->signatory_name }}</td></tr>
        <tr><td class="label">{{ $en ? 'Company name' : 'Nama Perusahaan' }}</td><td class="colon">:</td><td>{{ $company }}</td></tr>
        <tr><td class="label">{{ $en ? 'Position' : 'Jabatan' }}</td><td class="colon">:</td><td>{{ $letter->signatory_title }}</td></tr>
        @if(!empty($f['contact']))<tr><td class="label">{{ $en ? 'Phone / email' : 'No. HP/Alamat email' }}</td><td class="colon">:</td><td>{{ $f['contact'] }}</td></tr>@endif
    </table>
    <p><strong>{{ $en ? 'HEREBY DECLARES:' : 'DENGAN INI MENYATAKAN :' }}</strong></p>
    @if($en)
        <p>1. That the company has terminated the employment (PHK) of a number of employees, and proposes that these employees be deactivated from National Health Insurance (JKN) membership (list attached).</p>
        <p>2. That all data, information and documents attached to this letter are correct, and their accuracy is the responsibility of the company.</p>
        <p>3. That the employees proposed for deactivation have been informed of their rights and obligations related to National Health Insurance (JKN).</p>
        <p>4. That no employee has objected to the termination of employment, which was carried out in accordance with applicable laws and regulations.</p>
        <p>5. That if the company deactivates an employee who is still in a termination dispute or is still active, the company is obliged to re-register that employee and fulfil the contributions in accordance with applicable regulations.</p>
        <p>6. That if the company has provided incorrect documents, the company is ready to accept sanctions in accordance with laws and regulations.</p>
    @else
        <p>1. Bahwa telah dilakukan Pemutusan Hubungan Kerja (PHK) terhadap sejumlah karyawan dan PHK atas sejumlah karyawan tersebut diusulkan untuk dinonaktifkan dari kepesertaan JKN (daftar nama terlampir).</p>
        <p>2. Bahwa seluruh data, informasi, dan dokumen yang dilampirkan dalam surat ini adalah benar dan kebenarannya menjadi tanggung jawab perusahaan.</p>
        <p>3. Bahwa telah dilakukan sosialisasi kepada pekerja yang diusulkan untuk dinonaktifkan terkait hak dan kewajiban yang berkaitan dengan Jaminan Kesehatan Nasional (JKN).</p>
        <p>4. Bahwa tidak terdapat penolakan pekerja atas pemutusan hubungan kerja yang telah dilakukan sesuai dengan peraturan perundang-undangan yang berlaku.</p>
        <p>5. Apabila perusahaan melakukan penonaktifan kepada pekerja yang masih dalam proses perselisihan PHK atau masih berstatus aktif, maka perusahaan wajib mendaftarkan kembali pekerja tersebut dan memenuhi kewajiban iuran sesuai peraturan yang berlaku.</p>
        <p>6. Dalam hal perusahaan memberikan dokumen yang tidak benar, perusahaan siap menerima sanksi sesuai ketentuan perundang-undangan.</p>
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
