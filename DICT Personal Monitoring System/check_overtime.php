<?php
// Database configuration
$host = 'localhost';
$dbname = 'dict_monitoring';
$username = 'root';
$password = '';

try {
    // Create a PDO instance
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Check if the table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'overtime_requests'");
    
    if ($tableCheck->rowCount() === 0) {
        echo "The 'overtime_requests' table does not exist.\n";
        exit;
    }

    // Get table structure
    echo "<h2>Table Structure: overtime_requests</h2>";
    $structure = $pdo->query("DESCRIBE overtime_requests");
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($structure as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Get record count
    $count = $pdo->query("SELECT COUNT(*) as count FROM overtime_requests")->fetch();
    echo "<h3>Total records: " . $count['count'] . "</h3>";

    // Show sample records if they exist
    if ($count['count'] > 0) {
        echo "<h3>Sample Records (first 5):</h3>";
        $records = $pdo->query("SELECT * FROM overtime_requests LIMIT 5");
        
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        // Print headers
        echo "<tr>";
        foreach (array_keys($records->fetch(PDO::FETCH_ASSOC)) as $col) {
            echo "<th>" . htmlspecialchars($col) . "</th>";
        }
        $records->execute(); // Reset the cursor
        echo "</tr>";
        
        // Print data
        while ($row = $records->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (PDOException $e) {
    echo "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    
    // Show all tables in the database
    echo "<h3>Available tables in database '$dbname':</h3>";
    $tables = $pdo->query("SHOW TABLES");
    echo "<ul>";
    while ($table = $tables->fetch(PDO::FETCH_NUM)) {
        echo "<li>" . htmlspecialchars($table[0]) . "</li>";
    }
    echo "</ul>";
}
?>
