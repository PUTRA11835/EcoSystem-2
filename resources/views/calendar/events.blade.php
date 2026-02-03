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
        <button onclick="openEventModal()" class="inline-flex items-center px-4 py-2.5 primary-bg hover:opacity-90 text-white font-medium rounded-lg transition-all shadow-sm hover:shadow-md">
            <i class="fas fa-plus mr-2"></i>
            Create Event
        </button>
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
            <!-- Weekday Headers -->
            <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
                <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Sun</div>
                <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Mon</div>
                <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Tue</div>
                <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Wed</div>
                <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Thu</div>
                <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Fri</div>
                <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700">Sat</div>
            </div>

            <!-- Calendar Grid -->
            <div id="monthGrid" class="grid grid-cols-7" style="min-height: 600px;"></div>
        </div>

        <!-- Week View -->
        <div id="weekView" class="view-container hidden">
            <div class="grid grid-cols-8">
                <!-- Time column header -->
                <div class="border-r border-b border-gray-200 bg-gray-50 p-2"></div>
                <!-- Day headers will be inserted here -->
                <div id="weekHeaders" class="col-span-7 grid grid-cols-7"></div>
            </div>
            <div id="weekGrid" class="grid grid-cols-8" style="max-height: 600px; overflow-y: auto;"></div>
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
                    <button onclick="editEvent()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-all">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </button>
                    <button onclick="deleteEvent()" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-lg transition-all">
                        <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Event Modal -->
<div id="eventModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
    <div class="min-h-screen px-4 py-8 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
            <div class="primary-gradient text-white px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-xl font-bold flex items-center gap-2" id="eventModalTitle">
                    <i class="fas fa-calendar-plus"></i>
                    Create New Event
                </h3>
                <button onclick="closeEventModal()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form id="eventForm" class="p-6 space-y-5">
                <input type="hidden" id="eventId">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Event Title <span class="text-red-600">*</span>
                    </label>
                    <input type="text" id="eventTitleInput" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all" placeholder="e.g., Team Meeting">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea id="eventDescriptionInput" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all resize-none" placeholder="Add event description..."></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Start Date <span class="text-red-600">*</span>
                        </label>
                        <input type="date" id="eventStartDate" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Start Time <span class="text-red-600">*</span>
                        </label>
                        <input type="time" id="eventStartTime" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                        <input type="date" id="eventEndDate" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">End Time</label>
                        <input type="time" id="eventEndTime" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Location</label>
                    <input type="text" id="eventLocationInput" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all" placeholder="e.g., Meeting Room A">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Event Type <span class="text-red-600">*</span>
                    </label>
                    <select id="eventTypeInput" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all">
                        <option value="meeting">Meeting</option>
                        <option value="task">Task</option>
                        <option value="deadline">Deadline</option>
                        <option value="urgent">Urgent</option>
                        <option value="reminder">Reminder</option>
                    </select>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="allDayEvent" class="w-5 h-5 primary-text border-gray-300 rounded focus:ring-red-800">
                    <label for="allDayEvent" class="text-sm font-semibold text-gray-700">All day event</label>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="flex-1 primary-bg hover:opacity-90 text-white font-semibold py-3 rounded-lg transition-all shadow-sm hover:shadow-md">
                        <i class="fas fa-save mr-2"></i>
                        Save Event
                    </button>
                    <button type="button" onclick="closeEventModal()" class="px-8 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 rounded-lg transition-all">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="/js/calendar-events.js"></script>
@endpush
@endsection