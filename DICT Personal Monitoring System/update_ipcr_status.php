<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['target_id']) || !isset($_POST['status'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$target_id = (int)$_POST['target_id'];
$new_status = $_POST['status'];
$user_id = $_SESSION['user_id'];

// Validate status
$valid_statuses = ['Not Started', 'In Progress', 'Completed', 'On Hold', 'Cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Check if the target belongs to the user
    $stmt = $pdo->prepare("SELECT id FROM ipcr_targets WHERE id = ? AND user_id = ?");
    $stmt->execute([$target_id, $user_id]);
    
    if ($stmt->rowCount() === 0) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['success' => false, 'message' => 'Target not found or access denied']);
        exit();
    }
    
    // Update the status
    $updateStmt = $pdo->prepare("UPDATE ipcr_targets SET status = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$new_status, $target_id]);
    
    // Log the status change
    $logStmt = $pdo->prepare("INSERT INTO ipcr_status_logs (target_id, status, changed_by, changed_at) VALUES (?, ?, ?, NOW())");
    $logStmt->execute([$target_id, $new_status, $user_id]);
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Status updated successfully',
        'new_status' => $new_status
    ]);
    
} catch (PDOException $e) {
    // Log the error
    error_log("Error updating IPCR target status: " . $e->getMessage());
    
    // Return error response
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while updating the status',
        'error' => $e->getMessage()
    ]);
}
?>
