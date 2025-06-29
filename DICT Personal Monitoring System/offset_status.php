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
    
    // Fetch all activities for dropdowns
    try {
        // For overtime modal - all activities
        $activities_query = $pdo->query("SELECT id, title, start_date, end_date FROM activities ORDER BY start_date DESC, title ASC");
        $activities = $activities_query->fetchAll(PDO::FETCH_ASSOC);
        
        // For offset modal - only activities from approved overtime requests
        $offset_activities_query = $pdo->prepare("
            SELECT DISTINCT a.id, a.title, a.start_date, a.end_date 
            FROM activities a
            INNER JOIN overtime_requests orq ON a.id = orq.activity_id
            WHERE orq.user_id = ? AND orq.status = 'approved'
            ORDER BY a.start_date DESC, a.title ASC
        ");
        $offset_activities_query->execute([$user_id]);
        $offset_activities = $offset_activities_query->fetchAll(PDO::FETCH_ASSOC);
        
        // Log the number of activities found for debugging
        error_log("Fetched " . count($activities) . " activities for overtime and " . count($offset_activities) . " for offset");
        
    } catch (PDOException $e) {
        error_log("Error in activities queries: " . $e->getMessage());
        $activities = [];
        $offset_activities = [];
        $error_message = "Error loading activities. Please try again later.";
    }
    
        // Calculate statistics for the score cards
    try {
        // Get offset statistics
        $offset_stats = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as total_approved,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as total_pending,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as total_rejected,
                COUNT(*) as total_count
            FROM offset_requests 
            WHERE user_id = ?
        ");
        $offset_stats->execute([$user_id]);
        $offset_data = $offset_stats->fetch(PDO::FETCH_ASSOC);
        
        // Get overtime statistics
        $overtime_stats = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'approved' AND (used_days < total_days OR used_days IS NULL) THEN 1 END) as remaining_overtime,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_overtime,
                COALESCE(SUM(total_days - COALESCE(used_days, 0)), 0) as total_remaining_days,
                (SELECT COALESCE(SUM(total_days), 0) FROM overtime_requests WHERE user_id = ? AND status = 'pending') as total_pending_days
            FROM overtime_requests 
            WHERE user_id = ? AND status = 'approved'
        ");
        $overtime_stats->execute([$user_id, $user_id]);
        $overtime_data = $overtime_stats->fetch(PDO::FETCH_ASSOC);
        
        // Get used offsets (from overtime requests)
        $used_offsets_query = $pdo->prepare("
            SELECT COALESCE(SUM(used_days), 0) as total_used_days
            FROM overtime_requests 
            WHERE user_id = ? AND status = 'approved' AND used_days > 0
        ");
        $used_offsets_query->execute([$user_id]);
        $used_offsets_data = $used_offsets_query->fetch(PDO::FETCH_ASSOC);
        
        // Calculate the values for the score cards
        $remaining_offset = $overtime_data ? (int)$overtime_data['total_remaining_days'] : 0;
        $used_offsets = $used_offsets_data ? (int)$used_offsets_data['total_used_days'] : 0;
        $pending_offsets = $offset_data ? (int)$offset_data['total_pending'] : 0;
        $pending_overtime = $overtime_data ? (int)$overtime_data['pending_overtime'] : 0;
        $pending_requests = $pending_offsets + $pending_overtime;
        $total_pending_days = $overtime_data ? (int)$overtime_data['total_pending_days'] : 0;
        $total_offsets = $offset_data ? (int)$offset_data['total_count'] : 0;
        
        // Calculate remaining overtime (in hours) - assuming 8 hours per day
        $remaining_overtime_hours = $remaining_offset * 8; // Convert days to hours
        $remaining_overtime = max(0, $remaining_overtime_hours); // Ensure non-negative value
        
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
    <title>The Personal Monitoring System - Offset Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-bg: #0a192f;
            --secondary-bg: rgba(16, 32, 56, 0.9);
            --accent-color: #64ffda;
            --accent-secondary: #7928ca;
            --text-primary: #e6f1ff;
            --text-secondary: #8892b0;
        }
        
        body {
            background-color: var(--primary-bg);
            color: var(--text-primary);
        }
        
        /* Custom styles for offset and overtime sections */
        .request-container {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .request-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 500px;
            max-height: 600px;
            background: var(--secondary-bg);
            border: 1px solid rgba(100, 255, 218, 0.1);
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .request-card .card-header {
            background: rgba(16, 32, 56, 0.8);
            border-bottom: 1px solid rgba(100, 255, 218, 0.1);
            color: var(--accent-color);
            font-weight: 500;
            padding: 1rem 1.5rem;
        }
        
        .request-card .card-body {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            background: rgba(16, 32, 56, 0.6);
        }
        
        .request-table {
            margin-bottom: 0;
            color: var(--text-primary);
        }
        
        .request-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table {
            margin-bottom: 0;
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-primary);
            --bs-table-hover-color: var(--text-primary);
            --bs-table-hover-bg: rgba(100, 255, 218, 0.05);
        }
        
        .table th {
            font-weight: 600;
            color: var(--accent-color);
            background-color: rgba(16, 32, 56, 0.9);
            border-bottom: 1px solid rgba(100, 255, 218, 0.1);
            padding: 1rem 1.5rem;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .table td {
            vertical-align: middle;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(100, 255, 218, 0.05);
            color: var(--text-secondary);
        }
        
        .table tr:hover td {
            background: rgba(100, 255, 218, 0.05);
            color: var(--text-primary);
        }
        
        /* Custom scrollbar for table */
        .request-card .card-body::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .request-card .card-body::-webkit-scrollbar-track {
            background: rgba(100, 255, 218, 0.05);
            border-radius: 3px;
        }
        
        .request-card .card-body::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 3px;
        }
        
        /* Empty state styling */
        .text-muted {
            color: var(--text-secondary) !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .request-card {
                min-height: 400px;
                max-height: 500px;
            }
            
            .table th, .table td {
                padding: 0.75rem 1rem;
            }
        }
    </style>
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
        
        /* Glassmorphism Card Styles */
        .card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            position: relative;
            z-index: 1;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.01));
            z-index: -1;
            border-radius: 16px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .card:hover::before {
            opacity: 1;
        }
        .card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25), 0 10px 10px rgba(0, 0, 0, 0.1);
            border-color: rgba(255, 255, 255, 0.15);
        }
        .card-header {
            background: rgba(15, 23, 42, 0.7);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1.25rem 1.5rem;
        }
        .card-title {
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0;
            color: #f8fafc;
        }
        .stat-number {
            font-size: 2.4rem;
            font-weight: 700;
            line-height: 1.2;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0.5rem 0;
            position: relative;
            display: inline-block;
            text-shadow: 0 2px 10px rgba(99, 102, 241, 0.3);
        }
        
        .stat-number::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        .card:hover .stat-number::after {
            width: 60px;
        }
        .stat-label {
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .card:hover .stat-label {
            color: rgba(255, 255, 255, 0.9);
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1.25rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.05));
            z-index: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .card:hover .stat-icon {
            transform: rotate(5deg) scale(1.1);
        }
        
        .card:hover .stat-icon::before {
            opacity: 1;
        }
        
        .stat-icon i {
            position: relative;
            z-index: 1;
        }
        .stat-card {
            padding: 1.75rem 1.5rem;
            border-radius: 16px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            opacity: 0;
            transition: opacity 0.6s ease;
            pointer-events: none;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .stat-card:hover::after {
            opacity: 1;
        }
        
        /* Animated gradient border effect */
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 16px;
            padding: 2px;
            background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .card-header {
            background-color: var(--primary-bg);
            color: var(--text-white);
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
            color: #e2e8f0;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        
        .table th {
            border-top: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            color: #94a3b8;
            padding: 0.85rem 1.5rem;
            background: rgba(15, 23, 42, 0.5);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table td {
            vertical-align: middle;
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            background: rgba(15, 23, 42, 0.3);
            transition: all 0.2s ease;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table-hover tbody tr:hover td {
            background: rgba(255, 255, 255, 0.04);
            transform: translateX(2px);
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 600;
            border-radius: 6px;
            letter-spacing: 0.3px;
        }
        
        .status-pending {
            background: rgba(251, 191, 36, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.2);
        }
        
        .status-approved {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .status-rejected {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        /* Activity Name */
        .activity-name {
            font-weight: 500;
            color: #f8fafc;
            margin-bottom: 0.25rem;
            display: block;
        }
        
        .activity-date {
            font-size: 0.825rem;
            color: #94a3b8;
            display: block;
        }
        
        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #94a3b8;
            transition: all 0.2s ease;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            transform: translateY(-1px);
        }
        
        .action-btn.view {
            color: #60a5fa;
        }
        
        .action-btn.edit {
            color: #fbbf24;
        }
        
        .action-btn.delete {
            color: #f87171;
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
                <div class="row g-4 mb-5">
                    <!-- Remaining Offset -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4f46e5, #7c3aed);">
                            <div class="card-body position-relative overflow-hidden p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                        <h6 class="text-uppercase text-white-80 fw-semibold mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">REMAINING OFFSET</h6>
                                        <h2 class="text-white mb-0 d-flex align-items-baseline">
                                            <span class="display-6 fw-bold me-2"><?php echo $remaining_offset; ?></span>
                                            <span class="h5 text-white-60">days</span>
                                        </h2>
                                    </div>
                                    <div class="bg-white-20 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                        <i class="bi bi-hourglass-split text-white" style="font-size: 1.75rem;"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-white-70 fw-medium">Monthly Limit</small>
                                        <small class="fw-bold text-white"><?php echo number_format(($remaining_offset / 24) * 100, 1); ?>%</small>
                                    </div>
                                    <div class="progress" style="height: 8px; border-radius: 4px; background: rgba(255, 255, 255, 0.15);">
                                        <div class="progress-bar bg-white" role="progressbar" 
                                             style="width: <?php echo min(100, ($remaining_offset / 24) * 100); ?>%" 
                                             aria-valuenow="<?php echo $remaining_offset; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="24">
                                        </div>
                                    </div>
                                </div>
                                <div class="position-absolute" style="top: -30px; right: -30px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255, 255, 255, 0.08);"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Used Offsets -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #2563eb, #3b82f6);">
                            <div class="card-body position-relative overflow-hidden p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                        <h6 class="text-uppercase text-white-80 fw-semibold mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">USED OFFSETS</h6>
                                        <h3 class="mb-0 text-white"><?php echo $total_offsets; ?> <small class="h6">requests</small></h3>
                                    </div>
                                    <div class="bg-white-20 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                        <i class="bi bi-check2-circle text-white" style="font-size: 1.75rem;"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="d-flex flex-wrap gap-3">
                                        <span class="badge d-flex align-items-center px-3 py-2" style="background: rgba(255, 255, 255, 0.15); color: white; border: none; font-weight: 500;">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span><?php echo $used_offsets; ?> used</span>
                                        </span>
                                        <span class="badge d-flex align-items-center px-3 py-2" style="background: rgba(255, 255, 255, 0.15); color: white; border: none; font-weight: 500;">
                                            <i class="bi bi-hourglass-split text-warning me-2"></i>
                                            <span><?php echo $remaining_offset; ?> remaining</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="position-absolute" style="top: -30px; right: -30px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255, 255, 255, 0.08);"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Overtime Balance -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #059669, #10b981);">
                            <div class="card-body position-relative overflow-hidden p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                        <h6 class="text-uppercase text-white-80 fw-semibold mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">OVERTIME BALANCE</h6>
                                        <h3 class="mb-0 text-white"><?php echo $remaining_offset; ?> <small class="h6">days remaining</small></h3>
                                    </div>
                                    <div class="bg-white-20 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                        <i class="bi bi-stopwatch text-white" style="font-size: 1.75rem;"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-white-70 fw-medium">Monthly Allocation</small>
                                        <small class="fw-bold text-white"><?php echo number_format(($remaining_overtime / 40) * 100, 1); ?>%</small>
                                    </div>
                                    <div class="progress" style="height: 8px; border-radius: 4px; background: rgba(255, 255, 255, 0.15);">
                                        <div class="progress-bar bg-white" role="progressbar" 
                                             style="width: <?php echo min(100, ($remaining_overtime / 40) * 100); ?>%" 
                                             aria-valuenow="<?php echo $remaining_overtime; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="40">
                                        </div>
                                    </div>
                                </div>
                                <div class="position-absolute" style="top: -30px; right: -30px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255, 255, 255, 0.08);"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Requests -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #d97706, #f59e0b);">
                            <div class="card-body position-relative overflow-hidden p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                        <h6 class="text-uppercase text-white-80 fw-semibold mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">APPROVED OVERTIME</h6>
                                        <h3 class="mb-0 text-white"><?php echo $remaining_offset; ?> <small class="h6">days remaining</small></h3>
                                    </div>
                                    <div class="bg-white-20 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                        <i class="bi bi-check-circle text-white" style="font-size: 1.75rem;"></i>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="d-flex flex-wrap gap-3">
                                        <span class="badge d-flex align-items-center px-3 py-2" style="background: rgba(255, 255, 255, 0.15); color: white; border: none; font-weight: 500;">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span><?php echo $used_offsets; ?> days used</span>
                                        </span>
                                        <span class="badge d-flex align-items-center px-3 py-2" style="background: rgba(255, 255, 255, 0.15); color: white; border: none; font-weight: 500;">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span><?php echo $approved_requests ?? 0; ?> approved</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="position-absolute" style="top: -30px; right: -30px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255, 255, 255, 0.08);"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Overtime and Offset Tables -->
                <div class="row g-4">
                    <!-- Offset Requests -->
                    <div class="col-lg-6">
                        <div class="card request-card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0" style="color: #000;">Offset Requests</h5>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOffsetModal">
                                    <i class="bi bi-plus-lg me-1"></i> Add Request
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (empty($offset_requests)): ?>
                                    <div class="h-100 d-flex flex-column justify-content-center align-items-center text-muted">
                                        <i class="bi bi-inbox display-4 mb-3"></i>
                                        <p class="mb-0">No offset requests found</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive h-100">
                                        <table class="table table-hover request-table">
                                            <thead>
                                                <tr>
                                                    <th>Activity</th>
                                                    <th>Date</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($offset_requests as $request): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span class="fw-medium"><?php echo htmlspecialchars($request['activity_title'] ?? 'N/A'); ?></span>
                                                                <?php if (!empty($request['reason'])): ?>
                                                                <small class="text-muted">
                                                                    <i class="bi bi-chat-left-text me-1"></i>
                                                                    <?php echo htmlspecialchars($request['reason']); ?>
                                                                </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td class="text-nowrap"><?php echo date('M d, Y', strtotime($request['offset_date'])); ?></td>
                                                        <td class="text-end">
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteRequest('offset', <?php echo $request['id']; ?>)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
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
                    <div class="col-lg-6">
                        <div class="card request-card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0" style="color: #000;">Overtime Requests</h5>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOvertimeModal">
                                    <i class="bi bi-plus-lg me-1"></i> Add Request
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (empty($overtime_requests)): ?>
                                    <div class="h-100 d-flex flex-column justify-content-center align-items-center text-muted">
                                        <i class="bi bi-inbox display-4 mb-3"></i>
                                        <p class="mb-0">No overtime requests found</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive h-100">
                                        <table class="table table-hover request-table">
                                            <thead>
                                                <tr>
                                                    <th>Activity</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($overtime_requests as $request): 
                                                    $start_date = new DateTime($request['activity_start']);
                                                    $end_date = new DateTime($request['activity_end']);
                                                    $date_range = $start_date->format('M d') . ' - ' . $end_date->format('M d, Y');
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <span class="fw-medium"><?php echo htmlspecialchars($request['activity_title'] ?? 'N/A'); ?></span>
                                                                    <?php 
                                                                    $used_days = $request['used_days'] ?? 0;
                                                                    $total_days = $request['total_days'] ?? 1;
                                                                    $remaining_days = $total_days - $used_days;
                                                                    $status_class = $remaining_days <= 0 ? 'bg-secondary' : 'bg-success';
                                                                    $status_text = $remaining_days <= 0 ? 'Fully Utilized' : "$remaining_days day" . ($remaining_days != 1 ? 's' : '') . ' left';
                                                                    ?>
                                                                    <span class="badge <?php echo $status_class; ?> ms-2">
                                                                        <?php echo $status_text; ?>
                                                                    </span>
                                                                </div>
                                                                <small class="text-muted">
                                                                    <i class="bi bi-calendar3 me-1"></i>
                                                                    <?php echo $date_range; ?>
                                                                    <span class="badge bg-primary ms-2">
                                                                        <?php echo $total_days; ?> day<?php echo $total_days > 1 ? 's' : ''; ?>
                                                                    </span>
                                                                    <?php if ($used_days > 0): ?>
                                                                    <span class="badge bg-info ms-1">
                                                                        <?php echo $used_days; ?> used
                                                                    </span>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="d-flex justify-content-end gap-2">
                                                                <?php if ($request['status'] === 'pending'): ?>
                                                                <button class="action-btn edit edit-request" data-id="<?php echo $request['id']; ?>" title="Edit Request">
                                                                    <i class="bi bi-pencil-fill"></i>
                                                                </button>
                                                                <?php endif; ?>
                                                                <button class="action-btn delete delete-request text-danger" 
                                                                    onclick="deleteRequest('overtime', <?php echo $request['id']; ?>)" 
                                                                    title="Delete Request">
                                                                    <i class="bi bi-trash-fill"></i>
                                                                </button>
                                                            </div>
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
                        <label class="form-label">Overtime Activity</label>
                            <select name="activity_id" class="form-select" required>
                            <option value="" disabled selected>Select an overtime activity</option>
                            <?php if (empty($offset_activities)): ?>
                                <option value="" disabled>No approved overtime activities found</option>
                            <?php else: ?>
                                <?php foreach ($offset_activities as $activity): ?>
                                    <option value="<?php echo $activity['id']; ?>">
                                        <?php echo htmlspecialchars($activity['title']); ?>
                                        (<?php echo date('M d', strtotime($activity['start_date'])); ?> - <?php echo date('M d, Y', strtotime($activity['end_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </select>
                            <?php if (empty($offset_activities)): ?>
                                <div class="alert alert-warning mt-2">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    You don't have any approved overtime activities to offset.
                                </div>
                            <?php endif; ?>
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
                <form id="overtimeForm" onsubmit="submitOvertimeForm(event)">
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
                        <div class="mb-3">
                            <label class="form-label">Number of Days</label>
                            <input 
                                type="number" 
                                name="days" 
                                class="form-control" 
                                min="1" 
                                value="1" 
                                required
                                oninput="this.setCustomValidity('')">
                            <small class="form-text text-muted">Select an activity first to see the maximum allowed days</small>
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
        // Store activities data for client-side validation
        const activitiesData = <?php 
            $activitiesWithDates = [];
            foreach ($activities as $activity) {
                $activitiesWithDates[$activity['id']] = [
                    'start_date' => $activity['start_date'],
                    'end_date' => $activity['end_date']
                ];
            }
            echo json_encode($activitiesWithDates);
        ?>;

        // Update max days when activity selection changes
        document.querySelector('select[name="activity_id"]').addEventListener('change', function() {
            const activityId = this.value;
            const daysInput = document.querySelector('input[name="days"]');
            
            if (activityId && activitiesData[activityId]) {
                const startDate = new Date(activitiesData[activityId].start_date);
                const endDate = new Date(activitiesData[activityId].end_date);
                const diffTime = Math.abs(endDate - startDate);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include both start and end dates
                
                daysInput.setAttribute('max', diffDays);
                daysInput.setAttribute('title', `Maximum ${diffDays} days (based on activity duration)`);
                
                // Update help text
                const helpText = daysInput.nextElementSibling;
                if (helpText && helpText.classList.contains('form-text')) {
                    helpText.textContent = `Enter the number of days (max ${diffDays} days for this activity)`;
                }
            } else {
                daysInput.removeAttribute('max');
                daysInput.removeAttribute('title');
            }
        });

        // Function to handle overtime form submission
        function submitOvertimeForm(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
            
            fetch('save_overtime.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Overtime request submitted successfully!');
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addOvertimeModal'));
                    modal.hide();
                    // Reload the page to show the new request
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Failed to submit request');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            })
            .finally(() => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        }
        
        // Function to handle delete request
        function deleteRequest(type, id) {
            if (!confirm('Are you sure you want to delete this ' + type + ' request? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('request_id', id);
            
            const endpoint = type === 'offset' ? 'delete_offset.php' : 'delete_overtime.php';
            
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert(type + ' request deleted successfully');
                    // Reload the page to update the list
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Failed to delete request');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            });
        }
        
        // Function to handle edit request
        function editRequest(type, id) {
            // In a real application, you would fetch the request details and populate the form
            // For now, we'll just show an alert
            alert('Edit functionality will be implemented here for ' + type + ' ID: ' + id);
        }
        
        // Function to handle delete request
        function deleteRequest(type, id) {
            if (!confirm('Are you sure you want to delete this ' + type + ' request? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('request_id', id);
            
            const endpoint = type === 'offset' ? 'delete_offset.php' : 'delete_overtime.php';
            
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert(type + ' request deleted successfully');
                    // Reload the page to update the list
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Failed to delete request');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            });
        }
        
        // Function to handle offset form submission
        document.getElementById('offsetForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
            
            // Submit the form using fetch
            fetch(this.action, {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Offset request submitted successfully!');
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addOffsetModal'));
                    modal.hide();
                    // Reload the page
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Failed to submit request');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            })
            .finally(() => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });

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
