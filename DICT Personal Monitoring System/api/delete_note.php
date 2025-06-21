<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check if request is POST or DELETE
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get note ID from POST data or JSON body
$noteId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission
    $noteId = isset($_POST['id']) ? $_POST['id'] : null;
} else {
    // Handle DELETE request with JSON body
    $data = json_decode(file_get_contents('php://input'), true);
    $noteId = isset($data['id']) ? $data['id'] : null;
}

if (!$noteId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing note ID']);
    exit();
}

try {
    // First check if note exists and belongs to user
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$noteId, $_SESSION['user_id']]);
    $note = $stmt->fetch();

    if (!$note) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Note not found or unauthorized']);
        exit();
    }

    // Delete the note
    $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$noteId, $_SESSION['user_id']]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Note deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete note');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}