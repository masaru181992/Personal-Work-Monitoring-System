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
if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Note ID is required']);
    exit;
}

$required = ['title', 'content', 'priority', 'status'];
$missing = [];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: ' . implode(', ', $missing)
    ]);
    exit;
}

try {
    // First check if note exists and belongs to user
    $checkQuery = "SELECT * FROM notes WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$data['id'], $_SESSION['user_id']]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Note not found or unauthorized']);
        exit;
    }

    // Prepare the update query
    $query = "UPDATE notes SET 
                title = ?, 
                content = ?, 
                priority = ?, 
                status = ?,
                project_id = ?,
                reminder_date = ?,
                updated_at = NOW()
              WHERE id = ? AND user_id = ?";
    
    $stmt = $pdo->prepare($query);
    
    // Execute the update
    $result = $stmt->execute([
        $data['title'],
        $data['content'],
        $data['priority'],
        $data['status'],
        $data['project_id'] ?? null,
        $data['reminder_date'] ?? null,
        $data['id'],
        $_SESSION['user_id']
    ]);

    if ($result) {
        // Fetch the complete updated note data
        $query = "SELECT n.*, p.title as project_title 
                 FROM notes n 
                 LEFT JOIN projects p ON n.project_id = p.id 
                 WHERE n.id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$data['id']]);
        $updatedNote = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($updatedNote) {
            echo json_encode([
                'success' => true,
                'message' => 'Note updated successfully',
                'data' => $updatedNote
            ]);
        } else {
            throw new Exception('Failed to fetch updated note');
        }
    } else {
        throw new Exception('Failed to update note');
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