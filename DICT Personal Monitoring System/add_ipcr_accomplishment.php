<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $user_id = $_SESSION['user_id'];
        $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
        $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_STRING);
        $key_results = filter_input(INPUT_POST, 'key_results', FILTER_SANITIZE_STRING);
        $success_indicators = filter_input(INPUT_POST, 'success_indicators', FILTER_SANITIZE_STRING);
        $actual_accomplishments = filter_input(INPUT_POST, 'actual_accomplishments', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (!$year || !in_array($semester, ['First', 'Second']) || empty($key_results) || empty($success_indicators) || empty($actual_accomplishments)) {
            $_SESSION['error_message'] = "All fields are required and must be valid.";
            header("Location: ipcr_target_status.php");
            exit();
        }

        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO ipcr_accomplishments (
                user_id, 
                year, 
                semester, 
                key_results, 
                success_indicators, 
                actual_accomplishments
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $year,
            $semester,
            $key_results,
            $success_indicators,
            $actual_accomplishments
        ]);

        $_SESSION['success_message'] = "IPCR Accomplishment added successfully!";
    } catch (PDOException $e) {
        error_log("Error in add_ipcr_accomplishment.php: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while adding the IPCR accomplishment.";
    }

    // Redirect back to the IPCR target status page
    header("Location: ipcr_target_status.php");
    exit();
}
?>
