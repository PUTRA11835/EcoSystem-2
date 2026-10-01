{{-- Validation errors of the last submitted form. --}}
@if($errors->any())
    <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3" role="alert">
        <p class="font-semibold mb-1"><i class="fas fa-circle-exclamation mr-1"></i> The form could not be saved:</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
