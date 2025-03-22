<?php
$page_js = "post.js";
include 'includes/header.php';

// Get post ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$post_id = (int)$_GET['id'];

// Get post details
$post_query = "SELECT p.*, u.username FROM posts p JOIN users u ON p.user_id = u.user_id WHERE p.post_id = ?";
$stmt = mysqli_prepare($conn, $post_query);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: index.php");
    exit;
}

$post = mysqli_fetch_assoc($result);
$page_title = $post['title'];

// Process comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    $comment_content = sanitize_input($_POST['comment']);
    
    if (!empty($comment_content)) {
        $user_id = $_SESSION['user_id'];
        
        $insert_query = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "iis", $post_id, $user_id, $comment_content);
        
        if (mysqli_stmt_execute($stmt)) {
            $comment_id = mysqli_insert_id($conn);
            
            // Create notification for post owner
            if ($post['user_id'] != $user_id) {
                $post_title = htmlspecialchars(substr($post['title'], 0, 50));
                if (strlen($post['title']) > 50) {
                    $post_title .= '...';
                }
                $notification_content = $_SESSION['username'] . " commented on your post: \"$post_title\"";
                $notification_link = "post.php?id=$post_id#comment-$comment_id";
                create_notification($post['user_id'], $notification_content, $notification_link, 'comment', $post_id, $user_id);
            }
            
            // Redirect to avoid resubmit on refresh
            safe_redirect("post.php?id=$post_id#comment-$comment_id");
        }
    }
}

// Get comments
$comment_query = "SELECT c.*, u.username, 
                 (SELECT COUNT(*) FROM votes WHERE comment_id = c.comment_id AND vote_type = 1) as upvotes,
                 (SELECT COUNT(*) FROM votes WHERE comment_id = c.comment_id AND vote_type = -1) as downvotes
                 FROM comments c 
                 JOIN users u ON c.user_id = u.user_id 
                 WHERE c.post_id = ? 
                 ORDER BY c.parent_id IS NULL DESC, c.created_at ASC";
$stmt = mysqli_prepare($conn, $comment_query);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$comments_result = mysqli_stmt_get_result($stmt);
?>

<div class="content-area">
    <!-- Post -->
    <div class="card post-card" data-post-id="<?php echo $post['post_id']; ?>">
        <div class="vote-column">
            <button class="vote-btn vote-up <?php echo (is_logged_in() && get_user_vote($_SESSION['user_id'], $post['post_id']) == 1) ? 'upvoted' : ''; ?>" data-vote="1">
                <i class="fas fa-arrow-up"></i>
            </button>
            <div class="vote-count"><?php echo $post['upvotes'] - $post['downvotes']; ?></div>
            <button class="vote-btn vote-down <?php echo (is_logged_in() && get_user_vote($_SESSION['user_id'], $post['post_id']) == -1) ? 'downvoted' : ''; ?>" data-vote="-1">
                <i class="fas fa-arrow-down"></i>
            </button>
        </div>
        <div class="post-content">
            <div class="post-header">
                <div class="post-category">
                    <a href="index.php?category=<?php echo urlencode($post['category']); ?>"><?php echo htmlspecialchars($post['category']); ?></a>
                </div>
                <div class="post-meta">
                    Posted by <a href="profile.php?user=<?php echo urlencode($post['username']); ?>"><?php echo htmlspecialchars($post['username']); ?></a>
                    <?php echo time_elapsed_string($post['created_at']); ?>
                </div>
            </div>
            <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
            <div class="post-body">
                <?php echo nl2br($post['content']); ?>
            </div>
            <div class="post-footer">
                <div class="post-action report-btn" data-type="post" data-id="<?php echo $post['post_id']; ?>">
                    <i class="fas fa-flag"></i> Report
                </div>
                
                <?php if (is_logged_in() && $_SESSION['user_id'] == $post['user_id']): ?>
                <a href="edit-post.php?id=<?php echo $post['post_id']; ?>" class="post-action">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <?php endif; ?>
                
                <?php
                // Get tags for this post
                $tag_query = "SELECT t.name FROM tags t 
                             JOIN post_tags pt ON t.tag_id = pt.tag_id 
                             WHERE pt.post_id = ?";
                $tag_stmt = mysqli_prepare($conn, $tag_query);
                mysqli_stmt_bind_param($tag_stmt, "i", $post['post_id']);
                mysqli_stmt_execute($tag_stmt);
                $tag_result = mysqli_stmt_get_result($tag_stmt);
                
                if (mysqli_num_rows($tag_result) > 0) {
                    echo '<div class="post-tags">';
                    while ($tag = mysqli_fetch_assoc($tag_result)) {
                        echo '<a href="index.php?tag=' . urlencode($tag['name']) . '" class="post-tag">' . htmlspecialchars($tag['name']) . '</a>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    

<!-- Comments section -->
<div class="comments-section">
    <h3 class="comments-title"><?php echo mysqli_num_rows($comments_result); ?> Comments</h3>
    
    <?php if (is_logged_in()): ?>
    <div class="comment-form-container">
        <form id="comment-form" method="post">
            <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
            <div class="form-group">
                <textarea class="comment-textarea" name="comment" placeholder="What are your thoughts?" required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="comment-submit-btn">Comment</button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="login-to-comment">
        <p><a href="login.php" class="login-link">Log in</a> or <a href="signup.php" class="signup-link">sign up</a> to leave a comment</p>
    </div>
    <?php endif; ?>
    
    <!-- Comments list -->
    <?php if (mysqli_num_rows($comments_result) > 0): ?>
        <div class="comments-list">
            <?php 
            // Convert result to an array for easier processing
            $comments_array = [];
            mysqli_data_seek($comments_result, 0);
            while ($comment = mysqli_fetch_assoc($comments_result)) {
                $comments_array[] = $comment;
            }
            
            // Function to display comments recursively
            function display_comments($comments, $parent_id = null) {
                global $conn;
                
                foreach ($comments as $comment) {
                    if ($comment['parent_id'] == $parent_id) {
                        ?>
                        <div class="comment" id="comment-<?php echo $comment['comment_id']; ?>" data-comment-id="<?php echo $comment['comment_id']; ?>">
                            <?php if ($parent_id !== null): ?>
                            <button class="collapse-btn">−</button>
                            <?php endif; ?>
                            
                            <div class="comment-content">
                                <div class="comment-header">
                                    <a href="profile.php?user=<?php echo urlencode($comment['username']); ?>" class="comment-author"><?php echo htmlspecialchars($comment['username']); ?></a>
                                    <span class="comment-meta"><?php echo time_elapsed_string($comment['created_at']); ?></span>
                                </div>
                                
                                <div class="comment-body">
                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                </div>
                                
                                <div class="comment-actions">
                                    <div class="comment-votes">
                                        <button class="vote-btn vote-up <?php echo (is_logged_in() && get_user_vote($_SESSION['user_id'], null, $comment['comment_id']) == 1) ? 'upvoted' : ''; ?>" data-vote="1">
                                            <i class="fas fa-arrow-up"></i>
                                        </button>
                                        <span class="vote-count"><?php echo $comment['upvotes'] - $comment['downvotes']; ?></span>
                                        <button class="vote-btn vote-down <?php echo (is_logged_in() && get_user_vote($_SESSION['user_id'], null, $comment['comment_id']) == -1) ? 'downvoted' : ''; ?>" data-vote="-1">
                                            <i class="fas fa-arrow-down"></i>
                                        </button>
                                    </div>
                                    
                                    <?php if (is_logged_in()): ?>
                                        <button class="comment-action reply-btn">
                                            <i class="fas fa-reply"></i> Reply
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="comment-action report-btn" data-type="comment" data-id="<?php echo $comment['comment_id']; ?>">
                                        <i class="fas fa-flag"></i> Report
                                    </button>
                                    
                                    <?php if (is_logged_in() && $_SESSION['user_id'] == $comment['user_id']): ?>
                                        <button class="comment-action edit-btn">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="comment-action delete-btn">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Recursively display child comments -->
                            <div class="comment-thread">
                                <?php display_comments($comments, $comment['comment_id']); ?>
                            </div>
                        </div>
                        <?php
                    }
                }
            }
            
            // Display top-level comments
            display_comments($comments_array);
            ?>
        </div>
    <?php else: ?>
        <div class="no-comments">
            <div class="empty-state">
                <i class="far fa-comment-dots"></i>
                <h4>No comments yet</h4>
                <p>Be the first to share what you think!</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Report Modal -->
<div id="report-modal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Report Content</h2>
        <form action="report.php" method="post">
            <input type="hidden" id="report-type" name="type" value="">
            <input type="hidden" id="report-id" name="id" value="">
            
            <div class="form-group">
                <label for="report-reason">Reason</label>
                <select class="form-control" id="report-reason" name="reason" required>
                    <option value="">Select a reason</option>
                    <option value="spam">Spam</option>
                    <option value="harassment">Harassment</option>
                    <option value="violence">Violence</option>
                    <option value="misinformation">Misinformation</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="report-details">Details (optional)</label>
                <textarea class="form-control" id="report-details" name="details"></textarea>
            </div>
            
            <button type="submit" class="btn btn-block">Submit Report</button>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>