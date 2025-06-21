<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        // Update profile information
        if (!empty($full_name) && !empty($email)) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $_SESSION['user_id']]);
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            $success_message = 'Profile updated successfully!';
        }
        
        // Change password if all fields are filled
        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password !== $confirm_password) {
                $error_message = 'New password and confirm password do not match.';
            } else {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    
                    if (empty($success_message)) {
                        $success_message = 'Password updated successfully!';
                    } else {
                        $success_message = 'Profile and password updated successfully!';
                    }
                } else {
                    $error_message = 'Current password is incorrect.';
                }
            }
        }
    } catch (PDOException $e) {
        $error_message = 'An error occurred while updating your profile. Please try again.';
    }
}

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Personal Monitoring System - My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* System theme variables */
        :root {
            --primary-bg: #0a192f;
            --secondary-bg: rgba(16, 32, 56, 0.9);
            --accent-color: #64ffda;
            --accent-secondary: #7928ca;
            --text-primary: #ffffff;
            --text-secondary: #8892b0;
            --border-color: rgba(100, 255, 218, 0.1);
            --card-bg: rgba(16, 32, 56, 0.9);
            --hover-bg: rgba(100, 255, 218, 0.05);
            --shadow-sm: 0 4px 20px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 8px 30px rgba(100, 255, 218, 0.1);
        }

        body {
            background-color: var(--primary-bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(100, 255, 218, 0.1) 0%, transparent 50%),
                radial-gradient(at 100% 0%, rgba(121, 40, 202, 0.1) 0%, transparent 50%);
            font-family: 'Space Grotesk', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        .main-content {
            padding: 0.5rem 0.5rem 0.75rem;
            position: relative;
            margin: 0 auto;
            max-width: 900px;
            width: 95%;
            min-height: auto;
            backdrop-filter: blur(10px);
        }

        @media (max-width: 1200px) {
            .main-content {
                max-width: 95%;
                padding: 1rem 0.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                max-width: 100%;
                padding: 0.5rem;
            }
        }

        .card {
            background: rgba(22, 40, 65, 0.98);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(100, 255, 218, 0.2);
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
            color: #f8f9fa;
            margin: 0 auto;
            max-width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: rgba(100, 255, 218, 0.2);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(100, 255, 218, 0.2);
        }

        .form-control, .form-select {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(100, 255, 218, 0.25);
        }

        .btn-primary {
            background-color: var(--accent-color);
            border: none;
            color: var(--primary-bg);
            font-weight: 500;
            padding: 0.4rem 1.25rem;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #52e0b1;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(100, 255, 218, 0.3);
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(100, 255, 218, 0.15);
            border: 2px solid var(--accent-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            position: relative;
            overflow: hidden;
            color: var(--accent-color);
            transition: all 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(100, 255, 218, 0.3);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar i {
            font-size: 2.25rem;
            color: var(--accent-color);
        }

        .profile-avatar-edit {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--accent-color);
            color: var(--primary-bg);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-avatar-edit:hover {
            transform: scale(1.1);
        }

        .section-title {
            color: var(--accent-color);
            font-weight: 600;
            font-size: 1.25rem;
            margin: 0 0 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(100, 255, 218, 0.2);
            display: flex;
            align-items: center;
            position: relative;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-family: 'JetBrains Mono', monospace;
            text-shadow: 0 0 10px rgba(100, 255, 218, 0.3);
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100px;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-color), transparent);
            border-radius: 2px;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
            color: #4a5568;
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            border-radius: 8px;
            border: 1px solid rgba(100, 255, 218, 0.2);
            height: auto;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 400;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(100, 255, 218, 0.25);
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            outline: none;
        }
        
        .form-control::placeholder {
            color: #a0aec0;
            opacity: 0.8;
            font-weight: 300;
        }
        
        .btn {
            padding: 0.5rem 1.25rem;
            font-size: 0.9rem;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
            letter-spacing: 0.3px;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            font-family: 'Space Grotesk', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-color) 0%, #4ad3b5 100%);
            border: none;
            color: #000000;
            box-shadow: 0 4px 15px rgba(100, 255, 218, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 20px rgba(100, 255, 218, 0.4);
            color: #000000;
        }
        
        .btn-outline-primary {
            border: 1px solid var(--accent-color);
            color: var(--accent-color);
            background: transparent;
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(135deg, var(--accent-color) 0%, #4ad3b5 100%);
            transition: all 0.3s ease;
            z-index: -1;
            opacity: 0;
        }
        
        .btn-outline-primary:hover {
            color: #000000;
            border-color: transparent;
        }
        
        .btn-outline-primary:hover::before {
            width: 100%;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Include Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="main-content animate__animated animate__fadeIn">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="welcome-text" style="font-size: 1.5rem;">My Profile</h2>
                </div>
                
                <div class="card" style="max-width: 800px; max-height: 85vh; margin: 0 auto;">
                    <div class="card-body p-2 p-md-2">
                        <div class="row">
                            <!-- Main Content -->
                            <div class="col-12">
                                <div class="mb-4 mt-3">
                                    <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-primary"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                </div>
                                <?php if ($success_message): ?>
                                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill me-2"></i>
                                            <div><?php echo $success_message; ?></div>
                                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                            <div><?php echo $error_message; ?></div>
                                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <h2 class="section-title">Account Settings</h2>
                                <p class="text-white mb-4">Update your profile information and password</p>
                                
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label text-white mb-1">Full Name</label>
                                        <input type="text" class="form-control form-control-sm" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="email" class="form-label text-white mb-1">Email Address</label>
                                        <input type="email" class="form-control form-control-sm" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="pt-3 border-top">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <h3 class="section-title mb-0" style="font-size: 1.1rem;">Change Password</h3>
                                            <span class="badge" style="background: rgba(100, 255, 218, 0.1); color: var(--accent-color);">Optional</span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label text-white mb-1">Current Password</label>
                                            <input type="password" class="form-control form-control-sm" id="current_password" 
                                                   name="current_password" placeholder="Enter current password">
                                        </div>
                                        
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label for="new_password" class="form-label text-white mb-1">New Password</label>
                                                <input type="password" class="form-control form-control-sm" id="new_password" 
                                                       name="new_password" placeholder="Enter new password">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="confirm_password" class="form-label text-white mb-1">Confirm Password</label>
                                                <input type="password" class="form-control form-control-sm" id="confirm_password" 
                                                       name="confirm_password" placeholder="Confirm new password">
                                            </div>
                                        </div>
                                        
                                        <div class="form-text text-muted mb-2" style="font-size: 0.8rem;">
                                            <i class="bi bi-info-circle me-1"></i> Leave password fields blank to keep current password
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-2">
                                        <button type="submit" class="btn btn-primary px-4 py-2" style="min-width: 140px;">
                                            <i class="bi bi-save me-2"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
            /* Modal styles */
            .modal-content {
                background: rgba(22, 40, 65, 0.98);
                border: 1px solid rgba(100, 255, 218, 0.2);
                border-radius: 16px;
                overflow: hidden;
            }
            
            .modal-header {
                border-bottom: 1px solid rgba(100, 255, 218, 0.1);
            }
            
            .modal-footer {
                border-top: 1px solid rgba(100, 255, 218, 0.1);
            }
            
            .btn-close-white {
                filter: invert(1) grayscale(100%) brightness(200%);
            }
        </style>
        
        <script>
                            setTimeout(() => {
                                if (profilePhotoModal) {
                                    profilePhotoModal.hide();
                                }
                            }, 1000);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            const errorEvent = new CustomEvent('show-toast', {
                                detail: { 
                                    message: 'Error uploading photo. Please try again.',
                                    type: 'error'
                                }
                            });
                            window.dispatchEvent(errorEvent);
                        })
                        .finally(() => {
                            // Reset button state
                            if (uploadSpinner) {
                                uploadSpinner.classList.add('d-none');
                                uploadSpinner.classList.remove('spinner-border', 'spinner-border-sm');
                                uploadText.innerHTML = '<i class="bi bi-camera me-1"></i> Change Photo';
                                changePhotoBtn.disabled = false;
                            }
                            
                            // Reset file input
                            if (photoInput) {
                                photoInput.value = '';
                            }
                        });
                    };
                    
                    reader.readAsDataURL(file);
                };
                
                // Handle click on change photo button
                if (changePhotoBtn) {
                    changePhotoBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (photoInput) {
                            photoInput.click();
                        }
                    });
                }
                
                // Handle file selection
                if (photoInput) {
                    photoInput.addEventListener('change', function() {
                        if (this.files && this.files[0]) {
                            handleFileSelect(this.files[0]);
                        }
                    });
                }
                
                // Allow dropping files
                if (profileAvatar) {
                    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                        profileAvatar.addEventListener(eventName, preventDefaults, false);
                    });
                    
                    function preventDefaults(e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    
                    ['dragenter', 'dragover'].forEach(eventName => {
                        profileAvatar.addEventListener(eventName, highlight, false);
                    });
                    
                    ['dragleave', 'drop'].forEach(eventName => {
                        profileAvatar.addEventListener(eventName, unhighlight, false);
                    });
                    
                    function highlight() {
                        profileAvatar.classList.add('border', 'border-primary');
                    }
                    
                    function unhighlight() {
                        profileAvatar.classList.remove('border', 'border-primary');
                    }
                    
                    profileAvatar.addEventListener('drop', handleDrop, false);
                    
                    function handleDrop(e) {
                        const dt = e.dataTransfer;
                        const files = dt.files;
                        if (files.length) {
                            handleFileSelect(files[0]);
                        }
                    }
                }
            });
        </script>
        <script>
            // Update the live time with seconds
            function updateLiveTime() {
                const now = new Date();
                const options = { 
                    hour: 'numeric', 
                    minute: '2-digit', 
                    second: '2-digit',
                    hour12: true 
                };
                const timeElements = document.querySelectorAll('.live-time');
                timeElements.forEach(el => {
                    el.textContent = now.toLocaleTimeString('en-US', options);
                });
            }

            // Update time immediately and then every second
            updateLiveTime();
            setInterval(updateLiveTime, 1000);
            updateLiveTime(); // Initial call

            // Handle profile picture edit
            document.querySelector('.profile-avatar-edit').addEventListener('click', function() {
                // In a real application, you would open a file upload dialog here
                alert('Profile picture upload functionality would be implemented here.');
            });
        </script>
</body>
</html>
