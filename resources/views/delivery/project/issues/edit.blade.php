@extends('dashboard')
@section('title', 'Edit Project Issue')
@section('page-title', 'Edit Issue')
@section('page-subtitle', 'Update project issue details')

@section('content')
    <div class="px-4 sm:px-0">
        <form action="{{ route('project.updates.update', $update->id) }}" method="POST">
            @csrf
            @method('PATCH')
            <div class="bg-white overflow-hidden shadow-md rounded-lg">
                {{-- Header --}}
                <div class="p-4 sm:p-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-700">Edit Issue / Update</h3>
                    <p class="mt-1 text-xs sm:text-sm text-gray-600">
                        For Project: <span class="font-medium block sm:inline mt-1 sm:mt-0">{{ $update->project->description }}</span>
                    </p>
                </div>
                
                {{-- Form Content --}}
                <div class="p-4 sm:p-6">
                    {{-- Mobile and Desktop Grid Layout --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                        {{-- Highlight Issue --}}
                        <div class="lg:col-span-2">
                            <label for="highlight_issue" class="block font-medium text-sm text-gray-700 mb-1">
                                Highlight Issue <span class="text-red-500">*</span>
                            </label>
                            <textarea name="highlight_issue" 
                                    id="highlight_issue" 
                                    rows="3" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm sm:text-base" 
                                    required
                                    placeholder="Describe the issue...">{{ old('highlight_issue', $update->highlight_issue) }}</textarea>
                            @error('highlight_issue')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        {{-- Action --}}
                        <div class="lg:col-span-2">
                            <label for="action" class="block font-medium text-sm text-gray-700 mb-1">
                                Action <span class="text-red-500">*</span>
                            </label>
                            <textarea name="action" 
                                    id="action" 
                                    rows="3" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm sm:text-base" 
                                    required
                                    placeholder="Action to be taken...">{{ old('action', $update->action) }}</textarea>
                            @error('action')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        {{-- Mobile: Stack fields vertically --}}
                        {{-- Desktop: Side by side --}}
                        
                        {{-- Due Date --}}
                        <div class="col-span-1">
                            <label for="due_date" class="block font-medium text-sm text-gray-700 mb-1">
                                Due Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   name="due_date" 
                                   id="due_date" 
                                   value="{{ old('due_date', \Carbon\Carbon::parse($update->due_date)->format('Y-m-d')) }}" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm sm:text-base" 
                                   required>
                            @error('due_date')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        {{-- Status --}}
                        <div class="col-span-1">
                            <label for="status" class="block font-medium text-sm text-gray-700 mb-1">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select name="status" 
                                    id="status" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm primary-focus text-sm sm:text-base" 
                                    required>
                                <option value="">Select Status</option>
                                @foreach(['Open', 'In Progress', 'Done', 'Closed', 'To Be Discussed', 'To Be Confirmed'] as $status)
                                    <option value="{{ $status }}" 
                                            {{ old('status', $update->status) == $status ? 'selected' : '' }}
                                            class="{{ $status === 'Open' ? 'text-green-600' : 
                                                     ($status === 'In Progress' ? 'text-blue-600' :
                                                     ($status === 'Done' ? 'text-indigo-600' :
                                                     ($status === 'Closed' ? 'text-gray-600' :
                                                     ($status === 'To Be Discussed' ? 'text-yellow-600' :
                                                     ($status === 'To Be Confirmed' ? 'text-purple-600' : ''))))) }}">
                                        {{ $status }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        {{-- Complexity (if needed) --}}
                        @if(isset($update->complexity))
                        <div class="col-span-1 lg:col-span-2">
                            <label for="complexity" class="block font-medium text-sm text-gray-700 mb-1">
                                Complexity
                            </label>
                            <div class="mt-2 flex flex-wrap gap-4">
                                @foreach(['Low', 'Medium', 'High'] as $complexity)
                                    <label class="inline-flex items-center">
                                        <input type="radio" 
                                               name="complexity" 
                                               value="{{ $complexity }}"
                                               {{ old('complexity', $update->complexity) == $complexity ? 'checked' : '' }}
                                               class="form-radio h-4 w-4 text-indigo-600 transition duration-150 ease-in-out">
                                        <span class="ml-2 text-sm {{ $complexity === 'Low' ? 'text-gray-600' : 
                                                                    ($complexity === 'Medium' ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $complexity }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('complexity')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif
                    </div>

                    {{-- Info Box --}}
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs sm:text-sm text-blue-700">
                                    Make sure the information you entered is correct before saving the changes.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Form Actions - Sticky on Mobile --}}
                <div class="p-4 sm:p-6 bg-gray-50 border-t border-gray-200">
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <a href="{{ route('projects.show', $update->project_id) }}"
                           class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200 text-center justify-center">
                            Cancel
                        </a>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                            Update Issue
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <style>
        /* Smooth transitions */
        input, select, textarea {
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        /* Focus styles enhancement */
        .primary-focus:focus {
            outline: none;
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important;
        }

        /* Mobile specific styles */
        @media (max-width: 639px) {
            /* Sticky action buttons on mobile */
            form > div > div:last-child {
                position: sticky;
                bottom: 0;
                z-index: 10;
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            }
        }

        /* Textarea resize handle color */
        textarea {
            resize: vertical;
        }

        /* Custom radio button styles */
        input[type="radio"]:checked {
            background-color: #6366f1;
            border-color: #6366f1;
        }

        /* Animation for form elements */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .bg-white {
            animation: slideUp 0.3s ease-out;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-resize textarea
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                // Set initial height
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
                
                // Auto resize on input
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });

            // Prevent double-tap zoom on mobile
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function(event) {
                const now = Date.now();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);

            // Form validation feedback
            const form = document.querySelector('form');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            form.addEventListener('submit', function(e) {
                // Add loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <span class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Menyimpan...
                    </span>
                `;
            });

            // Add asterisk animation
            const requiredFields = document.querySelectorAll('span.text-red-500');
            requiredFields.forEach(field => {
                field.style.animation = 'pulse 2s infinite';
            });
        });
    </script>

    <style>
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
@endsection