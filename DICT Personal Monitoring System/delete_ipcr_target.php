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
if (!isset($_POST['target_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Missing target ID']);
    exit();
}

$target_id = (int)$_POST['target_id'];
$user_id = $_SESSION['user_id'];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // First, check if the target exists and belongs to the user
    $stmt = $pdo->prepare("SELECT id FROM ipcr_targets WHERE id = ? AND user_id = ?");
    $stmt->execute([$target_id, $user_id]);
    
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['success' => false, 'message' => 'Target not found or access denied']);
        exit();
    }
    
    // Delete related records first (if any)
    // Example: Delete status logs
    $deleteLogsStmt = $pdo->prepare("DELETE FROM ipcr_status_logs WHERE target_id = ?");
    $deleteLogsStmt->execute([$target_id]);
    
    // Now delete the target
    $deleteStmt = $pdo->prepare("DELETE FROM ipcr_targets WHERE id = ?");
    $deleteStmt->execute([$target_id]);
    
    // Commit the transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Target deleted successfully'
    ]);
    
} catch (PDOException $e) {
    // Rollback the transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    error_log("Error deleting IPCR target: " . $e->getMessage());
    
    // Return error response
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while deleting the target',
        'error' => $e->getMessage()
    ]);
}
?>
