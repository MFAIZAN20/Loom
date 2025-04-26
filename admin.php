<?php
include 'includes/db_connect.php';
include 'includes/functions.php';
include 'includes/auth_functions.php';

$page_title = "Admin Dashboard";
include 'includes/header.php';

if (!is_logged_in() || !is_admin()) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = $error = '';

if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    if ($delete_id != $user_id) {
        // First delete related records from notification_preferences table
        $delete_notifications_query = "DELETE FROM notification_preferences WHERE user_id = ?";
        $stmt_notifications = mysqli_prepare($conn, $delete_notifications_query);
        mysqli_stmt_bind_param($stmt_notifications, "i", $delete_id);
        mysqli_stmt_execute($stmt_notifications);
        
        // Delete notifications where the user is the recipient
        $delete_user_notifications_query = "DELETE FROM notifications WHERE user_id = ?";
        $stmt_user_notifications = mysqli_prepare($conn, $delete_user_notifications_query);
        mysqli_stmt_bind_param($stmt_user_notifications, "i", $delete_id);
        mysqli_stmt_execute($stmt_user_notifications);
        
        // Delete notifications where the user is the actor
        $delete_actor_notifications_query = "DELETE FROM notifications WHERE actor_id = ?";
        $stmt_actor_notifications = mysqli_prepare($conn, $delete_actor_notifications_query);
        mysqli_stmt_bind_param($stmt_actor_notifications, "i", $delete_id);
        mysqli_stmt_execute($stmt_actor_notifications);
        
        // Delete votes cast by the user
        $delete_votes_query = "DELETE FROM votes WHERE user_id = ?";
        $stmt_votes = mysqli_prepare($conn, $delete_votes_query);
        mysqli_stmt_bind_param($stmt_votes, "i", $delete_id);
        mysqli_stmt_execute($stmt_votes);
        
        // Then delete user
        $delete_query = "DELETE FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "User deleted successfully.";
        } else {
            $error = "Failed to delete user: " . mysqli_error($conn);
        }
    }
}

if (isset($_GET['ban_id']) && is_numeric($_GET['ban_id'])) {
    $ban_id = (int)$_GET['ban_id'];
    $reason = sanitize_input($_GET['reason'] ?? "Violation of community guidelines");
    
    if ($ban_id != $user_id) {
        $ban_query = "UPDATE users SET is_banned = 1, ban_reason = ?, banned_at = NOW() WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $ban_query);
        mysqli_stmt_bind_param($stmt, "si", $reason, $ban_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "User banned successfully.";
        } else {
            $error = "Failed to ban user: " . mysqli_error($conn);
        }
    } else {
        $error = "You cannot ban yourself.";
    }
}

if (isset($_GET['unban_id']) && is_numeric($_GET['unban_id'])) {
    $unban_id = (int)$_GET['unban_id'];
    
    $unban_query = "UPDATE users SET is_banned = 0, ban_reason = NULL, banned_at = NULL WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $unban_query);
    mysqli_stmt_bind_param($stmt, "i", $unban_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "User unbanned successfully.";
    } else {
        $error = "Failed to unban user: " . mysqli_error($conn);
    }
}

if (isset($_GET['resolve_report']) && is_numeric($_GET['resolve_report'])) {
    $report_id = (int)$_GET['resolve_report'];
    
    $update_query = "UPDATE reports SET resolved = 1 WHERE report_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Report marked as resolved.";
    } else {
        $error = "Failed to update report: " . mysqli_error($conn);
    }
}

if (isset($_GET['delete_content']) && isset($_GET['type']) && isset($_GET['report_id'])) {
    $content_id = (int)$_GET['delete_content'];
    $content_type = $_GET['type'];
    $report_id = (int)$_GET['report_id'];
    
    if ($content_type === 'post') {
        $user_query = "SELECT user_id FROM posts WHERE post_id = ?";
    } else if ($content_type === 'comment') {
        $user_query = "SELECT user_id FROM comments WHERE comment_id = ?";
    } else {
        $error = "Invalid content type";
    }
    
    if (!isset($error)) {
        $user_stmt = mysqli_prepare($conn, $user_query);
        mysqli_stmt_bind_param($user_stmt, "i", $content_id);
        mysqli_stmt_execute($user_stmt);
        mysqli_stmt_bind_result($user_stmt, $content_user_id);
        mysqli_stmt_fetch($user_stmt);
        mysqli_stmt_close($user_stmt);
        
        if ($content_type === 'post') {
            $delete_query = "DELETE FROM posts WHERE post_id = ?";
        } else if ($content_type === 'comment') {
            // For comments, we need to handle foreign key constraints with reports
            // First update reports to mark them as resolved
            $update_reports = "UPDATE reports SET resolved = 1 WHERE comment_id = ?";
            $reports_stmt = mysqli_prepare($conn, $update_reports);
            mysqli_stmt_bind_param($reports_stmt, "i", $content_id);
            if (!mysqli_stmt_execute($reports_stmt)) {
                $error = "Failed to update reports: " . mysqli_error($conn);
            }
            
            // Then delete the comment
            $delete_query = "DELETE FROM comments WHERE comment_id = ?";
        }
        
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $content_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $update_reports = "";
            if ($content_type === 'post') {
                $update_reports = "UPDATE reports SET resolved = 1 WHERE post_id = ?";
            } else {
                $update_reports = "UPDATE reports SET resolved = 1 WHERE comment_id = ?";
            }
            
            $stmt = mysqli_prepare($conn, $update_reports);
            mysqli_stmt_bind_param($stmt, "i", $content_id);
            mysqli_stmt_execute($stmt);
            
            $message = ucfirst($content_type) . " deleted and reports resolved.";
            
            if (isset($_GET['ban_user']) && $_GET['ban_user'] == 1 && $content_user_id != $user_id) {
                $ban_reason = sanitize_input($_GET['ban_reason'] ?? "Posting inappropriate content");
                $ban_query = "UPDATE users SET is_banned = 1, ban_reason = ?, banned_at = NOW() WHERE user_id = ?";
                $ban_stmt = mysqli_prepare($conn, $ban_query);
                mysqli_stmt_bind_param($ban_stmt, "si", $ban_reason, $content_user_id);
                
                if (mysqli_stmt_execute($ban_stmt)) {
                    $message .= " User has been banned.";
                }
            }
        } else {
            $error = "Failed to delete content: " . mysqli_error($conn);
        }
    }
}

$users_query = "SELECT * FROM users ORDER BY is_banned DESC, created_at DESC";
$users_result = mysqli_query($conn, $users_query);

$reports_query = "SELECT r.*, 
                  u.username AS reporter_username,
                  ru.user_id AS reported_user_id,
                  ru.username AS reported_username,
                  ru.is_banned AS is_user_banned,
                  p.title AS post_title, p.content AS post_content, pu.username AS post_author,
                  c.content AS comment_content, cu.username AS comment_author
                  FROM reports r
                  LEFT JOIN users u ON r.user_id = u.user_id
                  LEFT JOIN posts p ON r.post_id = p.post_id
                  LEFT JOIN users pu ON p.user_id = pu.user_id
                  LEFT JOIN comments c ON r.comment_id = c.comment_id
                  LEFT JOIN users cu ON c.user_id = cu.user_id
                  LEFT JOIN users ru ON (p.user_id = ru.user_id OR c.user_id = ru.user_id)
                  WHERE r.resolved = 0
                  ORDER BY r.created_at DESC";
$reports_result = mysqli_query($conn, $reports_query);
?>

<div class="admin-dashboard">
    <div class="admin-header">
        <h1><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
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
    </div>
    
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-flag"></i></div>
            <div class="stat-content">
                <h3>Reports</h3>
                <p class="stat-number"><?php echo mysqli_num_rows($reports_result); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <h3>Users</h3>
                <p class="stat-number"><?php echo mysqli_num_rows($users_result); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-ban"></i></div>
            <div class="stat-content">
                <h3>Banned Users</h3>
                <?php
                $banned_count = 0;
                mysqli_data_seek($users_result, 0);
                while ($u = mysqli_fetch_assoc($users_result)) {
                    if ($u['is_banned']) $banned_count++;
                }
                mysqli_data_seek($users_result, 0);
                ?>
                <p class="stat-number"><?php echo $banned_count; ?></p>
            </div>
        </div>
    </div>
    
    <div class="admin-tabs">
        <button class="tab-btn active" onclick="openTab('reported-content')">
            <i class="fas fa-flag"></i> Reported Content 
            <span class="badge"><?php echo mysqli_num_rows($reports_result); ?></span>
        </button>
        <button class="tab-btn" onclick="openTab('user-management')">
            <i class="fas fa-users"></i> User Management
        </button>
    </div>
    
    <div id="reported-content" class="tab-content active">
        <div class="admin-section">
            <div class="section-header">
                <h2>Reported Content</h2>
                <div class="section-actions">
                    <div class="search-box">
                        <input type="text" id="reportSearch" placeholder="Search reports..." onkeyup="filterReports()">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
            </div>
            
            <?php if (mysqli_num_rows($reports_result) > 0): ?>
                <div class="report-cards">
                    <?php while ($report = mysqli_fetch_assoc($reports_result)): ?>
                        <div class="report-card">
                            <div class="report-header">
                                <div>
                                    <?php if ($report['post_id']): ?>
                                        <span class="badge badge-primary">Post</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Comment</span>
                                    <?php endif; ?>
                                    <span class="report-time"><?php echo time_elapsed_string($report['created_at']); ?></span>
                                </div>
                                <div class="report-reason">
                                    <span class="reason-label"><?php echo ucfirst(htmlspecialchars($report['reason'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="report-content">
                                <?php if ($report['post_id']): ?>
                                    <h3 class="content-title"><?php echo htmlspecialchars($report['post_title']); ?></h3>
                                <?php endif; ?>
                                
                                <div class="content-preview">
                                    <?php 
                                    $content = $report['post_id'] ? $report['post_content'] : $report['comment_content'];
                                    echo htmlspecialchars(substr($content, 0, 150)) . (strlen($content) > 150 ? '...' : '');
                                    ?>
                                </div>
                                
                                <?php if ($report['details']): ?>
                                    <div class="report-details">
                                        <p><strong>Reporter's note:</strong> <?php echo htmlspecialchars($report['details']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="report-footer">
                                <div class="author-info">
                                    <div class="author-avatar">
                                        <img src="<?php echo htmlspecialchars(get_avatar_url($report['profile_image'])); ?>" alt="Profile">
                                    </div>
                                    <div>
                                        <div class="author-name">
                                            <?php echo $report['post_id'] ? htmlspecialchars($report['post_author']) : htmlspecialchars($report['comment_author']); ?>
                                            <?php if ($report['is_user_banned']): ?>
                                                <span class="banned-badge">Banned</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="reporter-name">
                                            Reported by: <?php echo htmlspecialchars($report['reporter_username']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="report-actions">
                                    <?php if ($report['post_id']): ?>
                                        <a href="post.php?id=<?php echo $report['post_id']; ?>" target="_blank" class="action-btn view-btn">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </a>
                                    <?php else: ?>
                                        <a href="post.php?id=<?php echo $report['post_id']; ?>#comment-<?php echo $report['comment_id']; ?>" target="_blank" class="action-btn view-btn">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$report['is_user_banned']): ?>
                                        <?php if ($report['post_id']): ?>
                                            <button class="action-btn delete-btn" onclick="showBanModal(
                                                '<?php echo $report['post_id']; ?>', 
                                                'post', 
                                                '<?php echo $report['report_id']; ?>', 
                                                '<?php echo $report['reported_user_id']; ?>', 
                                                '<?php echo htmlspecialchars($report['post_author']); ?>'
                                            )">
                                                <i class="fas fa-trash"></i> Delete & Review
                                            </button>
                                        <?php elseif ($report['comment_id']): ?>
                                            <button class="action-btn delete-btn" onclick="showBanModal(
                                                '<?php echo $report['comment_id']; ?>', 
                                                'comment', 
                                                '<?php echo $report['report_id']; ?>', 
                                                '<?php echo $report['reported_user_id']; ?>', 
                                                '<?php echo htmlspecialchars($report['comment_author']); ?>'
                                            )">
                                                <i class="fas fa-trash"></i> Delete & Review
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($report['post_id']): ?>
                                            <a href="?delete_content=<?php echo $report['post_id']; ?>&type=post&report_id=<?php echo $report['report_id']; ?>" 
                                            class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this post? The user is already banned.');">
                                                <i class="fas fa-trash"></i> Delete Post
                                            </a>
                                        <?php elseif ($report['comment_id']): ?>
                                            <a href="?delete_content=<?php echo $report['comment_id']; ?>&type=comment&report_id=<?php echo $report['report_id']; ?>" 
                                            class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this comment? The user is already banned.');">
                                                <i class="fas fa-trash"></i> Delete Comment
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <a href="?resolve_report=<?php echo $report['report_id']; ?>" 
                                       class="action-btn resolve-btn" onclick="return confirm('Mark this report as resolved without taking action?');">
                                        <i class="fas fa-check"></i> Dismiss
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>All Clear!</h3>
                    <p>There are no reports to review at this time.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="user-management" class="tab-content">
        <div class="admin-section">
            <div class="section-header">
                <h2>User Management</h2>
                <div class="section-actions">
                    <div class="search-box">
                        <input type="text" id="userSearch" placeholder="Search users..." onkeyup="filterUsers()">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <select id="statusFilter" onchange="filterUsers()">
                            <option value="all">All Users</option>
                            <option value="active">Active Only</option>
                            <option value="banned">Banned Only</option>
                            <option value="admin">Admins Only</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="user-cards">
                <?php mysqli_data_seek($users_result, 0); ?>
                <?php while ($u = mysqli_fetch_assoc($users_result)): ?>
                    <div class="user-card <?php echo $u['is_banned'] ? 'banned-user' : ''; ?> <?php echo $u['is_admin'] ? 'admin-user' : ''; ?>" data-username="<?php echo strtolower($u['username']); ?>" data-email="<?php echo strtolower($u['email']); ?>">
                        <div class="user-avatar">
                            <img src="<?php echo htmlspecialchars(get_avatar_url($u['profile_image'])); ?>" alt="<?php echo htmlspecialchars($u['username']); ?>">
                            <?php if ($u['is_admin']): ?>
                                <span class="admin-badge" title="Administrator"><i class="fas fa-star"></i></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="user-details">
                            <h3>
                                <a href="profile.php?user=<?php echo urlencode($u['username']); ?>">
                                    <?php echo htmlspecialchars($u['username']); ?>
                                </a>
                            </h3>
                            
                            <div class="user-meta">
                                <div class="user-email"><?php echo htmlspecialchars($u['email']); ?></div>
                                <div class="user-joined">Joined <?php echo time_elapsed_string($u['created_at']); ?></div>
                            </div>
                            
                            <div class="user-stats">
                                <div class="karma">
                                    <i class="fas fa-bolt"></i>
                                    <span><?php echo $u['karma']; ?> karma</span>
                                </div>
                                <div class="user-id">
                                    <i class="fas fa-id-badge"></i>
                                    <span>ID: <?php echo $u['user_id']; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($u['is_banned']): ?>
                            <div class="ban-info">
                                <div class="ban-status">
                                    <i class="fas fa-ban"></i>
                                    <span>Banned</span>
                                </div>
                                <?php if ($u['ban_reason']): ?>
                                <div class="ban-reason">
                                    "<?php echo htmlspecialchars($u['ban_reason']); ?>"
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="user-actions">
                            <?php if ($u['user_id'] != $user_id): ?>
                                <?php if (!$u['is_banned']): ?>
                                    <button class="action-btn ban-btn" onclick="showUserBanModal(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')">
                                        <i class="fas fa-ban"></i> Ban
                                    </button>
                                <?php else: ?>
                                    <a href="?unban_id=<?php echo $u['user_id']; ?>" class="action-btn unban-btn" onclick="return confirm('Unban this user?')">
                                        <i class="fas fa-undo"></i> Unban
                                    </a>
                                <?php endif; ?>
                                <a href="?delete_id=<?php echo $u['user_id']; ?>" class="action-btn delete-user-btn" onclick="return confirm('Delete this user? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            <?php else: ?>
                                <span class="current-user-badge">
                                    <i class="fas fa-user-check"></i> This is you
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<div id="banUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-ban"></i> Ban User</h2>
            <span class="close-modal">&times;</span>
        </div>
        
        <div class="modal-body">
            <div class="ban-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p>You are about to ban user <strong id="banUsername"></strong>. They will no longer be able to log in or participate in the community.</p>
            </div>
            
            <form id="banUserForm" action="">
                <input type="hidden" id="banUserId" name="ban_id">
                <div class="form-group">
                    <label for="banReason">Reason for ban:</label>
                    <textarea id="banReason" name="reason" class="form-control" placeholder="Explain why this user is being banned..." required></textarea>
                    <div class="form-help">This reason will be shown to the user when they attempt to log in.</div>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeBanModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="confirm-btn">
                        <i class="fas fa-check"></i> Ban User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="contentBanModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Reported Content Review</h2>
            <span class="close-modal">&times;</span>
        </div>
        
        <div class="modal-body">
            <div class="content-author">
                <p>You are reviewing content from user <strong id="contentAuthor"></strong></p>
            </div>
            
            <form id="contentBanForm" action="">
                <input type="hidden" id="contentId" name="delete_content">
                <input type="hidden" id="contentType" name="type">
                <input type="hidden" id="reportId" name="report_id">
                <input type="hidden" id="contentUserId" name="content_user_id">
                
                <div class="action-choice">
                    <div class="choice-option">
                        <input type="radio" id="deleteOnly" name="action_choice" value="delete_only" checked>
                        <label for="deleteOnly">
                            <div class="choice-icon"><i class="fas fa-trash"></i></div>
                            <div class="choice-text">
                                <span class="choice-title">Delete content only</span>
                                <span class="choice-desc">Remove this content but allow the user to continue using the platform</span>
                            </div>
                        </label>
                    </div>
                    
                    <div class="choice-option">
                        <input type="radio" id="deleteAndBan" name="action_choice" value="delete_and_ban">
                        <label for="deleteAndBan">
                            <div class="choice-icon"><i class="fas fa-ban"></i></div>
                            <div class="choice-text">
                                <span class="choice-title">Delete and ban user</span>
                                <span class="choice-desc">Remove this content and ban the user from the platform</span>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div id="banReasonContainer" class="form-group" style="display: none;">
                    <label for="contentBanReason">Reason for ban:</label>
                    <textarea id="contentBanReason" name="ban_reason" class="form-control" placeholder="Explain why this user is being banned..."></textarea>
                    <div class="form-help">This reason will be shown to the user when they attempt to log in.</div>
                    <input type="hidden" id="banUserCheckbox" name="ban_user" value="0">
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeContentBanModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="confirm-btn">
                        <i class="fas fa-check"></i> Confirm Action
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.admin-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
    color: #333;
}

/* Header Styling */
.admin-header {
    margin-bottom: 2rem;
    position: relative;
}

.admin-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

.admin-header h1 i {
    color: #3498db;
}

/* Alert Styling */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

/* Admin Stats Cards */
.admin-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, #f5f7fa, #ffffff);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    margin-right: 1rem;
    font-size: 1.5rem;
    background: #e8f0fe;
    color: #3498db;
}

.stat-content h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #5c6f7f;
}

.stat-number {
    font-size: 1.75rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0.25rem 0 0;
}

/* Tab Navigation */
.admin-tabs {
    display: flex;
    border-bottom: 1px solid #e0e0e0;
    margin-bottom: 1.5rem;
    overflow-x: auto;
    scrollbar-width: thin;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: #7f8c8d;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.tab-btn:hover {
    color: #3498db;
}

.tab-btn.active {
    color: #3498db;
    border-bottom-color: #3498db;
}

.tab-btn .badge {
    font-size: 0.8rem;
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
}

.section-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-box {
    position: relative;
}

.search-box input {
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 1px solid #e0e0e0;
    border-radius: 50px;
    font-size: 0.9rem;
    width: 250px;
    transition: all 0.2s;
}

.search-box input:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    outline: none;
}

.search-box i {
    position: absolute;
    left: 0.8rem;
    top: 50%;
    transform: translateY(-50%);
    color: #95a5a6;
}

.filter-options select {
    padding: 0.6rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 50px;
    font-size: 0.9rem;
    background-color: white;
    cursor: pointer;
}

.filter-options select:focus {
    border-color: #3498db;
    outline: none;
}

.admin-section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    text-align: center;
}

.empty-icon {
    font-size: 4rem;
    color: #2ecc71;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    color: #2c3e50;
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    color: #7f8c8d;
    font-size: 1.1rem;
    max-width: 400px;
    margin: 0;
}

.report-cards {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

.report-card {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #f0f0f0;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.report-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.report-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.report-time {
    font-size: 0.85rem;
    color: #95a5a6;
    margin-left: 0.75rem;
}

.report-reason {
    font-weight: 500;
}

.reason-label {
    background-color: #ff6b6b;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
}

.report-content {
    padding: 1.5rem;
    border-bottom: 1px solid #f0f0f0;
}

.content-title {
    margin: 0 0 0.75rem 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.content-preview {
    padding: 1rem;
    background-color: #f9f9f9;
    border-radius: 8px;
    font-size: 0.95rem;
    color: #444;
    line-height: 1.5;
    margin-bottom: 1rem;
    border-left: 3px solid #e0e0e0;
}

.report-details {
    font-style: italic;
    color: #777;
    font-size: 0.9rem;
    background-color: #fff5e6;
    padding: 0.75rem;
    border-radius: 8px;
    margin-top: 1rem;
    border-left: 3px solid #f39c12;
}

.report-details p {
    margin: 0;
}

.report-footer {
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #fcfcfc;
}

.author-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.author-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
}

.author-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.author-name {
    font-weight: 500;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.reporter-name {
    font-size: 0.8rem;
    color: #95a5a6;
    margin-top: 0.2rem;
}

.report-actions {
    display: flex;
    gap: 0.5rem;
}

/* User Cards */
.user-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.user-card {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #f0f0f0;
    padding: 1.5rem;
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s;
}

.user-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.banned-user {
    background-color: #fff5f5;
    border-color: #ffcccc;
}

.admin-user::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3498db, #2980b9);
    border-radius: 12px 12px 0 0;
}

.user-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    overflow: hidden;
    margin-bottom: 1.25rem;
    position: relative;
    border: 3px solid #f5f5f5;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.admin-badge {
    position: absolute;
    bottom: 0;
    right: 0;
    background-color: #f1c40f;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    border: 2px solid white;
}

.user-details h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.user-details h3 a {
    color: #2c3e50;
    text-decoration: none;
}

.user-details h3 a:hover {
    color: #3498db;
}

.user-meta {
    margin-bottom: 1rem;
}

.user-email {
    color: #7f8c8d;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.user-joined {
    color: #95a5a6;
    font-size: 0.85rem;
}

.user-stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.25rem;
    font-size: 0.9rem;
}

.user-stats > div {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: #7f8c8d;
}

.karma i {
    color: #e67e22;
}

.ban-info {
    margin-bottom: 1.25rem;
    padding: 0.75rem;
    background-color: #fff0f0;
    border-radius: 8px;
}

.ban-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #e74c3c;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.ban-reason {
    color: #7f8c8d;
    font-size: 0.9rem;
    font-style: italic;
}

.user-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.current-user-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background-color: #e8f0fe;
    color: #3498db;
    border-radius: 6px;
    font-size: 0.9rem;
}

/* Badge Styling */
.badge {
    padding: 0.3rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.badge-primary {
    background: #3498db;
    color: white;
}

.badge-secondary {
    background: #7f8c8d;
    color: white;
}

.banned-badge {
    background: #e74c3c;
    color: white;
    padding: 0.15rem 0.6rem;
    border-radius: 20px;
    font-size: 0.75rem;
    display: inline-block;
}

/* Button Styling */
.action-btn {
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
    text-decoration: none;
    border: none;
    cursor: pointer;
    background: transparent;
}

.view-btn {
    color: #3498db;
}

.view-btn:hover {
    background-color: #e8f0fe;
    text-decoration: none;
}

.delete-btn {
    color: #e74c3c;
}

.delete-btn:hover {
    background-color: #feebeb;
    text-decoration: none;
}

.resolve-btn {
    color: #2ecc71;
}

.resolve-btn:hover {
    background-color: #e8f8ee;
    text-decoration: none;
}

.ban-btn {
    color: #e74c3c;
}

.ban-btn:hover {
    background-color: #feebeb;
}

.unban-btn {
    color: #2ecc71;
}

.unban-btn:hover {
    background-color: #e8f8ee;
}

.delete-user-btn {
    color: #e74c3c;
}

.delete-user-btn:hover {
    background-color: #feebeb;
}

/* Modal Styling */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
    animation: fadeIn 0.2s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    border-radius: 12px;
    max-width: 550px;
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.15);
    animation: slideIn 0.3s;
    overflow: hidden;
}

@keyframes slideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 1.25rem 1.5rem;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.4rem;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modal-header i {
    color: #e74c3c;
}

.close-modal {
    font-size: 1.75rem;
    color: #aaa;
    cursor: pointer;
    transition: all 0.2s;
    line-height: 1;
}

.close-modal:hover {
    color: #555;
}

.modal-body {
    padding: 1.5rem;
}

.ban-warning {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background-color: #fff5f5;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.ban-warning i {
    color: #e74c3c;
    font-size: 1.5rem;
    margin-top: 0.25rem;
}

.ban-warning p {
    margin: 0;
    font-size: 0.95rem;
    color: #333;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #2c3e50;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-family: inherit;
    font-size: 1rem;
    transition: all 0.2s;
}

.form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    outline: none;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.form-help {
    font-size: 0.85rem;
    color: #7f8c8d;
    margin-top: 0.4rem;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}

.cancel-btn, .confirm-btn {
    padding: 0.6rem 1.5rem;
    font-size: 0.95rem;
    font-weight: 500;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cancel-btn {
    background-color: #f5f5f5;
    color: #7f8c8d;
    border: none;
}

.cancel-btn:hover {
    background-color: #e0e0e0;
}

.confirm-btn {
    background-color: #3498db;
    color: white;
    border: none;
}

.confirm-btn:hover {
    background-color: #2980b9;
}

/* Content Ban Modal Action Choice */
.action-choice {
    margin-bottom: 1.5rem;
}

.choice-option {
    margin-bottom: 0.75rem;
}

.choice-option input[type="radio"] {
    display: none;
}

.choice-option label {
    display: flex;
    padding: 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.choice-option input[type="radio"]:checked + label {
    border-color: #3498db;
    background-color: #f0f8ff;
}

.choice-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    background-color: #f0f0f0;
    border-radius: 50%;
    margin-right: 1rem;
    color: #7f8c8d;
    transition: all 0.2s;
}

.choice-option input[type="radio"]:checked + label .choice-icon {
    background-color: #3498db;
    color: white;
}

.choice-text {
    flex: 1;
}

.choice-title {
    display: block;
    font-weight: 600;
    font-size: 1.05rem;
    margin-bottom: 0.25rem;
}

.choice-desc {
    display: block;
    font-size: 0.9rem;
    color: #7f8c8d;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .admin-dashboard {
        padding: 1rem 0.5rem;
    }
    
    .admin-section {
        padding: 1.5rem 1rem;
    }
    
    .admin-header h1 {
        font-size: 1.75rem;
    }
    
    .search-box input {
        width: 100%;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .user-cards {
        grid-template-columns: 1fr;
    }
    
    .report-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .report-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .section-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .search-box, .filter-options, .search-box input {
        width: 100%;
    }
    
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
}
</style>

<script>
// Tab Navigation
function openTab(tabName) {
    const tabs = document.querySelectorAll('.tab-content');
    const buttons = document.querySelectorAll('.tab-btn');
    
    tabs.forEach(tab => tab.classList.remove('active'));
    buttons.forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabName).classList.add('active');
    document.querySelector(`.tab-btn[onclick="openTab('${tabName}')"]`).classList.add('active');
}

// Ban User Modal Functions
function showUserBanModal(userId, username) {
    document.getElementById('banUserId').value = userId;
    document.getElementById('banUsername').textContent = username;
    document.getElementById('banUserModal').style.display = 'block';
}

function closeBanModal() {
    document.getElementById('banUserModal').style.display = 'none';
}

// Content Ban Modal Functions
function showBanModal(contentId, contentType, reportId, userId, username) {
    document.getElementById('contentId').value = contentId;
    document.getElementById('contentType').value = contentType;
    document.getElementById('reportId').value = reportId;
    document.getElementById('contentUserId').value = userId;
    document.getElementById('contentAuthor').textContent = username;
    document.getElementById('contentBanModal').style.display = 'block';
    
    // Reset radio option
    document.getElementById('deleteOnly').checked = true;
    document.getElementById('banReasonContainer').style.display = 'none';
    document.getElementById('banUserCheckbox').value = '0';
}

function closeContentBanModal() {
    document.getElementById('contentBanModal').style.display = 'none';
}

// Handle radio button change for content ban modal
document.getElementById('deleteOnly').addEventListener('change', function() {
    document.getElementById('banReasonContainer').style.display = 'none';
    document.getElementById('banUserCheckbox').value = '0';
    document.getElementById('contentBanReason').removeAttribute('required');
});

document.getElementById('deleteAndBan').addEventListener('change', function() {
    document.getElementById('banReasonContainer').style.display = 'block';
    document.getElementById('banUserCheckbox').value = '1';
    document.getElementById('contentBanReason').setAttribute('required', 'required');
});

// Close modals when clicking outside
window.onclick = function(event) {
    const banModal = document.getElementById('banUserModal');
    const contentModal = document.getElementById('contentBanModal');
    
    if (event.target === banModal) {
        closeBanModal();
    }
    
    if (event.target === contentModal) {
        closeContentBanModal();
    }
}

// Close modals when clicking X
document.querySelectorAll('.close-modal').forEach(element => {
    element.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// Filter users
function filterUsers() {
    const searchInput = document.getElementById('userSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const userCards = document.querySelectorAll('.user-card');
    
    userCards.forEach(card => {
        const username = card.dataset.username;
        const email = card.dataset.email;
        const isBanned = card.classList.contains('banned-user');
        const isAdmin = card.classList.contains('admin-user');
        
        // Text search match
        const textMatch = username.includes(searchInput) || email.includes(searchInput);
        
        // Status filter match
        let statusMatch = true;
        if (statusFilter === 'banned') {
            statusMatch = isBanned;
        } else if (statusFilter === 'active') {
            statusMatch = !isBanned;
        } else if (statusFilter === 'admin') {
            statusMatch = isAdmin;
        }
        
        // Show or hide based on both filters
        if (textMatch && statusMatch) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// Filter reports
function filterReports() {
    const searchInput = document.getElementById('reportSearch').value.toLowerCase();
    const reportCards = document.querySelectorAll('.report-card');
    
    reportCards.forEach(card => {
        const content = card.innerText.toLowerCase();
        
        if (content.includes(searchInput)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php
// Include footer - this will close the HTML structure
include 'includes/footer.php';
?>
