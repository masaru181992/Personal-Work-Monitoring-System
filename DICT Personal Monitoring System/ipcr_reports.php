<?php
session_start();
require_once 'config/database.php';

// Handle form submission for adding/editing entries
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: ipcr_reports.php');
        exit();
    }
    
    // Debug: Log POST data
    error_log('POST data: ' . print_r($_POST, true));
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get and sanitize all inputs
        $entry_id = isset($_POST['entry_id']) ? (int)$_POST['entry_id'] : 0;
        $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
        $semester = isset($_POST['semester']) ? trim($_POST['semester']) : '';
        $function_type = isset($_POST['function_type']) ? trim($_POST['function_type']) : '';
        $success_indicators = isset($_POST['success_indicators']) ? trim($_POST['success_indicators']) : '';
        $actual_accomplishments = isset($_POST['actual_accomplishments']) ? trim($_POST['actual_accomplishments']) : '';
        $activities = isset($_POST['activities']) && is_array($_POST['activities']) ? $_POST['activities'] : [];
        
        // Validate required fields
        if (empty($year) || empty($semester) || empty($function_type) || empty($success_indicators) || empty($actual_accomplishments)) {
            throw new Exception('All fields are required');
        }
        
        if ($entry_id > 0) {
            // Update existing entry
            $stmt = $pdo->prepare("UPDATE ipcr_entries 
                                  SET year = :year, 
                                      semester = :semester, 
                                      function_type = :function_type, 
                                      success_indicators = :success_indicators, 
                                      actual_accomplishments = :actual_accomplishments,
                                      updated_at = NOW() 
                                  WHERE id = :id AND user_id = :user_id");
            
            $stmt->execute([
                ':year' => $year,
                ':semester' => $semester,
                ':function_type' => $function_type,
                ':success_indicators' => $success_indicators,
                ':actual_accomplishments' => $actual_accomplishments,
                ':id' => $entry_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $message = 'IPCR entry updated successfully';
        } else {
            // Add new entry
            $stmt = $pdo->prepare("INSERT INTO ipcr_entries 
                (user_id, year, semester, function_type, success_indicators, actual_accomplishments) 
                VALUES (:user_id, :year, :semester, :function_type, :success_indicators, :actual_accomplishments)");
                
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':year' => $year,
                ':semester' => $semester,
                ':function_type' => $function_type,
                ':success_indicators' => $success_indicators,
                ':actual_accomplishments' => $actual_accomplishments
            ]);
            
            $entry_id = $pdo->lastInsertId();
            $message = 'IPCR entry added successfully';
        }
        
        // Handle activities if we have a valid entry ID
        if ($entry_id > 0 && !empty($activities)) {
            // First, remove all existing relationships for this entry
            $stmt = $pdo->prepare("DELETE FROM ipcr_activities WHERE ipcr_entry_id = :entry_id");
            $stmt->execute([':entry_id' => $entry_id]);
            
            // Then, insert the new relationships
            $stmt = $pdo->prepare("INSERT INTO ipcr_activities (ipcr_entry_id, activity_id) VALUES (:entry_id, :activity_id)");
            
            foreach ($activities as $activity_id) {
                $activity_id = (int)$activity_id;
                if ($activity_id > 0) {
                    $stmt->execute([
                        ':entry_id' => $entry_id,
                        ':activity_id' => $activity_id
                    ]);
                }
            }
        }
        
        // Commit the transaction
        $pdo->commit();
        $_SESSION['success'] = $message;
        
    } catch (PDOException $e) {
        // Rollback the transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Database error: ' . $e->getMessage());
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error: ' . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: ipcr_reports.php');
    exit();
}

// Get all IPCR entries for the current user
$entries = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM ipcr_entries WHERE user_id = :user_id ORDER BY year DESC, semester DESC");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to fetch IPCR entries: ' . $e->getMessage();
}

// Fetch all activities for multiselect dropdown
$activities = [];
try {
    // Get unique activities by title, keeping the most recent one
    $stmt = $pdo->query(
        "SELECT 
            a.id, 
            a.title, 
            a.start_date, 
            a.created_at,
            p.title as project_title
         FROM (
             SELECT 
                 title,
                 MAX(COALESCE(start_date, created_at)) as latest_date
             FROM activities 
             WHERE title IS NOT NULL AND title != ''
             GROUP BY title
         ) as latest
         JOIN activities a ON a.title = latest.title 
             AND (a.start_date = latest.latest_date OR (a.start_date IS NULL AND a.created_at = latest.latest_date))
         LEFT JOIN projects p ON a.project_id = p.id
         ORDER BY latest.latest_date DESC, a.title ASC"
    );
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to fetch activities: ' . $e->getMessage();
}

// Get activity relationships for IPCR entries
$ipcr_activities = [];
try {
    $stmt = $pdo->query("SELECT * FROM ipcr_activities");
    $relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize by ipcr_entry_id
    foreach ($relationships as $rel) {
        if (!isset($ipcr_activities[$rel['ipcr_entry_id']])) {
            $ipcr_activities[$rel['ipcr_entry_id']] = [];
        }
        $ipcr_activities[$rel['ipcr_entry_id']][] = $rel['activity_id'];
    }
} catch (PDOException $e) {
    // Table might not exist yet, which is fine
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$user_id) {
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';

// Build the base query
$query = "SELECT * FROM ipcr_entries WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

// Add filters if they are set
if (isset($_GET['year']) && !empty($_GET['year'])) {
    $query .= " AND year = :year";
    $params[':year'] = $_GET['year'];
}

if (isset($_GET['semester']) && !empty($_GET['semester'])) {
    $query .= " AND semester = :semester";
    $params[':semester'] = $_GET['semester'];
}

if (isset($_GET['function_type']) && !empty($_GET['function_type'])) {
    $query .= " AND function_type = :function_type";
    $params[':function_type'] = $_GET['function_type'];
}

// Handle sorting
$sortable_columns = [
    'year' => 'year',
    'semester' => 'semester',
    'function_type' => 'function_type',
    'success_indicators' => 'success_indicators',
    'actual_accomplishments' => 'actual_accomplishments'
];

$sort_column = 'year';
$sort_direction = 'DESC';

if (isset($_GET['sort']) && array_key_exists($_GET['sort'], $sortable_columns)) {
    $sort_column = $_GET['sort'];
}

if (isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC'])) {
    $sort_direction = strtoupper($_GET['order']);
}

// Add sorting to the query
$query .= " ORDER BY $sort_column $sort_direction";

// Fetch filtered entries
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Error fetching entries: ' . $e->getMessage();
    $entries = [];
}

// Handle entry deletion
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM ipcr_entries WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$_GET['delete'], $user_id]);
        
        if ($result) {
            $success_message = 'Entry deleted successfully!';
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?success=1');
            exit();
        }
    } catch (PDOException $e) {
        $error_message = 'Error deleting entry: ' . $e->getMessage();
    }
}

// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System - IPCR Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Table Styles */
        .table {
            color: #000000;
            margin-bottom: 0;
            border-color: var(--border-color);
            background-color: var(--secondary-bg);

        }
        
        .table thead th {
            background-color: var(--secondary-bg);
            border-bottom: 2px solid var(--accent-color);
            color: var(--accent-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            padding: 0.75rem 1rem;
            font-family: 'Space Grotesk', sans-serif;
        }
        
        .table tbody tr {
            background-color: var(--primary-bg);
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table tbody tr:nth-child(odd) {
            background-color: var(--primary-bg);
        }
        
        .table tbody tr:hover {
            background-color: rgba(100, 255, 218, 0.1);
            transform: translateX(2px);
            box-shadow: 0 2px 8px rgba(100, 255, 218, 0.1);
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--border-color);
            color: #000000;
            font-size: 0.9rem;
        }
        
        .table tbody td:first-child {
            color: #000000;
            font-weight: 600;
        }
        
        .table .text-truncate {
            max-width: 300px;
        }
        
        /* Card Styles */
        .card {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: rgba(16, 32, 56, 0.7);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.25rem;
        }
        
        .card-body {
            padding: 0;
        }
        
        /* Form controls */
        .form-select, .form-control {
            background-color: var(--secondary-bg) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-white) !important;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 0 0.25rem rgba(100, 255, 218, 0.25) !important;
        }
        
        .form-select option {
            background-color: var(--primary-bg);
            color: var(--text-white);
        }
        
        .form-select option:checked {
            background-color: var(--accent-color);
            color: var(--primary-bg);
            font-weight: 500;
        }
        
        /* Style for selected activities */
        .selected-activities-container {
            background-color: #1a1a1a;
            border: 1px solid #2a2a2a !important;
        }
        
        .selected-activity-item {
            background-color: #2a2a2a;
            border-radius: 4px;
            padding: 4px 8px;
            margin-bottom: 4px;
        }
        
        .selected-activity-item:last-child {
            margin-bottom: 0;
        }
        
        .btn-remove-activity {
            padding: 0.15rem 0.3rem;
            font-size: 0.7rem;
            line-height: 1;
        }
        :root {
            --primary-bg: #0a192f;
            --secondary-bg: rgba(16, 32, 56, 0.9);
            --accent-color: #64ffda;
            --accent-secondary: #7928ca;
            --text-white: #ffffff;
            --text-muted: #a8b2d1;
            --border-color: rgba(100, 255, 218, 0.1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: #0a192f;
            color: var(--text-white);
            overflow-x: hidden;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            background: linear-gradient(180deg, #0a192f 0%, #0f1b35 100%);
            scrollbar-width: thin;
            position: fixed;
            width: 350px;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
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
        
        .sidebar-header {
            padding: 1rem 0.5rem;
            margin-bottom: 0.5rem;
            background: var(--primary-bg);
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-header h4 {
            color: var(--accent-color);
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sidebar .nav-menu {
            padding: 0.5rem 0;
        }
        
        .nav-item {
            color: #a0aec0;
            text-decoration: none;
            margin: 0.15rem 0.5rem;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
        }
        
        .nav-item:hover {
            background: rgba(100, 255, 218, 0.1);
            color: #e2e8f0;
            transform: translateX(4px);
        }

        .nav-item.active {
            background: rgba(100, 255, 218, 0.15);
            color: var(--accent-color);
            font-weight: 500;
            border-left: 3px solid var(--accent-color);
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
            margin-right: 0.5rem;
        }
        
        .badge {
            font-size: 0.65rem;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-weight: 600;
            margin-left: auto;
            min-width: 1.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 1.5rem;
            background: rgba(100, 255, 218, 0.1);
            color: var(--accent-color);
        }
        
        .sidebar-footer {
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--border-color);
            background: transparent;
            margin-top: 1rem;
            font-size: 0.8rem;
            color: #718096;
        }
        
        .nav-section-title {
            color: #718096;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin: 1.5rem 1rem 0.5rem;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid rgba(100, 255, 218, 0.1);
        }
        
        /* Main Content Styles */
        .main-content {
            overflow-x: auto;
            min-height: 100vh;
            padding: 1rem 2rem 2rem;
            margin-left: 350px;
            background: linear-gradient(180deg, #0a192f 0%, #0f1b35 100%);
            width: calc(100% - 350px);
        }
        
        .welcome-text {
            color: var(--accent-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            letter-spacing: -0.5px;
        }
        
        .subtitle-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 400;
            opacity: 0.9;
        }
        
        .card {
            background: rgba(16, 32, 56, 0.7);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 30px -10px rgba(2, 12, 27, 0.7);
            transition: all 0.3s ease;
            overflow: visible;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            min-width: fit-content;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        
        .card-header {
            background: rgba(16, 32, 56, 0.5);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            padding: 1.25rem 1.5rem;
            color: var(--accent-color);
        }
        
        .table {
            margin-bottom: 0;
            color: var(--text-white);
        }
        
        .table th {
            background: rgba(100, 255, 218, 0.05);
            color: var(--accent-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .table tbody tr:hover {
            background: rgba(100, 255, 218, 0.05);
        }
        
        .table {
            table-layout: fixed;
            width: 100%;
            margin-bottom: 0;
        }
        
        .table-responsive {
            border-radius: 0.5rem;
            overflow-x: auto;
            margin: 0 -0.5rem;
            padding: 0 0.5rem;
            max-width: 100%;
        }
        
        .table-responsive::-webkit-scrollbar {
            height: 6px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: rgba(100, 255, 218, 0.05);
            border-radius: 3px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: rgba(100, 255, 218, 0.2);
            border-radius: 3px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 255, 218, 0.3);
        }
        
        .table tbody tr td {
            border-color: rgba(100, 255, 218, 0.08);
            padding: 0.6rem 0.8rem;
            vertical-align: middle;
            word-wrap: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.3;
            font-size: 0.9rem;
        }
        
        .table tbody tr td:first-child {
            padding-left: 1.5rem;
        }
        
        .table tbody tr td:last-child {
            padding-right: 1.5rem;
            white-space: nowrap;
        }
        
        .table th {
            padding: 0.6rem 0.8rem !important;
            white-space: nowrap;
            position: sticky;
            top: 0;
            background: rgba(16, 32, 56, 0.95);
            z-index: 10;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--accent-color);
        }
        
        .table th:first-child {
            padding-left: 1.5rem !important;
        }
        
        .table th:last-child {
            padding-right: 1.5rem !important;
        }
        
        .pagination .page-link {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            margin: 0 3px;
            border-radius: 6px;
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .pagination .page-item.active .page-link {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: #0a192f;
            font-weight: 600;
        }
        
        .pagination .page-link:hover {
            background: rgba(100, 255, 218, 0.1);
            color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .pagination .page-item.disabled .page-link {
            background: transparent;
            border-color: var(--border-color);
            color: var(--text-muted);
            opacity: 0.5;
        }
        
        .card-footer {
            background: rgba(16, 32, 56, 0.5);
            border-top: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            color: var(--text-muted);
        }
        
        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: var(--primary-bg);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: transparent;
            border-color: var(--accent-color);
            color: var(--accent-color);
            transform: translateY(-2px);
        }
        
        .badge-accent {
            background-color: var(--accent-color);
            color: var(--primary-bg);
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.active {
                margin-left: 350px;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(100, 255, 218, 0.3);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 255, 218, 0.5);
        }
        
        .btn-group .btn {
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            background: rgba(10, 25, 47, 0.7);
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }
        
        .btn-group .btn:hover {
            color: var(--accent-color);
            background: rgba(100, 255, 218, 0.1);
        }
        
        .btn-group .btn.active {
            background: var(--accent-color);
            color: #0a192f;
            border-color: var(--accent-color);
            font-weight: 500;
        }
        
        .btn-group .btn:first-child {
            border-top-left-radius: 6px;
            border-bottom-left-radius: 6px;
        }
        
        .btn-group .btn:last-child {
            border-top-right-radius: 6px;
            border-bottom-right-radius: 6px;
        }
        
        .form-control {
            background: rgba(10, 25, 47, 0.7);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            color: var(--text-white);
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: rgba(10, 25, 47, 0.9);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(100, 255, 218, 0.15);
            color: var(--text-white);
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.7;
        }
        
        .input-group-text {
            background: rgba(10, 25, 47, 0.7);
            border: 1px solid var(--border-color);
            color: var(--accent-color);
            border-right: none;
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
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <div class="mb-3 mb-md-0">
                        <h2 class="welcome-text mb-1">IPCR Reports</h2>
                        <p class="subtitle-text mb-0">View and manage your IPCR reports</p>
                    </div>

                </div>
                
                <!-- Filters Card -->
                <div class="card mb-4 animate__animated animate__fadeIn">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters</h6>
                    </div>
                    <div class="card-body">
                        <form id="filterForm" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select class="form-select bg-dark text-white border-accent" id="filterYear" name="year" title="Filter by Year">
                                    <option value="">All Years</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($year = $currentYear + 5; $year >= 2000; $year--) {
                                        $selected = (isset($_GET['year']) && $_GET['year'] == $year) ? 'selected' : '';
                                        echo "<option value='$year' $selected>$year</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select bg-dark text-white border-accent" id="filterSemester" name="semester" title="Filter by Semester">
                                    <option value="">All Semesters</option>
                                    <option value="1st" <?php echo (isset($_GET['semester']) && $_GET['semester'] == '1st') ? 'selected' : ''; ?>>1st Semester</option>
                                    <option value="2nd" <?php echo (isset($_GET['semester']) && $_GET['semester'] == '2nd') ? 'selected' : ''; ?>>2nd Semester</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select bg-dark text-white border-accent" id="filterFunctionType" name="function_type" title="Filter by Function Type">
                                    <option value="">All Function Types</option>
                                    <?php
                                    $functionTypes = [
                                        'Core Function' => 'Core Function',
                                        'Support Function' => 'Support Function',
                                        'Special Tasks' => 'Special Tasks',
                                        'Other Functions' => 'Other Functions'
                                    ];
                                    foreach ($functionTypes as $type) {
                                        $selected = (isset($_GET['function_type']) && $_GET['function_type'] === $type) ? 'selected' : '';
                                        echo "<option value='$type' $selected>$type</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-funnel me-1"></i> Apply Filters
                                </button>
                                <a href="ipcr_reports.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- IPCR Details Table -->
                <div class="card animate__animated animate__fadeInUp">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-table me-2"></i>IPCR Details</h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-custom" data-bs-toggle="modal" data-bs-target="#addIpcrtModal" 
                                    title="Add a new IPCR entry">
                                <i class="bi bi-plus-lg me-1"></i>Add New Entry
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" 
                                    title="Click to add a new IPCR entry">
                                <i class="bi bi-info-circle"></i>
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: calc(100vh - 200px);">
                            <table class="table table-hover mb-0" style="width: 100%; min-width: 1200px;">
                                <thead>
                                    <tr>
                                        <?php
                                        function getSortLink($column, $label, $default_direction = 'asc') {
                                            $sort = $_GET['sort'] ?? 'year';
                                            $order = strtoupper($_GET['order'] ?? 'desc');
                                            $new_order = ($sort === $column && $order === 'ASC') ? 'desc' : 'asc';
                                            $icon = '';
                                            
                                            if ($sort === $column) {
                                                $icon = $order === 'ASC' ? ' <i class="bi bi-sort-down"></i>' : ' <i class="bi bi-sort-up"></i>';
                                            } else {
                                                $icon = ' <i class="bi bi-arrow-down-up" style="opacity: 0.5;"></i>';
                                            }
                                            
                                            $params = $_GET;
                                            $params['sort'] = $column;
                                            $params['order'] = $new_order;
                                            $query_string = http_build_query($params);
                                            
                                            return '<a href="?' . htmlspecialchars($query_string) . '" class="text-decoration-none text-white">' . $label . $icon . '</a>';
                                        }
                                        ?>
                                        <th style="width: 80px;" class="text-center"><?php echo getSortLink('year', 'Year'); ?></th>
                                        <th style="width: 100px;" class="text-center"><?php echo getSortLink('semester', 'Semester'); ?></th>
                                        <th style="width: 150px;" class="text-center"><?php echo getSortLink('function_type', 'Function Type'); ?></th>
                                        <th style="width: 300px; min-width: 250px;"><?php echo getSortLink('success_indicators', 'Success Indicators'); ?></th>
                                        <th style="width: 400px; min-width: 300px;"><?php echo getSortLink('actual_accomplishments', 'Actual Accomplishments'); ?></th>
                                        <th style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entries as $entry): ?>
                                        <tr data-entry-id="<?php echo $entry['id']; ?>">
                                            <td><?php echo $entry['year']; ?></td>
                                            <td><?php echo $entry['semester']; ?></td>
                                            <td class="text-center"><?php echo $entry['function_type']; ?></td>
                                            <td class="text-truncate" title="<?php echo $entry['success_indicators']; ?>"><?php echo $entry['success_indicators']; ?></td>
                                            <td class="text-truncate" title="<?php echo $entry['actual_accomplishments']; ?>"><?php echo $entry['actual_accomplishments']; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary me-1" title="Edit" onclick="handleEditClick(this)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" title="Delete" 
                                                        onclick="if (confirm('Are you sure you want to delete this entry?')) document.location.href='?delete=<?php echo $entry['id']; ?>';">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                Showing <span class="fw-bold">1</span> to <span class="fw-bold">3</span> of <span class="fw-bold">3</span> entries
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
                <!-- End IPCR Details Table -->
            </div>
        </div>

    <!-- jQuery (required for Bootstrap Select) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
    
    <!-- Add IPCR Entry Modal -->
    <div class="modal fade" id="addIpcrtModal" tabindex="-1" aria-labelledby="addIpcrtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark border border-accent">
                <div class="modal-header border-bottom border-accent">
                    <h5 class="modal-title" id="addIpcrtModalLabel">Add New IPCR Entry</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="year" class="form-label">Year</label>
                                <input type="number" class="form-control bg-dark text-white border-accent" id="year" name="year" 
                                       value="<?php echo date('Y'); ?>" min="2000" max="<?php echo date('Y') + 5; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select bg-dark text-white border-accent" id="semester" name="semester" required>
                                    <option value="1st">1st Semester</option>
                                    <option value="2nd" <?php echo date('n') > 6 ? 'selected' : ''; ?>>2nd Semester</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="function_type" class="form-label">Function Type</label>
                                <select class="form-select bg-dark text-white border-accent" id="function_type" 
                                        name="function_type" required>
                                    <option value="Core Function" <?php echo isset($_POST['function_type']) && $_POST['function_type'] === 'Core Function' ? 'selected' : ''; ?>>Core Function</option>
                                    <option value="Support Function" <?php echo isset($_POST['function_type']) && $_POST['function_type'] === 'Support Function' ? 'selected' : ''; ?>>Support Function</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="success_indicators" class="form-label">Success Indicators</label>
                                <textarea class="form-control bg-dark text-white border-accent" id="success_indicators" 
                                          name="success_indicators" rows="2" required></textarea>
                            </div>
                            <div class="col-12">
                                <label for="actual_accomplishments" class="form-label">Actual Accomplishments</label>
                                <textarea class="form-control bg-dark text-white border-accent" id="actual_accomplishments" 
                                          name="actual_accomplishments" rows="3" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-accent">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit IPCR Entry Modal -->
    <div class="modal fade" id="editIpcrtModal" tabindex="-1" aria-labelledby="editIpcrtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark border border-accent">
                <div class="modal-header border-bottom border-accent">
                    <h5 class="modal-title" id="editIpcrtModalLabel">Edit IPCR Entry</h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-info me-2 position-relative" id="showCheckedItemsBtn" title="Show Checked Items">
                            <i class="bi bi-list-check me-1"></i> Show Checked Items
                            <span id="checkedItemsCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem; display: none;">
                                0
                            </span>
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <form method="POST" action="" id="editIpcrtForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="entry_id" id="editEntryId">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="editYear" class="form-label">Year</label>
                                <input type="number" class="form-control bg-dark text-white border-accent" id="editYear" 
                                       name="year" min="2000" max="<?php echo date('Y') + 5; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editSemester" class="form-label">Semester</label>
                                <select class="form-select bg-dark text-white border-accent" id="editSemester" name="semester" required>
                                    <option value="1st">1st Semester</option>
                                    <option value="2nd">2nd Semester</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="editFunctionType" class="form-label">Function Type</label>
                                <select class="form-select bg-dark text-white border-accent" id="editFunctionType" 
                                        name="function_type" required>
                                    <option value="Core Function" <?php echo isset($_POST['function_type']) && $_POST['function_type'] === 'Core Function' ? 'selected' : ''; ?>>Core Function</option>
                                    <option value="Support Function" <?php echo isset($_POST['function_type']) && $_POST['function_type'] === 'Support Function' ? 'selected' : ''; ?>>Support Function</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="editSuccessIndicators" class="form-label">Success Indicators</label>
                                <textarea class="form-control bg-dark text-white border-accent" id="editSuccessIndicators" 
                                          name="success_indicators" rows="2" required></textarea>
                            </div>
                            <div class="col-12">
                                <label for="editActualAccomplishments" class="form-label">Actual Accomplishments</label>
                                <textarea class="form-control bg-dark text-white border-accent" id="editActualAccomplishments" 
                                          name="actual_accomplishments" rows="3" required></textarea>
                            </div>
                            <div class="col-12 mt-3">
                                <label for="editActivities" class="form-label">Related Activities</label>
                                <!-- Activities Search -->
                                <div class="mb-3">
                                    <input type="text" id="activitySearch" class="form-control bg-dark text-white border-accent" placeholder="Search activities...">
                                </div>
                                
                                <!-- Activities List -->
                                <div class="activities-list border rounded p-2 bg-darker mb-3" style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($activities as $activity): 
                                        $date = !empty($activity['start_date']) ? $activity['start_date'] : $activity['created_at'];
                                        $dateStr = date('M d, Y', strtotime($date));
                                        $displayText = $dateStr . ' - ' . htmlspecialchars(trim($activity['title']));
                                        if (!empty(trim($activity['project_title'] ?? ''))) {
                                            $displayText .= ' (' . htmlspecialchars(trim($activity['project_title'])) . ')';
                                        }
                                    ?>
                                    <div class="form-check mb-2 activity-item">
                                        <input class="form-check-input activity-checkbox" type="checkbox" 
                                               value="<?php echo $activity['id']; ?>" 
                                               id="activity_<?php echo $activity['id']; ?>"
                                               data-display="<?php echo htmlspecialchars($displayText); ?>">
                                        <label class="form-check-label w-100" for="activity_<?php echo $activity['id']; ?>">
                                            <?php echo $displayText; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Selected Activities -->
                                <div class="selected-activities">
                                    <div class="small text-muted mb-1">Selected Activities:</div>
                                    <div id="selectedActivities" class="border rounded p-2 bg-darker" style="min-height: 80px; max-height: 200px; overflow-y: auto;">
                                        <div class="text-muted small" id="noActivitiesSelected">No activities selected</div>
                                    </div>
                                </div>
                                
                                <!-- Hidden field to store selected activity IDs -->
                                <div id="selectedActivitiesInputs"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-accent">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
        /* Style for checked items modal */
        .checked-items-modal .modal-dialog {
            max-width: 600px;
        }
        .checked-items-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .checked-item {
            padding: 8px 12px;
            border-bottom: 1px solid #2a2a2a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .checked-item:last-child {
            border-bottom: none;
        }
        .checked-item .activity-title {
            flex-grow: 1;
        }
    </style>
    
    <!-- Checked Items Modal -->
    <div class="modal fade" id="checkedItemsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border border-accent">
                <div class="modal-header border-bottom border-accent">
                    <h5 class="modal-title">Currently Checked Items</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="checked-items-list">
                        <div id="checkedItemsList">
                            <!-- Checked items will be inserted here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-accent">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Update the checked items count
        function updateCheckedItemsCount() {
            const checkedCount = document.querySelectorAll('.activity-checkbox:checked').length;
            const countBadge = document.getElementById('checkedItemsCount');
            
            if (checkedCount > 0) {
                countBadge.textContent = checkedCount;
                countBadge.style.display = 'block';
            } else {
                countBadge.style.display = 'none';
            }
        }
        
        // Show checked items in a modal
        function showCheckedItems() {
            const checkedItems = document.querySelectorAll('.activity-checkbox:checked');
            const checkedItemsList = document.getElementById('checkedItemsList');
            
            if (checkedItems.length === 0) {
                checkedItemsList.innerHTML = '<div class="text-center p-3">No items are currently checked.</div>';
            } else {
                let html = '';
                checkedItems.forEach(checkbox => {
                    const label = document.querySelector(`label[for="${checkbox.id}"]`);
                    const activityText = label ? label.textContent.trim() : 'Unknown Activity';
                    
                    html += `
                        <div class="checked-item">
                            <span class="activity-title">${activityText}</span>
                            <span class="badge bg-primary">✓</span>
                        </div>`;
                });
                checkedItemsList.innerHTML = html;
            }
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('checkedItemsModal'));
            modal.show();
        }
        
        // Handle Edit Button Click
        function handleEditClick(button) {
            const row = button.closest('tr');
            const entryId = row.getAttribute('data-entry-id');
            const year = row.cells[0].textContent.trim();
            const semester = row.cells[1].textContent.trim();
            const functionType = row.cells[2].textContent.trim();
            const successIndicators = row.cells[3].textContent.trim();
            const actualAccomplishments = row.cells[4].textContent.trim();
            
            // Fill the form
            document.getElementById('editEntryId').value = entryId;
            document.getElementById('editYear').value = year;
            document.getElementById('editSemester').value = semester;
            document.getElementById('editFunctionType').value = functionType;
            document.getElementById('editSuccessIndicators').value = successIndicators;
            document.getElementById('editActualAccomplishments').value = actualAccomplishments;
            
            // Fetch and set the related activities
            fetchRelatedActivities(entryId);
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editIpcrtModal'));
            modal.show();
        }
        
        // Update the selected activities display
        function updateSelectedActivitiesDisplay(selectedIds) {
            const container = document.querySelector('.selected-activities-container');
            const noActivitiesMsg = document.getElementById('noActivitiesSelected');
            
            // Clear existing items
            const existingItems = container.querySelectorAll('.selected-activity-item');
            existingItems.forEach(item => item.remove());
            
            // Show/hide no activities message
            if (!selectedIds || selectedIds.length === 0) {
                noActivitiesMsg.style.display = 'block';
                return;
            }
            
            noActivitiesMsg.style.display = 'none';
            
            // Get all activity options to find the selected ones
            const select = document.getElementById('editActivities');
            const options = select.options;
            
            // Create a map of all activities by their values
            const activitiesMap = {};
            for (let i = 0; i < options.length; i++) {
                activitiesMap[options[i].value] = options[i].text;
            }
            
            // Add selected activities to the display
            selectedIds.forEach(activityId => {
                const activityText = activitiesMap[activityId];
                if (activityText) {
                    const activityItem = document.createElement('div');
                    activityItem.className = 'selected-activity-item d-flex justify-content-between align-items-center mb-1 p-1 border-bottom border-secondary';
                    activityItem.innerHTML = `
                        <span>${activityText}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-activity" data-value="${activityId}">
                            <i class="bi bi-x"></i>
                        </button>
                    `;
                    
                    // Add remove button handler
                    const removeBtn = activityItem.querySelector('.btn-remove-activity');
                    removeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const valueToRemove = this.getAttribute('data-value');
                        
                        // Remove from select
                        const select = $('#editActivities');
                        const currentValues = select.val() || [];
                        const newValues = currentValues.filter(val => val !== valueToRemove);
                        select.val(newValues);
                        select.selectpicker('refresh');
                        
                        // Update display
                        updateSelectedActivitiesDisplay(newValues);
                    });
                    
                    container.insertBefore(activityItem, noActivitiesMsg);
                }
            });
        }
        
        // Handle activity selection changes
        function setupActivityCheckboxes() {
            const checkboxes = document.querySelectorAll('.activity-checkbox');
            const searchInput = document.getElementById('activitySearch');
            const selectedActivitiesDiv = document.getElementById('selectedActivities');
            const noActivitiesMsg = document.getElementById('noActivitiesSelected');
            
            // Initialize count on page load
            updateCheckedItemsCount();
            
            // Toggle activity selection
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectedActivities();
                    updateCheckedItemsCount(); // Update count when checkboxes change
                });
            });
            
            // Search functionality
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const items = document.querySelectorAll('.activity-item');
                    
                    items.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
            
            // Update selected activities display
            window.updateSelectedActivities = function() {
                const selectedCheckboxes = document.querySelectorAll('.activity-checkbox:checked');
                const selectedIds = [];
                let selectedHtml = '';
                
                // Clear previous inputs
                const inputsContainer = document.getElementById('selectedActivitiesInputs');
                inputsContainer.innerHTML = '';
                
                // Create hidden inputs for form submission
                selectedCheckboxes.forEach(checkbox => {
                    const id = checkbox.value;
                    selectedIds.push(id);
                    
                    // Add hidden input
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'activities[]';
                    input.value = id;
                    inputsContainer.appendChild(input);
                    
                    // Add to display
                    selectedHtml += `
                        <div class="selected-activity-item d-flex justify-content-between align-items-center mb-2 p-2 bg-dark rounded">
                            <span>${checkbox.dataset.display}</span>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-sm" 
                                    onclick="document.getElementById('activity_${id}').click();">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>`;
                });
                
                // Update UI
                if (selectedCheckboxes.length > 0) {
                    noActivitiesMsg.style.display = 'none';
                    selectedActivitiesDiv.innerHTML = selectedHtml;
                } else {
                    noActivitiesMsg.style.display = 'block';
                    selectedActivitiesDiv.innerHTML = '';
                    selectedActivitiesDiv.appendChild(noActivitiesMsg);
                }
            };
        }
        
        // Initialize activity checkboxes when modal is shown
        document.getElementById('editIpcrtModal').addEventListener('shown.bs.modal', function () {
            setupActivityCheckboxes();
            
            // Add click handler for show checked items button
            const showCheckedBtn = document.getElementById('showCheckedItemsBtn');
            if (showCheckedBtn) {
                showCheckedBtn.addEventListener('click', showCheckedItems);
            }
        });
        
        // Fetch related activities for an IPCR entry
        async function fetchRelatedActivities(entryId) {
            try {
                const response = await fetch(`api/get_ipcr_activities.php?entry_id=${entryId}`);
                const data = await response.json();
                
                // Clear all selections first
                document.querySelectorAll('.activity-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                // Check the related activities
                if (data.activities && data.activities.length > 0) {
                    data.activities.forEach(activityId => {
                        const checkbox = document.getElementById(`activity_${activityId}`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                    
                    // Update the display
                    if (window.updateSelectedActivities) {
                        window.updateSelectedActivities();
                    }
                } else {
                    // No activities selected
                    const noActivitiesMsg = document.getElementById('noActivitiesSelected');
                    const selectedActivitiesDiv = document.getElementById('selectedActivities');
                    noActivitiesMsg.style.display = 'block';
                    selectedActivitiesDiv.innerHTML = '';
                    selectedActivitiesDiv.appendChild(noActivitiesMsg);
                }
                
            } catch (error) {
                console.error('Error fetching related activities:', error);
            }
        }
        
        // Function to collect form data including activities
        function collectFormData(form) {
            const formData = new FormData(form);
            const data = {};
            
            // Convert FormData to plain object
            formData.forEach((value, key) => {
                // Skip the activities[] fields from the form as we'll handle them separately
                if (key === 'activities[]') return;
                
                if (data[key] !== undefined) {
                    if (!Array.isArray(data[key])) {
                        data[key] = [data[key]];
                    }
                    data[key].push(value);
                } else {
                    data[key] = value;
                }
            });
            
            // Get selected activities
            const selectedActivities = [];
            document.querySelectorAll('.activity-checkbox:checked').forEach(checkbox => {
                selectedActivities.push(checkbox.value);
            });
            
            // Add activities to the data object
            if (selectedActivities.length > 0) {
                data['activities'] = selectedActivities;
            } else {
                // If no activities are selected, ensure the activities array is empty
                data['activities'] = [];
            }
            
            console.log('Collected form data:', data);
            return data;
        }
        
        // Handle form submission
        document.querySelector('#editIpcrtModal form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = e.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            
            try {
                // Collect form data including activities
                const data = collectFormData(form);
                
                // Log the data being sent
                console.log('Submitting form with data:', data);
                
                // Create URL-encoded form data
                const formData = new URLSearchParams();
                
                // Add all form fields
                for (const key in data) {
                    if (Array.isArray(data[key])) {
                        // Handle arrays (like activities[])
                        data[key].forEach(value => {
                            formData.append(key + '[]', value);
                        });
                    } else {
                        formData.append(key, data[key]);
                    }
                }
                
                // Send the request
                const response = await fetch('ipcr_reports.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData,
                    credentials: 'same-origin'
                });
                
                const responseText = await response.text();
                console.log('Server response:', responseText);
                
                if (!response.ok) {
                    throw new Error(`Server responded with status ${response.status}: ${responseText}`);
                }
                
                // Reload to show updated data and messages
                window.location.reload();
                
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while saving the changes. Please check the console for details and try again.');
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        });
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                placement: 'bottom',
                html: true,
                title: function() {
                    return '<div class="bg-dark text-white p-2 rounded">' + 
                           '<h6 class="mb-1">Add New IPCR Entry</h6>' +
                           '<p class="mb-0">Click to add a new IPCR entry with details about your key result areas, success indicators, and actual accomplishments.</p>' +
                           '</div>';
                }
            });
        });
        
        // Toggle sidebar on mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const sidebarOverlay = document.createElement('div');
            sidebarOverlay.className = 'sidebar-overlay';
            document.body.appendChild(sidebarOverlay);
            
            // Toggle sidebar when menu button is clicked
            document.querySelector('.sidebar-toggle').addEventListener('click', function() {
                sidebar.classList.toggle('active');
                mainContent.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });
            
            // Close sidebar when overlay is clicked
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                mainContent.classList.remove('active');
                this.classList.remove('active');
            });
            
            // Update current time
            function updateCurrentTime() {
                const now = new Date();
                const options = { 
                    hour: 'numeric', 
                    minute: '2-digit', 
                    second: '2-digit',
                    hour12: true 
                };
                document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', options);
                document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
            }
            
            // Update time every second
            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);
        });
    </script>
</body>
</html>
