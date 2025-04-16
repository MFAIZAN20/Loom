<?php
$page_title = "Edit Profile";
include 'includes/header.php';

// Redirect if not logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Process profile updates
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate email
    if (empty($email)) {
        $error = "Email is required";
    } else {
        // Check if email exists but belongs to another user
        $check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $stmt = mysqli_prepare($conn, $check_email);
        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $error = "Email already in use by another account";
        } else {
            // Prepare for updates
            $profile_picture_path = $user['profile_picture']; // Default to current picture
            $update_success = true;
            
            // Handle profile picture upload if provided
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/profile_pictures/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Process the uploaded file
                $file_name = $_FILES['profile_picture']['name'];
                $file_tmp = $_FILES['profile_picture']['tmp_name'];
                $file_size = $_FILES['profile_picture']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
                
                if (in_array($file_ext, $allowed_extensions)) {
                    if ($file_size <= 2097152) { // 2MB limit
                        $new_file_name = $user_id . '_' . time() . '.' . $file_ext;
                        $destination = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $profile_picture_path = $destination;
                        } else {
                            $error = "Error uploading file";
                            $update_success = false;
                        }
                    } else {
                        $error = "File size too large (max 2MB)";
                        $update_success = false;
                    }
                } else {
                    $error = "Invalid file type. Only JPG, PNG and GIF are allowed";
                    $update_success = false;
                }
            }
            
            // Update profile if no errors so far
            if ($update_success) {
                // Update email and profile picture
                $update_query = "UPDATE users SET email = ?, profile_picture = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ssi", $email, $profile_picture_path, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Profile updated successfully";
                    
                    // Update password if provided
                    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
                        // Verify current password
                        if (password_verify($current_password, $user['password_hash'])) {
                            if ($new_password === $confirm_password) {
                                // Update password
                                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                                $update_password = "UPDATE users SET password_hash = ? WHERE user_id = ?";
                                $stmt = mysqli_prepare($conn, $update_password);
                                mysqli_stmt_bind_param($stmt, "si", $password_hash, $user_id);
                                mysqli_stmt_execute($stmt);
                                
                                $success = "Profile and password updated successfully";
                            } else {
                                $error = "New passwords do not match";
                            }
                        } else {
                            $error = "Current password is incorrect";
                        }
                    }
                    
                    // Refresh user data
                    $stmt = mysqli_prepare($conn, $user_query);
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $user = mysqli_fetch_assoc($result);
                } else {
                    $error = "Error updating profile: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Check if profile image exists and set default if not
$profile_image = get_avatar_url($user['profile_picture'] ?? ($user['profile_image'] ?? null));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4C8BF5;
            --secondary-color: #FF6A13;
            --accent-color: #6c5ce7;
            --text-primary: #2d3436;
            --text-secondary: #636e72;
            --border-color: #dfe6e9;
            --surface-light: #ffffff;
            --shadow-depth1: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-depth2: 0 8px 24px rgba(0, 0, 0, 0.12);
            --transition-smooth: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: #f5f7fa;
            color: var(--text-primary);
        }

        /* Form Container */
        .form-container {
            background-color: var(--surface-light);
            border-radius: 16px;
            box-shadow: var(--shadow-depth1);
            padding: 2.5rem;
            margin: 2rem auto;
            max-width: 800px;
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-title {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all var(--transition-smooth);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(76, 139, 245, 0.2);
        }

        /* Profile Picture */
        .profile-picture-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #f0f0f0;
            border: 3px solid var(--surface-light);
            box-shadow: var(--shadow-depth1);
        }

        /* Password Section */
        .password-section-title {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        /* Button Styles */
        .btn {
            display: block;
            width: 100%;
            padding: 1rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            border: none;
            border-radius: 10px;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            color: white;
            cursor: pointer;
            transition: all var(--transition-smooth);
            box-shadow: 0 4px 15px rgba(76, 139, 245, 0.25);
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(76, 139, 245, 0.35);
        }

        .btn::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
        }

        .btn:hover::after {
            animation: shine 1.5s infinite;
        }

        @keyframes shine {
            100% {
                transform: translateX(100%);
            }
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: rgba(82, 196, 26, 0.1);
            border-left: 4px solid #52c41a;
            color: #135200;
        }

        .alert-danger {
            background-color: #fff5f5;
            border-left: 4px solid #ff4d4f;
            color: #cf1322;
        }

        /* Helper Text */
        .form-text {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        /* Form Footer */
        .form-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .form-footer a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .form-footer a:hover {
            color: var(--accent-color);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-container {
                padding: 2rem 1.5rem;
                margin: 1rem auto;
            }
            
            .form-title {
                font-size: 1.8rem;
            }
            
            .profile-picture {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="form-title">Edit Profile</h2>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-group profile-picture-container">
                <label>Current Profile Picture</label>
                <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile picture" class="profile-picture">
            </div>
            
            <div class="form-group">
                <label for="profile_picture">Update Profile Picture</label>
                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                <small class="form-text">Max size: 2MB. Supported formats: JPG, PNG, GIF</small>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                <small class="form-text">Username cannot be changed</small>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <h3 class="password-section-title">Change Password</h3>
            <small class="form-text">Leave blank to keep current password</small>

            <div class="form-group">
                <label for="current-password">Current Password</label>
                <input type="password" class="form-control" id="current-password" name="current_password">
            </div>

            <div class="form-group">
                <label for="new-password">New Password</label>
                <input type="password" class="form-control" id="new-password" name="new_password">
            </div>

            <div class="form-group">
                <label for="confirm-password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm-password" name="confirm_password">
            </div>

            <button type="submit" class="btn">Save Changes</button>

            <div class="form-footer">
                <a href="profile.php">Back to Profile</a>
            </div>
        </form>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
