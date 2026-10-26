@extends('dashboard')
@section('title', 'Employee Family')
@section('page-title', 'Employee Family')
@section('content')
@include('management.employee.partials.records', [
    'title' => 'Employee Family',
    'subtitle' => 'List of all employee family members',
    'dataUrl' => route('management.employee.family.data'),
    'emptyText' => 'No family members recorded',
    'columns' => [
        ['key' => 'name', 'label' => 'Family Member', 'type' => 'text'],
        ['key' => 'relation', 'label' => 'Relationship', 'type' => 'text'],
        ['key' => 'gender', 'label' => 'Gender', 'type' => 'text'],
        ['key' => 'birth_date', 'label' => 'Birth Date', 'type' => 'date'],
        ['key' => 'occupation', 'label' => 'Occupation', 'type' => 'text'],
    ],
])
@endsection
