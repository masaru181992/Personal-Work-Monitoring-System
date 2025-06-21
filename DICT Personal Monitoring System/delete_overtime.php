<?php
session_start();
require_once 'config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get request ID from POST data
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);

// Validate request ID
if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // First, check if the request exists and belongs to the user
    $stmt = $pdo->prepare("SELECT id FROM overtime_requests WHERE id = ? AND user_id = ?");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Overtime request not found or access denied']);
        exit;
    }
    
    // Delete the request
    $stmt = $pdo->prepare("DELETE FROM overtime_requests WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$request_id, $_SESSION['user_id']]);
    
    if (!$result) {
        $pdo->rollBack();
        throw new Exception('Failed to delete overtime request');
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    error_log('Error deleting overtime request: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}
