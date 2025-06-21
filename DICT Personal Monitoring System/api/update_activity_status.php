<?php
header('Content-Type: application/json');
require_once '../config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST input
$activityId = $_POST['activity_id'] ?? '';
$status = $_POST['status'] ?? '';
$notes = $_POST['notes'] ?? '';

// Validate required fields
if (empty($activityId) || empty($status)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Activity ID and status are required'
    ]);
    exit;
}

// Validate status value
$validStatuses = ['Pending', 'In Progress', 'Completed', 'On Hold'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid status value'
    ]);
    exit;
}

try {
    // First check if activity exists and belongs to user (or user has permission)
    $checkQuery = "SELECT a.* FROM activities a 
                  JOIN projects p ON a.project_id = p.id 
                  WHERE a.id = ? AND (a.user_id = ? OR p.manager_id = ?)";
    
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$activityId, $_SESSION['user_id'], $_SESSION['user_id']]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'message' => 'Activity not found or unauthorized'
        ]);
        exit;
    }

    // Prepare the update query
    $query = "UPDATE activities SET status = ?, updated_at = NOW() WHERE id = ?";
    $params = [$status, $activityId];
    
    // If there are notes, add them to the activity log
    if (!empty($notes)) {
        // Insert into activity_logs table if it exists
        $logQuery = "INSERT INTO activity_logs (activity_id, user_id, action, notes, created_at) 
                    VALUES (?, ?, 'status_update', ?, NOW())";
        
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->execute([$activityId, $_SESSION['user_id'], $notes]);
    }
    
    // Execute the update
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute($params);

    if ($result) {
        // Fetch the updated activity data
        $query = "SELECT a.*, p.title as project_title 
                 FROM activities a 
                 LEFT JOIN projects p ON a.project_id = p.id 
                 WHERE a.id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$activityId]);
        $updatedActivity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($updatedActivity) {
            echo json_encode([
                'success' => true,
                'message' => 'Activity status updated successfully',
                'data' => $updatedActivity
            ]);
        } else {
            throw new Exception('Failed to fetch updated activity');
        }
    } else {
        throw new Exception('Failed to update activity status');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// Close the database connection
$pdo = null;
?>
