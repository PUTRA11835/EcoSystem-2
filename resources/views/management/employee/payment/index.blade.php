@extends('dashboard')
@section('title', 'Employee Payment')
@section('page-title', 'Employee Payment')
@section('content')
@include('management.employee.partials.records', [
    'title' => 'Employee Payment',
    'subtitle' => 'List of all employee payment records',
    'dataUrl' => route('management.employee.payment.data'),
    'emptyText' => 'No payment records',
    'columns' => [
        ['key' => 'payment_method', 'label' => 'Method', 'type' => 'text'],
        ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
        ['key' => 'payment_status', 'label' => 'Status', 'type' => 'text'],
        ['key' => 'paid_at', 'label' => 'Paid At', 'type' => 'date'],
        ['key' => 'reference_number', 'label' => 'Reference', 'type' => 'text'],
    ],
])
@endsection
