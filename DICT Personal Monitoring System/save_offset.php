<?php
// Start session
session_start();

// Include database connection
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Validate required fields
if (!isset($_POST['activity_id']) || !isset($_POST['offset_date']) || !isset($_POST['reason'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$activity_id = $_POST['activity_id'];
$offset_date = $_POST['offset_date'];
$reason = $_POST['reason'];

// Validate date format
if (!preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $offset_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Check if activity exists and has approved overtime
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get the overtime request with remaining days for this activity and user
    $overtime_query = $pdo->prepare("
        SELECT id, total_days, used_days, status 
        FROM overtime_requests 
        WHERE activity_id = ? 
        AND user_id = ? 
        AND status = 'approved'
        AND (used_days < total_days OR used_days IS NULL)
        ORDER BY id ASC
        LIMIT 1
    ");
    $overtime_query->execute([$activity_id, $user_id]);
    $overtime = $overtime_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$overtime) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No available overtime days for this activity']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert the offset request
        $stmt = $pdo->prepare("
            INSERT INTO offset_requests 
            (user_id, activity_id, offset_date, reason, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 'approved', NOW(), NOW())
        ");
        
        $stmt->execute([$user_id, $activity_id, $offset_date, $reason]);
        
        // Calculate new used_days value
        $new_used_days = ($overtime['used_days'] ?? 0) + 1;
        $new_status = $new_used_days >= $overtime['total_days'] ? 'used' : 'approved';
        
        // Update the overtime request with new used_days and status
        $update_overtime = $pdo->prepare("
            UPDATE overtime_requests 
            SET used_days = ?, 
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $update_overtime->execute([$new_used_days, $new_status, $overtime['id']]);
        
        // Commit the transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Offset request submitted successfully']);
        
    } catch (Exception $e) {
        // Rollback the transaction on error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Error saving offset request: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
