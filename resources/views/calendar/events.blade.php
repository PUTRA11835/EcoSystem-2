@extends('dashboard')
@section('title', 'Events')
@section('page-title', 'Events')
@section('page-subtitle', 'Manage your events and schedule')

@section('content')
<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Events Calendar</h2>
            <p class="text-gray-600 mt-1">View and manage your events</p>
        </div>
        @if($can('calendar.events.create'))
        <button onclick="openEventModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            Create Event
        </button>
        @endif
    </div>

    <!-- Calendar Controls & View Selector -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <!-- Navigation Controls -->
            <div class="flex items-center gap-3">
                <button onclick="goToToday()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-all">
                    Today
                </button>
                <div class="flex items-center gap-2">
                    <button onclick="previousPeriod()" class="w-9 h-9 flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-all">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button onclick="nextPeriod()" class="w-9 h-9 flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-all">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <h3 id="currentPeriod" class="text-xl font-bold text-gray-900 min-w-[200px]">January 2026</h3>
            </div>

            <!-- View Selector -->
            <div class="flex items-center gap-2 bg-gray-100 p-1 rounded-lg">
                <button id="viewMonth" onclick="changeView('month')" class="px-4 py-2 bg-white text-gray-900 font-medium rounded-md shadow-sm transition-all">
                    <i class="fas fa-calendar-alt mr-2"></i>Month
                </button>
                <button id="viewWeek" onclick="changeView('week')" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-md transition-all">
                    <i class="fas fa-calendar-week mr-2"></i>Week
                </button>
                <button id="viewDay" onclick="changeView('day')" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-md transition-all">
                    <i class="fas fa-calendar-day mr-2"></i>Day
                </button>
            </div>
        </div>
    </div>

    <!-- Calendar Container -->
    <div id="calendarContainer" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- Month View -->
        <div id="monthView" class="view-container">
            <div class="overflow-x-auto">
                <!-- Weekday Headers -->
                <div class="grid grid-cols-7 min-w-[640px] bg-gray-50 border-b border-gray-200">
                    <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Sun</div>
                    <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Mon</div>
                    <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Tue</div>
                    <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Wed</div>
                    <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Thu</div>
                    <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Fri</div>
                    <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700">Sat</div>
                </div>

                <!-- Calendar Grid -->
                <div id="monthGrid" class="grid grid-cols-7 min-w-[640px]" style="min-height: 600px;"></div>
            </div>
        </div>

        <!-- Week View -->
        <div id="weekView" class="view-container hidden">
            <div class="overflow-x-auto">
                <div class="grid grid-cols-8 min-w-[720px]">
                    <!-- Time column header -->
                    <div class="border-r border-b border-gray-200 bg-gray-50 p-2"></div>
                    <!-- Day headers will be inserted here -->
                    <div id="weekHeaders" class="col-span-7 grid grid-cols-7"></div>
                </div>
                <div id="weekGrid" class="grid grid-cols-8 min-w-[720px]" style="max-height: 600px; overflow-y: auto;"></div>
            </div>
        </div>

        <!-- Day View -->
        <div id="dayView" class="view-container hidden">
            <div class="p-4">
                <div id="dayGrid"></div>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <h4 class="text-sm font-semibold text-gray-700 mb-3">Event Types</h4>
        <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-blue-500 rounded"></div>
                <span class="text-sm text-gray-600">Meeting</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-green-500 rounded"></div>
                <span class="text-sm text-gray-600">Task</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-purple-500 rounded"></div>
                <span class="text-sm text-gray-600">Deadline</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-500 rounded"></div>
                <span class="text-sm text-gray-600">Urgent</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                <span class="text-sm text-gray-600">Reminder</span>
            </div>
            <div class="flex items-center gap-2 pl-4 border-l border-gray-200">
                <div class="w-4 h-4 bg-red-100 border border-red-300 rounded"></div>
                <span class="text-sm text-red-600 font-medium">Hari Libur Nasional</span>
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div id="eventDetailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
    <div class="min-h-screen px-4 py-8 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            <div id="detailsHeader" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-calendar-check"></i>
                    Event Details
                </h3>
                <button onclick="closeDetailsModal()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <h4 id="eventTitle" class="text-2xl font-bold text-gray-900 mb-2"></h4>
                    <p id="eventDescription" class="text-gray-600"></p>
                </div>

                <div class="space-y-3 border-t border-gray-200 pt-4">
                    <div class="flex items-center gap-3 text-gray-700">
                        <i class="fas fa-clock w-5 text-gray-500"></i>
                        <span id="eventTime"></span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-700">
                        <i class="fas fa-map-marker-alt w-5 text-gray-500"></i>
                        <span id="eventLocation"></span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-700">
                        <i class="fas fa-tag w-5 text-gray-500"></i>
                        <span id="eventType" class="px-3 py-1 rounded-full text-sm font-semibold"></span>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button onclick="editEvent()" class="flex-1 inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Edit
                    </button>
                    <button onclick="deleteEvent()" class="flex-1 inline-flex items-center justify-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Event Modal -->
<div id="eventModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh]">

        <!-- Header -->
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <h3 id="eventModalTitle" class="text-xl font-bold text-gray-900">Create New Event</h3>
            <button onclick="closeEventModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <form id="eventForm" class="overflow-y-auto flex-1 p-6">
            <input type="hidden" id="eventId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">

                <!-- Column 1: Event Details -->
                <div class="space-y-5">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Event Details</h4>
                        <hr class="border-gray-200 mt-2 mb-5">
                    </div>

                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Event Title <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="eventTitleInput" required
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all"
                            placeholder="e.g., Team Meeting">
                    </div>

                    <!-- Event Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Event Type <span class="text-red-600">*</span>
                        </label>
                        <div class="relative">
                            <select id="eventTypeInput" required
                                class="w-full px-3 py-2.5 pr-10 border border-gray-300 rounded-lg text-sm appearance-none focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                                <option value="meeting">Meeting</option>
                                <option value="task">Task</option>
                                <option value="deadline">Deadline</option>
                                <option value="urgent">Urgent</option>
                                <option value="reminder">Reminder</option>
                            </select>
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                        <textarea id="eventDescriptionInput" rows="4"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all resize-none"
                            placeholder="Add event description..."></textarea>
                    </div>

                    <!-- All Day -->
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="allDayEvent" class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                        <label for="allDayEvent" class="text-sm font-medium text-gray-700">All day event</label>
                    </div>
                </div>

                <!-- Column 2: Schedule & Location -->
                <div class="space-y-5">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Schedule &amp; Location</h4>
                        <hr class="border-gray-200 mt-2 mb-5">
                    </div>

                    <!-- Start Date + Start Time -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Start Date <span class="text-red-600">*</span>
                            </label>
                            <input type="date" id="eventStartDate" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Start Time <span class="text-red-600">*</span>
                            </label>
                            <input type="time" id="eventStartTime" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                        </div>
                    </div>

                    <!-- End Date + End Time -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">End Date</label>
                            <input type="date" id="eventEndDate"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">End Time</label>
                            <input type="time" id="eventEndTime"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                        </div>
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Location</label>
                        <input type="text" id="eventLocationInput"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all"
                            placeholder="e.g., Meeting Room A">
                    </div>
                </div>

            </div>
        </form>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 flex-shrink-0">
            <button type="button" onclick="closeEventModal()"
                class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                Cancel
            </button>
            <button type="submit" form="eventForm"
                class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Save Event
            </button>
        </div>

    </div>
</div>

@push('scripts')
<script src="/js/calendar-events.js"></script>
@endpush
@endsection