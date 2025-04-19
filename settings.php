<?php
$page_title = "User Settings";
include 'includes/header.php';

// Check if user is logged in, redirect if not
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if (isset($_SESSION['settings_message'])) {
    $message = $_SESSION['settings_message'];
    unset($_SESSION['settings_message']);
}

if (isset($_SESSION['settings_error'])) {
    $error = $_SESSION['settings_error'];
    unset($_SESSION['settings_error']);
}

// Get user data
$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Get notification preferences
$notif_query = "SELECT * FROM notification_preferences WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $notif_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$notif_result = mysqli_stmt_get_result($stmt);

// If user has no notification preferences yet, create default settings
if (mysqli_num_rows($notif_result) === 0) {
    $insert_prefs = "INSERT INTO notification_preferences (user_id, comment_notifications, upvote_notifications, downvote_notifications, reply_notifications, mention_notifications, email_notifications) VALUES (?, 1, 1, 1, 1, 1, 1)";
    $stmt = mysqli_prepare($conn, $insert_prefs);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    
    // Refresh query
    mysqli_stmt_execute($stmt);
    $notif_result = mysqli_stmt_get_result($stmt);
}

$notification_preferences = mysqli_fetch_assoc($notif_result);

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Account settings form
    if (isset($_POST['update_account'])) {
        $email = sanitize_input($_POST['email']);
        $display_name = sanitize_input($_POST['display_name'] ?? '');
        $bio = sanitize_input($_POST['bio'] ?? '');
        $location = sanitize_input($_POST['location'] ?? '');
        $website = sanitize_input($_POST['website'] ?? '');
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email already exists
            $email_check = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
            $stmt = mysqli_prepare($conn, $email_check);
            mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                $error = "Email address is already in use.";
            } else {
                // Update user info
                $update_query = "UPDATE users SET email = ?, display_name = ?, bio = ?, location = ?, website = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "sssssi", $email, $display_name, $bio, $location, $website, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Account information updated successfully.";
                    
                    // Refresh user data
                    mysqli_stmt_execute($stmt);
                    $user_result = mysqli_stmt_get_result($stmt);
                    $user = mysqli_fetch_assoc($user_result);
                } else {
                    $error = "Error updating account information: " . mysqli_error($conn);
                }
            }
        }
    }
    
    // Password update form
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password_hash'])) {
                // Update password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password_hash = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "si", $password_hash, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Password updated successfully.";
                } else {
                    $error = "Error updating password: " . mysqli_error($conn);
                }
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
    
    // Notification preferences form
    if (isset($_POST['update_notifications'])) {
        $comment_notifications = isset($_POST['comment_notifications']) ? 1 : 0;
        $upvote_notifications = isset($_POST['upvote_notifications']) ? 1 : 0;
        $downvote_notifications = isset($_POST['downvote_notifications']) ? 1 : 0;
        $reply_notifications = isset($_POST['reply_notifications']) ? 1 : 0;
        $mention_notifications = isset($_POST['mention_notifications']) ? 1 : 0;
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        
        $update_query = "UPDATE notification_preferences SET 
            comment_notifications = ?, 
            upvote_notifications = ?, 
            downvote_notifications = ?,
            reply_notifications = ?,
            mention_notifications = ?,
            email_notifications = ? 
            WHERE user_id = ?";
            
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "iiiiiii", 
            $comment_notifications, 
            $upvote_notifications, 
            $downvote_notifications, 
            $reply_notifications,
            $mention_notifications,
            $email_notifications,
            $user_id
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Notification preferences updated successfully.";
            
            // Refresh notification preferences
            $stmt = mysqli_prepare($conn, $notif_query);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $notif_result = mysqli_stmt_get_result($stmt);
            $notification_preferences = mysqli_fetch_assoc($notif_result);
        } else {
            $error = "Error updating notification preferences: " . mysqli_error($conn);
        }
    }
    
    // Privacy settings form
    if (isset($_POST['update_privacy'])) {
        $show_email = isset($_POST['show_email']) ? 1 : 0;
        $show_location = isset($_POST['show_location']) ? 1 : 0;
        $show_website = isset($_POST['show_website']) ? 1 : 0;
        $profile_visibility = sanitize_input($_POST['profile_visibility']);
        
        // Check if privacy settings exist
        $check_query = "SELECT * FROM privacy_settings WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // Update existing settings
            $update_query = "UPDATE privacy_settings SET 
                show_email = ?, 
                show_location = ?, 
                show_website = ?,
                profile_visibility = ? 
                WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "iiisi", $show_email, $show_location, $show_website, $profile_visibility, $user_id);
        } else {
            // Create new settings
            $update_query = "INSERT INTO privacy_settings (user_id, show_email, show_location, show_website, profile_visibility) 
                VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "iiiis", $user_id, $show_email, $show_location, $show_website, $profile_visibility);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Privacy settings updated successfully.";
        } else {
            $error = "Error updating privacy settings: " . mysqli_error($conn);
        }
    }
    
    // Theme settings form
    if (isset($_POST['update_theme'])) {
        $theme_preference = sanitize_input($_POST['theme_preference']);
        
        // Check if theme settings exist
        $check_query = "SELECT * FROM user_preferences WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // Update existing settings
            $update_query = "UPDATE user_preferences SET theme = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "si", $theme_preference, $user_id);
        } else {
            // Create new settings
            $update_query = "INSERT INTO user_preferences (user_id, theme) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "is", $user_id, $theme_preference);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Theme preferences updated successfully.";
            
            // Update session for immediate effect
            $_SESSION['theme'] = $theme_preference;
        } else {
            $error = "Error updating theme preferences: " . mysqli_error($conn);
        }
    }
}

// Get privacy settings
$privacy_query = "SELECT * FROM privacy_settings WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $privacy_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$privacy_result = mysqli_stmt_get_result($stmt);
$privacy_settings = mysqli_fetch_assoc($privacy_result);

// Default privacy settings if none exist
if (!$privacy_settings) {
    $privacy_settings = [
        'show_email' => 0,
        'show_location' => 1,
        'show_website' => 1,
        'profile_visibility' => 'public'
    ];
}

// Get theme settings
$theme_query = "SELECT theme FROM user_preferences WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $theme_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$theme_result = mysqli_stmt_get_result($stmt);
$theme_row = mysqli_fetch_assoc($theme_result);
$theme_preference = $theme_row ? $theme_row['theme'] : 'system';
?>

<div class="settings-container">
    <div class="settings-header">
        <h1><i class="fas fa-cog"></i> Account Settings</h1>
        <p>Manage your account preferences and settings</p>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <div class="settings-tabs">
        <button class="tab-btn active" data-tab="account">
            <i class="fas fa-user"></i>
            <span>Account</span>
        </button>
        <button class="tab-btn" data-tab="security">
            <i class="fas fa-lock"></i>
            <span>Security</span>
        </button>
        <button class="tab-btn" data-tab="notifications">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
        </button>
        <button class="tab-btn" data-tab="privacy">
            <i class="fas fa-user-shield"></i>
            <span>Privacy</span>
        </button>
    </div>

    <div class="settings-content">
        <!-- Account Settings Tab -->
        <div id="account" class="settings-panel active">
            
            <div class="settings-section">
                <h2>Profile Photo</h2>
                <p>Update your profile picture</p>
                
                <form method="post" action="upload_profile_image.php" enctype="multipart/form-data" class="settings-form">
                    <div class="avatar-upload">
                        <div class="current-avatar">
                            <img src="<?php echo htmlspecialchars(get_avatar_url($user['profile_image'])); ?>" alt="Profile Photo">
                        </div>
                        <div class="avatar-actions">
                            <div class="form-group">
                                <label for="profile_image">Upload New Image</label>
                                <input type="file" id="profile_image" name="profile_image" accept="image/*">
                                <div class="form-help">Maximum file size: 2MB. Recommended dimensions: 300x300 pixels.</div>
                            </div>
                            <button type="submit" name="upload_avatar" class="btn btn-outline">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Security Settings Tab -->
        <div id="security" class="settings-panel">
            <div class="settings-section">
                <h2>Change Password</h2>
                <p>Update your account password</p>
                
                <form method="post" action="" class="settings-form">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <div class="form-help">Password must be at least 8 characters long</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
            <div class="settings-section">
                <h2>Session Management</h2>
                <p>Manage your active sessions</p>
                
                <div class="session-info">
                    <div class="session-device">
                        <i class="fas fa-laptop"></i>
                        <div class="session-details">
                            <div class="session-name">Current Device</div>
                            <div class="session-meta">Last active: Just now</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="if(confirm('Are you sure you want to log out of all other sessions?')) window.location='logout_all.php';">
                        <i class="fas fa-sign-out-alt"></i> Log Out All Other Devices
                    </button>
                </div>
            </div>
            
            <div class="settings-section">
                <h2>Account Actions</h2>
                <p>Critical account actions</p>
                
                <div class="danger-zone">
                    <button type="button" class="btn btn-danger" onclick="if(confirm('Are you sure you want to request your data? This will prepare a download with all your account information.')) window.location='request_data.php';">
                        <i class="fas fa-download"></i> Request My Data
                    </button>
                    
                    <button type="button" class="btn btn-danger" onclick="if(confirm('Are you absolutely sure you want to delete your account? This action cannot be undone.')) window.location='delete_account.php';">
                        <i class="fas fa-trash-alt"></i> Delete My Account
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Notifications Settings Tab -->
        <div id="notifications" class="settings-panel">
            <div class="settings-section">
                <h2>Notification Preferences</h2>
                <p>Control what notifications you receive</p>
                
                <form method="post" action="" class="settings-form">
                    <div class="notification-group">
                        <div class="switch-container">
                            <label for="comment_notifications">
                                <div class="switch-label">
                                    <div class="notification-type">
                                        <i class="fas fa-comment"></i>
                                        <span>Comment Notifications</span>
                                    </div>
                                    <div class="notification-desc">Receive notifications when someone comments on your posts</div>
                                </div>
                                <div class="switch-toggle">
                                    <input type="checkbox" id="comment_notifications" name="comment_notifications" <?php echo $notification_preferences['comment_notifications'] ? 'checked' : ''; ?>>
                                    <span class="switch"></span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="notification-group">
                        <div class="switch-container">
                            <label for="upvote_notifications">
                                <div class="switch-label">
                                    <div class="notification-type">
                                        <i class="fas fa-arrow-up"></i>
                                        <span>Upvote Notifications</span>
                                    </div>
                                    <div class="notification-desc">Receive notifications when someone upvotes your content</div>
                                </div>
                                <div class="switch-toggle">
                                    <input type="checkbox" id="upvote_notifications" name="upvote_notifications" <?php echo $notification_preferences['upvote_notifications'] ? 'checked' : ''; ?>>
                                    <span class="switch"></span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="notification-group">
                        <div class="switch-container">
                            <label for="downvote_notifications">
                                <div class="switch-label">
                                    <div class="notification-type">
                                        <i class="fas fa-arrow-down"></i>
                                        <span>Downvote Notifications</span>
                                    </div>
                                    <div class="notification-desc">Receive notifications when someone downvotes your content</div>
                                </div>
                                <div class="switch-toggle">
                                    <input type="checkbox" id="downvote_notifications" name="downvote_notifications" <?php echo $notification_preferences['downvote_notifications'] ? 'checked' : ''; ?>>
                                    <span class="switch"></span>
                                </div>
                            </label>
                        </div>
                    </div>
        
                    
                    
                    
                    <div class="form-actions">
                        <button type="submit" name="update_notifications" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Privacy Settings Tab -->
        <div id="privacy" class="settings-panel">
            <div class="settings-section">
                <h2>Privacy Settings</h2>
                <p>Control who can see your information</p>
                
                <form method="post" action="" class="settings-form">
                    <div class="form-group">
                        <label for="profile_visibility">Profile Visibility</label>
                        <select id="profile_visibility" name="profile_visibility">
                            <option value="public" <?php echo ($privacy_settings['profile_visibility'] === 'public') ? 'selected' : ''; ?>>Public - Anyone can view your profile</option>
                            <option value="members" <?php echo ($privacy_settings['profile_visibility'] === 'members') ? 'selected' : ''; ?>>Members Only - Only registered users can view your profile</option>
                            <option value="private" <?php echo ($privacy_settings['profile_visibility'] === 'private') ? 'selected' : ''; ?>>Private - Only you can view your profile</option>
                        </select>
                    </div>
                    
                    <div class="notification-group">
                        <div class="switch-container">
                            <label for="show_email">
                                <div class="switch-label">
                                    <div class="notification-type">
                                        <i class="fas fa-envelope"></i>
                                        <span>Show Email Address</span>
                                    </div>
                                    <div class="notification-desc">Display your email address on your profile</div>
                                </div>
                                <div class="switch-toggle">
                                    <input type="checkbox" id="show_email" name="show_email" <?php echo $privacy_settings['show_email'] ? 'checked' : ''; ?>>
                                    <span class="switch"></span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="notification-group">
                        <div class="switch-container">
                            <label for="show_location">
                                <div class="switch-label">
                                    <div class="notification-type">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>Show Location</span>
                                    </div>
                                    <div class="notification-desc">Display your location on your profile</div>
                                </div>
                                <div class="switch-toggle">
                                    <input type="checkbox" id="show_location" name="show_location" <?php echo $privacy_settings['show_location'] ? 'checked' : ''; ?>>
                                    <span class="switch"></span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="notification-group">
                        <div class="switch-container">
                            <label for="show_website">
                                <div class="switch-label">
                                    <div class="notification-type">
                                        <i class="fas fa-globe"></i>
                                        <span>Show Website</span>
                                    </div>
                                    <div class="notification-desc">Display your website on your profile</div>
                                </div>
                                <div class="switch-toggle">
                                    <input type="checkbox" id="show_website" name="show_website" <?php echo $privacy_settings['show_website'] ? 'checked' : ''; ?>>
                                    <span class="switch"></span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_privacy" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Privacy Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Appearance Settings Tab -->
        
</div>

<style>
/* Settings Page Styles */
.settings-container {
    max-width: 1000px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.settings-header {
    margin-bottom: 2rem;
}

.settings-header h1 {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 2rem;
    color: var(--text-color);
    margin-bottom: 0.5rem;
}

.settings-header p {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.settings-header h1 i {
    color: var(--primary-color);
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background-color: rgba(82, 196, 26, 0.1);
    border-left: 4px solid #52c41a;
    color: #135200;
}

.alert-danger {
    background-color: rgba(255, 77, 79, 0.1);
    border-left: 4px solid #ff4d4f;
    color: #a8071a;
}

/* Settings Tabs */
.settings-tabs {
    display: flex;
    background: white;
    border-radius: 12px;
    margin-bottom: 2rem;
    overflow-x: auto;
    scrollbar-width: none;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

.settings-tabs::-webkit-scrollbar {
    display: none;
}

.tab-btn {
    border: none;
    background: transparent;
    padding: 1.2rem 1.5rem;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 1rem;
    white-space: nowrap;
}

.tab-btn i {
    font-size: 1.1rem;
}

.tab-btn:hover {
    color: var(--primary-color);
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom: 3px solid var(--primary-color);
}

/* Settings Content */
.settings-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.settings-panel {
    display: none;
}

.settings-panel.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.settings-section {
    padding: 2rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.settings-section:last-child {
    border-bottom: none;
}

.settings-section h2 {
    font-size: 1.5rem;
    margin-top: 0;
    margin-bottom: 0.5rem;
    color: var(--text-color);
}

.settings-section p {
    color: var(--text-secondary);
    margin-top: 0;
    margin-bottom: 1.5rem;
}

/* Form Styles */
.settings-form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-color);
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="url"],
.form-group input[type="password"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 0.8rem 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    font-family: inherit;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.1);
    outline: none;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-group input[disabled] {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

.form-help {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 0.4rem;
}

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}

/* Avatar Upload Section */
.avatar-upload {
    display: flex;
    align-items: flex-start;
    gap: 2rem;
    margin-bottom: 1rem;
}

.current-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.current-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-actions {
    flex: 1;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.8rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
    box-shadow: 0 4px 10px rgba(24, 144, 255, 0.2);
}

.btn-primary:hover {
    background: var(--accent-color);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(24, 144, 255, 0.25);
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--primary-color);
    color: var(--primary-color);
}

.btn-outline:hover {
    background: rgba(24, 144, 255, 0.05);
    transform: translateY(-2px);
}

.btn-danger {
    background: transparent;
    border: 1px solid #ff4d4f;
    color: #ff4d4f;
}

.btn-danger:hover {
    background: rgba(255, 77, 79, 0.05);
}

/* Notification Toggle Switches */
.notification-group {
    margin-bottom: 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding-bottom: 1.5rem;
}

.notification-group:last-child {
    border-bottom: none;
}

.switch-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.switch-container label {
    display: flex;
    justify-content: space-between;
    width: 100%;
    cursor: pointer;
}

.switch-label {
    flex: 1;
}

.notification-type {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-color);
}

.notification-type i {
    color: var(--primary-color);
    font-size: 1.1rem;
}

.notification-desc {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.switch-toggle {
    position: relative;
    width: 48px;
    height: 24px;
}

.switch-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.switch {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ddd;
    transition: .4s;
    border-radius: 34px;
}

.switch:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .switch {
    background-color: var(--primary-color);
}

input:focus + .switch {
    box-shadow: 0 0 1px var(--primary-color);
}

input:checked + .switch:before {
    transform: translateX(24px);
}

/* Session Management */
.session-info {
    margin-bottom: 1.5rem;
}

.session-device {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 8px;
    background-color: rgba(0, 0, 0, 0.02);
}

.session-device i {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.session-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.session-meta {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

/* Danger Zone */
.danger-zone {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background-color: rgba(255, 77, 79, 0.05);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid rgba(255, 77, 79, 0.2);
}

/* Theme Options */
.theme-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.theme-option {
    position: relative;
}

.theme-option input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.theme-option label {
    cursor: pointer;
    display: block;
    text-align: center;
}

.theme-preview {
    border-radius: 10px;
    height: 120px;
    margin-bottom: 0.75rem;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 3px solid transparent;
}

.theme-option input:checked + label .theme-preview {
    border-color: var(--primary-color);
    box-shadow: 0 5px 15px rgba(24, 144, 255, 0.15);
    transform: translateY(-5px);
}

.light-theme {
    background-color: #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.dark-theme {
    background-color: #1a1a1a;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.system-theme {
    background: linear-gradient(135deg, #ffffff 50%, #1a1a1a 50%);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.theme-header {
    height: 25px;
    background-color: rgba(0, 0, 0, 0.1);
}

.dark-theme .theme-header {
    background-color: rgba(255, 255, 255, 0.1);
}

.system-theme .theme-header {
    background: linear-gradient(90deg, rgba(0, 0, 0, 0.1) 50%, rgba(255, 255, 255, 0.1) 50%);
}

.theme-content {
    padding: 10px;
}

.theme-line {
    height: 8px;
    background-color: rgba(0, 0, 0, 0.05);
    margin-bottom: 8px;
    border-radius: 4px;
}

.dark-theme .theme-line {
    background-color: rgba(255, 255, 255, 0.1);
}

.system-theme .theme-line {
    background: linear-gradient(90deg, rgba(0, 0, 0, 0.05) 50%, rgba(255, 255, 255, 0.1) 50%);
}

.theme-line.short {
    width: 60%;
}

.theme-name {
    font-weight: 600;
    color: var(--text-color);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .tab-btn span {
        display: none;
    }
    
    .tab-btn i {
        font-size: 1.25rem;
    }
    
    .settings-section {
        padding: 1.5rem;
    }
    
    .avatar-upload {
        flex-direction: column;
        align-items: center;
    }
    
    .avatar-actions {
        width: 100%;
    }
    
    .theme-options {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanels = document.querySelectorAll('.settings-panel');
    
    // Check for hash in URL
    const hash = window.location.hash.substring(1);
    if (hash) {
        activateTab(hash);
    }
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            activateTab(tabId);
            // Update URL hash
            window.history.replaceState(null, null, '#' + tabId);
        });
    });
    
    function activateTab(tabId) {
        // Remove active class from all tabs and panels
        tabButtons.forEach(button => button.classList.remove('active'));
        tabPanels.forEach(panel => panel.classList.remove('active'));
        
        // Add active class to selected tab and panel
        document.querySelector(`.tab-btn[data-tab="${tabId}"]`)?.classList.add('active');
        document.getElementById(tabId)?.classList.add('active');
    }
    
    // File upload preview
    const profileImage = document.getElementById('profile_image');
    const avatarImg = document.querySelector('.current-avatar img');
    
    if (profileImage && avatarImg) {
        profileImage.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarImg.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (newPassword && confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
        
        newPassword.addEventListener('input', function() {
            if (newPassword.value && confirmPassword.value) {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
