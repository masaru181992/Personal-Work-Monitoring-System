<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password. Please try again.";
        }
    } catch (PDOException $e) {
        $error = "Database error. Please try again later.";
        error_log("Login error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="cyber-line"></div>
    <div class="main-container">
        <div class="logo-container">
            <img src="assets/images/dict.png" alt="DICT Logo" class="rotating-logo">
            <h1 class="system-title">The Personal Monitoring System</h1>
            <p class="system-subtitle">Project Management System</p>
            <p class="location-text">Department of Information and Communications Technology</p>
        </div>

        <div class="access-cards-container">
            <div class="access-card">
                <h2 class="access-title">System Access</h2>
                <?php if ($error): ?>
                    <div class="alert" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                    <button type="submit" class="btn btn-custom">Log In</button>
                </form>
                <p class="restricted-text">Restricted to authorized personnel only</p>
            </div>
        </div>

        <div class="footer">
            © 2025 Department of Information and Communications Technology. All rights reserved.
        </div>
        <div class="version">
            Version 2.1
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 