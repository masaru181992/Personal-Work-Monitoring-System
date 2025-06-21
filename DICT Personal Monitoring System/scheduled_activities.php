<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch scheduled activities
$stmt = $pdo->query("SELECT a.*, p.title as project_title 
                     FROM activities a 
                     LEFT JOIN projects p ON a.project_id = p.id 
                     WHERE a.scheduled_date >= CURDATE() 
                     ORDER BY a.scheduled_date ASC");
$scheduled_activities = $stmt->fetchAll();

// Fetch projects for dropdown
$stmt = $pdo->query("SELECT id, title FROM projects WHERE status != 'completed'");
$projects = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System - Scheduled Activities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row h-100">
            <!-- Include Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="main-content animate__animated animate__fadeIn" style="margin-left: 350px; padding: 2rem;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="welcome-text">Scheduled Activities</h2>
                    <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                        <i class="bi bi-plus-circle"></i> Schedule Activity
                    </button>
                </div>

                <div class="row">
                    <!-- Calendar View -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="bi bi-calendar3"></i> Activity Calendar</h5>
                            </div>
                            <div class="card-body">
                                <div id="calendar"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Activities -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-clock"></i> Upcoming Activities</h5>
                            </div>
                            <div class="card-body">
                                <div class="upcoming-activities">
                                    <?php foreach ($scheduled_activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-date">
                                            <?php 
                                                $date = new DateTime($activity['scheduled_date']);
                                                echo $date->format('F j, Y'); 
                                            ?>
                                        </div>
                                        <div class="activity-details">
                                            <h6><?php echo htmlspecialchars($activity['title']); ?></h6>
                                            <p class="project-name">
                                                <i class="bi bi-folder-fill"></i>
                                                <?php echo htmlspecialchars($activity['project_title']); ?>
                                            </p>
                                            <p class="activity-time">
                                                <i class="bi bi-clock-fill"></i>
                                                <?php 
                                                    $time = new DateTime($activity['scheduled_time']);
                                                    echo $time->format('g:i A'); 
                                                ?>
                                            </p>
                                        </div>
                                        <div class="activity-status <?php echo strtolower($activity['status']); ?>">
                                            <?php echo $activity['status']; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Activity Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule New Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="scheduleForm">
                        <div class="mb-3">
                            <label class="form-label">Activity Title</label>
                            <input type="text" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Project</label>
                            <select class="form-select" required>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>">
                                    <?php echo htmlspecialchars($project['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select">
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notification</label>
                            <select class="form-select">
                                <option value="30">30 minutes before</option>
                                <option value="60">1 hour before</option>
                                <option value="1440">1 day before</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-custom">Schedule Activity</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                themeSystem: 'bootstrap5',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php foreach ($scheduled_activities as $activity): ?>
                    {
                        title: '<?php echo addslashes($activity['title']); ?>',
                        start: '<?php echo $activity['scheduled_date'] . 'T' . $activity['scheduled_time']; ?>',
                        className: 'activity-priority-<?php echo strtolower($activity['priority']); ?>'
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    // Handle event click
                },
                dateClick: function(info) {
                    // Handle date click
                    $('#scheduleModal').modal('show');
                }
            });
            calendar.render();

            // Set minimum date and time for scheduling
            const dateInput = document.querySelector('input[type="date"]');
            const timeInput = document.querySelector('input[type="time"]');
            
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            dateInput.min = todayStr;
            dateInput.value = todayStr;
            
            // Set default time to next hour
            const nextHour = new Date(today.setHours(today.getHours() + 1, 0, 0));
            const timeStr = nextHour.toTimeString().slice(0, 5);
            timeInput.value = timeStr;
            
            // Validate date and time selection
            const form = document.getElementById('scheduleForm');
            form.addEventListener('submit', function(e) {
                const selectedDate = new Date(dateInput.value + 'T' + timeInput.value);
                const now = new Date();
                
                if (selectedDate < now) {
                    e.preventDefault();
                    alert('Please select a future date and time');
                }
            });
        });
    </script>
</body>
</html> 