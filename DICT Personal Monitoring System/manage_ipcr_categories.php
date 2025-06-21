<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all categories
        try {
            $stmt = $pdo->query("SELECT * FROM ipcr_categories ORDER BY name");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $categories
            ]);
            
        } catch (PDOException $e) {
            error_log("Error fetching IPCR categories: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'POST':
        // Create a new category
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Category name is required']);
            exit();
        }
        
        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');
        
        try {
            $stmt = $pdo->prepare("INSERT INTO ipcr_categories (name, description, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$name, $description]);
            
            $category_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Category created successfully',
                'category_id' => $category_id
            ]);
            
        } catch (PDOException $e) {
            error_log("Error creating IPCR category: " . $e->getMessage());
            
            // Check for duplicate entry
            if ($e->errorInfo[1] == 1062) { // MySQL duplicate entry error code
                http_response_code(409);
                echo json_encode([
                    'success' => false, 
                    'message' => 'A category with this name already exists'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to create category',
                    'error' => $e->getMessage()
                ]);
            }
        }
        break;
        
    case 'PUT':
        // Update an existing category
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id']) || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Category ID and name are required']);
            exit();
        }
        
        $id = (int)$data['id'];
        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE ipcr_categories SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Category not found']);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Category updated successfully'
            ]);
            
        } catch (PDOException $e) {
            error_log("Error updating IPCR category: " . $e->getMessage());
            
            // Check for duplicate entry
            if ($e->errorInfo[1] == 1062) { // MySQL duplicate entry error code
                http_response_code(409);
                echo json_encode([
                    'success' => false, 
                    'message' => 'A category with this name already exists'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to update category',
                    'error' => $e->getMessage()
                ]);
            }
        }
        break;
        
    case 'DELETE':
        // Delete a category
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Category ID is required']);
            exit();
        }
        
        $id = (int)$data['id'];
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // First, check if there are any targets using this category
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ipcr_targets WHERE category_id = ?");
            $checkStmt->execute([$id]);
            $usageCount = $checkStmt->fetchColumn();
            
            if ($usageCount > 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Cannot delete category because it is being used by ' . $usageCount . ' target(s)'
                ]);
                exit();
            }
            
            // If no targets are using this category, proceed with deletion
            $deleteStmt = $pdo->prepare("DELETE FROM ipcr_categories WHERE id = ?");
            $deleteStmt->execute([$id]);
            
            if ($deleteStmt->rowCount() === 0) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Category not found']);
                exit();
            }
            
            // Commit the transaction
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
            
        } catch (PDOException $e) {
            // Rollback the transaction on error
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log("Error deleting IPCR category: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>
