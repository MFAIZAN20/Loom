<?php
$page_title = "Notification Settings";
// Fix the CSS path - remove redundant "css/" since that's likely handled in header.php
$page_css = "settings.css"; 

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'includes/header.php';

// Redirect if not logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get current preferences
$query = "SELECT * FROM notification_preferences WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

// Check for query errors
if (!$stmt) {
    die("Database query failed: " . mysqli_error($conn));
}

$result = mysqli_stmt_get_result($stmt);

// If no preferences set, create default
if (mysqli_num_rows($result) == 0) {
    $insert = "INSERT INTO notification_preferences (user_id, comment_notifications, upvote_notifications, downvote_notifications) 
               VALUES (?, 1, 1, 1)";
    $stmt = mysqli_prepare($conn, $insert);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    
    // Get default preferences
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$prefs = mysqli_fetch_assoc($result);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment_notifications = isset($_POST['comment_notifications']) ? 1 : 0;
    $upvote_notifications = isset($_POST['upvote_notifications']) ? 1 : 0;
    $downvote_notifications = isset($_POST['downvote_notifications']) ? 1 : 0;
    
    $update = "UPDATE notification_preferences SET 
               comment_notifications = ?, 
               upvote_notifications = ?, 
               downvote_notifications = ? 
               WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt, "iiii", $comment_notifications, $upvote_notifications, $downvote_notifications, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $success = "Notification settings updated successfully";
        
        // Refresh preferences
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $prefs = mysqli_fetch_assoc($result);
    } else {
        $error = "Error updating settings: " . mysqli_error($conn);
    }
}
?>

<main class="settings-wrapper">
    <div class="form-container" id="notification-settings">
        <h1 class="form-title">Notification Settings</h1>
        <p class="settings-description">Choose which notifications you'd like to receive on Loom</p>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle alert-icon"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group notification-option">
                <div class="option-details">
                    <div class="option-icon">
                        <i class="fas fa-comment"></i>
                    </div>
                    <div class="option-text">
                        <h3>Comment Notifications</h3>
                        <p>Get notified when someone comments on your posts</p>
                    </div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="comment_notifications" <?php echo $prefs['comment_notifications'] ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                    <span class="sr-only">Toggle comment notifications</span>
                </label>
            </div>
            
            <div class="form-group notification-option">
                <div class="option-details">
                    <div class="option-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="option-text">
                        <h3>Upvote Notifications</h3>
                        <p>Get notified when someone upvotes your posts or comments</p>
                    </div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="upvote_notifications" <?php echo $prefs['upvote_notifications'] ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                    <span class="sr-only">Toggle upvote notifications</span>
                </label>
            </div>
            
            <div class="form-group notification-option">
                <div class="option-details">
                    <div class="option-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="option-text">
                        <h3>Downvote Notifications</h3>
                        <p>Get notified when someone downvotes your posts or comments</p>
                    </div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="downvote_notifications" <?php echo $prefs['downvote_notifications'] ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                    <span class="sr-only">Toggle downvote notifications</span>
                </label>
            </div>
            
            <button type="submit" class="btn btn-block">
                <i class="fas fa-save"></i> Save Settings
            </button>
            
            <div class="form-footer">
                <a href="profile.php"><i class="fas fa-arrow-left"></i> Back to Profile</a>
            </div>
        </form>
    </div>
</main>

<script>
// Add this script to provide visual feedback when settings are changed
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('.toggle-switch input');
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const option = this.closest('.notification-option');
            if (this.checked) {
                option.classList.add('option-active');
            } else {
                option.classList.remove('option-active');
            }
        });
        
        // Set initial state
        if (toggle.checked) {
            toggle.closest('.notification-option').classList.add('option-active');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>