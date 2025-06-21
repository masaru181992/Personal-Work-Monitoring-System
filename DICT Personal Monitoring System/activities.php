<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        // Handle add action
        if ($_POST['action'] === 'add') {
            $project_id = (int)$_POST['project_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = strtolower(trim($_POST['status']));
            
            // Basic validation
            if (empty($title) || empty($start_date) || empty($end_date)) {
                throw new Exception("All fields are required.");
            }
            
            // Date validation
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $today = new DateTime();
            
            if ($end < $start) {
                throw new Exception("End date cannot be before start date.");
            }
            
            // Insert the activity
            $stmt = $pdo->prepare("INSERT INTO activities 
                (project_id, title, description, start_date, end_date, status) 
                VALUES (?, ?, ?, ?, ?, ?)");
                
            $result = $stmt->execute([
                $project_id, 
                $title, 
                $description, 
                $start_date, 
                $end_date, 
                $status
            ]);
            
            if ($result) {
                $success_message = "Activity added successfully!";
                // Clear form and redirect to prevent form resubmission
                echo '<script>
                    setTimeout(function() {
                        window.location.href = window.location.pathname;
                    }, 1000);
                </script>';
            } else {
                throw new Exception("Failed to add activity. Please try again.");
            }
        } 
        
        // Handle update action
        if ($_POST['action'] === 'update' && isset($_POST['activity_id'])) {
            $activity_id = (int)$_POST['activity_id'];
            $project_id = (int)$_POST['project_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = strtolower(trim($_POST['status']));
            
            // Basic validation
            if (empty($title) || empty($start_date) || empty($end_date)) {
                throw new Exception("All fields are required.");
            }
            
            // Date validation
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            
            if ($end < $start) {
                throw new Exception("End date cannot be before start date.");
            }
            
            // Update the activity
            $stmt = $pdo->prepare("UPDATE activities 
                SET project_id = ?, title = ?, description = ?, 
                    start_date = ?, end_date = ?, status = ?
                WHERE id = ?");
                
            $result = $stmt->execute([
                $project_id, 
                $title, 
                $description, 
                $start_date, 
                $end_date, 
                $status,
                $activity_id
            ]);
            
            if ($result) {
                $success_message = "Activity updated successfully!";
                // Redirect to prevent form resubmission
                echo '<script>
                    setTimeout(function() {
                        window.location.href = window.location.pathname;
                    }, 1000);
                </script>';
            } else {
                throw new Exception("Failed to update activity. Please try again.");
            }
        } 
        // Handle delete action
        elseif ($_POST['action'] === 'delete' && isset($_POST['activity_id'])) {
            $activity_id = (int)$_POST['activity_id'];
            if ($activity_id <= 0) {
                throw new Exception("Invalid activity ID");
            }
            
            $stmt = $pdo->prepare("DELETE FROM activities WHERE id = ?");
            if ($stmt->execute([$activity_id])) {
                $success_message = "Activity deleted successfully!";
            } else {
                throw new Exception("Failed to delete activity. Please try again.");
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch all projects for dropdown
$stmt = $pdo->query("SELECT id, title FROM projects ORDER BY title");
$projects = $stmt->fetchAll();

// Handle filter parameters
$project_filter = isset($_GET['project_filter']) ? (int)$_GET['project_filter'] : 0;
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$start_date_filter = isset($_GET['start_date_filter']) ? $_GET['start_date_filter'] : '';
$end_date_filter = isset($_GET['end_date_filter']) ? $_GET['end_date_filter'] : '';

// Build the base query
$query = "SELECT a.*, p.title as project_title 
          FROM activities a 
          LEFT JOIN projects p ON a.project_id = p.id 
          WHERE 1=1";
$params = [];

// Add filters to the query
if ($project_filter > 0) {
    $query .= " AND a.project_id = ?";
    $params[] = $project_filter;
}

if (!empty($status_filter)) {
    $query .= " AND LOWER(a.status) = LOWER(?)";
    $params[] = $status_filter;
}

if (!empty($start_date_filter)) {
    $query .= " AND a.end_date >= ?";
    $params[] = $start_date_filter;
}

if (!empty($end_date_filter)) {
    $query .= " AND a.start_date <= ?";
    $params[] = $end_date_filter;
}

// Add sorting
$query .= " ORDER BY a.start_date DESC, a.end_date DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System - Activities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-bg: #0a192f;
            --secondary-bg: rgba(16, 32, 56, 0.9);
            --accent-color: #64ffda;
            --accent-secondary: #7928ca;
            --text-white: #ffffff;
            --border-color: rgba(100, 255, 218, 0.1);
        }
        
        body {
            background-color: var(--primary-bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(100, 255, 218, 0.1) 0%, transparent 50%),
                radial-gradient(at 100% 0%, rgba(121, 40, 202, 0.1) 0%, transparent 50%);
            color: var(--text-white);
            font-family: 'Space Grotesk', sans-serif;
        }
        
        .form-control, .form-select {
            background: rgba(16, 32, 56, 0.9);
            border: 1px solid var(--border-color);
            color: var(--text-white);
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(16, 32, 56, 0.95);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(100, 255, 218, 0.2);
            color: var(--text-white);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-select option {
            background: var(--primary-bg);
            color: var(--text-white);
        }

        /* Modal Styles */
        .modal-content {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            color: var(--text-white);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px;
        }

        .modal-title {
            color: var(--text-white);
            font-weight: 600;
        }

        /* Sidebar Enhancement */
        .sidebar {
            min-height: 100vh;
            background: rgba(10, 25, 47, 0.95);
            border-right: 1px solid var(--border-color);
            padding-top: 20px;
        }

        .sidebar h3 {
            color: var(--text-white);
            font-size: 1.5rem;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(100, 255, 218, 0.05);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(100, 255, 218, 0.2);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 255, 218, 0.3);
        }

        .main-content {
            overflow-y: auto;
            height: 100vh;
            padding: 1.5rem;
            margin-left: 350px;
            max-width: calc(100% - 350px);
            box-sizing: border-box;
        }
        
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                max-width: 100%;
                padding: 1rem;
            }
        }

        /* Nav Item Styling */
        .nav-item {
            color: #a0aec0;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            display: flex;
            align-items: center;
            opacity: 0.8;
        }

        .sidebar a:hover, .sidebar a.active {
            background: linear-gradient(135deg, rgba(100, 255, 218, 0.1), rgba(121, 40, 202, 0.1));
            color: var(--accent-color);
            transform: translateX(5px);
            opacity: 1;
        }

        .sidebar a i {
            color: var(--accent-color);
            font-size: 1.2rem;
        }

        .sidebar a.active {
            background: linear-gradient(135deg, rgba(100, 255, 218, 0.2), rgba(121, 40, 202, 0.2));
            border-left: 4px solid var(--accent-color);
        }

        .main-content {
            padding: 30px;
            color: var(--text-white);
        }

        .card {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
        }

        .card-body {
            padding: 1.5rem;
        }

        .table {
            color: var(--text-white);
            margin: 0;
        }

        /* Table Header Colors */
        .table th {
            color: var(--accent-color);
            font-weight: 600;
            border-bottom: 2px solid var(--accent-color);
            padding: 15px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(100, 255, 218, 0.05);
        }

        /* Table Cell Colors */
        .table td {
            color: var(--text-white);
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            background: var(--secondary-bg);
        }

        .table tr:hover td {
            background: rgba(100, 255, 218, 0.05);
        }

        /* Button Enhancements */
        .btn-custom {
            background: linear-gradient(135deg, var(--accent-color) 0%, #4ad3b5 100%);
            color: var(--primary-bg);
            font-weight: 600;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(100, 255, 218, 0.4);
            color: var(--primary-bg);
        }

        .btn-custom i {
            font-size: 1.1rem;
        }

        /* Status Badge Colors */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
        }

        .status-pending {
            color: #ffd700;
            border-color: rgba(255, 215, 0, 0.3);
        }

        .status-in-progress {
            color: var(--accent-color);
            border-color: rgba(100, 255, 218, 0.3);
        }

        .status-completed {
            color: #00ff00;
            border-color: rgba(0, 255, 0, 0.3);
        }

        /* Alert Styles */
        .alert {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--text-white);
            backdrop-filter: blur(10px);
            border-radius: 8px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
        }

        /* Animation Classes */
        .animate__animated {
            animation-duration: 0.6s;
        }

        /* Card and Container Styles */
        .card {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
        }

        .table-responsive {
            background: var(--secondary-bg);
            border-radius: 16px;
            padding: 0;
        }

        /* Delete Button Style */
        .btn-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff4d4d;
        }

        .btn-danger:hover {
            background: rgba(220, 53, 69, 0.3);
            color: #ff4d4d;
        }

        /* Modal Form Labels */
        .form-label {
            color: var(--text-white);
            margin-bottom: 8px;
        }

        /* Table Column Widths */
        .table th:nth-child(1), .table td:nth-child(1) { width: 15%; min-width: 120px; } /* Project */
        .table th:nth-child(2), .table td:nth-child(2) { width: 15%; min-width: 120px; } /* Activity */
        .table th:nth-child(3), .table td:nth-child(3) { width: 25%; min-width: 180px; } /* Description */
        .table th:nth-child(4), .table td:nth-child(4) { width: 15%; min-width: 140px; } /* Target Date */
        .table th:nth-child(5), .table td:nth-child(5) { width: 15%; min-width: 120px; } /* Status */
        .table th:nth-child(6), .table td:nth-child(6) { width: 15%; min-width: 100px; } /* Actions */
        
        .table td {
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: middle;
        }
        
        .table td:nth-child(3) { /* Description column */
            max-width: 300px;
            white-space: normal;
            word-break: break-word;
        }

        /* Status Count Cards */
        .card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .card p {
            color: var(--text-white);
            opacity: 0.8;
            font-size: 0.9rem;
            font-weight: 500;
        }

        h3.status-not-started {
            color: #ffd700;
        }

        h3.status-in-progress {
            color: var(--accent-color);
        }

        h3.status-completed {
            color: #00ff00;
        }

        h3.status-on-hold {
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(100, 255, 218, 0.2);
        }

        /* Custom scrollbar */
        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #4dffd1;
        }
        
        /* Fixed header */
        .table thead th {
            position: sticky;
            top: 0;
            background-color: var(--secondary-bg);
            z-index: 10;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive table container */
        .table-container {
            max-height: calc(100vh - 250px);
            min-height: 300px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        @media (max-width: 992px) {
            .table-container {
                max-height: calc(100vh - 280px);
            }
            
            .table {
                width: 100%;
                margin-bottom: 1rem;
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table thead,
            .table tbody,
            .table th,
            .table td,
            .table tr {
                display: block;
            }
            
            .table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            .table tr {
                margin-bottom: 1rem;
                border: 1px solid var(--border-color);
                border-radius: 8px;
            }
            
            .table td {
                border: none;
                border-bottom: 1px solid var(--border-color);
                position: relative;
                padding-left: 50%;
                width: 100%;
                box-sizing: border-box;
            }
            
            .table td:before {
                position: absolute;
                left: 10px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: var(--accent-color);
            }
            
            .table td:nth-of-type(1):before { content: 'Project'; }
            .table td:nth-of-type(2):before { content: 'Activity'; }
            .table td:nth-of-type(3):before { content: 'Description'; }
            .table td:nth-of-type(4):before { content: 'Date Range'; }
            .table td:nth-of-type(5):before { content: 'Status'; }
            .table td:nth-of-type(6):before { content: 'Actions'; }
            
            .table td:last-child {
                border-bottom: none;
            }
            
            .table td .d-flex {
                justify-content: flex-end;
            }
        }

        .card {
            background: var(--secondary-bg);
            border: none;
            border-radius: 16px;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(100, 255, 218, 0.2);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row h-100">
            <!-- Include Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="main-content animate__animated animate__fadeIn">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                    <h2 class="mb-3 mb-md-0">Activities Management</h2>
                    <button type="button" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                        <i class="bi bi-plus-circle me-2"></i> Add Activity
                    </button>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success animate__animated animate__fadeIn"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger animate__animated animate__fadeIn"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Activities Table -->
                <!-- Compact Filter Form -->
                <div class="card mb-3 animate__animated animate__fadeIn">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-0">Project</label>
                                <select name="project_filter" class="form-select form-select-sm">
                                    <option value="">All Projects</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['id']; ?>" <?php echo (isset($_GET['project_filter']) && $_GET['project_filter'] == $project['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($project['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0">Status</label>
                                <select name="status_filter" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="not started" <?php echo (isset($_GET['status_filter']) && strtolower($_GET['status_filter']) == 'not started') ? 'selected' : ''; ?>>Not Started</option>
                                    <option value="in progress" <?php echo (isset($_GET['status_filter']) && strtolower($_GET['status_filter']) == 'in progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo (isset($_GET['status_filter']) && strtolower($_GET['status_filter']) == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="on hold" <?php echo (isset($_GET['status_filter']) && strtolower($_GET['status_filter']) == 'on hold') ? 'selected' : ''; ?>>On Hold</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0">From</label>
                                <input type="date" name="start_date_filter" class="form-control form-control-sm" value="<?php echo isset($_GET['start_date_filter']) ? htmlspecialchars($_GET['start_date_filter']) : ''; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0">To</label>
                                <input type="date" name="end_date_filter" class="form-control form-control-sm" value="<?php echo isset($_GET['end_date_filter']) ? htmlspecialchars($_GET['end_date_filter']) : ''; ?>">
                            </div>
                            <div class="col-md-3 text-end">
                                <button type="submit" class="btn btn-primary btn-sm me-1">
                                    <i class="bi bi-funnel me-1"></i>Apply
                                </button>
                                <a href="activities.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card animate__animated animate__fadeInUp">
                    <div class="card-body p-0">
                        <div class="table-responsive table-container">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Project
                                            <?php if (isset($_GET['project_filter']) && !empty($_GET['project_filter'])): ?>
                                                <span class="badge bg-info ms-1">Filtered</span>
                                            <?php endif; ?>
                                        </th>
                                        <th>Activity
                                            <?php if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])): ?>
                                                <span class="badge bg-info ms-1"><?php echo ucfirst(htmlspecialchars($_GET['status_filter'])); ?></span>
                                            <?php endif; ?>
                                        </th>
                                        <th>Description</th>
                                        <th>Date Range</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['project_title']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                        <td>
                                            <?php 
                                            $start_date = new DateTime($activity['start_date']);
                                            $end_date = new DateTime($activity['end_date']);
                                            
                                            if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
                                                // Single day
                                                echo $start_date->format('F j, Y');
                                            } else if ($start_date->format('Y-m') === $end_date->format('Y-m')) {
                                                // Same month, different days
                                                echo $start_date->format('F j') . ' - ' . $end_date->format('j, Y');
                                            } else if ($start_date->format('Y') === $end_date->format('Y')) {
                                                // Same year, different months
                                                echo $start_date->format('F j') . ' - ' . $end_date->format('F j, Y');
                                            } else {
                                                // Different years
                                                echo $start_date->format('F j, Y') . ' - ' . $end_date->format('F j, Y');
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $activity['status'])); ?>">
                                                <?php echo ucfirst($activity['status']); ?>
                                            </span>
                                        </td>
                                        <td class="d-flex gap-2">
                                            <button type="button" class="btn btn-primary btn-sm edit-activity" 
                                                data-id="<?php echo $activity['id']; ?>"
                                                data-project-id="<?php echo $activity['project_id']; ?>"
                                                data-title="<?php echo htmlspecialchars($activity['title']); ?>"
                                                data-description="<?php echo htmlspecialchars($activity['description']); ?>"
                                                data-start-date="<?php echo $activity['start_date']; ?>"
                                                data-end-date="<?php echo $activity['end_date']; ?>"
                                                data-status="<?php echo strtolower($activity['status']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editActivityModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this activity?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Activity Modal -->
    <div class="modal fade" id="addActivityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="project_id" class="form-label">Project</label>
                            <select class="form-control" id="project_id" name="project_id" required>
                                <?php if (empty($projects)): ?>
                                    <option value="">No projects available</option>
                                <?php else: ?>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['id']; ?>" 
                                            <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($project['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="title" class="form-label">Activity Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                                <div class="form-text text-muted">End date must be on or after start date.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="">Select Status</option>
                                <?php 
                                $statuses = ['not started', 'in progress', 'completed', 'on hold'];
                                $current_status = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : '';
                                foreach ($statuses as $status): 
                                    $selected = ($current_status === strtolower($status)) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $status; ?>" <?php echo $selected; ?>>
                                        <?php echo ucfirst($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Activity</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Activity Modal -->
    <div class="modal fade" id="editActivityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="activity_id" id="edit_activity_id">
                        <div class="mb-3">
                            <label for="edit_project_id" class="form-label">Project</label>
                            <select class="form-control" id="edit_project_id" name="project_id" required>
                                <?php if (empty($projects)): ?>
                                    <option value="">No projects available</option>
                                <?php else: ?>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['id']; ?>">
                                            <?php echo htmlspecialchars($project['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Activity Title</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
                                <div class="form-text text-muted">End date must be on or after start date.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <?php 
                                $statuses = ['not started', 'in progress', 'completed', 'on hold'];
                                foreach ($statuses as $status): 
                                ?>
                                    <option value="<?php echo $status; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Activity</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Live Clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
            document.querySelectorAll('.live-time').forEach(el => {
                el.textContent = timeString;
            });
        }

        // Update clock immediately and then every minute
        updateClock();
        setInterval(updateClock, 60000);

        // Update date when needed (e.g., at midnight)
        function updateDate() {
            const now = new Date();
            const dateString = now.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
            document.querySelectorAll('.current-date').forEach(el => {
                el.textContent = dateString;
            });
        }

        // Update date on page load
        updateDate();

        document.addEventListener('DOMContentLoaded', function() {
            // Add Activity Form
            const addActivityForm = document.querySelector('#addActivityModal form');
            const addStartDateInput = document.getElementById('start_date');
            const addEndDateInput = document.getElementById('end_date');
            
            // Edit Activity Form
            const editActivityForm = document.querySelector('#editActivityModal form');
            const editStartDateInput = document.getElementById('edit_start_date');
            const editEndDateInput = document.getElementById('edit_end_date');

            // Function to setup date validation
            function setupDateValidation(startInput, endInput, form) {
                // Update end date min when start date changes
                if (startInput && endInput) {
                    startInput.addEventListener('change', function() {
                        if (this.value) {
                            endInput.min = this.value;
                            if (endInput.value && new Date(endInput.value) < new Date(this.value)) {
                                endInput.value = this.value;
                            }
                        }
                    });
                    
                    // Initialize end date min if start date is already set
                    if (startInput.value) {
                        endInput.min = startInput.value;
                    }
                }
            }

            // Setup validation for add activity form
            if (addActivityForm) {
                setupDateValidation(addStartDateInput, addEndDateInput, addActivityForm);
                
                // Handle form submission with validation
                addActivityForm.addEventListener('submit', function(e) {
                    // Basic validation is handled by HTML5 required attributes
                    // Additional validation for dates
                    const startDate = new Date(addStartDateInput.value);
                    const endDate = new Date(addEndDateInput.value);
                    
                    if (endDate < startDate) {
                        e.preventDefault();
                        alert('End date cannot be before start date.');
                        return false;
                    }
                    
                    // If validation passes, the form will submit normally
                    return true;
                });
            }


            // Setup validation for edit activity form
            if (editActivityForm) {
                setupDateValidation(editStartDateInput, editEndDateInput, editActivityForm);
                
                // Handle form submission with validation
                editActivityForm.addEventListener('submit', function(e) {
                    // Basic validation is handled by HTML5 required attributes
                    // Additional validation for dates
                    const startDate = new Date(editStartDateInput.value);
                    const endDate = new Date(editEndDateInput.value);
                    
                    if (endDate < startDate) {
                        e.preventDefault();
                        alert('End date cannot be before start date.');
                        return false;
                    }
                    
                    // If validation passes, the form will submit normally
                    return true;
                });

                // Handle edit button click
                document.querySelectorAll('.edit-activity').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const projectId = this.getAttribute('data-project-id');
                        const title = this.getAttribute('data-title');
                        const description = this.getAttribute('data-description');
                        const startDate = this.getAttribute('data-start-date');
                        const endDate = this.getAttribute('data-end-date');
                        const status = this.getAttribute('data-status');

                        // Set form values
                        document.getElementById('edit_activity_id').value = id;
                        document.getElementById('edit_project_id').value = projectId;
                        document.getElementById('edit_title').value = title;
                        document.getElementById('edit_description').value = description;
                        document.getElementById('edit_start_date').value = startDate;
                        document.getElementById('edit_end_date').value = endDate;
                        document.getElementById('edit_status').value = status;
                        
                        // Update end date min based on start date
                        if (editStartDateInput && editEndDateInput) {
                            editStartDateInput.dispatchEvent(new Event('change'));
                        }
                    });
                });
            }
        });
    </script>
</body>
</html> 