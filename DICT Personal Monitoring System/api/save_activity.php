<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    echo json_encode($response);
    exit();
}

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    sendJsonResponse(false, 'Direct access not allowed');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    sendJsonResponse(false, 'Unauthorized access');
}

// Handle both regular form data and JSON input
$input = file_get_contents('php://input');
if (!empty($input) && empty($_POST)) {
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';
    if (strpos($contentType, 'application/json') !== false) {
        $_POST = json_decode(trim($input), true);
    } else {
        parse_str($input, $_POST);
    }
}

try {
    try {
        // Get form data with validation
        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = !empty($_POST['status']) ? trim($_POST['status']) : 'Pending';
        $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
        $user_id = $_SESSION['user_id'];
        
        // Log received data for debugging
        error_log('Received form data: ' . print_r($_POST, true));
        
        // Validate required fields
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        
        if (empty($start_date)) {
            throw new Exception('Start date is required');
        }
        
        // Validate dates
        $start_timestamp = strtotime($start_date);
        if ($start_timestamp === false) {
            throw new Exception('Invalid start date format');
        }
        
        if ($end_date) {
            $end_timestamp = strtotime($end_date);
            if ($end_timestamp === false) {
                throw new Exception('Invalid end date format');
            }
            if ($end_timestamp < $start_timestamp) {
                throw new Exception('End date cannot be before start date');
            }
        }



        // Prepare the SQL query
        if ($activity_id > 0) {
            // Update existing activity
            $sql = "UPDATE activities SET 
                    title = :title,
                    description = :description,
                    start_date = :start_date,
                    end_date = :end_date,
                    status = :status," . 
                    ($project_id ? "project_id = :project_id," : "project_id = NULL,") . "
                    updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $activity_id, PDO::PARAM_INT);
            $message = 'Activity updated successfully';
        } else {
            // Insert new activity
            $sql = "INSERT INTO activities 
                    (title, description, start_date, end_date, status" . 
                    ($project_id ? ", project_id" : "") . ", created_at, updated_at)
                    VALUES (:title, :description, :start_date, :end_date, :status" . 
                    ($project_id ? ", :project_id" : "") . 
                    ", NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            $message = 'Activity added successfully';
        }

        // Begin transaction
        $pdo->beginTransaction();

        try {
            // Bind parameters
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status);
            
            if ($project_id) {
                $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
            }

            // Execute the query
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute database query');
            }

            // If this was an insert, get the new activity ID
            if ($activity_id <= 0) {
                $activity_id = $pdo->lastInsertId();
            }
            
            // Commit the transaction
            $pdo->commit();
            
            // Return success response
            sendJsonResponse(true, $message, ['activity_id' => $activity_id]);
            
        } catch (Exception $e) {
            // Rollback the transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e; // Re-throw the exception to be caught by the outer try-catch
        }
        
    } catch (Exception $e) {
        // Log the error
        error_log('Error in save_activity.php: ' . $e->getMessage());
        
        // Send error response
        http_response_code(400);
        sendJsonResponse(false, $e->getMessage());
    }
    
} catch (Exception $e) {
    // This is a fallback for any uncaught exceptions
    error_log('Uncaught exception in save_activity.php: ' . $e->getMessage());
    
    http_response_code(500);
    sendJsonResponse(false, 'An unexpected error occurred: ' . $e->getMessage());
}
