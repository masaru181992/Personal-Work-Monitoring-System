<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Initialize filter variables
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
$project_filter = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : 'DESC';

// Validate sort parameters
$valid_sort_columns = ['created_at', 'updated_at', 'due_date', 'priority', 'status'];
$valid_sort_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'created_at';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'DESC';
}

// Build the query with filters
$query = "SELECT n.*, u.full_name, p.title as project_title 
         FROM notes n 
         LEFT JOIN users u ON n.user_id = u.id 
         LEFT JOIN projects p ON n.project_id = p.id 
         WHERE 1=1";
$params = [];

// Apply filters
if (!empty($priority_filter)) {
    $query .= " AND n.priority = ?";
    $params[] = $priority_filter;
}

if ($project_filter > 0) {
    $query .= " AND n.project_id = ?";
    $params[] = $project_filter;
}

if (!empty($status_filter)) {
    // Convert status to lowercase and ensure it matches one of the valid ENUM values
    $status_filter = strtolower($status_filter);
    $valid_statuses = ['pending', 'in_progress', 'completed', 'archived'];
    
    if (in_array($status_filter, $valid_statuses)) {
        $query .= " AND n.status = ?";
        $params[] = $status_filter;
    }
}

if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND n.created_at BETWEEN ? AND ?";
    $params[] = $start_date . ' 00:00:00';
    $params[] = $end_date . ' 23:59:59';
}

// Add sorting
$query .= " ORDER BY n.{$sort_by} {$sort_order}";

// Add secondary sort to ensure consistent ordering
if ($sort_by !== 'created_at') {
    $query .= ", n.created_at DESC";
}

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notes = $stmt->fetchAll();

// Fetch projects for linking
$stmt = $pdo->query("SELECT id, title FROM projects WHERE status != 'completed'");
$projects = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System - Notes Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Main Theme Variables */
        :root {
            --primary-bg: #0f172a;
            --secondary-bg: #1e293b;
            --accent-color: #64ffda;
            --accent-secondary: #7c3aed;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --text-secondary: #64748b;
            --border-color: #2d3748;
            --card-bg: #1e293b;
            --card-hover-bg: #334155;
            --hover-bg: rgba(100, 255, 218, 0.1);
            --text-white: #ffffff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark-blue: #1e40af;
            --indigo: #4f46e5;
            --purple: #7c3aed;
        }

        body {
            background-color: var(--primary-bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(100, 255, 218, 0.1) 0%, transparent 50%),
                radial-gradient(at 100% 0%, rgba(121, 40, 202, 0.1) 0%, transparent 50%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--text-light);
            min-height: 100vh;
        }

        .main-content {
            padding: 2rem;
            margin-left: 350px;
            max-width: calc(100% - 350px);
            transition: all 0.3s ease;
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                max-width: 100%;
                padding: 1.5rem;
            }
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            color: var(--text-light);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--accent-color);
        }

        .card-header {
            background: rgba(30, 41, 59, 0.9);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .card-header h5 {
            color: var(--accent-color);
            font-weight: 600;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: var(--accent-color);
        }


        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            border-radius: 6px;
        }

        .status-badge {
            padding: 0.35em 0.8em;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .status-badge i {
            font-size: 0.7em;
        }

        .status-high {
            color: #f87171;
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-medium {
            color: #fbbf24;
            background-color: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-low {
            color: #60a5fa;
            background-color: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .btn-custom {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-secondary));
            border: none;
            color: #0f172a;
            font-weight: 600;
            padding: 0.625rem 1.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            color: #0f172a;
            opacity: 0.95;
        }

        .btn-outline-secondary {
            color: var(--text-muted);
            border-color: var(--border-color);
        }

        .btn-outline-secondary:hover {
            background: var(--hover-bg);
            color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .form-control, .form-select {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            border-radius: 8px;
            padding: 8px 12px;
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--card-bg);
            color: var(--text-dark);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(100, 255, 218, 0.25);
        }

        .table {
            color: var(--text-light);
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            background-color: rgba(30, 41, 59, 0.8);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .table td {
            padding: 1.25rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-light);
            background-color: var(--card-bg);
            transition: all 0.2s ease;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover td {
            background-color: rgba(100, 255, 218, 0.05);
            transform: scale(1.01);
        }

        .table-hover tbody tr:hover {
            background-color: transparent;
        }

        .table-hover tbody tr:hover td {
            color: var(--accent-color);
        }

        .table-responsive {
            border-radius: 0 0 10px 10px;
            overflow: hidden;
        }

        @media (max-width: 992px) {
            .table-responsive {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                position: relative;
            }

            .table-responsive.table-container {
                display: block;
            }

            .table-responsive.table-container thead th {
                position: sticky;
                top: 0;
                background-color: var(--card-bg);
                z-index: 10;
                border-bottom: 2px solid var(--border-color);
            }

            .table-responsive.table-container thead th::after {
                content: '';
                position: absolute;
                left: 0;
                right: 0;
                bottom: -2px;
                height: 2px;
                background-color: var(--border-color);
            }

            /* Ensure table header has proper background when scrolling */
            .table-hover > tbody > tr:hover > * {
                --bs-table-accent-bg: var(--hover-bg);
                color: var(--text-light);
            }
            
            .table thead {
                display: none;
            }
            
            .table, .table tbody, .table tr, .table td {
                display: block;
                width: 100%;
            }
            
            .table tr {
                margin-bottom: 1rem;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                overflow: hidden;
            }
            
            .table td {
                text-align: right;
                padding-left: 50%;
                position: relative;
                border-top: 1px solid var(--border-color);
            }
            
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 1rem;
                width: 45%;
                padding-right: 1rem;
                font-weight: 600;
                color: var(--accent-color);
                text-align: left;
            }
            
            .table td:first-child {
                border-top: none;
            }
            
            .table td:last-child {
                text-align: right;
                padding-right: 1rem;

            .btn-close {
                filter: invert(1) brightness(0.8);
                opacity: 0.8;
                transition: opacity 0.2s ease;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }

        .btn-close {
            filter: invert(1) brightness(0.8);
            opacity: 0.8;
            transition: all 0.2s ease;
            background: none;
            font-size: 1.25rem;
            padding: 0.5rem;
            margin: -0.5rem -0.5rem -0.5rem auto;
        }

        .btn-close:hover {
            opacity: 1;
            filter: invert(1) brightness(1);
            transform: rotate(90deg);
        }
        
        /* Modal Styles */
        .modal-content {
            background-color: var(--card-bg);
            color: var(--text-light);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background-color: rgba(30, 41, 59, 0.9);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            backdrop-filter: blur(10px);
        }

        .modal-title {
            color: var(--accent-color);
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 1.25rem;
            margin: 0;
        }

        .modal-body {
            padding: 1.5rem;
            background-color: var(--card-bg);
        }

        .modal-footer {
            background-color: rgba(30, 41, 59, 0.9);
            border-top: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            backdrop-filter: blur(10px);
        }

        /* Form Controls */
        .form-control, .form-select {
            background-color: rgba(30, 41, 59, 0.5);
            border: 1px solid var(--border-color);
            color: var(--text-light);
            border-radius: 8px;
            padding: 0.6rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(30, 41, 59, 0.8);
            color: var(--text-light);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(100, 255, 218, 0.15);
        }

        .form-label {
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        /* Empty State */
        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            color: var(--accent-color);
            opacity: 0.7;
        }

        /* Responsive Utilities */
        @media (max-width: 768px) {
            .filters .col-md-3, 
            .filters .col-md-4,
            .filters .col-md-2 {
                margin-bottom: 1rem;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row h-100">
            <!-- Include Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Main Content -->
            <!-- Main Content -->
            <div class="main-content animate__animated animate__fadeIn">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                    <h2 class="mb-3 mb-md-0">Notes Management</h2>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small">Sort by:</span>
                        <form method="GET" class="d-flex gap-2">
                            <!-- Keep existing filter values -->
                            <input type="hidden" name="priority" value="<?php echo htmlspecialchars($priority_filter); ?>">
                            <input type="hidden" name="project_id" value="<?php echo $project_filter; ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            
                            <select name="sort_by" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                <option value="updated_at" <?php echo $sort_by === 'updated_at' ? 'selected' : ''; ?>>Last Updated</option>
                                <option value="priority" <?php echo $sort_by === 'priority' ? 'selected' : ''; ?>>Priority</option>
                                <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                            </select>
                            
                            <select name="sort_order" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                        </form>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="card mb-4 animate__animated animate__fadeInUp">
                    <div class="card-body">
                        <form action="" method="GET" id="filterForm" class="row g-3">
                            <div class="col-md-6 col-lg-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="">All Priorities</option>
                                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label for="project_id" class="form-label">Project</label>
                                <select class="form-select" id="project_id" name="project_id">
                                    <option value="0">All Projects</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" <?php echo $project_filter == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                                        <span class="badge bg-info">Pending</span>
                                    </option>
                                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>
                                        <span class="badge bg-warning">In Progress</span>
                                    </option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>
                                        <span class="badge bg-success">Completed</span>
                                    </option>
                                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>
                                        <span class="badge bg-secondary">Archived</span>
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6 col-lg-3 d-flex align-items-end justify-content-end">
                                <div class="d-flex gap-2 w-100">
                                    <button type="button" class="btn btn-outline-secondary flex-grow-1" id="resetFilters">
                                        <i class="bi bi-x-circle me-1"></i>Reset
                                    </button>
                                    <button type="submit" class="btn btn-custom flex-grow-1">
                                        <i class="bi bi-filter me-1"></i>Apply
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($success_message) && !empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message) && !empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Notes Table -->
                <div class="card animate__animated animate__fadeInUp">
                    <div class="card-body p-0">
                        <div class="table-responsive table-container" style="max-height: 70vh; overflow-y: auto;">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 20%">Title</th>
                                        <th style="width: 30%">Content</th>
                                        <th style="width: 10%">Priority</th>
                                        <th style="width: 15%">Project</th>
                                        <th style="width: 10%">Status</th>
                                        <th style="width: 10%">Modified At</th>
                                        <th style="width: 5%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notes as $note): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($note['title']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($note['content'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $note['priority'] === 'high' ? 'bg-danger' : ($note['priority'] === 'medium' ? 'bg-warning' : 'bg-info'); ?>">
                                                <?php echo ucfirst($note['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($note['project_id']): ?>
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($note['project_title']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                if($note['status'] === 'pending') {
                                                    echo 'bg-info';
                                                } elseif($note['status'] === 'in_progress') {
                                                    echo 'bg-warning';
                                                } elseif($note['status'] === 'completed') {
                                                    echo 'bg-success';
                                                } else {
                                                    echo 'bg-secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $note['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($note['updated_at'])); ?></td>
                                        <td class="text-center">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <button type="button" class="btn btn-primary btn-sm edit-note" 
                                                    data-id="<?php echo $note['id']; ?>" 
                                                    title="Edit Note">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm delete-note" 
                                                    data-id="<?php echo $note['id']; ?>" 
                                                    title="Delete Note">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($notes)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No notes found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

  
    <!-- Edit Note Modal -->
    <div class="modal fade" id="editNoteModal" tabindex="-1" aria-labelledby="editNoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editNoteModalLabel">Edit Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editNoteForm">
                        <input type="hidden" name="id" id="editNoteId">
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="editTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="editContent" class="form-label">Content</label>
                            <textarea class="form-control" id="editContent" name="content" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editPriority" class="form-label">Priority</label>
                                    <select class="form-select" id="editPriority" name="priority" required>
                                        <option value="high">High</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editStatus" class="form-label">Status</label>
                                    <select class="form-select" id="editStatus" name="status" required>
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editProject" class="form-label">Related Project (Optional)</label>
                            <select class="form-select" id="editProject" name="project_id">
                                <option value="">None</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>">
                                    <?php echo htmlspecialchars($project['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editReminderDate" class="form-label">Reminder Date (Optional)</label>
                            <input type="date" class="form-control" id="editReminderDate" name="reminder_date">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveNoteChanges">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage">
                Operation completed successfully!
            </div>
        </div>
    </div>
    
    <!-- Error Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="errorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-danger text-white">
                <strong class="me-auto">Error</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="errorToastMessage">
                An error occurred while processing your request.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Set current date
        document.addEventListener('DOMContentLoaded', function() {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', options);

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });

            // Initialize datepickers
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                if (!input.value) {
                    const today = new Date().toISOString().split('T')[0];
                    input.value = today;
                }
            });

        });

        // Handle delete note button click
        document.addEventListener('click', async function(e) {
            if (e.target.closest('.delete-note')) {
                const button = e.target.closest('.delete-note');
                const noteId = button.dataset.id;
                
                if (!confirm('Are you sure you want to delete this note? This action cannot be undone.')) {
                    return;
                }
                
                try {
                    const originalHtml = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                    
                    const response = await fetch('api/delete_note.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `id=${noteId}`
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success message
                        const toast = new bootstrap.Toast(document.getElementById('successToast'));
                        const toastMessage = document.getElementById('toastMessage');
                        toastMessage.textContent = 'Note deleted successfully!';
                        toast.show();
                        
                        // Remove the deleted note row from the table
                        button.closest('tr').remove();
                    } else {
                        throw new Error(result.message || 'Failed to delete note');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                    const toastMessage = document.getElementById('errorToastMessage');
                    toastMessage.textContent = 'Error: ' + (error.message || 'Failed to delete note');
                    toast.show();
                    
                    // Reset button state on error
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = '<i class="bi bi-trash"></i>';
                    }
                }
            }
        });

        // Handle edit note button click
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-note')) {
                const button = e.target.closest('.edit-note');
                const noteId = button.dataset.id;
                
                // Show loading state
                const originalHtml = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                
                // Fetch note details
                fetch(`api/get_note.php?id=${noteId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const note = data.data;
                            
                            // Populate form
                            document.getElementById('editNoteId').value = note.id;
                            document.getElementById('editTitle').value = note.title;
                            document.getElementById('editContent').value = note.content;
                            document.getElementById('editPriority').value = note.priority;
                            document.getElementById('editStatus').value = note.status;
                            
                            if (note.project_id) {
                                document.getElementById('editProject').value = note.project_id;
                            }
                            
                            if (note.reminder_date) {
                                const reminderDate = new Date(note.reminder_date);
                                document.getElementById('editReminderDate').value = reminderDate.toISOString().split('T')[0];
                            } else {
                                document.getElementById('editReminderDate').value = '';
                            }
                            
                            // Show modal
                            const modal = new bootstrap.Modal(document.getElementById('editNoteModal'));
                            modal.show();
                        } else {
                            throw new Error(data.message || 'Failed to load note');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                        const toastMessage = document.getElementById('errorToastMessage');
                        toastMessage.textContent = 'Error: ' + (error.message || 'Failed to load note');
                        toast.show();
                    })
                    .finally(() => {
                        button.disabled = false;
                        button.innerHTML = originalHtml;
                    });
            }
        });

        // Handle save note changes
        document.getElementById('saveNoteChanges')?.addEventListener('click', function() {
            const form = document.getElementById('editNoteForm');
            const formData = new FormData(form);
            const submitBtn = document.getElementById('saveNoteChanges');
            const originalBtnText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            
            // Send update request
            fetch('api/update_note.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const toast = new bootstrap.Toast(document.getElementById('successToast'));
                    const toastMessage = document.getElementById('toastMessage');
                    toastMessage.textContent = 'Note updated successfully!';
                    toast.show();
                    
                    // Close modal and reload page
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editNoteModal'));
                    modal.hide();
                    
                    // Reload after a short delay
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(data.message || 'Failed to update note');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                const toastMessage = document.getElementById('errorToastMessage');
                toastMessage.textContent = 'Error: ' + (error.message || 'Failed to update note');
                toast.show();
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
        
        // Handle reset filters button
        document.getElementById('resetFilters')?.addEventListener('click', function() {
            // Reset all form fields
            document.getElementById('priority').value = '';
            document.getElementById('project_id').value = '0';
            document.getElementById('status').value = '';
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            
            // Submit the form to refresh with no filters
            document.getElementById('filterForm').submit();
        });
        
        document.addEventListener('DOMContentLoaded', () => {
            // Set default date to current date for all date inputs
            const dateInputs = elements.dateInputs;
            const today = new Date().toISOString().split('T')[0];
            
            dateInputs.forEach(input => {
                input.min = today;
                input.value = today;
                
                // Add change event listener to validate dates
                input.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const currentDate = new Date();
                    
                    if (selectedDate < currentDate) {
                        this.value = today;
                        alert('Please select a date from today onwards');
                    }
                });
            });
            
            sortNotes();
            initializeEventListeners();
        });

        // Initialize event listeners
        function initializeEventListeners() {
            // Save note button click
            elements.saveNoteBtn.addEventListener('click', handleSaveNote);



            // Toggle view function
            elements.toggleView.addEventListener('click', () => {
                elements.notesGrid.classList.toggle('d-none');
                elements.notesList.classList.toggle('d-none');
                const icon = elements.toggleView.querySelector('i');
                icon.classList.toggle('bi-grid');
                icon.classList.toggle('bi-list');
            });

            // Filter and sort listeners
            elements.searchInput.addEventListener('input', filterNotes);
            elements.priorityFilter.addEventListener('change', filterNotes);
            elements.projectFilter.addEventListener('change', filterNotes);
            elements.sortSelect.addEventListener('change', sortNotes);

            // Reset form when modal is closed
            elements.addNoteModal.addEventListener('hidden.bs.modal', () => {
                elements.noteForm.reset();
                currentNoteId = null;
                elements.saveNoteBtn.textContent = 'Save Note';
            });


        }

        // Handle save note (for new notes)
        async function handleSaveNote() {
            const formData = new FormData(elements.noteForm);
            const noteData = {
                title: formData.get('title'),
                content: formData.get('content'),
                priority: formData.get('priority'),
                project_id: formData.get('project_id') || null,
                reminder_date: formData.get('reminder_date') || null
            };

            try {
                const response = await fetch('api/save_note.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(noteData)
                });

                const result = await response.json();

                if (result.success) {
                    // Close modal and refresh page
                    const modal = bootstrap.Modal.getInstance(elements.addNoteModal);
                    modal.hide();
                    window.location.reload();
                } else {
                    throw new Error(result.message || 'Failed to save note');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }





        // Filter notes function
        function filterNotes() {
            const searchTerm = elements.searchInput.value.toLowerCase();
            const priority = elements.priorityFilter.value.toLowerCase();
            const projectId = elements.projectFilter.value;
            
            const notes = document.querySelectorAll('.note-card');
            notes.forEach(note => {
                const title = note.querySelector('h5').textContent.toLowerCase();
                const content = note.querySelector('p').textContent.toLowerCase();
                const notePriority = note.dataset.priority;
                const noteProject = note.querySelector('.note-project')?.dataset.projectId || '';

                const matchesSearch = title.includes(searchTerm) || content.includes(searchTerm);
                const matchesPriority = !priority || notePriority === priority;
                const matchesProject = !projectId || noteProject === projectId;

                note.style.display = matchesSearch && matchesPriority && matchesProject ? '' : 'none';
            });
        }

        // Sort notes function
        function sortNotes() {
            const sortBy = elements.sortSelect.value;
            const notes = Array.from(document.querySelectorAll('.note-card'));
            const container = elements.notesGrid;

            notes.sort((a, b) => {
                if (sortBy === 'priority') {
                    const priorityOrder = { high: 3, medium: 2, low: 1 };
                    return priorityOrder[b.dataset.priority] - priorityOrder[a.dataset.priority];
                } else {
                    const dateA = new Date(a.dataset.date);
                    const dateB = new Date(b.dataset.date);
                    return dateB - dateA;
                }
            });

            notes.forEach(note => container.appendChild(note));
        }

        // Set initial sort to date
        elements.sortSelect.value = 'date';
    </script>
</body>
</html> 