<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Set the current page for sidebar highlighting
$current_page = 'about.php';

// Get counts for sidebar
$dashboard_count = 0;
$projects_count = $pdo->query("SELECT COUNT(*) as count FROM projects")->fetch(PDO::FETCH_ASSOC)['count'];
$activities_count = $pdo->query("SELECT 
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    COUNT(*) as total
    FROM activities")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System - About</title>
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
            --accent-tertiary: #0083b0;
            --accent-notes1: #ffb347;
            --accent-notes2: #ff5e62;
            --text-dark: #1a1a1a;
            --text-secondary-dark: #4a5568;
            --border-color: rgba(100, 255, 218, 0.3);
            --card-bg: #0f2748;
            --hover-bg: rgba(100, 255, 218, 0.05);
            --text-white: #ffffff;
            --note-edit-color: #64ffda;
            --note-delete-color: #ff5e62;
        }

        body {
            background-color: #0a192f;
            color: #e6f1ff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar Styles */
        .sidebar {
            background: var(--primary-bg);
            border-right: 1px solid var(--border-color);
            height: 100vh;
            width: 350px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-item {
            color: #a0aec0;
            text-decoration: none;
            margin: 0.25rem 0;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
        }

        .nav-item:hover {
            background: rgba(100, 255, 218, 0.1);
            color: var(--accent-color);
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(100, 255, 218, 0.2);
            color: var(--accent-color);
            font-weight: 500;
            border-left: 3px solid var(--accent-color);
            padding-left: calc(1.5rem - 3px);
        }

        .nav-item i {
            width: 24px;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .nav-section-title {
            color: var(--accent-color);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            padding: 0 1.5rem;
        }

        .main-content {
            padding: 2rem;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a192f 0%, #112240 100%);
            transition: all 0.3s ease;
            margin-left: 350px;
            width: calc(100% - 350px);
        }

        @media (max-width: 1200px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .toggle-sidebar {
                display: block !important;
            }
        }
        
        .toggle-sidebar {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--accent-color);
            color: var(--primary-bg);
            border: none;
            border-radius: 4px;
            padding: 0.5rem;
            cursor: pointer;
            z-index: 1100;
        }

        .welcome-text {
            color: var(--accent-color);
            font-weight: 600;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            background: rgba(16, 32, 56, 0.7);
            border-bottom: 1px solid var(--border-color);
            color: var(--accent-color);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .feature-list {
            list-style: none;
            padding-left: 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .feature-list li:before {
            content: '→';
            position: absolute;
            left: 0;
            color: var(--accent-color);
            font-weight: bold;
        }

        .system-info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(100, 255, 218, 0.1);
        }

        .system-info-item:last-child {
            border-bottom: none;
        }

        .system-info-item .label {
            color: var(--accent-color);
            font-weight: 500;
        }

        .system-info-item .value {
            color: #e6f1ff;
        }


        .contact-info p {
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }


        .contact-info i {
            color: var(--accent-color);
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        .social-links {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .social-links .btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: var(--accent-color);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }


        .social-links .btn:hover {
            background: var(--accent-color);
            color: #0a192f;
            transform: translateY(-3px);
        }


        .highlight-box {
            background: linear-gradient(135deg, rgba(100, 255, 218, 0.08) 0%, rgba(100, 255, 218, 0.02) 100%);
            border-left: 3px solid var(--accent-color);
            padding: 1.5rem;
            border-radius: 0 8px 8px 0;
            margin: 1.5rem 0;
        }

        .highlight-box h5 {
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <button class="toggle-sidebar" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <div class="container-fluid">
        <div class="row h-100" style="margin-left: 0; margin-right: 0;">
            <!-- Include Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="main-content animate__animated animate__fadeIn" style="margin-left: 350px; width: calc(100% - 350px);">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="welcome-text">About The Personal Monitoring System</h1>
                </div>

                <div class="row g-4">
                    <!-- Main Content Column -->
                    <div class="col-lg-8">
                        <!-- System Information -->
                        <div class="card">
                            <div class="card-header d-flex align-items-center">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <h5 class="mb-0">System Overview</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-4">The Personal Monitoring System is a personal management tool designed to track and monitor projects and activities. This system provides a centralized platform for managing work items, tracking progress, and ensuring timely completion of tasks.</p>
                                
                                <div class="highlight-box">
                                    <h5>Key Features</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <ul class="feature-list">
                                                <li>Comprehensive project and activity monitoring</li>
                                                <li>Real-time status updates and tracking</li>
                                                <li>Detailed progress visualization</li>
                                                <li>Priority-based task management</li>
                                                <li>Secure user authentication</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <ul class="feature-list">
                                                <li>Responsive design for all devices</li>
                                                <li>Notes and reminders system</li>
                                                <li>Performance analytics</li>
                                                <li>Project-activity relationships</li>
                                                <li>User-friendly dashboard</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- System Details -->
                        <div class="card mb-4">
                            <div class="card-header d-flex align-items-center">
                                <i class="bi bi-gear-fill me-2"></i>
                                <h5 class="mb-0">System Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="system-info-item">
                                    <span class="label">Version:</span>
                                    <span class="value">2.1.0</span>
                                </div>
                                <div class="system-info-item">
                                    <span class="label">Release Date:</span>
                                    <span class="value">June 10, 2024</span>
                                </div>
                                <div class="system-info-item">
                                    <span class="label">Last Updated:</span>
                                    <span class="value">June 20, 2025</span>
                                </div>
                                <div class="system-info-item">
                                    <span class="label">Developer:</span>
                                    <span class="value">Kent D. Alico</span>
                                </div>
                                <div class="system-info-item">
                                    <span class="label">License:</span>
                                    <span class="value">Proprietary</span>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="card mb-4">
                            <div class="card-header d-flex align-items-center">
                                <i class="bi bi-envelope-fill me-2"></i>
                                <h5 class="mb-0">Contact Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="contact-info">
                                    <p>
                                        <i class="bi bi-geo-alt-fill"></i>
                                        <span>Mabini, Tubajon, Province of Dinagat Islands</span>
                                    </p>
                                    <p>
                                        <i class="bi bi-telephone-fill"></i>
                                        <span>+649121619044</span>
                                    </p>
                                    <p class="mb-0">
                                        <i class="bi bi-envelope"></i>
                                        <span>salamander00000@gmail.com</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Content Section (if needed in the future) -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');

            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    document.body.classList.toggle('sidebar-active');
                });

                // Close sidebar when clicking outside on mobile
                mainContent.addEventListener('click', function() {
                    if (window.innerWidth <= 1200 && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                        document.body.classList.remove('sidebar-active');
                    }
                });

                // Close sidebar when a nav item is clicked on mobile
                const navItems = document.querySelectorAll('.nav-item');
                navItems.forEach(item => {
                    item.addEventListener('click', function() {
                        if (window.innerWidth <= 1200) {
                            sidebar.classList.remove('active');
                            document.body.classList.remove('sidebar-active');
                        }
                    });
                });

                // Handle window resize
                let resizeTimer;
                window.addEventListener('resize', function() {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function() {
                        if (window.innerWidth > 1200) {
                            sidebar.classList.remove('active');
                            document.body.classList.remove('sidebar-active');
                        }
                    }, 250);
                });
            }


            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Update time every second
        function updateLiveDateTime() {
            const now = new Date();
            
            // Update date (only if it's a new day)
            const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
            const dateElement = document.getElementById('current-date');
            if (dateElement) {
                dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
            }
            
            // Update time with seconds
            const timeOptions = { 
                hour: 'numeric', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
            }
        }


        // Update time immediately and then every second
        updateLiveDateTime();
        setInterval(updateLiveDateTime, 1000);
    </script>
    <script>

</body>
</html> 