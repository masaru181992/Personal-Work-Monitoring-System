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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $title = $_POST['title'];
            $description = $_POST['description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];

            $stmt = $pdo->prepare("INSERT INTO projects (title, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$title, $description, $start_date, $end_date, $status])) {
                $success_message = "Project added successfully!";
            } else {
                $error_message = "Error adding project.";
            }
        } elseif ($_POST['action'] == 'update' && isset($_POST['project_id'])) {
            $title = $_POST['title'];
            $description = $_POST['description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];
            $project_id = (int)$_POST['project_id'];

            $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
            if ($stmt->execute([$title, $description, $start_date, $end_date, $status, $project_id])) {
                $success_message = "Project updated successfully!";
            } else {
                $error_message = "Error updating project.";
            }
        } elseif ($_POST['action'] == 'delete' && isset($_POST['project_id'])) {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            if ($stmt->execute([$_POST['project_id']])) {
                $success_message = "Project deleted successfully!";
            } else {
                $error_message = "Error deleting project.";
            }
        }
    }
}

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'title';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Validate sort column
$valid_sorts = ['title', 'created_at', 'status'];
$sort = in_array($sort, $valid_sorts) ? $sort : 'title';
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Fetch all projects with sorting
$stmt = $pdo->query("SELECT * FROM projects ORDER BY $sort $order");
$projects = $stmt->fetchAll();

// Function to get sort link
function getSortLink($column, $label) {
    global $sort, $order;
    $newOrder = ($sort === $column && $order === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    
    if ($sort === $column) {
        $icon = $order === 'ASC' ? ' <i class="bi bi-arrow-up"></i>' : ' <i class="bi bi-arrow-down"></i>';
    } else {
        $icon = ' <i class="bi bi-arrow-down-up"></i>';
    }
    
    return '<a href="?sort=' . $column . '&order=' . $newOrder . '" class="sort-link">' . $label . $icon . '</a>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System - Projects</title>
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
            
            .table td:nth-of-type(1):before { content: 'Title'; }
            .table td:nth-of-type(2):before { content: 'Description'; }
            .table td:nth-of-type(3):before { content: 'Start Date'; }
            .table td:nth-of-type(4):before { content: 'End Date'; }
            .table td:nth-of-type(5):before { content: 'Status'; }
            .table td:nth-of-type(6):before { content: 'Actions'; }
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

        .status-not-started {
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

        .status-on-hold {
            color: #ff9900;
            border-color: rgba(255, 153, 0, 0.3);
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
        .table th:nth-child(1), .table td:nth-child(1) { width: 18%; min-width: 150px; } /* Title */
        .table th:nth-child(2), .table td:nth-child(2) { width: 27%; min-width: 200px; } /* Description */
        .table th:nth-child(3), .table td:nth-child(3) { width: 13%; min-width: 120px; } /* Start Date */
        .table th:nth-child(4), .table td:nth-child(4) { width: 13%; min-width: 120px; } /* End Date */
        .table th:nth-child(5), .table td:nth-child(5) { width: 14%; min-width: 120px; } /* Status */
        .table th:nth-child(6), .table td:nth-child(6) { width: 15%; min-width: 100px; } /* Actions */
        
        .table td {
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: middle;
        }
        
        .table td:nth-child(2) { /* Description column */
            max-width: 300px;
            white-space: normal;
            word-break: break-word;
        }
        
        /* Sorting Controls */
        .btn-sort {
            background: rgba(100, 255, 218, 0.1);
            border: 1px solid rgba(100, 255, 218, 0.2);
            color: #a0aec0;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-sort:hover, .btn-sort.active {
            background: rgba(100, 255, 218, 0.2);
            color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .btn-sort i {
            font-size: 0.9em;
        }
        
        .sorting-controls {
            background: rgba(16, 32, 56, 0.5);
            padding: 10px 15px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .sort-link {
            color: var(--text-white);
            text-decoration: none;
            display: block;
        }
        
        .sort-link:hover {
            color: var(--accent-color);
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
                    <h2 class="mb-3 mb-md-0">Projects Management</h2>
                    <button type="button" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                        <i class="bi bi-plus-circle"></i> Add New Project
                    </button>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success animate__animated animate__fadeIn"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger animate__animated animate__fadeIn"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Sorting Controls -->
                <div class="sorting-controls mb-3 d-flex gap-2 flex-wrap">
                    <span class="text-muted me-2 align-self-center">Sort by:</span>
                    <a href="?sort=title&order=<?php echo $sort === 'title' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>" 
                       class="btn btn-sm btn-sort <?php echo $sort === 'title' ? 'active' : ''; ?>">
                        <i class="bi bi-sort-alpha-down"></i> Name
                        <?php if ($sort === 'title'): ?>
                            <i class="bi bi-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>-short"></i>
                        <?php endif; ?>
                    </a>
                    <a href="?sort=created_at&order=<?php echo $sort === 'created_at' && $order === 'DESC' ? 'ASC' : 'DESC'; ?>" 
                       class="btn btn-sm btn-sort <?php echo $sort === 'created_at' ? 'active' : ''; ?>">
                        <i class="bi bi-calendar-event"></i> Date Added
                        <?php if ($sort === 'created_at'): ?>
                            <i class="bi bi-arrow-<?php echo $order === 'DESC' ? 'down' : 'up'; ?>-short"></i>
                        <?php endif; ?>
                    </a>
                    <a href="?sort=status&order=<?php echo $sort === 'status' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>" 
                       class="btn btn-sm btn-sort <?php echo $sort === 'status' ? 'active' : ''; ?>">
                        <i class="bi bi-filter-square"></i> Status
                        <?php if ($sort === 'status'): ?>
                            <i class="bi bi-arrow-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>-short"></i>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Projects Table -->
                <div class="card animate__animated animate__fadeInUp">
                    <div class="card-body p-0">
                        <div class="table-responsive table-container">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo getSortLink('title', 'Title'); ?></th>
                                        <th>Description</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['description']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($project['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($project['end_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project['status'])); ?>">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                        </td>
                                        <td class="d-flex gap-2">
                                            <button type="button" class="btn btn-primary btn-sm edit-project" 
                                                data-id="<?php echo $project['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($project['title']); ?>"
                                                data-description="<?php echo htmlspecialchars($project['description']); ?>"
                                                data-start-date="<?php echo $project['start_date']; ?>"
                                                data-end-date="<?php echo $project['end_date']; ?>"
                                                data-status="<?php echo $project['status']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editProjectModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this project?')">
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

    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="Not Started">Not Started</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="On Hold">On Hold</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Project</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div class="modal fade" id="editProjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="project_id" id="edit_project_id">
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <option value="Not Started">Not Started</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="On Hold">On Hold</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Project</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
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

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add Project Form
        const addStartDateInput = document.getElementById('start_date');
        const addEndDateInput = document.getElementById('end_date');
        const addForm = document.querySelector('#addProjectModal form');

        // Edit Project Form
        const editStartDateInput = document.getElementById('edit_start_date');
        const editEndDateInput = document.getElementById('edit_end_date');
        const editForm = document.querySelector('#editProjectModal form');

        // Function to handle date validation
        function setupDateValidation(startInput, endInput, form) {
            // Update end date minimum when start date changes
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

            // Validate form submission
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!startInput.value || !endInput.value) {
                        return true; // Let HTML5 validation handle required fields
                    }
                    
                    const startDate = new Date(startInput.value);
                    const endDate = new Date(endInput.value);
                    
                    if (endDate < startDate) {
                        e.preventDefault();
                        alert('End date cannot be earlier than start date');
                        return false;
                    }
                    return true;
                });
            }
        }

        // Setup validation for add project form
        if (addStartDateInput && addEndDateInput) {
            setupDateValidation(addStartDateInput, addEndDateInput, addForm);
        }

        // Setup validation for edit project form
        if (editStartDateInput && editEndDateInput) {
            setupDateValidation(editStartDateInput, editEndDateInput, editForm);
        }

        // Handle edit button click
        document.querySelectorAll('.edit-project').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                const description = this.getAttribute('data-description');
                const startDate = this.getAttribute('data-start-date');
                const endDate = this.getAttribute('data-end-date');
                const status = this.getAttribute('data-status');

                document.getElementById('edit_project_id').value = id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_start_date').value = startDate;
                document.getElementById('edit_end_date').value = endDate;
                document.getElementById('edit_status').value = status;
            });
        });
    });
    </script>
</body>
</html>