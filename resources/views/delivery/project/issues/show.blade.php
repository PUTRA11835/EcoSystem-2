@extends('dashboard')
@section('title', 'Project Issue Detail')
@section('page-title', 'Issue Detail')
@section('page-subtitle', 'View project issue details')

@section('content')
<div class="px-4 sm:px-6 py-4 bg-gray-50 border-b border-gray-200">
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('issues.index') }}" 
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300 text-sm sm:text-base inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back
            </a>
        </div>
    </div>
</div>


<div class="bg-white overflow-hidden shadow-md sm:rounded-lg mt-3">
    <div class="p-4 sm:p-6 border-b border-gray-200">
        <h3 class="text-lg sm:text-xl font-semibold text-gray-700">
            Issues for Project: <span class="font-normal block sm:inline mt-1 sm:mt-0">{{ $project->name }}</span>
        </h3>
        <p class="mt-1 text-xs sm:text-sm text-gray-600">Client: {{ $project->client->company_name ?? 'N/A' }}</p>
    </div>

    {{-- MOBILE VIEW: Card Layout --}}
    <div class="block lg:hidden px-4 pb-4">
        <div class="space-y-4 mt-4">
            @forelse($project->updates as $issue)
                <div class="issue-detail-card bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                    {{-- Card Header with Date --}}
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs text-gray-500">Date:</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $issue->created_at->format('d M Y') }}</p>
                            </div>
                            <div class="flex gap-2">
                                {{-- Status Badge --}}
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $issue->status === 'To Be Discussed' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($issue->status === 'To Be Confirmed' ? 'bg-blue-100 text-blue-800' :
                                       ($issue->status === 'Open' ? 'bg-green-100 text-green-800' :
                                       ($issue->status === 'Closed' ? 'bg-gray-100 text-gray-800' : 'bg-gray-100 text-gray-800'))) }}">
                                    {{ $issue->status }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Card Body --}}
                    <div class="px-4 py-3 space-y-3">
                        {{-- Issue --}}
                        <div>
                            <p class="text-xs text-gray-500 font-semibold">ISSUE:</p>
                            <p class="text-sm text-gray-900 mt-1">{{ $issue->highlight_issue }}</p>
                        </div>
                        
                        {{-- Action --}}
                        <div>
                            <p class="text-xs text-gray-500 font-semibold">ACTION:</p>
                            <p class="text-sm text-gray-900 mt-1">{{ $issue->action }}</p>
                        </div>
                        
                        {{-- Footer with Complexity and Due Date --}}
                        <div class="pt-3 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="text-xs text-gray-500">Complexity:</span>
                                    <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        {{ $issue->complexity === 'Medium' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($issue->complexity === 'Low' ? 'bg-gray-100 text-gray-800' :
                                           ($issue->complexity === 'High' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                        {{ $issue->complexity }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">Due Date:</p>
                                    <p class="text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($issue->due_date)->format('d M Y') }}</p>
                                    @if(!in_array($issue->status, ['Done', 'Closed']) && \Carbon\Carbon::parse($issue->due_date)->isPast())
                                        <span class="text-xs font-semibold text-red-600">
                                            Overdue
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white border border-gray-200 rounded-lg p-8">
                    <div class="flex flex-col items-center">
                        <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-gray-500 text-sm font-medium">This project has no issues yet.</p>
                        <p class="text-gray-400 text-xs mt-1">Issues will appear here after they are added to the project.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    {{-- DESKTOP VIEW: Table Layout --}}
    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 sticky top-0">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Issue
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Action
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Complexity
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Due Date
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($project->updates as $issue)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            {{ $issue->created_at->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <div class="max-w-xs break-words leading-5">
                                {{ $issue->highlight_issue }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <div class="max-w-xs break-words leading-5">
                                {{ $issue->action }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                {{ $issue->status === 'To Be Discussed' ? 'bg-yellow-100 text-yellow-800' : 
                                   ($issue->status === 'To Be Confirmed' ? 'bg-blue-100 text-blue-800' :
                                   ($issue->status === 'Open' ? 'bg-green-100 text-green-800' :
                                   ($issue->status === 'Closed' ? 'bg-gray-100 text-gray-800' : 'bg-gray-100 text-gray-800'))) }}">
                                {{ $issue->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                {{ $issue->complexity === 'Medium' ? 'bg-yellow-100 text-yellow-800' : 
                                   ($issue->complexity === 'Low' ? 'bg-gray-100 text-gray-800' :
                                   ($issue->complexity === 'High' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                {{ $issue->complexity }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                            <div class="flex flex-col">
                                {{ \Carbon\Carbon::parse($issue->due_date)->format('d M Y') }}
                                @if(!in_array($issue->status, ['Done', 'Closed']) && \Carbon\Carbon::parse($issue->due_date)->isPast())
                                    <span class="text-xs font-semibold text-red-600 mt-1">
                                        Overdue
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-gray-500 text-sm font-medium">Proyek ini belum memiliki data isu.</p>
                                <p class="text-gray-400 text-xs mt-1">Isu akan muncul di sini setelah ditambahkan ke proyek.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
    /* Smooth scroll behavior */
    html {
        scroll-behavior: smooth;
    }

    /* Card animation on mobile */
    @media (max-width: 1023px) {
        .issue-detail-card {
            animation: fadeIn 0.3s ease-in;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Custom scrollbar for desktop table */
    @media (min-width: 1024px) {
        .overflow-x-auto::-webkit-scrollbar {
            height: 8px;
        }
        
        .overflow-x-auto::-webkit-scrollbar-track {
            background: #f3f4f6;
            border-radius: 4px;
        }
        
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #9ca3af;
            border-radius: 4px;
        }
        
        .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
    }

    /* Mobile view specific styles */
    @media (max-width: 1023px) {
        .issue-detail-card {
            touch-action: manipulation;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Prevent double-tap zoom on mobile for buttons
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    });
</script>

@endsection