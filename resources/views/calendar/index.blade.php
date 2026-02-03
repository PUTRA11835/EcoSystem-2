@extends('dashboard')
@section('title', 'Calendar')
@section('content')
<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Calendar</h2>
            <p class="text-gray-600 mt-1">Manage your schedule and events</p>
        </div>
        <button onclick="openEventModal()" class="inline-flex items-center px-4 py-2.5 bg-red-800 hover:bg-red-900 text-white font-medium rounded-lg transition-all shadow-sm hover:shadow-md">
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
                    <button onclick="previousMonth()" class="w-9 h-9 flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-all">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button onclick="nextMonth()" class="w-9 h-9 flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-all">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <h3 id="currentMonth" class="text-xl font-bold text-gray-900 min-w-[200px]">January 2024</h3>
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

    <!-- Calendar Grid -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- Weekday Headers -->
        <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
            <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Sunday</div>
            <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Monday</div>
            <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Tuesday</div>
            <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Wednesday</div>
            <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Thursday</div>
            <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700 border-r border-gray-200">Friday</div>
            <div class="py-3 px-2 text-center text-sm font-semibold text-gray-700">Saturday</div>
        </div>

        <!-- Calendar Days -->
        <div id="calendarGrid" class="grid grid-cols-7" style="min-height: 600px;">
            <!-- Days will be generated here -->
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
            <!-- Modal Header -->
            <div id="detailsHeader" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-calendar-check"></i>
                    Event Details
                </h3>
                <button onclick="closeDetailsModal()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
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
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-red-800 to-red-900 text-white px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-xl font-bold flex items-center gap-2" id="eventModalTitle">
                    <i class="fas fa-calendar-plus"></i>
                    Create New Event
                </h3>
                <button onclick="closeEventModal()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="eventForm" class="p-6 space-y-5">
                <input type="hidden" id="eventId">
                
                <!-- Event Title -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Event Title <span class="text-red-600">*</span>
                    </label>
                    <input type="text" id="eventTitleInput" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all" placeholder="e.g., Team Meeting">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea id="eventDescriptionInput" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all resize-none" placeholder="Add event description..."></textarea>
                </div>

                <!-- Date & Time -->
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

                <!-- Location -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Location</label>
                    <input type="text" id="eventLocationInput" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all" placeholder="e.g., Meeting Room A">
                </div>

                <!-- Event Type -->
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

                <!-- All Day Event -->
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="allDayEvent" class="w-5 h-5 text-red-800 border-gray-300 rounded focus:ring-red-800">
                    <label for="allDayEvent" class="text-sm font-semibold text-gray-700">All day event</label>
                </div>

                <!-- Form Actions -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="flex-1 bg-red-800 hover:bg-red-900 text-white font-semibold py-3 rounded-lg transition-all shadow-sm hover:shadow-md">
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
<script>
    let currentDate = new Date();
    let currentView = 'month';
    let selectedEventId = null;

    // Dummy events data
    let events = [
        {
            id: 1,
            title: 'Team Meeting',
            description: 'Weekly team sync-up meeting',
            startDate: '2024-11-15',
            startTime: '09:00',
            endDate: '2024-11-15',
            endTime: '10:00',
            location: 'Meeting Room A',
            type: 'meeting',
            allDay: false
        },
        {
            id: 2,
            title: 'Project Deadline',
            description: 'Submit final project proposal',
            startDate: '2024-11-20',
            startTime: '17:00',
            endDate: '2024-11-20',
            endTime: '17:00',
            location: 'Office',
            type: 'deadline',
            allDay: false
        },
        {
            id: 3,
            title: 'Client Presentation',
            description: 'Present Q4 results to client',
            startDate: '2024-11-18',
            startTime: '14:00',
            endDate: '2024-11-18',
            endTime: '16:00',
            location: 'Client Office',
            type: 'urgent',
            allDay: false
        },
        {
            id: 4,
            title: 'Code Review',
            description: 'Review new feature implementation',
            startDate: '2024-11-12',
            startTime: '11:00',
            endDate: '2024-11-12',
            endTime: '12:00',
            location: 'Online',
            type: 'task',
            allDay: false
        },
        {
            id: 5,
            title: 'Birthday Reminder',
            description: "John's birthday celebration",
            startDate: '2024-11-25',
            startTime: '00:00',
            endDate: '2024-11-25',
            endTime: '23:59',
            location: 'Office Cafeteria',
            type: 'reminder',
            allDay: true
        }
    ];

    const eventTypeColors = {
        meeting: { bg: 'bg-blue-500', text: 'text-blue-700', bgLight: 'bg-blue-50', border: 'border-blue-200' },
        task: { bg: 'bg-green-500', text: 'text-green-700', bgLight: 'bg-green-50', border: 'border-green-200' },
        deadline: { bg: 'bg-purple-500', text: 'text-purple-700', bgLight: 'bg-purple-50', border: 'border-purple-200' },
        urgent: { bg: 'bg-red-500', text: 'text-red-700', bgLight: 'bg-red-50', border: 'border-red-200' },
        reminder: { bg: 'bg-yellow-500', text: 'text-yellow-700', bgLight: 'bg-yellow-50', border: 'border-yellow-200' }
    };

    document.addEventListener('DOMContentLoaded', function() {
        renderCalendar();
        
        // All day checkbox handler
        document.getElementById('allDayEvent').addEventListener('change', function() {
            const startTime = document.getElementById('eventStartTime');
            const endTime = document.getElementById('eventEndTime');
            if (this.checked) {
                startTime.disabled = true;
                endTime.disabled = true;
                startTime.value = '00:00';
                endTime.value = '23:59';
            } else {
                startTime.disabled = false;
                endTime.disabled = false;
            }
        });
    });

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        // Update header
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        document.getElementById('currentMonth').textContent = `${monthNames[month]} ${year}`;
        
        // Get first day of month and number of days
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        
        const calendarGrid = document.getElementById('calendarGrid');
        calendarGrid.innerHTML = '';
        
        let dayCount = 1;
        let nextMonthDay = 1;
        
        // Total cells needed (6 rows × 7 days)
        const totalCells = 42;
        
        for (let i = 0; i < totalCells; i++) {
            const dayCell = document.createElement('div');
            dayCell.className = 'border-r border-b border-gray-200 p-2 min-h-[100px] hover:bg-gray-50 transition-all cursor-pointer';
            
            let dateStr = '';
            let isCurrentMonth = false;
            
            if (i < firstDay) {
                // Previous month days
                const prevDay = daysInPrevMonth - firstDay + i + 1;
                dayCell.classList.add('bg-gray-50');
                dayCell.innerHTML = `<div class="text-sm font-semibold text-gray-400 mb-1">${prevDay}</div>`;
            } else if (dayCount <= daysInMonth) {
                // Current month days
                isCurrentMonth = true;
                dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(dayCount).padStart(2, '0')}`;
                
                const isToday = new Date().toDateString() === new Date(year, month, dayCount).toDateString();
                const dayHeader = document.createElement('div');
                dayHeader.className = `text-sm font-semibold mb-1 ${isToday ? 'text-red-800' : 'text-gray-700'}`;
                
                if (isToday) {
                    dayHeader.innerHTML = `<span class="inline-flex items-center justify-center w-7 h-7 bg-red-800 text-white rounded-full">${dayCount}</span>`;
                } else {
                    dayHeader.textContent = dayCount;
                }
                
                dayCell.appendChild(dayHeader);
                
                // Add events for this day
                const dayEvents = events.filter(e => e.startDate === dateStr);
                const eventsContainer = document.createElement('div');
                eventsContainer.className = 'space-y-1';
                
                dayEvents.forEach(event => {
                    const eventEl = document.createElement('div');
                    const colors = eventTypeColors[event.type];
                    eventEl.className = `${colors.bg} text-white text-xs px-2 py-1 rounded truncate hover:shadow-md transition-all`;
                    eventEl.textContent = event.title;
                    eventEl.onclick = (e) => {
                        e.stopPropagation();
                        showEventDetails(event.id);
                    };
                    eventsContainer.appendChild(eventEl);
                });
                
                dayCell.appendChild(eventsContainer);
                dayCell.onclick = () => openEventModal(dateStr);
                
                dayCount++;
            } else {
                // Next month days
                dayCell.classList.add('bg-gray-50');
                dayCell.innerHTML = `<div class="text-sm font-semibold text-gray-400 mb-1">${nextMonthDay}</div>`;
                nextMonthDay++;
            }
            
            // Remove right border for last column
            if ((i + 1) % 7 === 0) {
                dayCell.classList.remove('border-r');
            }
            
            calendarGrid.appendChild(dayCell);
        }
    }

    function previousMonth() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    }

    function nextMonth() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    }

    function goToToday() {
        currentDate = new Date();
        renderCalendar();
    }

    function changeView(view) {
        currentView = view;
        
        // Update button styles
        ['viewMonth', 'viewWeek', 'viewDay'].forEach(id => {
            const btn = document.getElementById(id);
            if (id === 'view' + view.charAt(0).toUpperCase() + view.slice(1)) {
                btn.className = 'px-4 py-2 bg-white text-gray-900 font-medium rounded-md shadow-sm transition-all';
            } else {
                btn.className = 'px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-md transition-all';
            }
        });
        
        // For now, just show alert. You can implement week/day views later
        if (view !== 'month') {
            alert(`${view.charAt(0).toUpperCase() + view.slice(1)} view coming soon!`);
        }
    }

    function openEventModal(date = null) {
        document.getElementById('eventModalTitle').innerHTML = '<i class="fas fa-calendar-plus"></i> Create New Event';
        document.getElementById('eventForm').reset();
        document.getElementById('eventId').value = '';
        
        if (date) {
            document.getElementById('eventStartDate').value = date;
            document.getElementById('eventEndDate').value = date;
        }
        
        document.getElementById('eventModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeEventModal() {
        document.getElementById('eventModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function showEventDetails(eventId) {
        const event = events.find(e => e.id === eventId);
        if (!event) return;
        
        selectedEventId = eventId;
        const colors = eventTypeColors[event.type];
        
        document.getElementById('eventTitle').textContent = event.title;
        document.getElementById('eventDescription').textContent = event.description || 'No description';
        document.getElementById('eventTime').textContent = event.allDay 
            ? 'All day event' 
            : `${event.startTime} - ${event.endTime}`;
        document.getElementById('eventLocation').textContent = event.location || 'No location';
        
        const typeEl = document.getElementById('eventType');
        typeEl.textContent = event.type.charAt(0).toUpperCase() + event.type.slice(1);
        typeEl.className = `px-3 py-1 rounded-full text-sm font-semibold ${colors.bgLight} ${colors.text}`;
        
        const header = document.getElementById('detailsHeader');
        header.className = `text-white px-6 py-4 rounded-t-2xl flex items-center justify-between ${colors.bg.replace('500', '600')}`;
        
        document.getElementById('eventDetailsModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeDetailsModal() {
        document.getElementById('eventDetailsModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function editEvent() {
        const event = events.find(e => e.id === selectedEventId);
        if (!event) return;
        
        closeDetailsModal();
        
        document.getElementById('eventModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Event';
        document.getElementById('eventId').value = event.id;
        document.getElementById('eventTitleInput').value = event.title;
        document.getElementById('eventDescriptionInput').value = event.description;
        document.getElementById('eventStartDate').value = event.startDate;
        document.getElementById('eventStartTime').value = event.startTime;
        document.getElementById('eventEndDate').value = event.endDate;
        document.getElementById('eventEndTime').value = event.endTime;
        document.getElementById('eventLocationInput').value = event.location;
        document.getElementById('eventTypeInput').value = event.type;
        document.getElementById('allDayEvent').checked = event.allDay;
        
        if (event.allDay) {
            document.getElementById('eventStartTime').disabled = true;
            document.getElementById('eventEndTime').disabled = true;
        }
        
        document.getElementById('eventModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function deleteEvent() {
        if (!confirm('Are you sure you want to delete this event?')) return;
        
        events = events.filter(e => e.id !== selectedEventId);
        closeDetailsModal();
        renderCalendar();
        alert('✓ Event deleted successfully!');
    }

    document.getElementById('eventForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const eventId = document.getElementById('eventId').value;
        const eventData = {
            id: eventId ? parseInt(eventId) : events.length > 0 ? Math.max(...events.map(e => e.id)) + 1 : 1,
            title: document.getElementById('eventTitleInput').value,
            description: document.getElementById('eventDescriptionInput').value,
            startDate: document.getElementById('eventStartDate').value,
            startTime: document.getElementById('eventStartTime').value,
            endDate: document.getElementById('eventEndDate').value || document.getElementById('eventStartDate').value,
            endTime: document.getElementById('eventEndTime').value || document.getElementById('eventStartTime').value,
            location: document.getElementById('eventLocationInput').value,
            type: document.getElementById('eventTypeInput').value,
            allDay: document.getElementById('allDayEvent').checked
        };
        
        if (eventId) {
            // Update existing event
            const index = events.findIndex(e => e.id === parseInt(eventId));
            events[index] = eventData;
            alert('✓ Event updated successfully!');
        } else {
            // Add new event
            events.push(eventData);
            alert('✓ Event created successfully!');
        }
        
        closeEventModal();
        renderCalendar();
    });
</script>
@endpush
@endsection