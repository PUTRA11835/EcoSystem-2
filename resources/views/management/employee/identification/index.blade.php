@extends('dashboard')
@section('title', 'Employee Identification')
@section('page-title', 'Employee Identification')
@section('content')
@include('management.employee.partials.records', [
    'title' => 'Employee Identification',
    'subtitle' => 'List of all employee identifications',
    'dataUrl' => route('management.employee.identification.data'),
    'emptyText' => 'No identifications recorded',
    'columns' => [
        ['key' => 'identification_type', 'label' => 'ID Type', 'type' => 'text'],
        ['key' => 'identification_number', 'label' => 'ID Number', 'type' => 'text'],
        ['key' => 'valid_from', 'label' => 'Valid From', 'type' => 'date'],
        ['key' => 'valid_to', 'label' => 'Valid Until', 'type' => 'date'],
    ],
])
@endsection
