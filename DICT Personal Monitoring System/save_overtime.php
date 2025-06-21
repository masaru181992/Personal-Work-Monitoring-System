<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate input data
$activity_id = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
$days = filter_input(INPUT_POST, 'days', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$user_id = $_SESSION['user_id'];

// Validate required fields
if (!$activity_id) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Activity is required']);
    exit();
}

if (!$days) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Number of days must be at least 1']);
    exit();
}

try {
    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get activity details including start and end dates
    $stmt = $pdo->prepare("SELECT id, start_date, end_date FROM activities WHERE id = ?");
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$activity) {
        throw new Exception('Selected activity does not exist');
    }
    
    // Calculate maximum allowed days based on activity duration
    $start_date = new DateTime($activity['start_date']);
    $end_date = new DateTime($activity['end_date']);
    $interval = $start_date->diff($end_date);
    $max_days = $interval->days + 1; // +1 to include both start and end dates
    
    if ($days > $max_days) {
        throw new Exception("Number of days cannot exceed the activity duration ($max_days days)");
    }
    
    // Insert the overtime request with status 'approved' and activity dates
    $stmt = $pdo->prepare("
        INSERT INTO overtime_requests 
        (user_id, activity_id, total_days, status, start_date, end_date, created_at, updated_at) 
        VALUES (?, ?, ?, 'approved', ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $user_id, 
        $activity_id, 
        $days,
        $activity['start_date'],
        $activity['end_date']
    ]);
    
    // Get the ID of the newly created request
    $request_id = $pdo->lastInsertId();
    
    // Commit the transaction
    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Overtime request submitted successfully',
        'request_id' => $request_id
    ]);
    
} catch (PDOException $e) {
    // Rollback the transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in save_overtime.php: " . $e->getMessage());
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while saving the request. Please try again.'
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
