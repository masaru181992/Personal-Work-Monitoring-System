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

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Validate input
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $activity_id = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
    $reason = trim($_POST['reason'] ?? '');

    if (!$request_id || !$activity_id || empty($reason)) {
        throw new Exception('All fields are required');
    }

    // Check if the request exists and belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM overtime_requests WHERE id = ? AND user_id = ?");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    $existing_request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing_request) {
        throw new Exception('Overtime request not found');
    }

    // Update the request
    $stmt = $pdo->prepare("
        UPDATE overtime_requests 
        SET activity_id = ?, 
            reason = ?,
            updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ");

    $success = $stmt->execute([
        $activity_id,
        $reason,
        $request_id,
        $_SESSION['user_id']
    ]);

    if (!$success) {
        throw new Exception('Failed to update overtime request');
    }

    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
