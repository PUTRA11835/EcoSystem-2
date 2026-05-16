// Calendar Events JavaScript
let currentDate = new Date();
let currentView = 'month';
let selectedEventId = null;
let events = [];

// ── Indonesian National Holidays ──────────────────────────────────────────────
// Cache per tahun: { 2025: [{date, localName, name}, ...], 2026: [...] }
const holidayCache = {};
let holidaysLoaded = false;

async function loadHolidays(year) {
    if (holidayCache[year]) return;
    try {
        const res = await fetch(`https://date.nager.at/api/v3/PublicHolidays/${year}/ID`);
        if (!res.ok) throw new Error('Holiday API error');
        holidayCache[year] = await res.json();
    } catch {
        holidayCache[year] = [];
    }
}

function getHoliday(dateStr) {
    const year = parseInt(dateStr.split('-')[0]);
    const list = holidayCache[year] || [];
    return list.find(h => h.date === dateStr) || null;
}
// ─────────────────────────────────────────────────────────────────────────────

const eventTypeColors = {
    meeting: { bg: 'bg-blue-500', text: 'text-blue-700', bgLight: 'bg-blue-50', border: 'border-blue-200', gradient: 'from-blue-600 to-blue-700' },
    task: { bg: 'bg-green-500', text: 'text-green-700', bgLight: 'bg-green-50', border: 'border-green-200', gradient: 'from-green-600 to-green-700' },
    deadline: { bg: 'bg-purple-500', text: 'text-purple-700', bgLight: 'bg-purple-50', border: 'border-purple-200', gradient: 'from-purple-600 to-purple-700' },
    urgent: { bg: 'bg-red-500', text: 'text-red-700', bgLight: 'bg-red-50', border: 'border-red-200', gradient: 'from-red-600 to-red-700' },
    reminder: { bg: 'bg-yellow-500', text: 'text-yellow-700', bgLight: 'bg-yellow-50', border: 'border-yellow-200', gradient: 'from-yellow-600 to-yellow-700' }
};

document.addEventListener('DOMContentLoaded', function() {
    loadEvents();
    
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

    // Form submit handler
    document.getElementById('eventForm').addEventListener('submit', handleFormSubmit);
});

async function loadEvents() {
    const year = currentDate.getFullYear();
    await loadHolidays(year);
    try {
        const response = await fetch('/api/events');
        const data = await response.json();
        if (data.success) {
            events = data.data;
        }
    } catch (error) {
        console.error('Error loading events:', error);
    }
    renderCalendar();
}

function renderCalendar() {
    switch(currentView) {
        case 'month':
            renderMonthView();
            break;
        case 'week':
            renderWeekView();
            break;
        case 'day':
            renderDayView();
            break;
    }
}

function renderMonthView() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Update header
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('currentPeriod').textContent = `${monthNames[month]} ${year}`;
    
    // Get first day of month and number of days
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    
    const monthGrid = document.getElementById('monthGrid');
    monthGrid.innerHTML = '';
    
    let dayCount = 1;
    let nextMonthDay = 1;
    
    // Total cells needed (6 rows × 7 days)
    const totalCells = 42;
    
    for (let i = 0; i < totalCells; i++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'border-r border-b border-gray-200 p-2 min-h-[100px] hover:bg-gray-50 transition-all cursor-pointer';
        
        let dateStr = '';
        
        if (i < firstDay) {
            // Previous month days
            const prevDay = daysInPrevMonth - firstDay + i + 1;
            dayCell.classList.add('bg-gray-50');
            dayCell.innerHTML = `<div class="text-sm font-semibold text-gray-400 mb-1">${prevDay}</div>`;
        } else if (dayCount <= daysInMonth) {
            // Current month days
            dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(dayCount).padStart(2, '0')}`;

            const isToday = new Date().toDateString() === new Date(year, month, dayCount).toDateString();
            const holiday = getHoliday(dateStr);

            if (holiday) {
                dayCell.classList.add('bg-red-50');
            }

            const dayHeader = document.createElement('div');
            dayHeader.className = `text-sm font-semibold mb-1 flex items-center justify-between ${isToday ? 'text-red-800' : holiday ? 'text-red-600' : 'text-gray-700'}`;

            const dateNumEl = document.createElement('span');
            if (isToday) {
                dateNumEl.innerHTML = `<span class="inline-flex items-center justify-center w-7 h-7 bg-red-800 text-white rounded-full">${dayCount}</span>`;
            } else {
                dateNumEl.textContent = dayCount;
            }
            dayHeader.appendChild(dateNumEl);

            dayCell.appendChild(dayHeader);

            // Holiday badge
            if (holiday) {
                const holidayBadge = document.createElement('div');
                holidayBadge.className = 'text-[10px] font-medium text-red-600 bg-red-100 px-1.5 py-0.5 rounded mb-1 truncate';
                holidayBadge.title = holiday.name;
                holidayBadge.textContent = holiday.localName;
                dayCell.appendChild(holidayBadge);
            }

            // Add events for this day
            const dayEvents = events.filter(e => e.start_date === dateStr);
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
        
        monthGrid.appendChild(dayCell);
    }
}

function renderWeekView() {
    const weekStart = new Date(currentDate);
    weekStart.setDate(currentDate.getDate() - currentDate.getDay());
    
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekStart.getDate() + 6);
    
    // Update header
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    document.getElementById('currentPeriod').textContent = 
        `${monthNames[weekStart.getMonth()]} ${weekStart.getDate()} - ${monthNames[weekEnd.getMonth()]} ${weekEnd.getDate()}, ${weekEnd.getFullYear()}`;
    
    // Render week headers
    const weekHeaders = document.getElementById('weekHeaders');
    weekHeaders.innerHTML = '';
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    for (let i = 0; i < 7; i++) {
        const date = new Date(weekStart);
        date.setDate(weekStart.getDate() + i);
        const isToday = new Date().toDateString() === date.toDateString();
        const weekDateStr = date.toISOString().split('T')[0];
        const weekHoliday = getHoliday(weekDateStr);

        const headerDiv = document.createElement('div');
        headerDiv.className = `border-r border-b border-gray-200 p-2 text-center ${isToday ? 'bg-red-50' : weekHoliday ? 'bg-red-50' : 'bg-gray-50'}`;
        headerDiv.innerHTML = `
            <div class="text-xs font-semibold ${weekHoliday ? 'text-red-500' : 'text-gray-500'}">${dayNames[i]}</div>
            <div class="text-lg font-bold ${isToday ? 'text-red-800' : weekHoliday ? 'text-red-600' : 'text-gray-900'}">${date.getDate()}</div>
            ${weekHoliday ? `<div class="text-[9px] text-red-500 font-medium truncate" title="${weekHoliday.name}">${weekHoliday.localName}</div>` : ''}
        `;
        weekHeaders.appendChild(headerDiv);
    }
    
    // Render time slots
    const weekGrid = document.getElementById('weekGrid');
    weekGrid.innerHTML = '';
    
    for (let hour = 0; hour < 24; hour++) {
        // Time label
        const timeCell = document.createElement('div');
        timeCell.className = 'border-r border-b border-gray-200 bg-gray-50 p-2 text-xs text-gray-600 text-right';
        timeCell.textContent = `${hour.toString().padStart(2, '0')}:00`;
        weekGrid.appendChild(timeCell);
        
        // Day cells
        for (let day = 0; day < 7; day++) {
            const date = new Date(weekStart);
            date.setDate(weekStart.getDate() + day);
            const dateStr = date.toISOString().split('T')[0];
            
            const dayCell = document.createElement('div');
            dayCell.className = 'border-r border-b border-gray-200 p-1 min-h-[40px] hover:bg-gray-50 cursor-pointer transition-all';
            
            // Find events for this hour
            const hourEvents = events.filter(e => {
                if (e.start_date !== dateStr) return false;
                const eventHour = parseInt(e.start_time.split(':')[0]);
                return eventHour === hour;
            });
            
            hourEvents.forEach(event => {
                const eventEl = document.createElement('div');
                const colors = eventTypeColors[event.type];
                eventEl.className = `${colors.bg} text-white text-xs px-2 py-1 rounded mb-1 truncate hover:shadow-md transition-all`;
                eventEl.textContent = `${event.start_time} ${event.title}`;
                eventEl.onclick = (e) => {
                    e.stopPropagation();
                    showEventDetails(event.id);
                };
                dayCell.appendChild(eventEl);
            });
            
            dayCell.onclick = () => openEventModal(dateStr, `${hour.toString().padStart(2, '0')}:00`);
            weekGrid.appendChild(dayCell);
        }
    }
}

function renderDayView() {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    document.getElementById('currentPeriod').textContent = 
        `${dayNames[currentDate.getDay()]}, ${monthNames[currentDate.getMonth()]} ${currentDate.getDate()}, ${currentDate.getFullYear()}`;
    
    const dayGrid = document.getElementById('dayGrid');
    dayGrid.innerHTML = '';

    const dateStr = currentDate.toISOString().split('T')[0];
    const dayHoliday = getHoliday(dateStr);

    if (dayHoliday) {
        const banner = document.createElement('div');
        banner.className = 'flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-2 mb-4 text-sm font-medium';
        banner.innerHTML = `<i class="fas fa-flag"></i> Hari Libur Nasional: <span class="font-bold">${dayHoliday.localName}</span><span class="text-red-400 text-xs ml-1">(${dayHoliday.name})</span>`;
        dayGrid.appendChild(banner);
    }

    const dayEvents = events.filter(e => e.start_date === dateStr).sort((a, b) => {
        return a.start_time.localeCompare(b.start_time);
    });

    if (dayEvents.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'text-gray-500 text-center py-8';
        empty.textContent = 'No events scheduled for this day';
        dayGrid.appendChild(empty);
        return;
    }
    
    dayEvents.forEach(event => {
        const colors = eventTypeColors[event.type];
        const eventCard = document.createElement('div');
        eventCard.className = `border-l-4 ${colors.border} bg-white p-4 mb-3 rounded-lg shadow-sm hover:shadow-md transition-all cursor-pointer`;
        eventCard.innerHTML = `
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h4 class="font-bold text-gray-900 mb-1">${event.title}</h4>
                    <p class="text-sm text-gray-600 mb-2">${event.description || 'No description'}</p>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span><i class="fas fa-clock mr-1"></i>${event.start_time} - ${event.end_time || event.start_time}</span>
                        <span><i class="fas fa-map-marker-alt mr-1"></i>${event.location || 'No location'}</span>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-semibold ${colors.bgLight} ${colors.text}">
                    ${event.type.charAt(0).toUpperCase() + event.type.slice(1)}
                </span>
            </div>
        `;
        eventCard.onclick = () => showEventDetails(event.id);
        dayGrid.appendChild(eventCard);
    });
}

async function previousPeriod() {
    if (currentView === 'month') {
        currentDate.setMonth(currentDate.getMonth() - 1);
    } else if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() - 7);
    } else {
        currentDate.setDate(currentDate.getDate() - 1);
    }
    await loadHolidays(currentDate.getFullYear());
    renderCalendar();
}

async function nextPeriod() {
    if (currentView === 'month') {
        currentDate.setMonth(currentDate.getMonth() + 1);
    } else if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() + 7);
    } else {
        currentDate.setDate(currentDate.getDate() + 1);
    }
    await loadHolidays(currentDate.getFullYear());
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
    
    // Show/hide views
    document.getElementById('monthView').classList.toggle('hidden', view !== 'month');
    document.getElementById('weekView').classList.toggle('hidden', view !== 'week');
    document.getElementById('dayView').classList.toggle('hidden', view !== 'day');
    
    renderCalendar();
}

function openEventModal(date = null, time = null) {
    document.getElementById('eventModalTitle').innerHTML = '<i class="fas fa-calendar-plus"></i> Create New Event';
    document.getElementById('eventForm').reset();
    document.getElementById('eventId').value = '';
    
    if (date) {
        document.getElementById('eventStartDate').value = date;
        document.getElementById('eventEndDate').value = date;
    }
    if (time) {
        document.getElementById('eventStartTime').value = time;
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
    document.getElementById('eventTime').textContent = event.all_day 
        ? 'All day event' 
        : `${event.start_time} - ${event.end_time || event.start_time}`;
    document.getElementById('eventLocation').textContent = event.location || 'No location';
    
    const typeEl = document.getElementById('eventType');
    typeEl.textContent = event.type.charAt(0).toUpperCase() + event.type.slice(1);
    typeEl.className = `px-3 py-1 rounded-full text-sm font-semibold ${colors.bgLight} ${colors.text}`;
    
    const header = document.getElementById('detailsHeader');
    header.className = `text-white px-6 py-4 rounded-t-2xl flex items-center justify-between bg-gradient-to-r ${colors.gradient}`;
    
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
    document.getElementById('eventDescriptionInput').value = event.description || '';
    document.getElementById('eventStartDate').value = event.start_date;
    document.getElementById('eventStartTime').value = event.start_time;
    document.getElementById('eventEndDate').value = event.end_date || event.start_date;
    document.getElementById('eventEndTime').value = event.end_time || event.start_time;
    document.getElementById('eventLocationInput').value = event.location || '';
    document.getElementById('eventTypeInput').value = event.type;
    document.getElementById('allDayEvent').checked = event.all_day;
    
    if (event.all_day) {
        document.getElementById('eventStartTime').disabled = true;
        document.getElementById('eventEndTime').disabled = true;
    }
    
    document.getElementById('eventModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

async function deleteEvent() {
    if (!confirm('Are you sure you want to delete this event?')) return;
    
    try {
        const response = await fetch(`/api/events/${selectedEventId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeDetailsModal();
            await loadEvents();
            showNotification('Event deleted successfully!', 'success');
        } else {
            showNotification('Failed to delete event: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting event:', error);
        showNotification('Failed to delete event', 'error');
    }
}

async function handleFormSubmit(e) {
    e.preventDefault();
    
    const eventId = document.getElementById('eventId').value;
    const eventData = {
        title: document.getElementById('eventTitleInput').value,
        description: document.getElementById('eventDescriptionInput').value,
        start_date: document.getElementById('eventStartDate').value,
        start_time: document.getElementById('eventStartTime').value,
        end_date: document.getElementById('eventEndDate').value || document.getElementById('eventStartDate').value,
        end_time: document.getElementById('eventEndTime').value || document.getElementById('eventStartTime').value,
        location: document.getElementById('eventLocationInput').value,
        type: document.getElementById('eventTypeInput').value,
        all_day: document.getElementById('allDayEvent').checked
    };
    
    try {
        const url = eventId ? `/api/events/${eventId}` : '/api/events';
        const method = eventId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(eventData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeEventModal();
            await loadEvents();
            showNotification(eventId ? 'Event updated successfully!' : 'Event created successfully!', 'success');
        } else {
            showNotification('Failed to save event: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error saving event:', error);
        showNotification('Failed to save event', 'error');
    }
}