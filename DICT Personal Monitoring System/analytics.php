<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Base WHERE clause for date filtering
$whereClause = "";
$params = [];

// Apply date filters
if ($filter === 'year') {
    $whereClause = "WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
} elseif ($filter === 'month') {
    $whereClause = "WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
} elseif ($filter === 'week') {
    $whereClause = "WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
} elseif (!empty($start_date) && !empty($end_date)) {
    $whereClause = "WHERE start_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date . ' 23:59:59';
}

// 1. Project Statistics
$query = "SELECT 
            COUNT(*) as total_projects,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) as delayed,
            AVG(TIMESTAMPDIFF(DAY, start_date, COALESCE(completed_date, CURDATE()))) as avg_completion_days,
            ROUND((SUM(CASE WHEN status = 'completed' AND completed_date <= deadline THEN 1 ELSE 0 END) / 
                  NULLIF(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0)) * 100, 1) as on_time_rate
          FROM projects 
          $whereClause";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$project_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Monthly Project Completion (Last 6 months)
$query = "SELECT 
            DATE_FORMAT(completed_date, '%b %Y') as month,
            COUNT(*) as completed_projects,
            ROUND(AVG(TIMESTAMPDIFF(DAY, start_date, completed_date)), 1) as avg_days_to_complete
          FROM projects 
          WHERE status = 'completed' 
            AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(completed_date, '%Y-%m')
          ORDER BY completed_date DESC";
$monthly_completion = $pdo->query($query)->fetchAll();

// 3. Project Status Distribution
$query = "SELECT 
            status,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0) / (SELECT COUNT(*) FROM projects $whereClause), 1) as percentage
          FROM projects 
          $whereClause
          GROUP BY status";
$status_distribution = $pdo->prepare($query);
$status_distribution->execute($params);
$status_data = $status_distribution->fetchAll(PDO::FETCH_ASSOC);

// 4. Top Projects by Activity Count
$query = "SELECT 
            p.id,
            p.title,
            COUNT(a.id) as activity_count,
            p.status,
            p.start_date,
            p.deadline
          FROM projects p
          LEFT JOIN activities a ON p.id = a.project_id
          $whereClause
          GROUP BY p.id, p.title
          ORDER BY activity_count DESC
          LIMIT 5";
$top_projects = $pdo->prepare($query);
$top_projects->execute($params);
$top_projects_data = $top_projects->fetchAll(PDO::FETCH_ASSOC);

// 5. Activity Status Distribution
$query = "SELECT 
            status,
            COUNT(*) as count
          FROM activities
          GROUP BY status";
$activity_status = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$status_labels = [];
$status_counts = [];
$status_colors = [
    'completed' => '#10b981',
    'in_progress' => '#3b82f6',
    'pending' => '#f59e0b',
    'delayed' => '#ef4444'
];

foreach ($status_data as $status) {
    $status_labels[] = ucfirst(str_replace('_', ' ', $status['status']));
    $status_counts[] = $status['count'];
}

// Prepare monthly completion data
$months = [];
$completed_projects = [];
$avg_days = [];

foreach (array_reverse($monthly_completion) as $month) {
    $months[] = $month['month'];
    $completed_projects[] = (int)$month['completed_projects'];
    $avg_days[] = (float)$month['avg_days_to_complete'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System - Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar animate__animated animate__slideInLeft">
                <h3 class="text-white text-center mb-4">DICT PMS</h3>
                <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="projects.php"><i class="bi bi-folder"></i> Projects</a>
                <a href="activities.php"><i class="bi bi-list-check"></i> Activities</a>
                <a href="analytics.php" class="active"><i class="bi bi-graph-up"></i> Analytics</a>
                <a href="notes.php"><i class="bi bi-journal-text"></i> Important Notes</a>
                <a href="about.php"><i class="bi bi-info-circle"></i> About</a>
                <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content animate__animated animate__fadeIn">
                <h2 class="welcome-text mb-4">Analytics Dashboard</h2>

                <!-- Filters -->
                <div class="filters mb-4">
                    <select class="form-select form-select-sm d-inline-block w-auto me-2">
                        <option value="all">All Time</option>
                        <option value="year">This Year</option>
                        <option value="month">This Month</option>
                        <option value="week">This Week</option>
                    </select>
                    <input type="date" class="form-control form-control-sm d-inline-block w-auto me-2" />
                    <input type="date" class="form-control form-control-sm d-inline-block w-auto" />
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Project Status Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="projectStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Monthly Project Completion</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="completionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Average Completion Time</h5>
                            </div>
                            <div class="card-body text-center">
                                <h2 class="mb-0">45 Days</h2>
                                <p class="text-muted">Per Project</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>On-Time Delivery Rate</h5>
                            </div>
                            <div class="card-body text-center">
                                <h2 class="mb-0">85%</h2>
                                <p class="text-muted">Projects Completed on Schedule</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Resource Utilization</h5>
                            </div>
                            <div class="card-body text-center">
                                <h2 class="mb-0">92%</h2>
                                <p class="text-muted">Average Team Utilization</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Analysis -->
                <div class="card">
                    <div class="card-header">
                        <h5>Project Activity Analysis</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize charts with dark theme
        Chart.defaults.color = '#8a8d97';
        Chart.defaults.borderColor = 'rgba(108, 99, 255, 0.1)';

        // Project Status Chart
        new Chart(document.getElementById('projectStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['In Progress', 'Completed', 'Pending', 'Delayed'],
                datasets: [{
                    data: [30, 45, 15, 10],
                    backgroundColor: [
                        '#6c63ff',
                        '#28a745',
                        '#ffc107',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Completion Chart
        new Chart(document.getElementById('completionChart'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Completed Projects',
                    data: [5, 8, 12, 7, 9, 11],
                    borderColor: '#6c63ff',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Activity Distribution Chart
        new Chart(document.getElementById('activityChart'), {
            type: 'bar',
            data: {
                labels: ['Project A', 'Project B', 'Project C', 'Project D', 'Project E'],
                datasets: [{
                    label: 'Number of Activities',
                    data: [25, 18, 15, 12, 10],
                    backgroundColor: '#6c63ff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 