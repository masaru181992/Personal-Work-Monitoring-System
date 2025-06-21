<?php
session_start();
require_once 'config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if required fields are provided
$required_fields = [
    'title', 'category_id', 'target_quantity', 'target_date', 'status', 'priority'
];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "$field is required"]);
        exit();
    }
}

// Sanitize and validate input
$user_id = $_SESSION['user_id'];
$title = trim($_POST['title']);
$description = trim($_POST['description'] ?? '');
$category_id = (int)$_POST['category_id'];
$target_quantity = (float)$_POST['target_quantity'];
$quantity_accomplished = (float)($_POST['quantity_accomplished'] ?? 0);
$unit = trim($_POST['unit'] ?? 'unit(s)');
$target_date = $_POST['target_date'];
$status = trim($_POST['status']);
$priority = trim($_POST['priority']);

// Validate status and priority
$valid_statuses = ['Not Started', 'In Progress', 'Completed', 'On Hold', 'Cancelled'];
$valid_priorities = ['Low', 'Medium', 'High'];

if (!in_array($status, $valid_statuses) || !in_array($priority, $valid_priorities)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status or priority']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if the category exists
    $categoryStmt = $pdo->prepare("SELECT id FROM ipcr_categories WHERE id = ?");
    $categoryStmt->execute([$category_id]);
    
    if ($categoryStmt->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        exit();
    }
    
    // Insert the new target
    $insertStmt = $pdo->prepare("
        INSERT INTO ipcr_targets (
            user_id, title, description, category_id, 
            target_quantity, quantity_accomplished, unit, 
            target_date, status, priority, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $insertStmt->execute([
        $user_id,
        $title,
        $description,
        $category_id,
        $target_quantity,
        $quantity_accomplished,
        $unit,
        $target_date,
        $status,
        $priority
    ]);
    
    $target_id = $pdo->lastInsertId();
    
    // Log the initial status
    $logStmt = $pdo->prepare("
        INSERT INTO ipcr_status_logs (target_id, status, changed_by, changed_at)
        VALUES (?, ?, ?, NOW())
    ");
    $logStmt->execute([$target_id, $status, $user_id]);
    
    // Commit the transaction
    $pdo->commit();
    
    // Return success response with the new target ID
    echo json_encode([
        'success' => true,
        'message' => 'Target added successfully',
        'target_id' => $target_id
    ]);
    
} catch (PDOException $e) {
    // Rollback the transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    error_log("Error adding IPCR target: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while adding the target',
        'error' => $e->getMessage()
    ]);
}
?>
