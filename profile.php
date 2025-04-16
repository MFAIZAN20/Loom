<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering at the very beginning of the file
ob_start();

// Include header (which now includes init.php)
include 'includes/header.php';

// At the top of your file, ensure that functions.php is included
// This might be done through another include like header.php or init.php
// If not, add this near the top of the file:
require_once 'includes/functions.php';

// Near the top of the file, add the badge level definitions
$badge_levels = get_badge_levels();

// Handle post deletion
if (isset($_GET['delete']) && is_logged_in()) {
    $post_id = (int)$_GET['delete'];

    // Verify post belongs to logged-in user
    $check_query = "SELECT * FROM posts WHERE post_id = ? AND user_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    
    if (!$check_stmt) {
        die("Prepare failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($check_stmt, "ii", $post_id, $_SESSION['user_id']);
    
    if (!mysqli_stmt_execute($check_stmt)) {
        die("Execute failed: " . mysqli_stmt_error($check_stmt));
    }
    
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        // Delete the post
        $delete_query = "DELETE FROM posts WHERE post_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        
        if (!$delete_stmt) {
            die("Prepare failed: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($delete_stmt, "i", $post_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $_SESSION['message'] = "Post deleted successfully.";
        } else {
            $_SESSION['message'] = "Error deleting post: " . mysqli_stmt_error($delete_stmt);
        }
        
        mysqli_stmt_close($delete_stmt);
    } else {
        $_SESSION['message'] = "Post not found or you don't have permission to delete it.";
    }

    mysqli_stmt_close($check_stmt);
    
    // Redirect to current profile page
    $redirect_url = "profile.php";
    if (isset($_GET['user'])) {
        $redirect_url .= "?user=" . urlencode($_GET['user']);
    }
    safe_redirect("profile.php");
    exit;
}

// Check if viewing own profile or someone else's
if (isset($_GET['user'])) {
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $_GET['user'])) {
        header("Location: index.php");
        exit;
    }
    
    $username = sanitize_input($_GET['user']);

    $user_query = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $user_query);
    if (!$stmt) {
        die("Database error: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        header("Location: index.php");
        exit;
    }

    $user = mysqli_fetch_assoc($result);
    $page_title = htmlspecialchars($user['username']) . "'s Profile";
    $viewing_own = is_logged_in() && $_SESSION['username'] === $username;
    mysqli_stmt_close($stmt);
} else {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }

    $username = $_SESSION['username'];

    $user_query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $user_query);
    if (!$stmt) {
        die("Database error: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    $page_title = "My Profile";
    $viewing_own = true;
    mysqli_stmt_close($stmt);
}

$profile_image = get_avatar_url($user['profile_picture'] ?? ($user['profile_image'] ?? null));

// Find the user's badge based on karma
$user_badge = get_user_badge($user['karma']);

// Query to get user's posts
$posts_query = "SELECT p.*, COUNT(c.comment_id) as comment_count 
                FROM posts p 
                LEFT JOIN comments c ON p.post_id = c.post_id 
                WHERE p.user_id = ? 
                GROUP BY p.post_id, p.title, p.content, p.user_id, p.category, p.created_at, p.upvotes, p.downvotes
                ORDER BY p.created_at DESC 
                LIMIT 10";
$stmt = mysqli_prepare($conn, $posts_query);
if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $user['user_id']);
mysqli_stmt_execute($stmt);
$posts_result = mysqli_stmt_get_result($stmt);

// Query to get user's comments
$comments_query = "SELECT c.*, p.title as post_title, p.post_id, 
                  (SELECT COUNT(*) FROM votes WHERE comment_id = c.comment_id AND vote_type = 1) as upvotes,
                  (SELECT COUNT(*) FROM votes WHERE comment_id = c.comment_id AND vote_type = -1) as downvotes
                  FROM comments c 
                  JOIN posts p ON c.post_id = p.post_id 
                  WHERE c.user_id = ? 
                  ORDER BY c.created_at DESC 
                  LIMIT 20";
$comments_stmt = mysqli_prepare($conn, $comments_query);
mysqli_stmt_bind_param($comments_stmt, "i", $user['user_id']);
mysqli_stmt_execute($comments_stmt);
$comments_result = mysqli_stmt_get_result($comments_stmt);

// Query to get user's upvoted posts
$upvoted_query = "SELECT p.*, u.username, COUNT(c.comment_id) as comment_count,
                 (SELECT COUNT(*) FROM votes WHERE post_id = p.post_id AND vote_type = 1) as upvotes,
                 (SELECT COUNT(*) FROM votes WHERE post_id = p.post_id AND vote_type = -1) as downvotes
                 FROM votes v
                 JOIN posts p ON v.post_id = p.post_id
                 JOIN users u ON p.user_id = u.user_id
                 LEFT JOIN comments c ON p.post_id = c.post_id
                 WHERE v.user_id = ? AND v.vote_type = 1 AND v.post_id IS NOT NULL
                 GROUP BY p.post_id, p.title, p.content, p.user_id, u.username, p.category, p.created_at
                 ORDER BY v.created_at DESC
                 LIMIT 10";
$upvoted_stmt = mysqli_prepare($conn, $upvoted_query);
mysqli_stmt_bind_param($upvoted_stmt, "i", $user['user_id']);
mysqli_stmt_execute($upvoted_stmt);
$upvoted_result = mysqli_stmt_get_result($upvoted_stmt);
?>

<link rel="stylesheet" href="css/profile.css">

<main class="container">
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <div class="profile-container">
        <!-- Enhanced profile header with gradient background -->
        <div class="profile-header">
            <div class="profile-cover">
                <?php if ($viewing_own): ?>
                    <button class="edit-cover-btn" title="Change cover image">
                        <i class="fas fa-camera"></i>
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="profile-header-content">
                <div class="profile-avatar">
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>">
                    <?php if ($viewing_own): ?>
                        <div class="change-avatar-overlay">
                            <i class="fas fa-camera"></i>
                            <span>Change</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                    <span class="badge user-badge" style="background-color: <?php echo $user_badge['color']; ?>">
                        <?php echo $user_badge['name']; ?>
                    </span>
                    
                    <div class="user-badge-container">
                        <?php if (!empty($user['is_admin']) && $user['is_admin']): ?>
                            <span class="user-badge admin-badge">
                                <i class="fas fa-shield-alt"></i> Admin
                            </span>
                        <?php endif; ?>
                        
                        <span class="user-badge joined-badge">
                            <i class="fas fa-calendar-check"></i> 
                            Joined <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </span>

                        <!-- Last Login Badge -->
                        <?php if (!empty($user['last_login'])): ?>
                        <span class="user-badge last-login-badge">
                            <i class="fas fa-clock"></i>
                            Last seen <?php echo time_elapsed_string($user['last_login']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-icon karma-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?php echo htmlspecialchars($user['karma']); ?></div>
                                <div class="stat-label">Karma</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon posts-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?php echo mysqli_num_rows($posts_result); ?></div>
                                <div class="stat-label">Posts</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon member-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value">
                                    <?php echo time_elapsed_string($user['created_at']); ?>
                                </div>
                                <div class="stat-label">Member</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($viewing_own): ?>
                        <div class="profile-actions">
                            <a href="edit-profile.php" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                            <a href="settings.php" class="btn btn-outline">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Profile navigation tabs -->
        <div class="profile-tabs">
            <a href="#posts" class="tab-item active">
                <i class="fas fa-file-alt"></i> Posts
            </a>
            <a href="#comments" class="tab-item">
                <i class="fas fa-comments"></i> Comments
            </a>
            <a href="#upvoted" class="tab-item">
                <i class="fas fa-arrow-up"></i> Upvoted
            </a>
            <?php if ($viewing_own): ?>
                <a href="#saved" class="tab-item">
                    <i class="fas fa-bookmark"></i> Saved
                </a>
            <?php endif; ?>
        </div>

        <!-- Profile content area -->
        <div id="posts" class="profile-content tab-content active">
            <div class="content-header">
                <h2 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Recent Posts
                </h2>
                
                <?php if ($viewing_own): ?>
                    <a href="create-post.php" class="create-btn">
                        <i class="fas fa-plus"></i>
                        New Post
                    </a>
                <?php endif; ?>
            </div>

            <?php if (mysqli_num_rows($posts_result) > 0): ?>
                <div class="posts-grid">
                    <?php mysqli_data_seek($posts_result, 0); ?>
                    <?php while ($post = mysqli_fetch_assoc($posts_result)): ?>
                        <div class="post-card" data-post-id="<?php echo htmlspecialchars($post['post_id']); ?>">
                            <div class="vote-column">
                                <button class="vote-btn upvote <?php if (isset($_SESSION['vote_post_'.$post['post_id']]) && $_SESSION['vote_post_'.$post['post_id']] == 1) echo 'active'; ?>">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <div class="vote-count"><?php echo (int)($post['upvotes'] - $post['downvotes']); ?></div>
                                <button class="vote-btn downvote <?php if (isset($_SESSION['vote_post_'.$post['post_id']]) && $_SESSION['vote_post_'.$post['post_id']] == -1) echo 'active'; ?>">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                            </div>
                            
                            <div class="post-content">
                                <div class="post-header">
                                    <div class="post-category">
                                        <a href="index.php?category=<?php echo urlencode($post['category']); ?>">
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($post['category']); ?>
                                        </a>
                                    </div>
                                    <div class="post-meta">
                                        <i class="far fa-clock"></i>
                                        <?php echo time_elapsed_string($post['created_at']); ?>
                                    </div>
                                </div>
                                
                                <h3 class="post-title">
                                    <a href="post.php?id=<?php echo htmlspecialchars($post['post_id']); ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </h3>
                                
                                <div class="post-body">
                                    <?php
                                    $content = nl2br(htmlspecialchars($post['content']));
                                    echo (strlen($content) > 150) ? substr($content, 0, 150) . '...' : $content;
                                    ?>
                                </div>
                                
                                <div class="post-footer">
                                    <a href="post.php?id=<?php echo htmlspecialchars($post['post_id']); ?>" class="post-action">
                                        <i class="fas fa-comment"></i> 
                                        <span><?php echo (int)$post['comment_count']; ?> Comments</span>
                                    </a>
                                    
                                    <a href="post.php?id=<?php echo htmlspecialchars($post['post_id']); ?>" class="post-action">
                                        <i class="fas fa-external-link-alt"></i>
                                        <span>View Post</span>
                                    </a>
                                    
                                    <?php if ($viewing_own): ?>
                                        <a href="edit-post.php?id=<?php echo htmlspecialchars($post['post_id']); ?>" class="post-action edit-action">
                                            <i class="fas fa-edit"></i>
                                            <span>Edit</span>
                                        </a>
                                        
                                        <a href="profile.php?<?php echo isset($_GET['user']) ? 'user='.urlencode($_GET['user']).'&' : ''; ?>delete=<?php echo htmlspecialchars($post['post_id']); ?>" 
                                           class="post-action delete-action"
                                           onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.');">
                                            <i class="fas fa-trash-alt"></i>
                                            <span>Delete</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="pagination-container">
                    <a href="#" class="pagination-btn disabled">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <span class="page-indicator">Page 1</span>
                    <a href="#" class="pagination-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
            <?php else: ?>
                <div class="no-posts-message">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>No posts yet</h3>
                        <p>When posts are created, they'll appear here.</p>
                        <?php if ($viewing_own): ?>
                            <a href="create-post.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create your first post
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Hidden tab content (other tabs are not implemented yet) -->
        <div id="comments" class="profile-content tab-content">
            <div class="content-header">
                <h2 class="section-title">
                    <i class="fas fa-comments"></i>
                    My Comments
                </h2>
            </div>
            
            <?php if (mysqli_num_rows($comments_result) > 0): ?>
                <div class="comments-container">
                    <?php while ($comment = mysqli_fetch_assoc($comments_result)): ?>
                        <div class="comment-card" data-comment-id="<?php echo $comment['comment_id']; ?>">
                            <div class="comment-header">
                                <div class="comment-post-title">
                                    On post: <a href="post.php?id=<?php echo htmlspecialchars($comment['post_id']); ?>#comment-<?php echo $comment['comment_id']; ?>">
                                        <?php echo htmlspecialchars($comment['post_title']); ?>
                                    </a>
                                </div>
                                <div class="comment-meta">
                                    <i class="far fa-clock"></i>
                                    <?php echo time_elapsed_string($comment['created_at']); ?>
                                </div>
                            </div>
                            <div class="comment-body">
                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                            </div>
                            <div class="comment-footer">
                                <div class="comment-votes">
                                    <button class="vote-btn vote-up <?php echo (is_logged_in() && get_user_vote($_SESSION['user_id'], null, $comment['comment_id']) == 1) ? 'upvoted' : ''; ?>" data-vote="1">
                                        <i class="fas fa-arrow-up"></i>
                                    </button>
                                    <span class="vote-count"><?php echo $comment['upvotes'] - $comment['downvotes']; ?></span>
                                    <button class="vote-btn vote-down <?php echo (is_logged_in() && get_user_vote($_SESSION['user_id'], null, $comment['comment_id']) == -1) ? 'downvoted' : ''; ?>" data-vote="-1">
                                        <i class="fas fa-arrow-down"></i>
                                    </button>
                                </div>
                                <?php if ($viewing_own): ?>
                                    <div class="comment-actions">
                                        <a href="post.php?id=<?php echo htmlspecialchars($comment['post_id']); ?>#comment-<?php echo $comment['comment_id']; ?>" class="comment-action">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </a>
                                        <button class="comment-action delete-btn" data-id="<?php echo $comment['comment_id']; ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-comments-message">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3>No comments yet</h3>
                        <p>When you comment on posts, they'll appear here.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="upvoted" class="profile-content tab-content">
            <div class="content-header">
                <h2 class="section-title">
                    <i class="fas fa-arrow-up"></i>
                    Upvoted Posts
                </h2>
            </div>
            
            <?php if (mysqli_num_rows($upvoted_result) > 0): ?>
                <div class="posts-grid">
                    <?php while ($post = mysqli_fetch_assoc($upvoted_result)): ?>
                        <div class="post-card" data-post-id="<?php echo htmlspecialchars($post['post_id']); ?>">
                            <div class="vote-column">
                                <button class="vote-btn vote-up upvoted" data-vote="1">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <div class="vote-count"><?php echo (int)($post['upvotes'] - $post['downvotes']); ?></div>
                                <button class="vote-btn vote-down" data-vote="-1">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                            </div>
                            
                            <div class="post-content">
                                <div class="post-header">
                                    <div class="post-category">
                                        <a href="index.php?category=<?php echo urlencode($post['category']); ?>">
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($post['category']); ?>
                                        </a>
                                    </div>
                                    <div class="post-meta">
                                        Posted by <a href="profile.php?user=<?php echo urlencode($post['username']); ?>">
                                            <?php echo htmlspecialchars($post['username']); ?>
                                        </a>
                                        <i class="far fa-clock"></i>
                                        <?php echo time_elapsed_string($post['created_at']); ?>
                                    </div>
                                </div>
                                
                                <h3 class="post-title">
                                    <a href="post.php?id=<?php echo htmlspecialchars($post['post_id']); ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </h3>
                                
                                <div class="post-body">
                                    <?php
                                    $content = nl2br(htmlspecialchars($post['content']));
                                    echo (strlen($content) > 150) ? substr($content, 0, 150) . '...' : $content;
                                    ?>
                                </div>
                                
                                <div class="post-footer">
                                    <a href="post.php?id=<?php echo htmlspecialchars($post['post_id']); ?>" class="post-action">
                                        <i class="fas fa-comment"></i> 
                                        <span><?php echo (int)$post['comment_count']; ?> Comments</span>
                                    </a>
                                    
                                    <a href="post.php?id=<?php echo htmlspecialchars($post['post_id']); ?>" class="post-action">
                                        <i class="fas fa-external-link-alt"></i>
                                        <span>View Post</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-upvoted-message">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <h3>No upvoted posts yet</h3>
                        <p>The posts you upvote will appear here.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($viewing_own): ?>
        <div id="saved" class="profile-content tab-content">
            <!-- Saved posts will be loaded here -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-bookmark"></i>
                </div>
                <h3>Saved Posts</h3>
                <p>This feature is coming soon!</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Profile tab navigation
document.addEventListener('DOMContentLoaded', function() {
    const tabItems = document.querySelectorAll('.tab-item');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabItems.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            tabItems.forEach(item => item.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to current tab
            this.classList.add('active');
            
            // Show corresponding content
            const target = this.getAttribute('href').substring(1);
            document.getElementById(target).classList.add('active');
        });
    });
    
    // Post menu toggle
    document.querySelectorAll('.post-menu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const menu = this.nextElementSibling;
            document.querySelectorAll('.post-menu').forEach(m => {
                if (m !== menu) m.classList.remove('active');
            });
            menu.classList.toggle('active');
        });
    });
    
    // Close post menus when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.post-management')) {
            document.querySelectorAll('.post-menu').forEach(menu => {
                menu.classList.remove('active');
            });
        }
    });
    
    // Handle vote buttons for comments
    const commentVoteButtons = document.querySelectorAll('.comment-votes .vote-btn');
    commentVoteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const commentCard = this.closest('.comment-card');
            const commentId = commentCard.dataset.commentId;
            const voteType = this.dataset.vote;
            const voteCount = commentCard.querySelector('.vote-count');
            const upvoteBtn = commentCard.querySelector('.vote-up');
            const downvoteBtn = commentCard.querySelector('.vote-down');
            
            // Call AJAX to vote on comment
            fetch('ajax/vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `comment_id=${commentId}&vote_type=${voteType}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update vote count
                    voteCount.textContent = data.vote_count || data.voteCount;
                    
                    // Update button states
                    upvoteBtn.classList.remove('upvoted');
                    downvoteBtn.classList.remove('downvoted');
                    
                    if (data.user_vote === 1 || data.newVoteType === 1) {
                        upvoteBtn.classList.add('upvoted');
                    } else if (data.user_vote === -1 || data.newVoteType === -1) {
                        downvoteBtn.classList.add('downvoted');
                    }
                    
                    showNotification('Vote recorded successfully', 'success');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
    
    // Handle delete buttons for comments
    const deleteButtons = document.querySelectorAll('.comment-action.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
                const commentId = this.dataset.id;
                
                fetch('ajax/delete_comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `comment_id=${commentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove comment from DOM with animation
                        const commentCard = this.closest('.comment-card');
                        commentCard.style.opacity = '0';
                        
                        setTimeout(() => {
                            commentCard.remove();
                            showNotification('Comment deleted successfully', 'success');
                        }, 300);
                    } else {
                        showNotification(data.message || 'Failed to delete comment', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred', 'error');
                });
            }
        });
    });
    
    // Post vote buttons in upvoted tab
    const postVoteButtons = document.querySelectorAll('.post-card .vote-btn');
    postVoteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const postCard = this.closest('.post-card');
            const postId = postCard.dataset.postId;
            const voteType = this.dataset.vote;
            const voteCount = postCard.querySelector('.vote-count');
            const upvoteBtn = postCard.querySelector('.vote-up');
            const downvoteBtn = postCard.querySelector('.vote-down');
            
            // Call AJAX to vote on post
            fetch('ajax/vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}&vote_type=${voteType}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update vote count
                    voteCount.textContent = data.vote_count || data.voteCount;
                    
                    // Update button states
                    upvoteBtn.classList.remove('upvoted');
                    downvoteBtn.classList.remove('downvoted');
                    
                    if (data.user_vote === 1 || data.newVoteType === 1) {
                        upvoteBtn.classList.add('upvoted');
                    } else if (data.user_vote === -1 || data.newVoteType === -1) {
                        downvoteBtn.classList.add('downvoted');
                    }
                    
                    showNotification('Vote recorded successfully', 'success');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
    
    // Simple notification function
    window.showNotification = function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateY(0)';
            notification.style.opacity = '1';
        }, 10);
        
        // Remove after a delay
        setTimeout(() => {
            notification.style.transform = 'translateY(-10px)';
            notification.style.opacity = '0';
            
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    };
});
</script>

<?php 
include 'includes/footer.php';

// Flush the output buffer at the end
ob_end_flush();
?>
