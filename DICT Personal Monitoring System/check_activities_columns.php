<?php
require_once 'config/database.php';

try {
    // Check the current structure of the activities table
    $stmt = $pdo->query("DESCRIBE activities");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current activities table structure:</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check if we can insert a test record
    echo "<h2>Test Insert:</h2>";
    try {
        $test_title = "Test Activity " . time();
        $stmt = $pdo->prepare("INSERT INTO activities (project_id, title, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            1, // Assuming there's a project with ID 1
            $test_title,
            "Test description",
            date('Y-m-d'),
            date('Y-m-d', strtotime('+1 day')),
            'Not Started'
        ]);
        
        if ($result) {
            echo "<div style='color: green;'>Test record inserted successfully!</div>";
            
            // Clean up
            $pdo->exec("DELETE FROM activities WHERE title = '" . addslashes($test_title) . "'");
        } else {
            echo "<div style='color: red;'>Failed to insert test record</div>";
        }
    } catch (PDOException $e) {
        echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
    }
    
} catch(PDOException $e) {
    die("<div style='color: red;'>Database Error: " . $e->getMessage() . "</div>");
}

echo "<p><a href='activities.php'>Go to Activities</a></p>";
?>
