<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if entry_id is provided
if (!isset($_GET['entry_id']) || empty($_GET['entry_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Entry ID is required']);
    exit();
}

$entry_id = filter_input(INPUT_GET, 'entry_id', FILTER_VALIDATE_INT);

// Validate entry_id
if (!$entry_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid Entry ID']);
    exit();
}

try {
    // Check if the entry belongs to the current user
    $stmt = $pdo->prepare("SELECT id FROM ipcr_entries WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $entry_id, ':user_id' => $_SESSION['user_id']]);
    
    if ($stmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Entry not found or access denied']);
        exit();
    }
    
    // Get the related activities
    $stmt = $pdo->prepare("SELECT activity_id FROM ipcr_activities WHERE ipcr_entry_id = :entry_id");
    $stmt->execute([':entry_id' => $entry_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    header('Content-Type: application/json');
    echo json_encode(['activities' => $activities]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
