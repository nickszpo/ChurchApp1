<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

require_once 'config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

// Include the AppointmentManager
require_once 'includes/AppointmentManager.php';
$appointmentManager = new AppointmentManager($pdo);

// Include the ResourceManager
require_once 'includes/ResourceManager.php';
$resourceManager = new ResourceManager($pdo);

// Get filter parameters
$resourceId = isset($_GET['resource_id']) ? (int)$_GET['resource_id'] : null;
$startDate = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$endDate = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
$view = isset($_GET['view']) ? $_GET['view'] : 'dayGridMonth';

// Get resources for filter dropdown
$resources = $resourceManager->getAllResources();

// Set page title
$page_title = 'Appointment Calendar';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Main Content -->
        <main class="col-md-12 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Appointment Calendar</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="new-appointment.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle"></i> New Appointment
                        </a>
                    </div>
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="prevPeriod">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="today">Today</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="nextPeriod">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="dayView">Day</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary active" id="weekView">Week</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="monthView">Month</button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="calendarFilters" class="row g-3">
                        <div class="col-md-4">
                            <label for="resourceFilter" class="form-label">Filter by Resource</label>
                            <select class="form-select" id="resourceFilter" name="resource_id">
                                <option value="">All Resources</option>
                                <?php foreach ($resources as $resource): ?>
                                    <option value="<?= $resource['id'] ?>" 
                                        <?= $resourceId == $resource['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($resource['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" name="start" value="<?= $startDate ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" name="end" value="<?= $endDate ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <button type="button" id="resetFilters" class="btn btn-outline-secondary">Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Calendar -->
            <div class="card">
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalLabel">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 id="eventTitle"></h6>
                    <div class="text-muted mb-2" id="eventTime"></div>
                    <div class="mb-2" id="eventService"></div>
                    <div class="mb-2" id="eventResources"></div>
                    <div class="mb-2" id="eventDescription"></div>
                    <div class="badge" id="eventStatus"></div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-primary" id="viewAppointmentBtn">View Details</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include FullCalendar CSS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />

<!-- Include FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the calendar
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: '<?= $view ?>',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        initialDate: '<?= $startDate ?>',
        navLinks: true, // can click day/week names to navigate views
        selectable: true,
        selectMirror: true,
        select: function(arg) {
            // Redirect to new appointment with selected date/time
            var start = arg.startStr;
            var end = arg.endStr;
            window.location.href = 'new-appointment.php?start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end);
            calendar.unselect();
        },
        eventClick: function(info) {
            // Show event details in modal
            var event = info.event;
            
            document.getElementById('eventTitle').textContent = event.title;
            document.getElementById('eventTime').textContent = 
                event.start ? event.start.toLocaleString() : '';
                
            if (event.end) {
                document.getElementById('eventTime').textContent += ' - ' + 
                    (event.end ? event.end.toLocaleString() : '');
            }
            
            // Set extended properties
            if (event.extendedProps.service_name) {
                document.getElementById('eventService').innerHTML = 
                    '<strong>Service:</strong> ' + event.extendedProps.service_name;
            }
            
            if (event.extendedProps.resources && event.extendedProps.resources.length > 0) {
                var resourcesHtml = '<strong>Resources:</strong> ' + 
                    event.extendedProps.resources.map(r => r.name).join(', ');
                document.getElementById('eventResources').innerHTML = resourcesHtml;
            }
            
            if (event.extendedProps.description) {
                document.getElementById('eventDescription').innerHTML = 
                    '<strong>Description:</strong> ' + event.extendedProps.description;
            }
            
            // Set status badge
            var statusEl = document.getElementById('eventStatus');
            if (event.extendedProps.status) {
                var status = event.extendedProps.status.toLowerCase();
                var statusClass = 'bg-secondary';
                
                if (status === 'confirmed') statusClass = 'bg-success';
                else if (status === 'pending') statusClass = 'bg-warning text-dark';
                else if (status === 'cancelled') statusClass = 'bg-danger';
                else if (status === 'completed') statusClass = 'bg-info';
                
                statusEl.className = 'badge ' + statusClass;
                statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                statusEl.style.display = 'inline-block';
            } else {
                statusEl.style.display = 'none';
            }
            
            // Set view details link
            if (event.id) {
                var viewBtn = document.getElementById('viewAppointmentBtn');
                viewBtn.href = 'appointment.php?id=' + event.id;
                viewBtn.style.display = 'inline-block';
            } else {
                document.getElementById('viewAppointmentBtn').style.display = 'none';
            }
            
            var modal = new bootstrap.Modal(document.getElementById('eventModal'));
            modal.show();
            
            info.jsEvent.preventDefault();
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            // Get events from the API
            var params = {
                start: fetchInfo.startStr,
                end: fetchInfo.endStr
            };
            
            // Add resource filter if set
            var resourceId = document.getElementById('resourceFilter').value;
            if (resourceId) {
                params.resource_id = resourceId;
            }
            
            // Add user filter for non-admin users
            <?php if ($_SESSION['role'] !== 'admin'): ?>
                params.user_id = <?= $_SESSION['user_id'] ?>;
            <?php endif; ?>
            
            // Make API request
            var queryString = Object.keys(params)
                .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(params[key]))
                .join('&');
                
            fetch('/api/appointment.php?' + queryString)
                .then(response => response.json())
                .then(data => {
                    // Transform data for FullCalendar
                    var events = data.map(event => ({
                        id: event.id,
                        title: event.title,
                        start: event.start_time,
                        end: event.end_time,
                        backgroundColor: event.resources && event.resources[0] ? 
                            (event.resources[0].color_code || '#3b82f6') : '#3b82f6',
                        borderColor: event.resources && event.resources[0] ? 
                            (event.resources[0].color_code || '#3b82f6') : '#3b82f6',
                        extendedProps: {
                            description: event.description,
                            status: event.status,
                            service_name: event.service_name,
                            resources: event.resources || []
                        }
                    }));
                    
                    successCallback(events);
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    failureCallback(error);
                });
        },
        eventDidMount: function(info) {
            // Add tooltip with event details
            if (info.event.extendedProps.description || info.event.extendedProps.resources) {
                var tooltipContent = [];
                
                if (info.event.extendedProps.service_name) {
                    tooltipContent.push('<strong>Service:</strong> ' + info.event.extendedProps.service_name);
                }
                
                if (info.event.extendedProps.resources && info.event.extendedProps.resources.length > 0) {
                    var resources = info.event.extendedProps.resources.map(r => r.name).join(', ');
                    tooltipContent.push('<strong>Resources:</strong> ' + resources);
                }
                
                if (info.event.extendedProps.description) {
                    tooltipContent.push('<strong>Description:</strong> ' + info.event.extendedProps.description);
                }
                
                if (tooltipContent.length > 0) {
                    new bootstrap.Tooltip(info.el, {
                        title: tooltipContent.join('<br>'),
                        html: true,
                        container: 'body',
                        placement: 'top'
                    });
                }
            }
        }
    });
    
    // Render the calendar
    calendar.render();
    
    // Navigation buttons
    document.getElementById('today').addEventListener('click', function() {
        calendar.today();
        updateDateRange(calendar);
    });
    
    document.getElementById('prevPeriod').addEventListener('click', function() {
        calendar.prev();
        updateDateRange(calendar);
    });
    
    document.getElementById('nextPeriod').addEventListener('click', function() {
        calendar.next();
        updateDateRange(calendar);
    });
    
    // View buttons
    document.getElementById('dayView').addEventListener('click', function() {
        calendar.changeView('timeGridDay');
        updateActiveViewButton('dayView');
    });
    
    document.getElementById('weekView').addEventListener('click', function() {
        calendar.changeView('timeGridWeek');
        updateActiveViewButton('weekView');
    });
    
    document.getElementById('monthView').addEventListener('click', function() {
        calendar.changeView('dayGridMonth');
        updateActiveViewButton('monthView');
    });
    
    // Update active view button
    function updateActiveViewButton(activeId) {
        ['dayView', 'weekView', 'monthView'].forEach(function(id) {
            var btn = document.getElementById(id);
            if (id === activeId) {
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-secondary');
            } else {
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-outline-secondary');
            }
        });
    }
    
    // Update date range inputs when view changes
    function updateDateRange(calendar) {
        var view = calendar.view;
        document.getElementById('startDate').value = formatDate(view.activeStart);
        document.getElementById('endDate').value = formatDate(view.activeEnd);
    }
    
    // Format date as YYYY-MM-DD
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    // Handle filter form submission
    document.getElementById('calendarFilters').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        var formData = new FormData(this);
        var params = new URLSearchParams();
        
        // Add all form data to params
        for (var pair of formData.entries()) {
            if (pair[1]) {
                params.append(pair[0], pair[1]);
            }
        }
        
        // Navigate to the same page with new query parameters
        window.location.href = 'calendar.php?' + params.toString();
    });
    
    // Reset filters
    document.getElementById('resetFilters').addEventListener('click', function() {
        window.location.href = 'calendar.php';
    });
    
    // Initialize active view button
    updateActiveViewButton('<?= $view === 'timeGridDay' ? 'dayView' : ($view === 'timeGridWeek' ? 'weekView' : 'monthView') ?>');
});
</script>

<?php include 'includes/footer.php'; ?>
