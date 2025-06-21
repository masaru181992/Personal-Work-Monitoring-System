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

// Get note ID from query string
$noteId = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    // Get all active projects for dropdown
    $projects = $pdo->query("SELECT id, title FROM projects WHERE status != 'completed' ORDER BY title")->fetchAll();
    
    if ($noteId > 0) {
        // Get specific note
        $query = "SELECT n.*, p.title as project_title 
                  FROM notes n 
                  LEFT JOIN projects p ON n.project_id = p.id 
                  WHERE n.id = :id AND n.user_id = :user_id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':id' => $noteId,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        $note = $stmt->fetch();
        
        if (!$note) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note not found']);
            exit;
        }
        
        // Format dates for display
        $note['created_at'] = date('Y-m-d H:i:s', strtotime($note['created_at']));
        if ($note['reminder_date']) {
            $note['reminder_date'] = date('Y-m-d', strtotime($note['reminder_date']));
        }
        
        echo json_encode([
            'success' => true,
            'data' => $note,
            'projects' => $projects
        ]);
    } else {
        // Return empty note for new note creation
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => null,
                'title' => '',
                'content' => '',
                'priority' => 'medium',
                'status' => 'active',
                'project_id' => null,
                'reminder_date' => null
            ],
            'projects' => $projects
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
