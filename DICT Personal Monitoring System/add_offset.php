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
    $activity_id = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
    $offset_date = $_POST['offset_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (!$activity_id || !$offset_date || empty($reason)) {
        throw new Exception('All fields are required');
    }

    // Validate date
    $date = new DateTime($offset_date);
    $now = new DateTime();
    
    if ($date < $now) {
        throw new Exception('Offset date must be in the future');
    }

    // Check if the activity exists
    $stmt = $pdo->prepare("SELECT id FROM activities WHERE id = ?");
    $stmt->execute([$activity_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid activity selected');
    }

    // Create the offset request
    $stmt = $pdo->prepare("
        INSERT INTO offset_requests 
        (user_id, activity_id, offset_date, reason, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
    ");

    $success = $stmt->execute([
        $_SESSION['user_id'],
        $activity_id,
        $offset_date,
        $reason
    ]);

    if (!$success) {
        throw new Exception('Failed to create offset request');
    }

    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
