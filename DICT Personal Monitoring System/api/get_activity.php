<?php
require_once '../config/database.php';

// Check if activity ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Activity ID is required']);
    exit;
}

$activityId = $_GET['id'];

try {
    // Get the activity details
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            p.title as project_title 
        FROM activities a
        LEFT JOIN projects p ON a.project_id = p.id
        WHERE a.id = ?
    ");
    $stmt->execute([$activityId]);
    $activity = $stmt->fetch();

    if (!$activity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Activity not found']);
        exit;
    }

    // Get all available projects for dropdown
    $stmt = $pdo->prepare("SELECT id, title FROM projects ORDER BY title");
    $stmt->execute();
    $projects = $stmt->fetchAll();

    // Format the response
    $response = [
        'success' => true,
        'data' => $activity,
        'projects' => $projects
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
