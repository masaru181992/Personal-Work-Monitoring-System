<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Validate input
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    
    if (!$request_id) {
        throw new Exception('Invalid request ID');
    }

    // Check if the request exists and belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM offset_requests WHERE id = ? AND user_id = ?");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception('Offset request not found or access denied');
    }

// Allow deletion of any status request - both pending and approved

    // Delete the request
    $stmt = $pdo->prepare("DELETE FROM offset_requests WHERE id = ? AND user_id = ?");
    $success = $stmt->execute([$request_id, $_SESSION['user_id']]);

    if (!$success) {
        throw new Exception('Failed to delete offset request');
    }

    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
