<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if target ID is provided
if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Target ID is required']);
    exit();
}

$target_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Get the target with category name and user details
    $stmt = $pdo->prepare("
        SELECT 
            t.*, 
            c.name as category_name,
            u.first_name,
            u.last_name,
            u.employee_id,
            u.position,
            u.department
        FROM ipcr_targets t
        JOIN ipcr_categories c ON t.category_id = c.id
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND (t.user_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
    ");
    
    $stmt->execute([$target_id, $user_id, $user_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['success' => false, 'message' => 'Target not found or access denied']);
        exit();
    }
    
    // Get status history
    $historyStmt = $pdo->prepare("
        SELECT 
            l.*, 
            CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
        FROM ipcr_status_logs l
        JOIN users u ON l.changed_by = u.id
        WHERE l.target_id = ?
        ORDER BY l.changed_at DESC
    ");
    $historyStmt->execute([$target_id]);
    $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate progress
    $progress = 0;
    if ($target['target_quantity'] > 0) {
        $progress = min(100, round(($target['quantity_accomplished'] / $target['target_quantity']) * 100));
    }
    
    // Format dates
    $created_date = new DateTime($target['created_at']);
    $target_date = new DateTime($target['target_date']);
    $now = new DateTime();
    $days_remaining = $now->diff($target_date)->format('%r%a');
    $is_overdue = $days_remaining < 0;
    $days_remaining = abs($days_remaining);
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'id' => $target['id'],
            'title' => $target['title'],
            'description' => $target['description'],
            'category' => $target['category_name'],
            'target_quantity' => $target['target_quantity'],
            'quantity_accomplished' => $target['quantity_accomplished'],
            'unit' => $target['unit'],
            'target_date' => $target_date->format('F j, Y'),
            'status' => $target['status'],
            'priority' => $target['priority'],
            'progress' => $progress,
            'created_at' => $created_date->format('F j, Y'),
            'updated_at' => !empty($target['updated_at']) ? (new DateTime($target['updated_at']))->format('F j, Y') : null,
            'days_remaining' => $is_overdue ? -$days_remaining : $days_remaining,
            'is_overdue' => $is_overdue,
            'assigned_to' => [
                'name' => $target['first_name'] . ' ' . $target['last_name'],
                'employee_id' => $target['employee_id'],
                'position' => $target['position'],
                'department' => $target['department']
            ],
            'status_history' => array_map(function($item) {
                $date = new DateTime($item['changed_at']);
                return [
                    'status' => $item['status'],
                    'changed_by' => $item['changed_by_name'],
                    'changed_at' => $date->format('M j, Y g:i A')
                ];
            }, $statusHistory)
        ]
    ];
    
    // Return the response
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Log the error
    error_log("Error fetching IPCR target details: " . $e->getMessage());
    
    // Return error response
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching target details',
        'error' => $e->getMessage()
    ]);
}
?>
