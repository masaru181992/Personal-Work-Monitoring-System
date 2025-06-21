<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if ID is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    $_SESSION['error_message'] = "Invalid request. No accomplishment ID provided.";
    header("Location: ipcr_target_status.php");
    exit();
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

try {
    // Verify that the accomplishment belongs to the current user
    $stmt = $pdo->prepare("SELECT id FROM ipcr_accomplishments WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error_message'] = "You don't have permission to delete this accomplishment.";
        header("Location: ipcr_target_status.php");
        exit();
    }
    
    // Delete the accomplishment
    $deleteStmt = $pdo->prepare("DELETE FROM ipcr_accomplishments WHERE id = ?");
    $deleteStmt->execute([$id]);
    
    $_SESSION['success_message'] = "IPCR Accomplishment deleted successfully!";
} catch (PDOException $e) {
    error_log("Error in delete_ipcr_accomplishment.php: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while deleting the IPCR accomplishment.";
}

// Redirect back to the IPCR target status page
header("Location: ipcr_target_status.php");
exit();
?>
