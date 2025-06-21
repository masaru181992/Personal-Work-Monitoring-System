<?php
// This file contains the IPCR sidebar HTML that can be included in other pages
require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Get counts for sidebar
$dashboard_count = 0; // Dashboard doesn't need a count

// Get total projects count
$projects_count = $pdo->query("SELECT COUNT(*) as count FROM projects")->fetch(PDO::FETCH_ASSOC)['count'];

// Get activities count by status
$activities_count = $pdo->query("SELECT 
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    COUNT(*) as total
    FROM activities")->fetch(PDO::FETCH_ASSOC);
?>
<!-- Smart Sidebar -->
<div class="col-md-2 sidebar animate__animated animate__slideInLeft position-fixed d-flex flex-column" style="background: var(--primary-bg); border-right: 1px solid rgba(100, 255, 218, 0.1); height: 100vh; width: 350px; z-index: 1000; left: 0; top: 0;">
    <!-- App Header -->
    <div class="sidebar-header text-center py-3 px-2 border-bottom border-secondary border-opacity-25">
        <div class="d-flex align-items-center justify-content-center mb-2">
            <i class="bi bi-speedometer2 me-2" style="font-size: 1.5rem; color: var(--accent-color);"></i>
            <h4 class="mb-0" style="font-weight: 700; letter-spacing: 1px; color: var(--accent-color);">DICT PMS</h4>
        </div>
    </div>

    <!-- User Info -->
    <div class="user-info-section text-center py-3 px-2 border-bottom border-secondary border-opacity-25">
        <h5 class="welcome-text text-white mb-1 fw-bold" style="font-size: 2rem;">
            <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>
            <i class="bi bi-patch-check-fill ms-1" style="color: #4cc9f0; font-size: 2rem;"></i>
        </h5>
        <p class="user-role mb-2" style="font-size: 0.7rem; color: var(--accent-color); background: rgba(100, 255, 218, 0.1); display: inline-block; padding: 2px 12px; border-radius: 20px; font-weight: 500;">
            <?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'User')); ?>
        </p>
        <div class="current-time d-flex flex-column align-items-center small mt-2" style="color: #a0aec0; line-height: 1.2;">
            <div class="date-display mb-1">
                <i class="bi bi-calendar3 me-1"></i>
                <span id="current-date"><?php echo date('F j, Y'); ?></span>
            </div>
            <div class="time-display">
                <i class="bi bi-clock me-1"></i>
                <span id="current-time"><?php echo date('g:i:s A'); ?></span>
            </div>
        </div>
    </div>

    <script>
    // Update time every second
    function updateLiveDateTime() {
        const now = new Date();
        
        // Update date (only if it's a new day)
        const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', dateOptions);
        
        // Update time with seconds
        const timeOptions = { 
            hour: 'numeric', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: true 
        };
        document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', timeOptions);
    }

    // Update time immediately and then every second
    updateLiveDateTime();
    setInterval(updateLiveDateTime, 1000);
    </script>

    <!-- Navigation Menu -->
    <div class="flex-grow-1 overflow-auto" style="scrollbar-width: thin;">
        <div class="nav-menu py-2">
            <a href="dashboard.php" class="nav-item d-flex align-items-center px-3 py-2 <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                <span>Dashboard</span>
                <span class="badge bg-accent text-dark ms-auto">3</span>
            </a>
            <a href="projects.php" class="nav-item d-flex align-items-center px-3 py-2 <?php echo $current_page === 'projects.php' ? 'active' : ''; ?>">
                <i class="bi bi-folder me-2"></i>
                <span>Projects</span>
                <span class="badge bg-danger ms-auto"><?php echo $projects_count; ?></span>
            </a>
            <a href="activities.php" class="nav-item d-flex align-items-center px-3 py-2 <?php echo $current_page === 'activities.php' ? 'active' : ''; ?>">
                <i class="bi bi-list-check me-2"></i>
                <span>Activities</span>
                <span class="badge bg-warning text-dark ms-auto" 
                      data-bs-toggle="tooltip" 
                      data-bs-placement="right" 
                      title="Pending: <?php echo $activities_count['pending'] ?? 0; ?>
In Progress: <?php echo $activities_count['in_progress'] ?? 0; ?>
Completed: <?php echo $activities_count['completed'] ?? 0; ?>">
                    <?php echo $activities_count['total'] ?? 0; ?>
                </span>
            </a>
            <a href="notes.php" class="nav-item d-flex align-items-center px-3 py-2 <?php echo $current_page === 'notes.php' ? 'active' : ''; ?>">
                <i class="bi bi-journal-text me-2"></i>
                <span>Notes</span>
            </a>
            <a href="point_of_contacts.php" class="nav-item d-flex align-items-center px-3 py-2 <?php echo $current_page === 'point_of_contacts.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-lines-fill me-2"></i>
                <span>Point of Contacts</span>
            </a>
            
            <!-- IPCR Section -->
            <div class="nav-section-title px-3 py-2 mt-2 mb-1 d-flex align-items-center">
                <span style="font-size: 0.75rem; color: var(--accent-color); font-weight: 600; letter-spacing: 1px;">PERFORMANCE</span>
                <div class="flex-grow-1 ms-2" style="height: 1px; background: linear-gradient(to right, rgba(100, 255, 218, 0.3), transparent);"></div>
            </div>
            
            <a href="ipcr_target_status.php" class="nav-item d-flex align-items-center px-3 py-2 <?php echo $current_page === 'ipcr_target_status.php' ? 'active' : ''; ?>">
                <i class="bi bi-bullseye me-2"></i>
                <span>IPCR Target Status</span>
            </a>
            <a href="ipcr_reports.php" class="nav-item d-flex align-items-center px-3 py-2 <?php echo $current_page === 'ipcr_reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                <span>IPCR Reports</span>
            </a>
        </div>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer p-3 border-top border-secondary border-opacity-25">
        <div class="d-flex justify-content-between align-items-center">
            <a href="profile.php" class="btn btn-sm btn-outline-accent" style="border-color: rgba(100, 255, 218, 0.3); color: var(--accent-color);">
                <i class="bi bi-person me-1"></i> Profile
            </a>
            <a href="logout.php" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</div>
