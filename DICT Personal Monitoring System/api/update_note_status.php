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
$data = $_POST;

// Validate required fields
if (empty($data['note_id']) || empty($data['status']) || !isset($data['priority'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Note ID, status, and priority are required'
    ]);
    exit;
}

// Validate status value
$valid_statuses = ['pending', 'in_progress', 'completed'];
if (!in_array($data['status'], $valid_statuses)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid status value'
    ]);
    exit;
}

// Validate priority value
$valid_priorities = ['low', 'medium', 'high'];
if (!in_array($data['priority'], $valid_priorities)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid priority value'
    ]);
    exit;
}

try {
    // First check if note exists and belongs to user
    $checkQuery = "SELECT * FROM notes WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$data['note_id'], $_SESSION['user_id']]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Note not found or unauthorized']);
        exit;
    }

    // Prepare the update query
    $query = "UPDATE notes SET status = ?, priority = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    
    // Execute the update
    $result = $stmt->execute([
        $data['status'],
        $data['priority'],
        $data['note_id'],
        $_SESSION['user_id']
    ]);

    if ($result) {
        // Fetch the complete updated note data
        $query = "SELECT n.*, p.title as project_title 
                 FROM notes n 
                 LEFT JOIN projects p ON n.project_id = p.id 
                 WHERE n.id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$data['note_id']]);
        $updatedNote = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($updatedNote) {
            echo json_encode([
                'success' => true,
                'message' => 'Note status updated successfully',
                'data' => $updatedNote
            ]);
        } else {
            throw new Exception('Failed to fetch updated note');
        }
    } else {
        throw new Exception('Failed to update note status');
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