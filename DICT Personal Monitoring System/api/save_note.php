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
$required = ['title', 'content', 'priority', 'status'];
$missing = [];
foreach ($required as $field) {
    if (empty($data[$field])) {
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
    // Check if this is an update (note_id is provided)
    $noteId = isset($data['note_id']) ? intval($data['note_id']) : 0;
    
    if ($noteId > 0) {
        // Update existing note
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
        $result = $stmt->execute([
            $data['title'],
            $data['content'],
            $data['priority'],
            $data['status'],
            $data['project_id'] ?? null,
            $data['reminder_date'] ?? null,
            $noteId,
            $_SESSION['user_id']
        ]);
        
        if ($result) {
            // Fetch the updated note data
            $query = "SELECT n.*, u.full_name 
                     FROM notes n 
                     LEFT JOIN users u ON n.user_id = u.id 
                     WHERE n.id = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$noteId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Note updated successfully',
                'data' => $note
            ]);
        } else {
            throw new Exception('Failed to update note');
        }
    } else {
        // Create new note
        $query = "INSERT INTO notes (
                    title, 
                    content, 
                    priority, 
                    status, 
                    user_id, 
                    project_id, 
                    reminder_date,
                    created_at,
                    updated_at
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $data['title'],
            $data['content'],
            $data['priority'],
            $data['status'],
            $_SESSION['user_id'],
            $data['project_id'] ?? null,
            $data['reminder_date'] ?? null
        ]);
        
        if ($result) {
            $noteId = $pdo->lastInsertId();
            
            // Fetch the newly created note data
            $query = "SELECT n.*, u.full_name 
                     FROM notes n 
                     LEFT JOIN users u ON n.user_id = u.id 
                     WHERE n.id = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$noteId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Note created successfully',
                'data' => $note
            ]);
        } else {
            throw new Exception('Failed to create note');
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>