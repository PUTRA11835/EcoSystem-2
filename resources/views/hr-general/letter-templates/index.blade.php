@extends('dashboard')
@section('title', 'Letter Templates')
@section('page-title', 'Letter Templates')
@section('page-subtitle', 'Letterheads — a header and a footer image — and the letters each one is printed on.')

@php
    // Capabilities of this page, as ticked in Management → Roles.
    $canCreate = $canDo('general.letter-templates', 'create');
    $canEdit   = $canDo('general.letter-templates', 'edit');
    $canDelete = $canDo('general.letter-templates', 'delete');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-gray-50 disabled:text-gray-500';
    $file = 'block w-full text-xs text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border file:border-gray-200 file:bg-white file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-50';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $parts = ['header' => 'Header', 'footer' => 'Footer'];
    $imageHelp = 'JPG or PNG, up to 2 MB. Printed across the full width of an A4 page, so use an image as wide as the page (about 2480 px for a sharp print).';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('partials.hub-tabs', ['hubTabs' => [[
        'label' => 'Settings', 'icon' => 'sliders', 'route' => 'general.letter-templates.index',
        'is' => 'general/letter-templates*', 'gate' => 'general.letter-templates', 'strict' => true,
    ]]])
    @include('hr-general.recruitment.components.form-errors')

    @if($canCreate)
        <form action="{{ route('general.letter-templates.store') }}" method="POST" enctype="multipart/form-data"
            class="bg-white rounded-xl border border-gray-200 shadow-sm">
            @csrf
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800">Add Letterhead</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">{{ $imageHelp }}</p>
            </div>
            <div class="px-5 py-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div>
                    <label for="newName" class="{{ $label }}">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="newName" required maxlength="100" value="{{ old('_letterhead') === 'new' ? old('name') : '' }}"
                        placeholder="e.g. Eclectic Consulting 2026" class="{{ $input }}">
                </div>
                @foreach($parts as $part => $partLabel)
                    <div>
                        <label for="new{{ $partLabel }}" class="{{ $label }}">{{ $partLabel }} Image</label>
                        <input type="file" name="{{ $part }}" id="new{{ $partLabel }}" accept=".jpg,.jpeg,.png" class="{{ $file }}">
                    </div>
                @endforeach
                <div class="lg:col-span-3">
                    <span class="{{ $label }}">Use this letterhead for</span>
                    <div class="flex flex-wrap gap-x-5 gap-y-1.5">
                        @foreach($letterTypes as $type => $typeLabel)
                            <label class="flex items-center gap-1.5 text-sm text-gray-700">
                                <input type="checkbox" name="letter_types[]" value="{{ $type }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                {{ $typeLabel }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">A letter is printed on one letterhead: ticking it here takes it off the letterhead that had it.</p>
                </div>
            </div>
            <input type="hidden" name="_letterhead" value="new">
            <div class="px-5 py-4 border-t border-gray-100 flex justify-end">
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">
                    <i class="fas fa-plus text-xs"></i> Add Letterhead
                </button>
            </div>
        </form>
    @endif

    @forelse($letterheads as $letterhead)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <form action="{{ route('general.letter-templates.update', $letterhead) }}" method="POST" enctype="multipart/form-data" id="letterhead{{ $letterhead->id }}">
                @csrf
                <input type="hidden" name="_letterhead" value="{{ $letterhead->id }}">
                <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-bold text-gray-800">{{ $letterhead->name }}</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse($letterhead->letter_types ?? [] as $type)
                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-bold">{{ $letterTypes[$type] ?? $type }}</span>
                        @empty
                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-bold">Not used by any letter</span>
                        @endforelse
                    </div>
                </div>

                <fieldset @disabled(!$canEdit) class="px-5 py-4 space-y-4">
                    <div class="sm:w-1/2 lg:w-1/3">
                        <label for="name{{ $letterhead->id }}" class="{{ $label }}">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name{{ $letterhead->id }}" required maxlength="100"
                            value="{{ old('_letterhead') == $letterhead->id ? old('name') : $letterhead->name }}" class="{{ $input }}">
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @foreach($parts as $part => $partLabel)
                            <div>
                                <span class="{{ $label }}">{{ $partLabel }} Image</span>
                                @if($letterhead->pathOf($part))
                                    <div class="border border-gray-200 rounded-lg bg-gray-50 p-2 mb-2">
                                        <img src="{{ route('general.letter-templates.image', [$letterhead, $part]) }}?v={{ $letterhead->updated_at?->timestamp }}"
                                            alt="{{ $partLabel }} of {{ $letterhead->name }}" class="w-full h-auto bg-white">
                                    </div>
                                @else
                                    <p class="border border-dashed border-gray-200 rounded-lg px-3 py-4 mb-2 text-center text-[11px] text-gray-400">No {{ strtolower($partLabel) }} image.</p>
                                @endif
                                @if($canEdit)
                                    <input type="file" name="{{ $part }}" accept=".jpg,.jpeg,.png" aria-label="Replace the {{ strtolower($partLabel) }} image" class="{{ $file }}">
                                    @if($letterhead->pathOf($part))
                                        <label class="flex items-center gap-1.5 text-[11px] text-gray-600 mt-1.5">
                                            <input type="checkbox" name="remove_{{ $part }}" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                            Remove the {{ strtolower($partLabel) }} image
                                        </label>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <span class="{{ $label }}">Use this letterhead for</span>
                        <div class="flex flex-wrap gap-x-5 gap-y-1.5">
                            @foreach($letterTypes as $type => $typeLabel)
                                <label class="flex items-center gap-1.5 text-sm text-gray-700">
                                    <input type="checkbox" name="letter_types[]" value="{{ $type }}" @checked($letterhead->usedFor($type))
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    {{ $typeLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </fieldset>
            </form>

            @if($canEdit || $canDelete)
                <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-end gap-2">
                    @if($canDelete)
                        @include('hr-general.recruitment.components.icon-action', [
                            'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete letterhead',
                            'post' => route('general.letter-templates.destroy', $letterhead),
                            'confirm' => 'Delete the letterhead "' . $letterhead->name . '"? Letters that use it are printed on plain paper afterwards.',
                            'confirmTitle' => 'Delete Letterhead', 'confirmOk' => 'Delete',
                        ])
                    @endif
                    @if($canEdit)
                        <button type="submit" form="letterhead{{ $letterhead->id }}" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save</button>
                    @endif
                </div>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center text-gray-400 text-sm">
            No letterhead yet. Letters are printed on plain paper until one is added and ticked for them.
        </div>
    @endforelse
</div>

@include('hr-general.recruitment.components.confirm-forms')
@endsection
