<?php
$page_title = "Home";
include 'includes/header.php';

// Get posts with pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$limit = 10;
$offset = ($page - 1) * $limit;

$category = isset($_GET['category']) ? sanitize_input($_GET['category']) : null;
$tag = isset($_GET['tag']) ? sanitize_input($_GET['tag']) : null;

// Build query based on filters
$query = "SELECT p.*, u.username, COUNT(c.comment_id) as comment_count 
          FROM posts p 
          LEFT JOIN users u ON p.user_id = u.user_id 
          LEFT JOIN comments c ON p.post_id = c.post_id";

$params = [];
$types = "";

if ($category) {
    $query .= " WHERE p.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($tag) {
    if ($category) {
        $query .= " AND p.post_id IN (
                    SELECT pt.post_id FROM post_tags pt 
                    JOIN tags t ON pt.tag_id = t.tag_id 
                    WHERE t.name = ?)";
    } else {
        $query .= " WHERE p.post_id IN (
                    SELECT pt.post_id FROM post_tags pt 
                    JOIN tags t ON pt.tag_id = t.tag_id 
                    WHERE t.name = ?)";
    }
    $params[] = $tag;
    $types .= "s";
}

$query .= " GROUP BY p.post_id ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $query);

if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<link rel="stylesheet" href="css/post-list.css">

<div class="container">
    <div class="content-area">
        <?php if (is_logged_in()): ?>
            <div class="create-post-btn">
                <a href="create-post.php" class="btn">
                    <i class="fas fa-plus-circle"></i> Create New Post
                </a>
            </div>
        <?php endif; ?>

        <?php if ($category || $tag): ?>
            <div class="filter-info">
                <div>
                    <?php if ($category): ?>
                        <p>Browsing Category: <strong><?php echo htmlspecialchars($category); ?></strong></p>
                    <?php endif; ?>

                    <?php if ($tag): ?>
                        <p>Posts tagged: <strong><?php echo htmlspecialchars($tag); ?></strong></p>
                    <?php endif; ?>
                </div>
                <a href="index.php">Clear Filters</a>
            </div>
        <?php endif; ?>

        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($post = mysqli_fetch_assoc($result)): ?>
                <div class="post-card" data-post-id="<?php echo $post['post_id']; ?>">
                    <div class="vote-column">
                        <button
                            class="vote-btn vote-up <?php echo (is_logged_in() && get_user_vote($_SESSION['user_id'], $post['post_id']) == 1) ? 'upvoted' : ''; ?>"
                            data-vote="1">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <div class="vote-count"><?php echo $post['upvotes'] - $post['downvotes']; ?></div>
                        <button
                            class="vote-btn vote-down <?php echo (is_logged_in() && get_user_vote($_SESSION['user_id'], $post['post_id']) == -1) ? 'downvoted' : ''; ?>"
                            data-vote="-1">
                            <i class="fas fa-arrow-down"></i>
                        </button>
                    </div>
                    <div class="post-content">
                        <div class="post-header">
                            <div class="post-category">
                                <a href="index.php?category=<?php echo urlencode($post['category']); ?>">
                                    <?php echo htmlspecialchars($post['category']); ?>
                                </a>
                            </div>
                            <div class="post-meta">
                                Posted by <a href="profile.php?user=<?php echo urlencode($post['username']); ?>">
                                    <?php echo htmlspecialchars($post['username']); ?>
                                </a>
                                <span class="post-date">
                                    <i class="far fa-clock"></i> <?php echo time_elapsed_string($post['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        <h2 class="post-title">
                            <a href="post.php?id=<?php echo $post['post_id']; ?>">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </h2>
                        <div class="post-body">
                            <?php
                            $content = htmlspecialchars($post['content']);
                            echo (strlen($content) > 200) ? substr($content, 0, 200) . '...' : $content;
                            ?>
                        </div>
                        <div class="post-footer">
                            <div class="post-actions-left">
                                <a href="post.php?id=<?php echo $post['post_id']; ?>" class="post-action">
                                    <i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?> Comments
                                </a>
                                
                                <div class="post-action report-btn" data-type="post" data-id="<?php echo $post['post_id']; ?>">
                                    <i class="fas fa-flag"></i> Report
                                </div>
                            </div>

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
            <?php endwhile; ?>

            <!-- Pagination -->
            <?php
            // Count total posts for pagination
            $count_query = "SELECT COUNT(*) as total FROM posts";
            $count_params = [];
            $count_types = "";

            if ($category) {
                $count_query .= " WHERE category = ?";
                $count_params[] = $category;
                $count_types .= "s";
            }

            if ($tag) {
                if ($category) {
                    $count_query .= " AND post_id IN (
                                    SELECT pt.post_id FROM post_tags pt 
                                    JOIN tags t ON pt.tag_id = t.tag_id 
                                    WHERE t.name = ?)";
                } else {
                    $count_query .= " WHERE post_id IN (
                                    SELECT pt.post_id FROM post_tags pt 
                                    JOIN tags t ON pt.tag_id = t.tag_id 
                                    WHERE t.name = ?)";
                }
                $count_params[] = $tag;
                $count_types .= "s";
            }

            $count_stmt = mysqli_prepare($conn, $count_query);

            if ($count_params) {
                mysqli_stmt_bind_param($count_stmt, $count_types, ...$count_params);
            }

            mysqli_stmt_execute($count_stmt);
            $count_result = mysqli_stmt_get_result($count_stmt);
            $count_row = mysqli_fetch_assoc($count_result);
            $total_posts = $count_row['total'];

            $total_pages = ceil($total_posts / $limit);

            if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $url_params = '';
                    if ($category)
                        $url_params .= '&category=' . urlencode($category);
                    if ($tag)
                        $url_params .= '&tag=' . urlencode($tag);

                    // Previous button
                    if ($page > 1): ?>
                        <a href="index.php?page=<?php echo $page - 1; ?><?php echo $url_params; ?>" class="btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <!-- Page numbers -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="index.php?page=<?php echo $i; ?><?php echo $url_params; ?>" 
                           class="btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next button -->
                    <?php if ($page < $total_pages): ?>
                        <a href="index.php?page=<?php echo $page + 1; ?><?php echo $url_params; ?>" class="btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="no-posts-message">
                <p>No posts found. Be the first to create a post!</p>
                <?php if (is_logged_in()): ?>
                    <a href="create-post.php" class="btn">Create Post</a>
                <?php else: ?>
                    <a href="login.php" class="btn">Login to create a post</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Vote buttons interaction
    const voteBtns = document.querySelectorAll('.vote-btn');
    
    voteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const postCard = this.closest('.post-card');
            const postId = postCard.getAttribute('data-post-id');
            const voteValue = this.getAttribute('data-vote');
            
            // Check if user is logged in
            const isLoggedIn = document.body.classList.contains('logged-in');
            
            if (!isLoggedIn) {
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname);
                return;
            }
            
            // Send vote request
            fetch('vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}&vote=${voteValue}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update vote count
                    postCard.querySelector('.vote-count').innerText = data.votes;
                    
                    // Update button states
                    const upvoteBtn = postCard.querySelector('.vote-up');
                    const downvoteBtn = postCard.querySelector('.vote-down');
                    
                    upvoteBtn.classList.toggle('upvoted', data.user_vote === 1);
                    downvoteBtn.classList.toggle('downvoted', data.user_vote === -1);
                } else {
                    alert(data.message || 'Error processing vote.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
    
    // Report modal functionality
    const modal = document.getElementById('report-modal');
    const reportBtns = document.querySelectorAll('.report-btn');
    const closeBtn = document.querySelector('.close-modal');
    
    reportBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const contentType = this.getAttribute('data-type');
            const contentId = this.getAttribute('data-id');
            
            // Check if user is logged in
            const isLoggedIn = document.body.classList.contains('logged-in');
            
            if (!isLoggedIn) {
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname);
                return;
            }
            
            // Set form values
            document.getElementById('report-type').value = contentType;
            document.getElementById('report-id').value = contentId;
            
            // Show modal
            modal.style.display = 'block';
        });
    });
    
    // Close modal
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>