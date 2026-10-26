@extends('dashboard')
@section('title', 'Employee Attachment')
@section('page-title', 'Employee Attachment')
@section('content')
@include('management.employee.partials.records', [
    'title' => 'Employee Attachment',
    'subtitle' => 'List of all uploaded employee documents',
    'dataUrl' => route('management.employee.attachment.data'),
    'emptyText' => 'No documents uploaded',
    'columns' => [
        ['key' => 'document_type', 'label' => 'Type', 'type' => 'text'],
        ['key' => 'document_title', 'label' => 'Title', 'type' => 'text'],
        ['key' => 'file_name', 'label' => 'File Name', 'type' => 'text'],
        ['key' => 'file_size', 'label' => 'Size', 'type' => 'bytes'],
        ['key' => 'uploaded_by', 'label' => 'Uploaded By', 'type' => 'text'],
        ['key' => 'created_at', 'label' => 'Uploaded', 'type' => 'datetime'],
    ],
])
@endsection
