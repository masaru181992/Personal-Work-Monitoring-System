<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_edit = isset($_GET['edit']) && (int)$_GET['edit'] > 0;
$target_id = $is_edit ? (int)$_GET['edit'] : 0;
$target = null;
$categories = [];

try {
    // Fetch all categories for the dropdown
    $categoryStmt = $pdo->query("SELECT * FROM ipcr_categories ORDER BY name");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If in edit mode, fetch the target details
    if ($is_edit && $target_id > 0) {
        $targetStmt = $pdo->prepare("
            SELECT t.*, c.name as category_name 
            FROM ipcr_targets t
            JOIN ipcr_categories c ON t.category_id = c.id
            WHERE t.id = ? AND t.user_id = ?
        ");
        $targetStmt->execute([$target_id, $user_id]);
        $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$target) {
            $_SESSION['error_message'] = 'Target not found or access denied';
            header('Location: ipcr_target_status.php');
            exit();
        }
    }
} catch (PDOException $e) {
    error_log("Error in ipcr_target_form.php: " . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while loading the form';
    header('Location: ipcr_target_status.php');
    exit();
}
?>

<!-- Add/Edit Target Modal -->
<div class="modal fade" id="ipcrTargetModal" tabindex="-1" aria-labelledby="ipcrTargetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="ipcrTargetModalLabel">
                    <?= $is_edit ? 'Edit IPCR Target' : 'Add New IPCR Target' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="ipcrTargetForm" action="<?= $is_edit ? 'update_ipcr_target.php' : 'add_ipcr_target.php' ?>" method="POST">
                <div class="modal-body">
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="target_id" value="<?= htmlspecialchars($target['id']) ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required
                               value="<?= htmlspecialchars($target['title'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?= 
                            htmlspecialchars($target['description'] ?? '') 
                        ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"
                                        <?= (isset($target['category_id']) && $target['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="target_date" class="form-label">Target Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="target_date" name="target_date" required
                                   value="<?= isset($target['target_date']) ? date('Y-m-d', strtotime($target['target_date'])) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="target_quantity" class="form-label">Target Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="target_quantity" name="target_quantity" 
                                   min="0" step="0.01" required
                                   value="<?= htmlspecialchars($target['target_quantity'] ?? '1') ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="quantity_accomplished" class="form-label">Accomplished</label>
                            <input type="number" class="form-control" id="quantity_accomplished" name="quantity_accomplished" 
                                   min="0" step="0.01" 
                                   value="<?= htmlspecialchars($target['quantity_accomplished'] ?? '0') ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="unit" class="form-label">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit" 
                                   value="<?= htmlspecialchars($target['unit'] ?? 'unit(s)') ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Not Started" <?= (isset($target['status']) && $target['status'] === 'Not Started') ? 'selected' : '' ?>>Not Started</option>
                                <option value="In Progress" <?= (isset($target['status']) && $target['status'] === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed" <?= (isset($target['status']) && $target['status'] === 'Completed') ? 'selected' : '' ?>>Completed</option>
                                <option value="On Hold" <?= (isset($target['status']) && $target['status'] === 'On Hold') ? 'selected' : '' ?>>On Hold</option>
                                <option value="Cancelled" <?= (isset($target['status']) && $target['status'] === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="Low" <?= (isset($target['priority']) && $target['priority'] === 'Low') ? 'selected' : '' ?>>Low</option>
                                <option value="Medium" <?= !isset($target['priority']) || (isset($target['priority']) && $target['priority'] === 'Medium') ? 'selected' : '' ?>>Medium</option>
                                <option value="High" <?= (isset($target['priority']) && $target['priority'] === 'High') ? 'selected' : '' ?>>High</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?= $is_edit ? 'Update Target' : 'Add Target' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle form submission via AJAX
$(document).ready(function() {
    $('#ipcrTargetForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const formData = form.serialize();
        const submitBtn = form.find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        
        // Disable submit button and show loading state
        submitBtn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...'
        );
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    const toast = new bootstrap.Toast(document.getElementById('toastSuccess'));
                    document.getElementById('toastSuccessMessage').textContent = response.message;
                    toast.show();
                    
                    // Close modal and reload the page after a short delay
                    const modal = bootstrap.Modal.getInstance(document.getElementById('ipcrTargetModal'));
                    modal.hide();
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Show error message
                    const toast = new bootstrap.Toast(document.getElementById('toastError'));
                    document.getElementById('toastErrorMessage').textContent = 
                        response.message || 'An error occurred. Please try again.';
                    toast.show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                const toast = new bootstrap.Toast(document.getElementById('toastError'));
                document.getElementById('toastErrorMessage').textContent = 
                    'An error occurred while processing your request. Please try again.';
                toast.show();
            },
            complete: function() {
                // Re-enable submit button and restore original text
                submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    });
    
    // Initialize date picker with min date set to today
    const today = new Date().toISOString().split('T')[0];
    $('#target_date').attr('min', today);
    
    // Calculate and update progress when quantities change
    $('#target_quantity, #quantity_accomplished').on('input', function() {
        const target = parseFloat($('#target_quantity').val()) || 0;
        const accomplished = parseFloat($('#quantity_accomplished').val()) || 0;
        
        if (target > 0) {
            const progress = Math.min(100, Math.round((accomplished / target) * 100));
            
            // Update status based on progress
            if (progress >= 100) {
                $('#status').val('Completed');
            } else if (progress > 0) {
                $('#status').val('In Progress');
            } else {
                $('#status').val('Not Started');
            }
        }
    });
});
</script>
