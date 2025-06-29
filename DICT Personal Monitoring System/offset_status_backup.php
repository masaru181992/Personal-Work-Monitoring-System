<?php
// Start session and set error reporting
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Function to calculate working days between two dates (excluding weekends)
function calculateWorkingDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day'); // Include the end date in the calculation
    
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $workingDays = 0;
    
    foreach ($period as $date) {
        // Check if the day is not a weekend (Saturday = 6, Sunday = 0)
        if ($date->format('N') < 6) {
            $workingDays++;
        }
    }
    
    return $workingDays;
}

// Initialize variables
$offset_requests = [];
$overtime_requests = [];
$activities = [];
$remaining_offset = 0;
$remaining_overtime = 0;
$pending_requests = 0;
$used_offsets = 0;

try {
    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if tables exist
    $tables = $pdo->query("SHOW TABLES LIKE 'offset_requests'")->rowCount();
    if ($tables == 0) {
        throw new Exception("Required database tables are missing. Please run database migrations.");
    }
    
    // Fetch offset requests
    try {
        $offset_query = $pdo->prepare("
            SELECT orq.*, a.title as activity_title 
            FROM offset_requests orq
            LEFT JOIN activities a ON orq.activity_id = a.id
            WHERE orq.user_id = ?
            ORDER BY orq.offset_date DESC, orq.created_at DESC
        ");
        $offset_query->execute([$user_id]);
        $offset_requests = $offset_query->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in offset query: " . $e->getMessage());
        $offset_requests = [];
    }
    
    // Fetch overtime requests with activity details
    try {
        $overtime_query = $pdo->prepare("
            SELECT orq.*, a.title as activity_title, 
                   a.start_date as activity_start, a.end_date as activity_end
            FROM overtime_requests orq
            LEFT JOIN activities a ON orq.activity_id = a.id
            WHERE orq.user_id = ?
            ORDER BY orq.start_date DESC, orq.created_at DESC
        ");
        $overtime_query->execute([$user_id]);
        $overtime_requests = $overtime_query->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in overtime query: " . $e->getMessage());
        $overtime_requests = [];
    }
    
    // Fetch activities for dropdown
    try {
        // First, check if activities table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'activities'")->rowCount() > 0;
        if (!$tableExists) {
            throw new Exception("Activities table does not exist in the database.");
        }
        
        // Then fetch activities
        $activities_query = $pdo->prepare("SELECT id, title FROM activities WHERE user_id = ? ORDER BY title");
        $activities_query->execute([$user_id]);
        $activities = $activities_query->fetchAll(PDO::FETCH_ASSOC);
        
        // Log for debugging
        error_log("Fetched " . count($activities) . " activities for user $user_id");
        
    } catch (Exception $e) {
        $error_msg = "Error fetching activities: " . $e->getMessage();
        error_log($error_msg);
        $activities = [];
        // Only show error if in development
        if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
            $error_message = (isset($error_message) ? $error_message . "<br>" : "") . $error_msg;
        }
    }
    
        // Calculate remaining offset and overtime - using separate queries for better reliability
    try {
        // Get offset statistics
        $offset_stats = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN status = 'approved' AND (used = 0 OR used IS NULL) THEN 1 ELSE 0 END) as remaining,
                SUM(CASE WHEN used = 1 THEN 1 ELSE 0 END) as used,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM offset_requests 
            WHERE user_id = ?
        ");
        $offset_stats->execute([$user_id]);
        $offset_data = $offset_stats->fetch(PDO::FETCH_ASSOC);
        
        // Get overtime statistics
        $overtime_stats = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN status = 'approved' AND (offset_used = 0 OR offset_used IS NULL) THEN 1 ELSE 0 END) as remaining,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM overtime_requests 
            WHERE user_id = ?
        ");
        $overtime_stats->execute([$user_id]);
        $overtime_data = $overtime_stats->fetch(PDO::FETCH_ASSOC);
        
        // Set the values with proper null checks
        $remaining_offset = $offset_data ? (int)$offset_data['remaining'] : 0;
        $used_offsets = $offset_data ? (int)$offset_data['used'] : 0;
        $pending_offsets = $offset_data ? (int)$offset_data['pending'] : 0;
        $pending_overtime = $overtime_data ? (int)$overtime_data['pending'] : 0;
        $remaining_overtime = $overtime_data ? (int)$overtime_data['remaining'] : 0;
        
        // Calculate total pending requests
        $pending_requests = $pending_offsets + $pending_overtime;
        
    } catch (PDOException $e) {
        error_log("Statistics query error: " . $e->getMessage());
        // Continue with default values if there's an error
        $remaining_offset = 0;
        $used_offsets = 0;
        $remaining_overtime = 0;
        $pending_requests = 0;
    }
    
} catch (PDOException $e) {
    $error_details = "Database error in offset_status.php: " . $e->getMessage() . 
                   "\nSQL Error Code: " . $e->getCode() . 
                   "\nFile: " . $e->getFile() . ":" . $e->getLine();
    error_log($error_details);
    
    // More detailed error for local development
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        $error_message = "<h4>Database Error</h4>";
        $error_message .= "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        $error_message .= "<p><strong>SQL Error Code:</strong> " . $e->getCode() . "</p>";
        $error_message .= "<p><small>File: " . $e->getFile() . " (Line: " . $e->getLine() . ")</small></p>";
    } else {
        $error_message = "An error occurred while fetching data. Please try again later.";
    }
} catch (Exception $e) {
    error_log("General error in offset_status.php: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DICT Project Monitoring System - Offset Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
        
        .main-content {
            padding: 2rem;
            transition: all 0.3s ease;
            background: transparent;
        }
        
        .card {
            background-color: var(--secondary-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--primary-bg);
            color: var(--text-white);
            padding: 10px;
            border-radius: 10px 10px 0 0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .table {
            background-color: var(--secondary-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .table th, .table td {
            padding: 10px;
            border: none;
            border-radius: 10px;
        }
        
        .table th {
            background-color: var(--primary-bg);
            color: var(--text-white);
        }
        
        .table td {
            background-color: var(--secondary-bg);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-10 ms-auto p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-2">Overtime & Offset Management</h2>
                        <p class="text-muted mb-0">View and manage your overtime and offset requests</p>
                    </div>
                    <div class="text-muted">
                        <i class="bi bi-calendar3 me-1"></i> <?php echo date('F j, Y'); ?>
                    </div>
                </div>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <!-- Remaining Offset -->
                    <div class="col-md-3 mb-3">
                        <div class="card bg-white rounded-3 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase text-muted small mb-2">Remaining Offset</h6>
                                        <h3 class="mb-0"><?php echo $remaining_offset; ?> <small class="text-muted">days</small></h3>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-clock-history text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Remaining Overtime -->
                    <div class="col-md-3 mb-3">
                        <div class="card bg-white rounded-3 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase text-muted small mb-2">Remaining Overtime</h6>
                                        <h3 class="mb-0"><?php echo $remaining_overtime; ?> <small class="text-muted">days</small></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-stopwatch text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Requests -->
                    <div class="col-md-3 mb-3">
                        <div class="card bg-white rounded-3 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase text-muted small mb-2">Pending Requests</h6>
                                        <h3 class="mb-0"><?php echo $pending_requests; ?> <small class="text-muted">requests</small></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-hourglass-split text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Used Offsets -->
                    <div class="col-md-3 mb-3">
                        <div class="card bg-white rounded-3 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase text-muted small mb-2">Used Offsets</h6>
                                        <h3 class="mb-0"><?php echo $used_offsets; ?> <small class="text-muted">days</small></h3>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-check-circle text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Overtime and Offset Tables -->
                <div class="row">
                    <!-- Offset Requests -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Offset Requests</h5>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOffsetModal">
                                    <i class="bi bi-plus-lg me-1"></i> Add Request
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($offset_requests)): ?>
                                    <div class="text-center p-4 text-muted">
                                        <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                        No offset requests found
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Activity</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($offset_requests as $request): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($request['activity_title'] ?? 'N/A'); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($request['offset_date'])); ?></td>
                                                        <td>
                                                            <?php
                                                            $status_class = [
                                                                'pending' => 'bg-warning',
                                                                'approved' => 'bg-success',
                                                                'rejected' => 'bg-danger'
                                                            ][$request['status']] ?? 'bg-secondary';
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($request['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if ($request['status'] === 'pending'): ?>
                                                                <button class="btn btn-sm btn-outline-danger" 
                                                                        onclick="deleteRequest('offset', <?php echo $request['id']; ?>)">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Overtime Requests -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Overtime Requests</h5>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOvertimeModal">
                                    <i class="bi bi-plus-lg me-1"></i> Add Request
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($overtime_requests)): ?>
                                    <div class="text-center p-4 text-muted">
                                        <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                        No overtime requests found
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Activity</th>
                                                    <th>Period</th>
                                                    <th>Status</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($overtime_requests as $request): 
                                                    $days = calculateWorkingDays($request['start_date'], $request['end_date']);
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($request['activity_title'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <?php echo date('M d', strtotime($request['start_date'])); ?> - 
                                                            <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                                            <small class="d-block text-muted"><?php echo $days; ?> working days</small>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status_class = [
                                                                'pending' => 'bg-warning',
                                                                'approved' => 'bg-success',
                                                                'rejected' => 'bg-danger'
                                                            ][$request['status']] ?? 'bg-secondary';
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($request['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if ($request['status'] === 'pending'): ?>
                                                                <button class="btn btn-sm btn-outline-primary me-1" 
                                                                        onclick="editRequest('overtime', <?php echo $request['id']; ?>)">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" 
                                                                        onclick="deleteRequest('overtime', <?php echo $request['id']; ?>)">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Offset Modal -->
    <div class="modal fade" id="addOffsetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="offsetForm" action="save_offset.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Offset Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Activity</label>
                            <select name="activity_id" class="form-select" required>
                                <option value="" disabled selected>Select an activity</option>
                                <?php foreach ($activities as $activity): ?>
                                    <option value="<?php echo $activity['id']; ?>">
                                        <?php echo htmlspecialchars($activity['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Offset Date</label>
                            <input type="date" name="offset_date" class="form-control" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Overtime Modal -->
    <div class="modal fade" id="addOvertimeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="overtimeForm" action="save_overtime.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Overtime Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Activity</label>
                            <select name="activity_id" class="form-select" required>
                                <option value="" disabled selected>Select an activity</option>
                                <?php foreach ($activities as $activity): ?>
                                    <option value="<?php echo $activity['id']; ?>">
                                        <?php echo htmlspecialchars($activity['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to handle delete request
        function deleteRequest(type, id) {
            if (confirm('Are you sure you want to delete this ' + type + ' request?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_' + type + '.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id';
                input.value = id;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Function to handle edit request
        function editRequest(type, id) {
            // In a real application, you would fetch the request details and populate the form
            // For now, we'll just show an alert
            alert('Edit functionality will be implemented here for ' + type + ' ID: ' + id);
        }
        
        // Initialize date pickers with today's date as default
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="offset_date"]').min = today;
            document.querySelector('input[name="start_date"]').min = today;
            document.querySelector('input[name="end_date"]').min = today;
        });
    </script>
</body>
</html>
