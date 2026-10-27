@extends('dashboard')
@section('title', 'Employee Bank Account')
@section('page-title', 'Employee Bank Account')
@section('content')
@include('management.employee.partials.records', [
    'title' => 'Employee Bank Account',
    'subtitle' => 'List of all employee bank accounts',
    'dataUrl' => route('management.employee.bank.data'),
    'emptyText' => 'No bank accounts recorded',
    'columns' => [
        ['key' => 'bank_name', 'label' => 'Bank', 'type' => 'text'],
        ['key' => 'account_number', 'label' => 'Account Number', 'type' => 'text'],
        ['key' => 'account_holder', 'label' => 'Account Holder', 'type' => 'text'],
        ['key' => 'valid_from', 'label' => 'Valid From', 'type' => 'date'],
    ],
])
@endsection
