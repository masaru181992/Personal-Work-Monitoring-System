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

// Check if ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Target ID is required']);
    exit();
}

$target_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Get the target with category name
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name 
        FROM ipcr_targets t
        JOIN ipcr_categories c ON t.category_id = c.id
        WHERE t.id = ? AND t.user_id = ?
    ");
    $stmt->execute([$target_id, $user_id]);
    
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Target not found or access denied']);
        exit();
    }
    
    // Get all categories for the dropdown
    $categories = $pdo->query("SELECT * FROM ipcr_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the target data with categories
    echo json_encode([
        'success' => true,
        'data' => $target,
        'categories' => $categories
    ]);
    
} catch (PDOException $e) {
    // Log the error
    error_log("Error fetching IPCR target: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching the target',
        'error' => $e->getMessage()
    ]);
}
?>
