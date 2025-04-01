<?php
// Prevent PHP errors from breaking JSON output
error_reporting(0);
// Start output buffering to capture any unexpected output
ob_start();

// Use full path to includes for consistency
require_once dirname(__DIR__) . '/includes/init.php';

// Set the correct path for the session cookie
$path = '/';
if (dirname($_SERVER['SCRIPT_NAME']) != '/') {
    $path = dirname(dirname($_SERVER['SCRIPT_NAME']));
}
session_set_cookie_params(0, $path);

// Start session explicitly if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Capture any unexpected output
$unexpected_output = ob_get_clean();
// Start fresh buffer
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to vote', 'session_status' => session_status(), 'session_id' => session_id()]);
    exit;
}

$user_id = $_SESSION['user_id'];
$vote_type = isset($_POST['vote_type']) ? (int)$_POST['vote_type'] : 0;
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : null;

if ($vote_type !== 1 && $vote_type !== -1) {
    echo json_encode(['success' => false, 'message' => 'Invalid vote type']);
    exit;
}

if (($post_id && $comment_id) || (!$post_id && !$comment_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid vote target']);
    exit;
}

mysqli_query($conn, "SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");

mysqli_begin_transaction($conn);

try {
    if ($post_id) {
        $check_query = "SELECT vote_id, vote_type FROM votes WHERE user_id = ? AND post_id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
    } else {
        $check_query = "SELECT vote_id, vote_type FROM votes WHERE user_id = ? AND comment_id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $comment_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $vote = mysqli_fetch_assoc($result);
        $existing_vote_type = $vote['vote_type'];

        if ($existing_vote_type === $vote_type) {
            if ($post_id) {
                $delete_query = "DELETE FROM votes WHERE user_id = ? AND post_id = ?";
                $stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
            } else {
                $delete_query = "DELETE FROM votes WHERE user_id = ? AND comment_id = ?";
                $stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($stmt, "ii", $user_id, $comment_id);
            }
            mysqli_stmt_execute($stmt);

            if ($post_id) {
                if ($vote_type === 1) {
                    $update_query = "UPDATE posts SET upvotes = upvotes - 1 WHERE post_id = ?";
                } else {
                    $update_query = "UPDATE posts SET downvotes = downvotes - 1 WHERE post_id = ?";
                }
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "i", $post_id);
            } else {
                if ($vote_type === 1) {
                    $update_query = "UPDATE comments SET upvotes = upvotes - 1 WHERE comment_id = ?";
                } else {
                    $update_query = "UPDATE comments SET downvotes = downvotes - 1 WHERE comment_id = ?";
                }
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "i", $comment_id);
            }
            mysqli_stmt_execute($stmt);

            $vote_type = 0; 
        } else {
            if ($post_id) {
                $update_query = "UPDATE votes SET vote_type = ? WHERE user_id = ? AND post_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "iii", $vote_type, $user_id, $post_id);
            } else {
                $update_query = "UPDATE votes SET vote_type = ? WHERE user_id = ? AND comment_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "iii", $vote_type, $user_id, $comment_id);
            }
            mysqli_stmt_execute($stmt);

            if ($post_id) {
                if ($vote_type === 1) {
                    $update_query = "UPDATE posts SET upvotes = upvotes + 1, downvotes = downvotes - 1 WHERE post_id = ?";
                } else {
                    $update_query = "UPDATE posts SET upvotes = upvotes - 1, downvotes = downvotes + 1 WHERE post_id = ?";
                }
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "i", $post_id);
            } else {
                if ($vote_type === 1) {
                    $update_query = "UPDATE comments SET upvotes = upvotes + 1, downvotes = downvotes - 1 WHERE comment_id = ?";
                } else {
                    $update_query = "UPDATE comments SET upvotes = upvotes - 1, downvotes = downvotes + 1 WHERE comment_id = ?";
                }
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "i", $comment_id);
            }
            mysqli_stmt_execute($stmt);
        }
    } else {
        if ($post_id) {
            $insert_query = "INSERT INTO votes (user_id, post_id, vote_type) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $post_id, $vote_type);
        } else {
            $insert_query = "INSERT INTO votes (user_id, comment_id, vote_type) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $comment_id, $vote_type);
        }
        mysqli_stmt_execute($stmt);

        if ($post_id) {
            if ($vote_type === 1) {
                $update_query = "UPDATE posts SET upvotes = upvotes + 1 WHERE post_id = ?";
            } else {
                $update_query = "UPDATE posts SET downvotes = downvotes + 1 WHERE post_id = ?";
            }
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "i", $post_id);
        } else {
            if ($vote_type === 1) {
                $update_query = "UPDATE comments SET upvotes = upvotes + 1 WHERE comment_id = ?";
            } else {
                $update_query = "UPDATE comments SET downvotes = downvotes + 1 WHERE comment_id = ?";
            }
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "i", $comment_id);
        }
        mysqli_stmt_execute($stmt);

        if ($post_id) {
            $get_post_owner = "SELECT user_id FROM posts WHERE post_id = ?";
            $target_id = $post_id;
        } else {
            $get_post_owner = "SELECT user_id FROM comments WHERE comment_id = ?";
            $target_id = $comment_id;
        }

        $stmt = mysqli_prepare($conn, $get_post_owner);
        mysqli_stmt_bind_param($stmt, "i", $target_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $owner = mysqli_fetch_assoc($result);

        if ($owner && $owner['user_id'] != $user_id) {
            $karma_change = ($vote_type === 1) ? 1 : -1;
            $update_karma = "UPDATE users SET karma = karma + ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_karma);
            mysqli_stmt_bind_param($stmt, "ii", $karma_change, $owner['user_id']);
            mysqli_stmt_execute($stmt);
        }
    }

    if ($post_id) {
        $count_query = "SELECT upvotes - downvotes as vote_count FROM posts WHERE post_id = ?";
        $stmt = mysqli_prepare($conn, $count_query);
        mysqli_stmt_bind_param($stmt, "i", $post_id);
    } else {
        $count_query = "SELECT upvotes - downvotes as vote_count FROM comments WHERE comment_id = ?";
        $stmt = mysqli_prepare($conn, $count_query);
        mysqli_stmt_bind_param($stmt, "i", $comment_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $count_data = mysqli_fetch_assoc($result);
    $vote_count = $count_data['vote_count'];

    mysqli_commit($conn);
    
    // Get final output and discard
    ob_end_clean();

    echo json_encode([
        'success' => true, 
        'vote_count' => $vote_count,
        'user_vote' => $vote_type   
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    
    // Get final output and discard
    ob_end_clean();
    
    echo json_encode(['success' => false, 'message' => 'Error processing vote']);
}

// Handle notifications outside of the transaction
ob_start();

if ($post_id) {
    $get_owner = "SELECT user_id, title FROM posts WHERE post_id = ?";
    $target_id = $post_id;
    $content_type = 'post';
} else {
    $get_owner = "SELECT c.user_id, p.title, p.post_id FROM comments c 
                 JOIN posts p ON c.post_id = p.post_id 
                 WHERE c.comment_id = ?";
    $target_id = $comment_id;
    $content_type = 'comment';
}

$stmt = mysqli_prepare($conn, $get_owner);
mysqli_stmt_bind_param($stmt, "i", $target_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$owner = mysqli_fetch_assoc($result);

if ($owner && $owner['user_id'] != $user_id) {
    $title = htmlspecialchars(substr($owner['title'], 0, 50));
    $notification_type = ($vote_type == 1) ? 'upvote' : 'downvote';

    if ($content_type == 'post') {
        $notification_content = ($vote_type == 1)
            ? "Someone upvoted your post: \"$title\""
            : "Someone downvoted your post: \"$title\"";
        $notification_link = "post.php?id=$post_id";
        create_notification($owner['user_id'], $notification_content, $notification_link, $notification_type, $post_id, $user_id);
    } else {
        $notification_content = ($vote_type == 1)
            ? "Someone upvoted your comment on: \"$title\""
            : "Someone downvoted your comment on: \"$title\"";
        $notification_link = "post.php?id=" . $owner['post_id'] . "#comment-$comment_id";
        create_notification($owner['user_id'], $notification_content, $notification_link, $notification_type, $comment_id, $user_id);
    }
}
// Discard any output from notification creation
ob_end_clean();
?>
