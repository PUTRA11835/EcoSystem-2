@extends('dashboard')
@section('title', 'Employee Address')
@section('page-title', 'Employee Address')
@section('content')
@include('management.employee.partials.records', [
    'title' => 'Employee Address',
    'subtitle' => 'List of all employee addresses',
    'dataUrl' => route('management.employee.address.data'),
    'emptyText' => 'No addresses recorded',
    'columns' => [
        ['key' => 'address_type', 'label' => 'Type', 'type' => 'text'],
        ['key' => 'street', 'label' => 'Address', 'type' => 'text'],
        ['key' => 'city', 'label' => 'City', 'type' => 'text'],
        ['key' => 'district', 'label' => 'District', 'type' => 'text'],
        ['key' => 'region', 'label' => 'Province', 'type' => 'text'],
        ['key' => 'postal_code', 'label' => 'Postal Code', 'type' => 'text'],
        ['key' => 'is_primary', 'label' => 'Primary', 'type' => 'bool'],
    ],
])
@endsection
