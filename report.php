<?php
include 'includes/header.php';

// Redirect if not logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Process report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = sanitize_input($_POST['type']);
    $id = (int)$_POST['id'];
    $reason = sanitize_input($_POST['reason']);
    $details = sanitize_input($_POST['details']);
    $user_id = $_SESSION['user_id'];
    
    // Validate inputs
    if (!in_array($type, ['post', 'comment'])) {
        $error = "Invalid content type";
    } elseif (empty($reason)) {
        $error = "Please select a reason for reporting";
    } else {
        // Insert report
        if ($type === 'post') {
            $insert_query = "INSERT INTO reports (user_id, post_id, reason, details) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "iiss", $user_id, $id, $reason, $details);
        } else {
            $insert_query = "INSERT INTO reports (user_id, comment_id, reason, details) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "iiss", $user_id, $id, $reason, $details);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $success = true;
        } else {
            $error = "Error submitting report: " . mysqli_error($conn);
        }
    }
}
?>

<div class="content-area">
    <div class="form-container">
        <h2 class="form-title">Report Content</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <p>Thank you for your report. We will review it as soon as possible.</p>
                <p><a href="index.php">Return to homepage</a></p>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger">
                <p><?php echo $error; ?></p>
                <p><a href="javascript:history.back()">Go back</a></p>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <p>Please use the report button on posts or comments to submit a report.</p>
                <p><a href="index.php">Return to homepage</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>