<?php
session_start();
require_once 'config/database.php';

// This file should be included in a page where $target_id is set
if (!isset($target_id) || !is_numeric($target_id)) {
    die('Invalid target ID');
}

$user_id = $_SESSION['user_id'] ?? 0;

try {
    // Get the target with category name and user details
    $stmt = $pdo->prepare("
        SELECT 
            t.*, 
            c.name as category_name,
            u.first_name,
            u.last_name,
            u.employee_id,
            u.position,
            u.department,
            u.avatar
        FROM ipcr_targets t
        JOIN ipcr_categories c ON t.category_id = c.id
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND (t.user_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
    ");
    
    $stmt->execute([$target_id, $user_id, $user_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target) {
        die('Target not found or access denied');
    }
    
    // Get status history
    $historyStmt = $pdo->prepare("
        SELECT 
            l.*, 
            CONCAT(u.first_name, ' ', u.last_name) as changed_by_name,
            u.avatar as changed_by_avatar
        FROM ipcr_status_logs l
        JOIN users u ON l.changed_by = u.id
        WHERE l.target_id = ?
        ORDER BY l.changed_at DESC
    ");
    $historyStmt->execute([$target_id]);
    $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate progress
    $progress = 0;
    if ($target['target_quantity'] > 0) {
        $progress = min(100, round(($target['quantity_accomplished'] / $target['target_quantity']) * 100));
    }
    
    // Format dates
    $created_date = new DateTime($target['created_at']);
    $target_date = new DateTime($target['target_date']);
    $now = new DateTime();
    $days_remaining = $now->diff($target_date)->format('%r%a');
    $is_overdue = $days_remaining < 0;
    $days_remaining = abs($days_remaining);
    
    // Get status color
    $status_colors = [
        'Not Started' => 'secondary',
        'In Progress' => 'primary',
        'Completed' => 'success',
        'On Hold' => 'warning',
        'Cancelled' => 'danger'
    ];
    
    $status_color = $status_colors[$target['status']] ?? 'secondary';
    
    // Get priority color
    $priority_colors = [
        'Low' => 'success',
        'Medium' => 'warning',
        'High' => 'danger'
    ];
    
    $priority_color = $priority_colors[$target['priority']] ?? 'secondary';
    
} catch (PDOException $e) {
    error_log("Error in ipcr_target_details_modal.php: " . $e->getMessage());
    die('An error occurred while loading target details');
}
?>

<!-- Target Details Modal -->
<div class="modal fade" id="targetDetailsModal" tabindex="-1" aria-labelledby="targetDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="targetDetailsModalLabel">IPCR Target Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h4 class="mb-1"><?= htmlspecialchars($target['title']) ?></h4>
                        <p class="text-muted mb-2">
                            <i class="fas fa-tag me-1"></i> 
                            <span class="badge bg-<?= $status_color ?>"><?= $target['status'] ?></span>
                            <span class="ms-2 badge bg-<?= $priority_color ?>"><?= $target['priority'] ?> Priority</span>
                        </p>
                        <?php if (!empty($target['description'])): ?>
                            <p class="mb-3"><?= nl2br(htmlspecialchars($target['description'])) ?></p>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Progress</span>
                                <span><?= $progress ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-<?= $status_color ?>" role="progressbar" 
                                     style="width: <?= $progress ?>%" 
                                     aria-valuenow="<?= $progress ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <div class="text-muted small mt-1">
                                <?= $target['quantity_accomplished'] ?> of <?= $target['target_quantity'] ?> <?= $target['unit'] ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-muted mb-3">Target Information</h6>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-calendar-day text-primary me-2"></i>
                                        <strong>Due:</strong> 
                                        <span class="float-end">
                                            <?= $target_date->format('M j, Y') ?>
                                            <?php if ($is_overdue): ?>
                                                <span class="badge bg-danger ms-1">Overdue</span>
                                            <?php else: ?>
                                                <span class="text-muted small">(<?= $days_remaining ?> days <?= $days_remaining === 1 ? '' : 's' ?> left)</span>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-folder-open text-primary me-2"></i>
                                        <strong>Category:</strong> 
                                        <span class="float-end"><?= htmlspecialchars($target['category_name']) ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-user-tie text-primary me-2"></i>
                                        <strong>Assigned To:</strong> 
                                        <div class="float-end text-end">
                                            <div><?= htmlspecialchars($target['first_name'] . ' ' . $target['last_name']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($target['position']) ?></div>
                                        </div>
                                    </li>
                                    <li class="mb-0">
                                        <i class="fas fa-building text-primary me-2"></i>
                                        <strong>Department:</strong> 
                                        <span class="float-end"><?= htmlspecialchars($target['department']) ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status History -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Status History</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (empty($statusHistory)): ?>
                                <div class="list-group-item text-center text-muted py-4">
                                    No status history available
                                </div>
                            <?php else: ?>
                                <?php foreach ($statusHistory as $history): 
                                    $history_date = new DateTime($history['changed_at']);
                                    $status_color = $status_colors[$history['status']] ?? 'secondary';
                                ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-sm">
                                                    <span class="avatar-title rounded-circle bg-soft-<?= $status_color ?> text-<?= $status_color ?>">
                                                        <?= substr($history['status'], 0, 1) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="mb-1">
                                                        <span class="badge bg-<?= $status_color ?>"><?= $history['status'] ?></span>
                                                    </h6>
                                                    <small class="text-muted"><?= $history_date->format('M j, Y g:i A') ?></small>
                                                </div>
                                                <p class="mb-0 small">
                                                    Updated by <?= htmlspecialchars($history['changed_by_name']) ?>
                                                </p>
                                                <?php if (!empty($history['notes'])): ?>
                                                    <div class="mt-2 p-2 bg-light rounded">
                                                        <small class="text-muted">
                                                            <i class="fas fa-comment-dots me-1"></i>
                                                            <?= nl2br(htmlspecialchars($history['notes'])) ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Log (optional) -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Recent Activity</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-history fa-2x mb-2"></i>
                            <p class="mb-0">Activity log will be displayed here</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer
            <?php if ($_SESSION['user_id'] == $target['user_id'] || ($_SESSION['role'] ?? '') === 'admin'): ?>
                <div class="me-auto">
                    <button type="button" class="btn btn-outline-primary btn-sm" 
                            data-bs-toggle="modal" data-bs-target="#editTargetModal"
                            data-id="<?= $target['id'] ?>">
                        <i class="fas fa-edit me-1"></i> Edit
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" 
                            onclick="deleteTarget(<?= $target['id'] ?>)">
                        <i class="fas fa-trash-alt me-1"></i> Delete
                    </button>
                </div>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
</div>

<script>
// Function to load target details via AJAX
function loadTargetDetails(targetId) {
    // Show loading state
    const modal = new bootstrap.Modal(document.getElementById('targetDetailsModal'));
    const modalBody = document.querySelector('#targetDetailsModal .modal-body');
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading target details...</p>
        </div>
    `;
    
    // Show the modal
    modal.show();
    
    // Fetch target details
    fetch(`view_ipcr_target.php?id=${targetId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update modal with the fetched data
                updateModalWithTargetData(data.data);
            } else {
                throw new Error(data.message || 'Failed to load target details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-circle text-danger fa-3x mb-3"></i>
                    <p class="text-danger">Failed to load target details. Please try again.</p>
                    <button class="btn btn-primary btn-sm" onclick="loadTargetDetails(${targetId})">
                        <i class="fas fa-sync-alt me-1"></i> Retry
                    </button>
                </div>
            `;
        });
}

// Function to update modal with target data
function updateModalWithTargetData(target) {
    // This function would update the modal with the target data
    // For now, we'll just log it to the console
    console.log('Target data:', target);
    
    // In a real implementation, you would update the modal HTML here
    // For example:
    // document.getElementById('targetTitle').textContent = target.title;
    // document.getElementById('targetStatus').textContent = target.status;
    // ... and so on
}

// Add event listener for when the modal is shown
document.getElementById('targetDetailsModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const targetId = button.getAttribute('data-id');
    
    if (targetId) {
        loadTargetDetails(targetId);
    }
});
</script>
