<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch yearly statistics
$currentYear = date('Y');
$fiveYearsAgo = $currentYear - 4;

// Get yearly activities
$yearlyStats = $pdo->query(
    "SELECT 
        YEAR(a.created_at) as year,
        COUNT(a.id) as total_activities,
        SUM(CASE WHEN LOWER(a.status) = 'completed' THEN 1 ELSE 0 END) as completed_activities,
        SUM(CASE WHEN LOWER(a.status) = 'in progress' THEN 1 ELSE 0 END) as in_progress_activities,
        (SELECT COUNT(DISTINCT p.id) FROM projects p 
         WHERE YEAR(p.created_at) = YEAR(a.created_at)) as total_projects
    FROM activities a
    WHERE a.created_at >= '$fiveYearsAgo-01-01'
    GROUP BY YEAR(a.created_at)
    ORDER BY year DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Get current year stats or set defaults
$currentYearStats = [
    'year' => $currentYear,
    'total_activities' => 0,
    'completed_activities' => 0,
    'in_progress_activities' => 0,
    'total_projects' => 0
];

foreach ($yearlyStats as $yearData) {
    if ($yearData['year'] == $currentYear) {
        $currentYearStats = $yearData;
        break;
    }
}

// Calculate completion percentage for current year
$completion_percentage = $currentYearStats['total_activities'] > 0 ? 
    round(($currentYearStats['completed_activities'] / $currentYearStats['total_activities']) * 100) : 0;

// Calculate in progress percentage for current year
$in_progress_percentage = $currentYearStats['total_activities'] > 0 ? 
    round(($currentYearStats['in_progress_activities'] / $currentYearStats['total_activities']) * 100) : 0;

// Prepare stats array with current year data
$stats = [
    // Current year stats
    'current_year' => $currentYear,
    'total_activities' => (int)$currentYearStats['total_activities'],
    'completed_count' => (int)$currentYearStats['completed_activities'],
    'in_progress_count' => (int)$currentYearStats['in_progress_activities'],
    'total_projects' => (int)$currentYearStats['total_projects'],
    'completion_percentage' => $completion_percentage,
    'in_progress_percentage' => $in_progress_percentage,
    'yearly_stats' => $yearlyStats,
    
    // Additional stats can be added here
];

// Create variables for cleaner template usage
$total_activities = $stats['total_activities'];
$completed_count = $stats['completed_count'];
$in_progress_count = $stats['in_progress_count'];
$total_projects = $stats['total_projects'];
$completion_percentage = $stats['completion_percentage'];
$in_progress_percentage = $stats['in_progress_percentage'];
$currentYear = $stats['current_year'];

// Fetch activities by status
$activities = [
    'ongoing' => $pdo->query(
        "SELECT a.*, p.title as project_title 
         FROM activities a 
         LEFT JOIN projects p ON a.project_id = p.id 
         WHERE LOWER(a.status) = 'in progress' 
         AND a.end_date <= CURDATE()
         ORDER BY a.end_date DESC, a.start_date DESC"
    )->fetchAll(),
    'upcoming' => $pdo->query(
        "SELECT a.*, p.title as project_title 
         FROM activities a 
         LEFT JOIN projects p ON a.project_id = p.id 
         WHERE a.start_date > CURDATE()
         ORDER BY a.start_date ASC, a.created_at DESC"
    )->fetchAll()
];

// Fetch important notes
$important_notes = $pdo->query(
    "SELECT n.*, u.full_name, p.title as project_title 
     FROM notes n 
     LEFT JOIN users u ON n.user_id = u.id 
     LEFT JOIN projects p ON n.project_id = p.id 
     WHERE n.status NOT IN ('completed', 'archived')
     ORDER BY 
        CASE n.priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
        END,
        n.created_at DESC"
)->fetchAll();

// Fetch all active projects for dropdown
$stmt = $pdo->query("SELECT id, title FROM projects WHERE status != 'completed' ORDER BY title");
$projects = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/dashboard-edit-buttons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Updated theme with white containers */
        :root {
            --primary-bg: #0a192f;
            --secondary-bg: rgba(16, 32, 56, 0.9);
            --accent-color: #64ffda;
            --accent-secondary: #7928ca;
            --accent-tertiary: #0083b0;
            --accent-notes1: #ffb347;
            --accent-notes2: #ff5e62;
            --text-dark: #1a1a1a;
            --text-secondary-dark: #4a5568;
            --border-color: rgba(100, 255, 218, 0.3);
            --card-bg: #ffffff;
            --hover-bg: rgba(100, 255, 218, 0.05);
            --text-white: #ffffff;
            --note-edit-color: #64ffda;
            --note-delete-color: #ff5e62;
        }

        /* Modern button styles */
        .note-action-btn {
            position: relative;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(100, 255, 218, 0.05);
            color: var(--accent-color);
            overflow: hidden;
            z-index: 1;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .note-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(100, 255, 218, 0.1),
                transparent
            );
            transition: 0.6s;
            z-index: -1;
        }

        .note-action-btn:hover::before {
            left: 100%;
        }

        .note-edit-btn {
            color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .note-edit-btn:hover {
            background: rgba(100, 255, 218, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px -2px rgba(100, 255, 218, 0.2);
        }

        .note-delete-btn {
            color: #ff6b6b;
            border-color: #ff6b6b;
        }

        .note-delete-btn:hover {
            background: rgba(255, 107, 107, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px -2px rgba(255, 107, 107, 0.2);
        }

        .note-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(100, 255, 218, 0.3);
        }

        .note-action-btn i {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1rem;
        }

        .note-action-btn:hover i {
            transform: scale(1.15);
        }

        .note-actions, .activity-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Activity card styles */
        .activity-card {
            background: rgba(10, 25, 47, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(100, 255, 218, 0.1) !important;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        .activity-title {
            color: var(--accent-color);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .activity-due {
            font-size: 0.85rem;
            color: #a5b1c9;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .activity-due i {
            color: #64ffda;
        }

        .activity-description {
            color: #e2e8f0;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }

        .activity-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .activity-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .recent-activities-header, .important-notes-header {
            background: var(--secondary-bg) !important;
            color: var(--text-white) !important;
            border-bottom: 2px solid var(--accent-color);
            border-radius: 16px 16px 0 0;
            box-shadow: none;
            padding: 0.75rem 1.25rem;
            margin: 0;
        }
        .dashboard-card-section {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            color: var(--text-white);
            margin: 0;
            padding: 0;
        }
        .dashboard-card-section .card-body {
            padding: 0;
            margin: 0;
        }
        .dashboard-card-section .card-body {
            background: var(--secondary-bg);
            color: var(--text-white);
            border-radius: 0 0 16px 16px;
        }
        .dashboard-card-section .list-group-item {
            background: rgba(28,32,59,0.93) !important;
            color: var(--text-white) !important;
            border-bottom: 1px solid var(--border-color);
            border-radius: 0;
            margin-bottom: 0;
            padding: 0.6rem 1rem;
            box-shadow: none;
            transition: background 0.2s, box-shadow 0.2s;
            position: relative;
            z-index: 1;
            font-size: 0.85rem;
            border-left: none;
            border-right: none;
        }
        .dashboard-card-section .list-group-item:first-child {
            border-top: none;
        }
        .dashboard-card-section .list-group-item:last-child {
            border-bottom: none;
            border-radius: 0 0 16px 16px;
        }
        
        /* Ensure dropdown menu is above other elements */
        .dropdown-menu {
            z-index: 9999 !important;
            position: absolute !important;
            bottom: 100% !important;
            top: auto !important;
            left: 0 !important;
            right: auto !important;
            margin-bottom: 5px;
            min-width: 180px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border: 1px solid rgba(100,255,218,0.2);
            background: rgba(28,32,59,0.98) !important;
            transform: none !important;
            padding: 0.5rem 0;
            border-radius: 12px;
            display: block !important;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s, visibility 0.2s;
        }
        
        .dropdown.show .dropdown-menu {
            opacity: 1;
            visibility: visible;
        }
        
        .dropdown-item {
            color: #e2e8f0 !important;
            padding: 0.5rem 1.25rem;
            border-radius: 12px;
            transition: background 0.2s;
        }
        
        .dropdown-item:hover {
            background: rgba(100,255,218,0.15) !important;
            color: #fff !important;
        }
        
        /* Fix for dropdown in activity cards */
        .card {
            position: relative;
            z-index: 1;
            margin-bottom: 1rem;
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
        }
        
        .dropdown {
            position: relative;
            z-index: 2;
            display: inline-block;
        }
        
        .dropdown-toggle::after {
            vertical-align: middle;
            margin-left: 0.5em;
        }
        
        .btn-update-status {
            background: rgba(100,255,218,0.1) !important;
            border: 1px solid var(--accent-color);
            color: var(--accent-color) !important;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            border-radius: 20px;
            transition: all 0.2s ease;
        }
        
        .btn-update-status:hover {
            background: rgba(100,255,218,0.2) !important;
            transform: translateY(-1px);
        }
        .dashboard-card-section .list-group-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .dashboard-card-section .list-group-item:hover {
            background: rgba(100,255,218,0.08) !important;
            box-shadow: 0 4px 16px rgba(100,255,218,0.08);
        }
        .dashboard-card-section .activity-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.25rem;
            letter-spacing: 0.01em;
        }
        .dashboard-card-section .activity-desc {
            color: #b2becd;
            font-size: 0.98rem;
            margin-bottom: 0.5rem;
        }
        .dashboard-card-section .activity-meta {
            font-size: 0.93rem;
            color: #a0aec0;
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        .dashboard-card-section .badge {
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 20px;
            padding: 5px 12px;
            letter-spacing: 0.01em;
        }
        .dashboard-card-section .project-badge {
            background: rgba(100,255,218,0.13);
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
            margin-left: 7px;
        }
        .dashboard-card-section .activity-date-badge {
            background: #222e3c;
            color: #fff;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 600;
            padding: 6px 14px;
            display: inline-block;
            margin-left: 0.5rem;
            letter-spacing: 0.01em;
        }
        .dashboard-card-section .activity-date-badge i {
            color: var(--accent-color);
        }
        .dashboard-card-section .badge {
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 20px;
        }
        .dashboard-card-section .btn,
        .dashboard-card-section .btn-outline-primary,
        .dashboard-card-section .btn-outline-danger {
            border-radius: 8px;
            font-weight: 500;
        }

        body {
            background-color: var(--primary-bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(100, 255, 218, 0.1) 0%, transparent 50%),
                radial-gradient(at 100% 0%, rgba(121, 40, 202, 0.1) 0%, transparent 50%);
            font-family: 'Space Grotesk', sans-serif;
            color: var(--text-white);
        }

        .main-content {
            padding: 30px;
            position: relative;
            color: var(--text-white);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stats-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            position: relative;
            overflow: hidden;
            color: var(--text-dark);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-color), var(--accent-secondary));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover::before {
            opacity: 1;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(100, 255, 218, 0.2);
        }

        .stats-icon {
            color: var(--accent-color);
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 10px 0;
        }

        .stats-title {
            color: var(--text-secondary-dark);
            font-size: 1rem;
            font-weight: 500;
        }

        /* Info Cards */
        .info-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(100, 255, 218, 0.2);
        }

        .info-card .card-header {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-secondary));
            padding: 20px;
            position: relative;
        }

        .info-card .card-header h5 {
            color: var(--primary-bg);
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card .card-header h5 i {
            color: var(--primary-bg);
        }

        .info-card .card-body {
            padding: 1rem;
            background: var(--card-bg);
        }

        /* Table Styling */
        .table {
            color: var(--text-dark);
            margin: 0;
            font-family: 'JetBrains Mono', monospace;
        }

        .table th {
            color: var(--text-dark);
            font-weight: 600;
            border-bottom: 2px solid var(--accent-color);
            padding: 15px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(100, 255, 218, 0.05);
        }

        .table td {
            color: var(--text-secondary-dark);
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .table tr:hover td {
            background: rgba(100, 255, 218, 0.05);
            color: var(--text-dark);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            background: var(--card-bg);
        }

        .status-not-started {
            color: #6c757d;
            border: 1px solid #6c757d;
        }

        .status-pending {
            color: #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
            border: 1px solid #ffc107;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }

        .status-in-progress {
            color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.1);
            border: 1px solid #0d6efd;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .status-completed {
            color: #198754;
            background-color: rgba(25, 135, 84, 0.1);
            border: 1px solid #198754;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }

        .status-on-hold {
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        /* Priority Badges */
        .priority-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .priority-high {
            background-color: #dc3545;
            color: white;
        }

        .priority-medium {
            background-color: #ffc107;
            color: var(--text-dark);
        }

        .priority-low {
            background-color: #28a745;
            color: white;
        }

        /* Welcome Text */
        .welcome-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-white);
            margin-bottom: 0.2rem;
        }

        /* Current Date */
        .current-date {
            font-size: 1.1rem;
            color: var(--text-white);
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .current-date i {
            color: var(--accent-color);
        }

        /* Animations */
        .animate__fadeInUp {
            animation-duration: 0.6s;
        }

        .animate__delay-1s {
            animation-delay: 0.3s;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--primary-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-secondary);
        }

        /* Notes Container Styles */
        .notes-container {
            display: flex;
            flex-direction: row;
            gap: 20px;
            padding: 10px;
            width: 100%;
            margin: 0 auto;
            overflow-x: auto;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 15px;
            min-height: 150px; /* Reduced height since we're showing less content */
        }

        .note-card {
            flex: 0 0 250px; /* Slightly smaller width */
            min-height: 150px; /* Changed from fixed height to min-height */
            background: rgba(28, 32, 59, 0.8);
            border-radius: 10px;
            padding: 12px;
            border: 1px solid var(--border-color);
            position: relative;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(100, 255, 218, 0.2);
        }

        /* Note Header */
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        /* Recent Activities Scrollable Container */
        .recent-activities-container {
            max-height: 600px; /* Maximum height with scroll */
            min-height: 200px; /* Minimum height to show scrollbar */
            overflow-y: auto;
            padding: 1rem;
            background: var(--secondary-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            scrollbar-width: thin;
            scrollbar-color: #c1c1c1 #f1f1f1;
        }
        
        .recent-activities-scroll {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        /* Custom scrollbar */
        .recent-activities-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .recent-activities-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .recent-activities-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .recent-activities-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Ensure each activity card has consistent height */
        .recent-activities-scroll .card {
            min-height: 100px; /* Slightly reduced height to fit more items */
            transition: transform 0.2s ease;
            flex-shrink: 0; /* Prevent cards from shrinking */
        }
        
        .recent-activities-scroll .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
        }

        /* Important Notes Scrollable Container */
        .important-notes-container {
            height: 420px; /* Match height with recent activities */
            overflow-y: auto;
            padding: 0.5rem;
        }
        
        .important-notes-scroll {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        /* Custom scrollbar for important notes */
        .important-notes-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .important-notes-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .important-notes-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .important-notes-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Ensure each note card has consistent height */
        .important-notes-scroll .card {
            min-height: 120px;
            transition: transform 0.2s ease;
        }
        
        .important-notes-scroll .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
        }
        
        .note-priority {
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #ffffff;
        }

        /* Note Title */
        .note-title {
            color: var(--text-white);
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Note Footer */
        .note-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 8px;
            border-top: 1px solid var(--border-color);
        }

        .note-date {
            color: var(--text-white);
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .note-actions {
            display: flex;
            gap: 5px;
        }

        .note-actions .btn-custom-outline {
            padding: 2px 6px;
            font-size: 0.8rem;
        }

        /* Priority Colors */
        .note-priority.high {
            background: rgba(220, 53, 69, 0.8);
        }

        .note-priority.medium {
            background: rgba(255, 193, 7, 0.8);
        }

        .note-priority.low {
            background: rgba(40, 167, 69, 0.8);
        }

        /* Important Notes Card */
        .card.animate__animated.animate__fadeInUp.mb-4 {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-secondary));
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h5 {
            color: var(--primary-bg);
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Enhanced Sidebar Styling */
        .sidebar {
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            background: linear-gradient(180deg, #0a192f 0%, #0f1b35 100%);
            scrollbar-width: thin;
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

        .main-content {
            overflow-y: auto;
            height: 100vh;
            padding: 2rem 2rem 4rem;
            margin-left: 350px;
        }

        .sidebar-header {
            padding: 1rem 0.5rem;
            margin-bottom: 0.5rem;
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
        }

        /* Quick Actions */
        .quick-actions .btn {
            transition: all 0.2s;
            border-radius: 8px;
        }

        .quick-actions .btn:hover {
            background: rgba(100, 255, 218, 0.15) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Badges */
        .badge {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
        }

        .bg-accent {
            background-color: var(--accent-color) !important;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {<div class="activity-footer">
        </div>
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(100, 255, 218, 0.05);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(100, 255, 218, 0.2);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 255, 218, 0.3);
        }

        
        /* Status Indicator */
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        
        .status-online {
            background-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row h-100">
            <!-- Include Sidebar -->
            <?php include 'sidebar.php'; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content animate__animated animate__fadeIn" style="margin-left: 350px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="welcome-text">Dashboard Overview</h2>
                    </div>
                </div>
                
                <!-- Scorecard Section -->
                <div class="row g-0">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="card-title mb-0 small text-uppercase fw-bold" style="font-size: 0.75rem;"><i class="bi bi-speedometer2 me-1"></i>Performance Scorecard</h5>
                                    <div class="text-muted small" style="font-size: 0.7rem;">Updated just now</div>
                                </div>
                                <div class="row g-1">
                                    <?php 
                                    // Calculate completion percentage if not already set
                                    if (!isset($stats['completion_percentage'])) {
                                        $stats['completion_percentage'] = $stats['total_activities'] > 0 
                                            ? round(($stats['completed_count'] / $stats['total_activities']) * 100) 
                                            : 0;
                                    }
                                    
                                    // Set current year if not already set
                                    if (!isset($stats['current_year'])) {
                                        $stats['current_year'] = date('Y');
                                    }
                                    
                                    // Set in_progress_percentage if not already set
                                    if (!isset($stats['in_progress_percentage'])) {
                                        $stats['in_progress_percentage'] = $stats['total_activities'] > 0 
                                            ? round(($stats['in_progress_count'] / $stats['total_activities']) * 100) 
                                            : 0;
                                    }
                                    
                                    $statCards = [
                                        [
                                            'title' => 'Activities',
                                            'value' => $stats['total_activities'],
                                            'icon' => 'bi-list-check',
                                            'bg' => 'primary',
                                            'trend' => 'up',
                                            'trend_value' => 0,
                                            'change' => 'Current Year',
                                            'link' => 'activities.php?year=' . $currentYear,
                                            'subtitle' => $stats['completed_count'] . ' completed',
                                            'trend_improvement' => true
                                        ],
                                        [
                                            'title' => 'Completion Rate',
                                            'value' => $stats['completion_percentage'] . '%',
                                            'icon' => 'bi-check2-all',
                                            'bg' => 'success',
                                            'progress' => $stats['completion_percentage'],
                                            'change' => 'of ' . $stats['total_activities'] . ' activities',
                                            'trend_improvement' => $stats['completion_percentage'] > 50,
                                            'link' => 'activities.php?status=completed&year=' . $currentYear
                                        ],
                                        [
                                            'title' => 'In Progress',
                                            'value' => $stats['in_progress_count'],
                                            'icon' => 'bi-hourglass-split',
                                            'bg' => 'warning',
                                            'progress' => $stats['in_progress_percentage'],
                                            'change' => $stats['in_progress_percentage'] . '% of total',
                                            'trend_improvement' => $stats['in_progress_count'] < ($stats['total_activities'] * 0.3), // Good if less than 30% in progress
                                            'link' => 'activities.php?status=in+progress&year=' . $currentYear
                                        ],
                                        [
                                            'title' => 'Projects',
                                            'value' => $stats['total_projects'],
                                            'icon' => 'bi-folder2-open',
                                            'bg' => 'info',
                                            'trend' => 'up',
                                            'trend_value' => 0,
                                            'change' => 'Current Year',
                                            'subtitle' => 'Active projects',
                                            'trend_improvement' => true,
                                            'link' => 'projects.php?year=' . $currentYear
                                        ]
                                    ];
                                    
                                    foreach ($statCards as $card): 
                                        $trend_icon = isset($card['trend']) && $card['trend'] === 'up' ? 'bi-arrow-up' : 'bi-arrow-down';
                                        $trend_class = isset($card['trend']) && $card['trend'] === 'up' ? 'text-success' : 'text-danger';
                                    ?>
                                    <div class="col-6 col-md-3 p-1">
                                        <div class="stats-card bg-white p-2 rounded-3 shadow-sm h-100 position-relative overflow-hidden" 
                                             <?php echo isset($card['link']) ? 'onclick="window.location.href=\'' . $card['link'] . '\'" style="cursor: pointer;"' : ''; ?>>
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="w-100">
                                                    <div class="d-flex justify-content-between align-items-center mb-0">
                                                        <h6 class="text-muted mb-0 small fw-semibold text-uppercase text-truncate" style="font-size: 0.7rem;"><?= htmlspecialchars($card['title']) ?></h6>
                                                        <div class="icon bg-soft-<?= $card['bg'] ?> text-<?= $card['bg'] ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                            <i class="bi <?= $card['icon'] ?>" style="font-size: 0.9rem;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-baseline mb-0">
                                                        <div class="fw-bold text-<?= $card['bg'] ?> mb-0" style="font-size: 1.5rem;">
                                                            <?= htmlspecialchars($card['value']) ?>
                                                        </div>
                                                        <?php if (isset($card['trend_value'])): ?>
                                                        <div class="ms-1 d-flex align-items-center">
                                                            <span class="badge bg-soft-<?= $card['trend_improvement'] ? 'success' : 'danger' ?> text-<?= $card['trend_improvement'] ? 'success' : 'danger' ?> px-1 py-0" style="font-size: 0.6rem;">
                                                                <i class="bi <?= $card['trend'] === 'up' ? 'bi-arrow-up' : 'bi-arrow-down' ?> me-0"></i>
                                                                <?= abs($card['trend_value']) ?>%
                                                            </span>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (isset($card['subtitle'])): ?>
                                                    <div class="text-muted small mb-1" style="font-size: 0.7rem;"><?= $card['subtitle'] ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (isset($card['progress'])): ?>
                                            <div class="mt-1">
                                                <div class="d-flex justify-content-between small text-muted mb-1" style="font-size: 0.65rem;">
                                                    <span>Progress</span>
                                                    <span><?= $card['progress'] ?>%</span>
                                                </div>
                                                <div class="progress" style="height: 4px;">
                                                    <div class="progress-bar bg-<?= $card['bg'] ?>" role="progressbar" 
                                                         style="width: <?= $card['progress'] ?>%" 
                                                         aria-valuenow="<?= $card['progress'] ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($card['change'])): ?>
                                            <div class="mt-2 pt-1 border-top small" style="font-size: 0.7rem;">
                                                <div class="d-flex align-items-center text-<?= $card['trend_improvement'] ? 'success' : 'danger' ?>">
                                                    <i class="bi <?= $card['trend_improvement'] ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow' ?> me-1"></i>
                                                    <span class="text-muted"><?= $card['change'] ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <!-- Hover effect -->
                                            <div class="position-absolute top-0 end-0 bg-<?= $card['bg'] ?>" style="width: 40px; height: 40px; border-radius: 0 0 0 100%; opacity: 0.1;"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes, Upcoming & Pending Activities Section -->
                <div class="row g-2">
                    <!-- Notes Container -->
                    <div class="col-12 col-lg-4 p-1">
                        <div class="card h-100 shadow-sm border-0 mb-0">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>My Notes</h6>
                                <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#noteModal">
                                    <i class="bi bi-plus"></i> Add
                                </button>
                            </div>
                            <div class="card-body p-0 h-100 d-flex flex-column">
                                <div class="list-group list-group-flush flex-grow-1 overflow-auto" id="notesContainer" style="max-height: 500px;">
                                    <?php if (!empty($important_notes)): ?>
                                        <?php foreach ($important_notes as $note): ?>
                                        <div class="activity-card" data-note-id="<?= $note['id'] ?>">
                                            <h6 class="activity-title"><?= htmlspecialchars($note['title']) ?></h6>
                                            
                                            <div class="activity-meta">
                                                <span class="activity-tag" style="background: <?= $note['priority'] === 'high' ? 'rgba(255, 71, 87, 0.2)' : ($note['priority'] === 'medium' ? 'rgba(255, 184, 0, 0.2)' : 'rgba(0, 184, 255, 0.2)') ?>; color: <?= $note['priority'] === 'high' ? '#ff4757' : ($note['priority'] === 'medium' ? '#ffb800' : '#00b8ff') ?>; border: 1px solid <?= $note['priority'] === 'high' ? 'rgba(255, 71, 87, 0.3)' : ($note['priority'] === 'medium' ? 'rgba(255, 184, 0, 0.3)' : 'rgba(0, 184, 255, 0.3)') ?>; padding: 0.35em 0.65em;">
                                                    <i class="bi bi-<?= $note['priority'] === 'high' ? 'exclamation-triangle' : ($note['priority'] === 'medium' ? 'exclamation-circle' : 'info-circle') ?>"></i> <?= ucfirst($note['priority']) ?>
                                                </span>
                                                <span class="activity-tag" style="background: <?= $note['status'] === 'completed' ? 'rgba(46, 213, 115, 0.2)' : ($note['status'] === 'archived' ? 'rgba(165, 177, 201, 0.2)' : 'rgba(100, 255, 218, 0.2)') ?>; color: <?= $note['status'] === 'completed' ? '#2ed573' : ($note['status'] === 'archived' ? '#a5b1c9' : '#64ffda') ?>; border: 1px solid <?= $note['status'] === 'completed' ? 'rgba(46, 213, 115, 0.3)' : ($note['status'] === 'archived' ? 'rgba(165, 177, 201, 0.3)' : 'rgba(100, 255, 218, 0.3)') ?>; padding: 0.35em 0.65em;">
                                                    <i class="bi bi-<?= $note['status'] === 'completed' ? 'check-circle' : ($note['status'] === 'archived' ? 'archive' : 'circle') ?>"></i> <?= ucfirst($note['status']) ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Note content is hidden as per user request -->
                                            
                                            <div class="activity-footer">
                                                <div class="activity-actions">
                                                    <button class="note-action-btn note-edit-btn edit-note" data-note-id="<?= $note['id'] ?>" title="Edit Note">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </div>
                                                <?php if (!empty($note['project_title'])): ?>
                                                    <span class="activity-tag" style="background: rgba(0, 168, 255, 0.2); color: #00a8ff; border: 1px solid rgba(0, 168, 255, 0.3);">
                                                        <i class="bi bi-folder2-open"></i> <?= htmlspecialchars($note['project_title']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center p-4 text-muted">
                                            <i class="bi bi-journal-text display-6 d-block mb-2"></i>
                                            <p class="mb-0">No notes found. Add a new note to get started.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent text-center">
                                    <a href="activities.php" class="btn btn-sm btn-outline-primary">
                                        View All Notes <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Activities Container -->
                    <div class="col-12 col-lg-4 p-1">
                        <div class="card h-100 shadow-sm border-0 mb-0">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Upcoming Activities</h6>
                                <span class="badge bg-white text-success"><?= count($activities['upcoming'] ?? []) ?></span>
                            </div>
                            <div class="card-body p-0 h-100 d-flex flex-column">
                                <div class="flex-grow-1 overflow-auto" id="upcomingActivities" style="max-height: 500px;">
                                    <?php if (!empty($activities['upcoming'])): ?>
                                        <?php 
                                        // Include the activity card template if not already included
                                        if (!function_exists('get_activity_card_html')) {
                                            require_once 'includes/activity-card.php';
                                        }
                                        
                                        // Loop through upcoming activities and render each card
                                        foreach ($activities['upcoming'] as $activity): 
                                            echo get_activity_card_html($activity, 'upcoming');
                                        endforeach; 
                                        ?>
                                    <?php else: ?>
                                        <div class="text-center p-4 text-muted">
                                            <i class="bi bi-calendar-x display-6 d-block mb-2"></i>
                                            <p class="mb-0">No upcoming activities scheduled.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent text-center">
                                    <a href="activities.php" class="btn btn-sm btn-outline-success">
                                        View All Activities <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- In Progress Activities Container -->
                    <div class="col-12 col-lg-4 p-1">
                        <div class="card h-100 shadow-sm border-0 mb-0">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-exclamation-circle me-2"></i>Due Activities</h6>
                                <span class="badge bg-white text-info"><?= count($activities['ongoing'] ?? []) ?></span>
                            </div>
                            <div class="card-body p-0 h-100 d-flex flex-column">
                                <div class="flex-grow-1 overflow-auto" id="pendingActivities" style="max-height: 500px;">
                                    <?php if (!empty($activities['ongoing'])): ?>
                                        <?php 
                                        // Include the activity card template if not already included
                                        if (!function_exists('get_activity_card_html')) {
                                            require_once 'includes/activity-card.php';
                                        }
                                        
                                        // Loop through ongoing activities and render each card
                                        foreach ($activities['ongoing'] as $activity): 
                                            echo get_activity_card_html($activity, 'in-progress');
                                        endforeach; 
                                        ?>
                                    <?php else: ?>
                                        <div class="text-center p-4 text-muted">
                                            <i class="bi bi-check2-circle display-6 d-block mb-2"></i>
                                            <p class="mb-0">No pending activities. Great job!</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent text-center">
                                    <a href="activities.php?status=in-progress" class="btn btn-sm btn-outline-info">
                                        View All Activities <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Success Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="statusToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                Activity status updated successfully!
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <!-- Status Update Modal -->
    <!-- Status Update Modal -->
    <div class="modal fade" id="statusUpdateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content light-theme">
                <div class="modal-header">
                    <h5 class="modal-title">Update Activity Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Updating status for: <strong id="activityTitle"></strong></p>
                    <form id="statusUpdateForm">
                        <input type="hidden" id="activityId" name="activity_id">
                        <div class="mb-3">
                            <label for="statusSelect" class="form-label">Status</label>
                            <select class="form-select" id="statusSelect" name="status" required>
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="On Hold">On Hold</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="statusNotes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="statusNotes" name="notes" rows="3" placeholder="Add any additional notes about this status update"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveStatusUpdate">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content light-theme">
                <div class="modal-header">
                    <h5 class="modal-title">Update Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="updateStatusForm">
                        <input type="hidden" name="note_id" id="updateNoteId">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select light-theme" name="status" id="updateStatus">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select light-theme" name="priority" id="updatePriority">
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-custom" id="saveStatus">Update Note</button>
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
                An error occurred. Please try again.
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard-edit-buttons.js"></script>

    <!-- Main Script -->
    <script>
    // Clock functionality
    function updateClock() {
        const timeElement = document.getElementById('current-time');
        if (!timeElement) return;
        
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
        
        timeElement.textContent = timeString;
    }

    // Show toast notification
    function showToast(type, message) {
        const toastElement = document.getElementById(`${type}Toast`);
        const toastMessage = document.getElementById(`${type}ToastMessage`);
        
        if (toastElement && toastMessage) {
            toastMessage.textContent = message;
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
        }
    }



    // Handle status update for activities
    async function handleActivityStatusUpdate() {
        const form = document.getElementById('statusUpdateForm');
        const formData = new FormData(form);
        const saveButton = document.getElementById('saveStatusUpdate');
        const originalText = saveButton.innerHTML;
        const activityId = formData.get('activity_id');
        const newStatus = formData.get('status');
        const notes = formData.get('notes');

        // Show loading state
        saveButton.disabled = true;
        saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';

        try {
            const response = await fetch('api/update_activity_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    activity_id: activityId,
                    status: newStatus,
                    notes: notes || ''
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('success', 'Activity status updated successfully!');
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('statusUpdateModal'));
                modal.hide();
                
                // Update the UI without refreshing
                const statusBadge = document.querySelector(`.activity-status[data-activity-id="${activityId}"]`);
                if (statusBadge) {
                    // Update status badge
                    const statusClass = getStatusBadgeClass(newStatus);
                    statusBadge.className = `badge ${statusClass} activity-status`;
                    statusBadge.textContent = newStatus;
                    
                    // If this is a card, update the status text as well
                    const card = statusBadge.closest('.card');
                    if (card) {
                        const statusText = card.querySelector('.activity-status-text');
                        if (statusText) {
                            statusText.textContent = newStatus;
                        }
                    }
                }
                
            } else {
                throw new Error(data.message || 'Failed to update activity status');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('error', error.message || 'An error occurred while updating the activity status.');
        } finally {
            // Reset button state
            saveButton.disabled = false;
            saveButton.innerHTML = originalText;
        }
    }
    
    // Helper function to get appropriate badge class based on status
    function getStatusBadgeClass(status) {
        switch(status.toLowerCase()) {
            case 'completed':
                return 'bg-success-subtle text-success';
            case 'in progress':
                return 'bg-primary-subtle text-primary';
            case 'on hold':
                return 'bg-warning-subtle text-warning';
            case 'pending':
            default:
                return 'bg-secondary-subtle text-secondary';
        }
    }
    }



    // Handle update status button clicks
    function setupStatusUpdateButtons() {
        document.querySelectorAll('.update-status').forEach(button => {
            button.addEventListener('click', function() {
                const noteId = this.getAttribute('data-note-id');
                const status = this.getAttribute('data-status');
                const priority = this.closest('.card').querySelector('.badge').textContent.trim().toLowerCase();
                
                document.getElementById('updateNoteId').value = noteId;
                document.getElementById('updateStatus').value = status;
                document.getElementById('updatePriority').value = priority;
                
                const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
                modal.show();
            });
        });
    }

    // Setup click handler for status update button
    document.addEventListener('click', function(e) {
        if (e.target && e.target.matches('.update-activity-status')) {
            e.preventDefault();
            const activityId = e.target.getAttribute('data-activity-id');
            const activityTitle = e.target.getAttribute('data-activity-title');
            const currentStatus = e.target.getAttribute('data-current-status') || 'Pending';
            
            // Set modal fields
            document.getElementById('activityTitle').textContent = activityTitle;
            document.getElementById('activityId').value = activityId;
            document.getElementById('statusSelect').value = currentStatus;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
            modal.show();
        }
    });
    
    // Handle save status update button click
    const saveStatusBtn = document.getElementById('saveStatusUpdate');
    if (saveStatusBtn) {
        saveStatusBtn.addEventListener('click', handleActivityStatusUpdate);
    }
    
    // Also handle form submission with Enter key
    const statusForm = document.getElementById('statusUpdateForm');
    if (statusForm) {
        statusForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleActivityStatusUpdate();
        });
    }

    // Function to open status update modal
    function openStatusUpdateModal(activityId, activityTitle, currentStatus) {
        document.getElementById('activityId').value = activityId;
        document.getElementById('activityTitle').textContent = activityTitle;
        
        // Set the current status in the select dropdown
        const statusSelect = document.getElementById('statusSelect');
        if (statusSelect) {
            // Find and select the current status
            for (let i = 0; i < statusSelect.options.length; i++) {
                if (statusSelect.options[i].value === currentStatus) {
                    statusSelect.selectedIndex = i;
                    break;
                }
            }
        }
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
        modal.show();
    }

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listener for the save status update button
        document.getElementById('saveStatusUpdate').addEventListener('click', function() {
            handleActivityStatusUpdate();
        });
        
        // Add click handlers for all update status buttons
        document.querySelectorAll('.update-status-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const activityId = this.getAttribute('data-activity-id');
                const activityTitle = this.getAttribute('data-activity-title');
                const currentStatus = this.getAttribute('data-current-status') || 'Pending';
                openStatusUpdateModal(activityId, activityTitle, currentStatus);
            });
        });
        
        // Initialize Bootstrap modals
        const statusUpdateModal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
        
        // Initialize dropdown toggles
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const dropdown = this.closest('.dropdown');
                const isOpen = dropdown.classList.contains('show');
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown').forEach(d => {
                    if (d !== dropdown) {
                        d.classList.remove('show');
                    }
                });
                
                // Toggle current dropdown
                if (!isOpen) {
                    dropdown.classList.add('show');
                }
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        });
        
        // Prevent dropdown from closing when clicking inside
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
        
        // Start clock
        updateClock();
        setInterval(updateClock, 1000);
        
        // Setup status update buttons
        document.querySelectorAll('.status-update').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const activityId = this.getAttribute('data-activity-id');
                const newStatus = this.getAttribute('data-status');
                
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';
                
                // Send AJAX request
                fetch('update_activity_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${activityId}&status=${encodeURIComponent(newStatus)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success toast
                        const toastEl = document.getElementById('statusToast');
                        const toast = new bootstrap.Toast(toastEl);
                        toast.show();
                        
                        // Reload the page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert('Error updating status: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating status. Please try again.');
                })
                .finally(() => {
                    // Reset button text
                    this.innerHTML = originalText;
                });
            });
        });
        
        // Reset form when add note button is clicked
        const addNoteBtn = document.querySelector('[data-bs-target="#noteModal"]');
        if (addNoteBtn) {
            addNoteBtn.addEventListener('click', function() {
                document.getElementById('noteModalTitle').textContent = 'Add Important Note';
                document.getElementById('noteForm').reset();
                document.getElementById('noteId').value = ''; // Clear any existing note ID
            });
        }
    });
    </script>
    
    <!-- Activity Modal -->
    <div class="modal fade" id="activityModal" tabindex="-1" aria-labelledby="activityModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="activityModalLabel">Edit Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="activityForm" action="api/save_activity.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="activity_id" id="activityId">
                        <div class="mb-3">
                            <label for="activityTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="activityTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="activityStartDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="activityStartDate" name="start_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="activityEndDate" class="form-label">End Date (Optional)</label>
                            <input type="date" class="form-control" id="activityEndDate" name="end_date">
                        </div>
                        <div class="mb-3">
                            <label for="activityDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="activityDescription" name="description" rows="4"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="activityPriority" class="form-label">Priority</label>
                                <select class="form-select" id="activityPriority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="activityProject" class="form-label">Project (Optional)</label>
                                <select class="form-select" id="activityProject" name="project_id">
                                    <option value="">None</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Note Modal -->
    <div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="noteModalLabel">Add New Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="noteForm" action="api/save_note.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="note_id" id="noteId">
                        <div class="mb-3">
                            <label for="noteTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="noteTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="noteContent" class="form-label">Content</label>
                            <textarea class="form-control" id="noteContent" name="content" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="notePriority" class="form-label">Priority</label>
                                <select class="form-select" id="notePriority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="noteStatus" class="form-label">Status</label>
                                <select class="form-select" id="noteStatus" name="status" required>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="noteProject" class="form-label">Project (Optional)</label>
                            <select class="form-select" id="noteProject" name="project_id">
                                <option value="">None</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Note</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Add event listener for when the note modal is hidden
    document.getElementById('noteModal').addEventListener('hidden.bs.modal', function () {
        location.reload();
    });

    // Handle note form submission
    document.getElementById('noteForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        const noteId = form.note_id.value;
        const status = form.status.value;
        
        // If status is 'completed', delete the note instead of updating it
        if (status === 'completed' && noteId) {
            if (!confirm('Are you sure you want to delete this note? This action cannot be undone.')) {
                return;
            }
            
            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
                
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
                    document.getElementById('successToast').querySelector('.toast-body').textContent = 'Note deleted successfully!';
                    toast.show();
                    
                    // Close modal and refresh notes
                    const modal = bootstrap.Modal.getInstance(document.getElementById('noteModal'));
                    modal.hide();
                    
                    // Reload the page to show updated notes
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(result.message || 'Failed to delete note');
                }
            } catch (error) {
                console.error('Error:', error);
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                document.getElementById('errorToast').querySelector('.toast-body').textContent = 
                    'Error: ' + (error.message || 'Failed to delete note');
                toast.show();
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        } else {
            // Original update logic for non-completed status
            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
                
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    const toast = new bootstrap.Toast(document.getElementById('successToast'));
                    document.getElementById('successToast').querySelector('.toast-body').textContent = 
                        noteId ? 'Note updated successfully!' : 'Note added successfully!';
                    toast.show();
                    
                    // Close modal and refresh notes
                    const modal = bootstrap.Modal.getInstance(document.getElementById('noteModal'));
                    modal.hide();
                    
                    // Reload the page to show updated notes
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(result.message || 'Failed to save note');
                }
            } catch (error) {
                console.error('Error:', error);
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                document.getElementById('errorToast').querySelector('.toast-body').textContent = 
                    'Error: ' + (error.message || 'Failed to save note');
                toast.show();
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }
    });

    // Function to handle activity editing
    async function editActivity(activityId) {
        try {
            const response = await fetch(`api/get_activity.php?id=${activityId}`);
            const result = await response.json();
            
            if (result.success) {
                const activity = result.data;
                const modal = document.getElementById('activityModal');
                
                // Set form values
                modal.querySelector('#activityId').value = activity.id;
                modal.querySelector('#activityTitle').value = activity.title || '';
                modal.querySelector('#activityStartDate').value = activity.start_date ? activity.start_date.split(' ')[0] : '';
                modal.querySelector('#activityEndDate').value = activity.end_date ? activity.end_date.split(' ')[0] : '';
                modal.querySelector('#activityDescription').value = activity.description || '';
                
                // Set priority
                if (activity.priority) {
                    modal.querySelector('#activityPriority').value = activity.priority;
                }
                
                // Set project dropdown
                const projectSelect = modal.querySelector('#activityProject');
                projectSelect.innerHTML = '<option value="">None</option>';
                
                if (result.projects && result.projects.length > 0) {
                    result.projects.forEach(project => {
                        const option = document.createElement('option');
                        option.value = project.id;
                        option.textContent = project.title;
                        if (activity.project_id == project.id) {
                            option.selected = true;
                        }
                        projectSelect.appendChild(option);
                    });
                }
                
                // Show the modal
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            } else {
                throw new Error(result.message || 'Failed to load activity details');
            }
        } catch (error) {
            console.error('Error fetching activity:', error);
            const toast = new bootstrap.Toast(document.getElementById('errorToast'));
            document.getElementById('errorToast').querySelector('.toast-body').textContent = 'Error loading activity details: ' + (error.message || 'Unknown error occurred');
            toast.show();
        }
    }

    // Function to handle editing a note
    async function editNote(noteId) {
        try {
            const response = await fetch(`api/get_note.php?id=${noteId}`);
            const result = await response.json();
            
            if (result.success) {
                const note = result.data;
                const modal = document.getElementById('noteModal');
                
                // Set form values
                modal.querySelector('#noteId').value = note.id;
                modal.querySelector('#noteTitle').value = note.title || '';
                modal.querySelector('#noteContent').value = note.content || '';
                modal.querySelector('#notePriority').value = note.priority || 'medium';
                
                // Set status
                if (note.status) {
                    modal.querySelector('#noteStatus').value = note.status;
                }
                
                // Populate project dropdown
                const projectSelect = modal.querySelector('#noteProject');
                projectSelect.innerHTML = '<option value="">None</option>';
                
                if (result.projects && result.projects.length > 0) {
                    result.projects.forEach(project => {
                        const option = document.createElement('option');
                        option.value = project.id;
                        option.textContent = project.title;
                        if (note.project_id == project.id) {
                            option.selected = true;
                        }
                        projectSelect.appendChild(option);
                    });
                }
                
                // Update modal title and show
                modal.querySelector('.modal-title').textContent = 'Edit Note';
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            } else {
                throw new Error(result.message || 'Failed to load note details');
            }
        } catch (error) {
            console.error('Error fetching note:', error);
            const toast = new bootstrap.Toast(document.getElementById('errorToast'));
            document.getElementById('errorToast').querySelector('.toast-body').textContent = 'Error loading note details: ' + (error.message || 'Unknown error occurred');
            toast.show();
        }
    }

    // Initialize event listeners when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Handle edit note button clicks using event delegation
        document.addEventListener('click', function(e) {
            if (e.target && e.target.closest('.edit-note')) {
                e.preventDefault();
                const noteId = e.target.closest('.edit-note').getAttribute('data-note-id');
                if (noteId) {
                    editNote(noteId);
                }
            }
        });
        
        // Initialize add note button
        const addNoteBtn = document.querySelector('.btn[data-bs-target="#noteModal"]');
        if (addNoteBtn) {
            addNoteBtn.addEventListener('click', function(e) {
                if (e.target.closest('.edit-note')) return; // Skip if it's an edit button
                
                const modal = document.getElementById('noteModal');
                modal.querySelector('.modal-title').textContent = 'Add New Note';
                modal.querySelector('form').reset();
                modal.querySelector('#noteId').value = '';
                
                // Reset project dropdown
                const projectSelect = modal.querySelector('#noteProject');
                if (projectSelect) {
                    projectSelect.innerHTML = '<option value="">None</option>';
                }
                
                // Set default values
                modal.querySelector('#notePriority').value = 'medium';
                modal.querySelector('#noteStatus').value = 'active';
                
                // Load projects for the dropdown
                fetch('api/get_note.php')
                    .then(response => response.json())
                    .then(result => {
                        if (result.projects && result.projects.length > 0) {
                            const projectSelect = document.getElementById('noteProject');
                            if (projectSelect) {
                                projectSelect.innerHTML = '<option value="">None</option>';
                                result.projects.forEach(project => {
                                    const option = document.createElement('option');
                                    option.value = project.id;
                                    option.textContent = project.title;
                                    projectSelect.appendChild(option);
                                });
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading projects:', error);
                    });
                
                // Show the modal
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            });
        }
    });

    // Handle delete note
    document.querySelectorAll('.delete-note').forEach(button => {
        button.addEventListener('click', async function() {
            const noteId = this.getAttribute('data-note-id');
            
            if (confirm('Are you sure you want to delete this note?')) {
                try {
                    const response = await fetch(`api/delete_note.php?id=${noteId}`, {
                        method: 'DELETE'
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success message
                        const toast = new bootstrap.Toast(document.getElementById('successToast'));
                        document.getElementById('successToast').querySelector('.toast-body').textContent = 'Note deleted successfully!';
                        toast.show();
                        
                        // Remove note from UI
                        document.querySelector(`[data-note-id="${noteId}"]`).remove();
                    } else {
                        throw new Error(result.message || 'Failed to delete note');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                    document.getElementById('errorToast').querySelector('.toast-body').textContent = 
                        'Error: ' + (error.message || 'Failed to delete note');
                    toast.show();
                }
            }
        });
    });

    // Reset form when modal is hidden
    document.getElementById('noteModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('noteForm').reset();
        document.getElementById('noteId').value = '';
        document.getElementById('noteModalLabel').textContent = 'Add New Note';
    });

    // Update time every second
    function updateTime() {
            const now = new Date();
            const options = { 
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true 
            };
            const timeElement = document.querySelector('.live-time');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', options);
            }
        }
        
        // Update time immediately and then every second
        updateTime();
        setInterval(updateTime, 1000);

        // Function to open status update modal
        function openStatusModal(activityId, activityTitle, currentStatus) {
            document.getElementById('activityId').value = activityId;
            document.getElementById('activityTitle').textContent = activityTitle;
            
            // Set the current status in the dropdown
            const statusSelect = document.getElementById('statusSelect');
            statusSelect.value = currentStatus;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
            modal.show();
        }
        
        // Handle save status update
        document.getElementById('saveStatusUpdate').addEventListener('click', function() {
            const form = document.getElementById('statusUpdateForm');
            const formData = new FormData(form);
            const saveButton = this;
            const originalText = saveButton.innerHTML;
            
            // Show loading state
            saveButton.disabled = true;
            saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            
            // Send AJAX request
            fetch('update_activity_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success toast
                    const toastEl = document.getElementById('statusToast');
                    const toast = new bootstrap.Toast(toastEl);
                    toast.show();
                    
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('statusUpdateModal'));
                    modal.hide();
                    
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Error updating status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status. Please try again.');
            })
            .finally(() => {
                // Reset button state
                saveButton.disabled = false;
                saveButton.innerHTML = originalText;
            });
        });
        
        // Add hover effect for  menu items
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarLinks = document.querySelectorAll('.sidebar > a');
            
            sidebarLinks.forEach(link => {
                // Add initial state
                link.style.transition = 'all 0.3s ease';
                link.style.borderRadius = '8px';
                link.style.margin = '4px 10px';
                link.style.padding = '10px 15px';
                
                // Add hover effect
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(8px)';
                    this.style.background = 'rgba(100, 255, 218, 0.1)';
                    this.style.boxShadow = '2px 2px 12px rgba(100, 255, 218, 0.15)';
                });
                
                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                    this.style.background = '';
                    this.style.boxShadow = 'none';
                });
                
                // Add active state for current page
                if (this.href === window.location.href) {
                    this.style.background = 'rgba(100, 255, 218, 0.15)';
                    this.style.borderLeft = '3px solid var(--accent-color)';
                }
            });
            
            // Add subtle animation to user avatar
            const avatar = document.querySelector('.user-avatar');
            if (avatar) {
                avatar.style.transition = 'all 0.3s ease';
                
                avatar.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05) rotate(5deg)';
                    this.style.boxShadow = '0 5px 15px rgba(100, 255, 218, 0.3)';
                });
                
                avatar.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0)';
                    this.style.boxShadow = 'none';
                });
            }
        });
    </script>
    
    <!-- Edit Activity Modal -->
    <div class="modal fade" id="editActivityModal" tabindex="-1" aria-labelledby="editActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editActivityModalLabel">Edit Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dashboard Edit Buttons JS -->
    <script src="assets/js/dashboard-edit-buttons.js"></script>
</body>
</html> 