@extends('dashboard')

@section('title', 'Import Reimbursement')
@section('page-title', 'Import Reimbursement')
@section('page-subtitle', 'Create several reimbursement documents from one Excel file')

@section('page-actions')
    <a href="{{ route('general.reimbursement.index') }}"
       class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
        Back to List
    </a>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

    {{-- Sifat "gagal utuh" disampaikan sebelum tombolnya, bukan sesudah.
         Orang yang mengunggah 200 baris berhak tahu apa yang terjadi bila satu
         di antaranya salah. --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-4">
        <p class="text-sm text-blue-900">
            <span class="font-semibold">All or nothing.</span>
            The whole file is imported inside one transaction. If a single row is invalid, nothing is saved
            and the problems are listed back to you — you never end up with half the file imported.
        </p>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm space-y-5">
        <div class="pb-4 border-b-2 border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900">Upload File</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Rows sharing the same <strong>document</strong> value become items of one document.
            </p>
        </div>

        <form method="POST" action="{{ route('general.reimbursement.import.store') }}"
              enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                    Excel file <span class="text-red-600">*</span>
                </label>
                <input type="file" name="file" required accept=".xlsx,.xls,.csv"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-gray-400 mt-1">.xlsx, .xls, or .csv — up to 4 MB.</p>
                @error('file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                    <i class="fas fa-file-import"></i> Import
                </button>

                <a href="{{ route('general.reimbursement.import.template') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-green-700 text-sm font-semibold rounded-lg border border-green-300 hover:bg-green-50 transition-all">
                    <i class="fas fa-file-excel"></i> Download Template
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 mb-1">Expected columns</h2>
        <p class="text-sm text-gray-500 mb-4">
            The first row must contain these headings, spelled exactly like this. Order does not matter.
        </p>

        <div class="border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3">Column</th>
                        <th class="px-4 py-3">Meaning</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $meaning = [
                            'document'          => 'Any label that groups rows into one document, e.g. RB-1.',
                            'employee_eci'      => 'Employee number. Must be an active employee.',
                            'request_date'      => 'Document date.',
                            'title'             => 'Reimbursement title — one line for the whole document.',
                            'supporting_url'    => 'Link to the supporting document.'
                                                   . ($settings->require_supporting_url ? ' Required by the current settings.' : ''),
                            'item_description'  => 'Description of this cost line.',
                            'branch_code'       => 'Branch code the cost is charged to, e.g. EC-JOGJA.',
                            'receipt_no'        => 'Receipt number.'
                                                   . ($settings->require_receipt_no ? ' Required by the current settings.' : ''),
                            'receipt_date_from' => 'Receipt date.',
                            'receipt_date_to'   => 'End date for a multi-day receipt. Leave empty for a single day.',
                            'amount'            => 'Amount in IDR, digits only.',
                        ];
                    @endphp
                    @foreach($columns as $column)
                    <tr>
                        <td class="px-4 py-2.5 font-mono text-xs text-red-800 font-semibold whitespace-nowrap">{{ $column }}</td>
                        <td class="px-4 py-2.5 text-gray-700">{{ $meaning[$column] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-400 mt-3">
            Imported documents go through the same rules and the same approval workflow as documents
            submitted on screen — nothing is skipped.
        </p>
    </div>
</div>
@endsection
