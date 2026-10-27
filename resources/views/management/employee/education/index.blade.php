@extends('dashboard')
@section('title', 'Employee Education')
@section('page-title', 'Employee Education')
@section('content')
@include('management.employee.partials.records', [
    'title' => 'Employee Education',
    'subtitle' => 'List of all employee education records',
    'dataUrl' => route('management.employee.education.data'),
    'emptyText' => 'No education records',
    'columns' => [
        ['key' => 'education_type', 'label' => 'Level', 'type' => 'text'],
        ['key' => 'institute_place', 'label' => 'Institution', 'type' => 'text'],
        ['key' => 'branch_of_study', 'label' => 'Major', 'type' => 'text'],
        ['key' => 'degree', 'label' => 'Degree', 'type' => 'text'],
        ['key' => 'graduation_year', 'label' => 'Graduation Year', 'type' => 'text'],
    ],
])
@endsection
