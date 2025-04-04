<?php
$page_title = "Leaderboard - Top Contributors";
$page_css = "leaderboard.css";
$page_js = "leaderboard.js";
include 'includes/header.php';

// Determine time period filter
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$period_clause = '';
$period_title = 'All Time';

switch ($period) {
    case 'day':
        $period_clause = "WHERE DATE(u.created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $period_title = 'Today';
        break;
    case 'week':
        $period_clause = "WHERE DATE(u.created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        $period_title = 'This Week';
        break;
    case 'month':
        $period_clause = "WHERE DATE(u.created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $period_title = 'This Month';
        break;
    case 'year':
        $period_clause = "WHERE DATE(u.created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $period_title = 'This Year';
        break;
}

// Get leaderboard data
$query = "
    SELECT 
        u.user_id,
        u.username,
        u.profile_image AS avatar, -- Correct column name from your database
        u.karma,
        COUNT(DISTINCT p.post_id) as post_count,
        COUNT(DISTINCT c.comment_id) as comment_count
    FROM 
        users u
    LEFT JOIN 
        posts p ON u.user_id = p.user_id
    LEFT JOIN 
        comments c ON u.user_id = c.user_id
    $period_clause
    GROUP BY 
        u.user_id
    ORDER BY 
        u.karma DESC
    LIMIT 50
";

$result = mysqli_query($conn, $query);

// Get badge levels
$badge_levels = get_badge_levels();

// Calculate user rank if logged in
$user_rank = null;
$user_badge = null;
if (is_logged_in()) {
    // Get current user's rank
    $rank_query = "
        SELECT count(*) + 1 as rank_num
        FROM users
        WHERE karma > (SELECT karma FROM users WHERE user_id = ?)
    ";
    $stmt = mysqli_prepare($conn, $rank_query);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $rank_result = mysqli_stmt_get_result($stmt);
    $user_rank = mysqli_fetch_assoc($rank_result)['rank_num'];
    
    // Get user's badge
    $user_query = "SELECT karma FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $user_query);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    $user_karma = mysqli_fetch_assoc($user_result)['karma'];
    $user_badge = get_user_badge($user_karma, $badge_levels);
}
?>

<!-- Add inline styles to ensure filter visibility -->
<style>
.period-filter.active {
    background-color: #4c8bf5 !important; 
    color: white !important;
    font-weight: bold !important;
    border: 1px solid white !important;
    text-shadow: 0px 1px 1px rgba(0,0,0,0.3) !important;
}
</style>

<div class="container leaderboard-container">
    <div class="leaderboard-header">
        <h1>Leaderboard <span class="period-title"><?php echo $period_title; ?></span></h1>
        
        <div class="period-filters">
            <a href="leaderboard.php?period=day" class="period-filter <?php echo $period == 'day' ? 'active' : ''; ?>">Today</a>
            <a href="leaderboard.php?period=week" class="period-filter <?php echo $period == 'week' ? 'active' : ''; ?>">This Week</a>
            <a href="leaderboard.php?period=month" class="period-filter <?php echo $period == 'month' ? 'active' : ''; ?>">This Month</a>
            <a href="leaderboard.php?period=year" class="period-filter <?php echo $period == 'year' ? 'active' : ''; ?>">This Year</a>
            <a href="leaderboard.php" class="period-filter <?php echo $period == 'all' ? 'active' : ''; ?>">All Time</a>
        </div>
    </div>
    
    <?php if (is_logged_in()): ?>
    <div class="user-rank-card">
        <div class="user-rank-position">
            <span class="rank-number">#<?php echo $user_rank; ?></span>
        </div>
        <div class="user-rank-info">
            <?php 
                // Try to get the avatar from session, or fall back to fetching from database
                $avatar = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 
                         (isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : null);
                
                // If we don't have it in session, fetch from database
                if (!$avatar && isset($_SESSION['user_id'])) {
                    $avatar_query = "SELECT profile_image FROM users WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $avatar_query);
                    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
                    mysqli_stmt_execute($stmt);
                    $avatar_result = mysqli_stmt_get_result($stmt);
                    $avatar_data = mysqli_fetch_assoc($avatar_result);
                    $avatar = $avatar_data ? $avatar_data['profile_image'] : null;
                }
            ?>
            <img src="<?php echo get_avatar_url($avatar); ?>" alt="Your Avatar" class="user-avatar">
            <div class="user-details">
                <div class="username-badge">
                    <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <span class="badge" style="background-color: <?php echo $user_badge['color']; ?>">
                        <?php echo $user_badge['name']; ?>
                    </span>
                </div>
                <div class="karma-progress">
                    <?php 
                    // Find next badge level
                    $current_karma = $user_karma;
                    $next_badge = null;
                    $progress = 100;
                    
                    foreach ($badge_levels as $level) {
                        if ($current_karma < $level['min_karma']) {
                            $next_badge = $level;
                            $prev_min = $user_badge['min_karma'];
                            $next_min = $level['min_karma'];
                            $progress = (($current_karma - $prev_min) / ($next_min - $prev_min)) * 100;
                            break;
                        }
                    }
                    ?>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                    
                    <div class="progress-text">
                        <span><?php echo number_format($current_karma); ?> karma</span>
                        <?php if ($next_badge): ?>
                        <span><?php echo number_format($next_badge['min_karma'] - $current_karma); ?> to <?php echo $next_badge['name']; ?></span>
                        <?php else: ?>
                        <span>Highest rank achieved!</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; // End of "if (is_logged_in())" check ?>
    
    <div class="leaderboard-badges-info">
        <h3>Badge Levels</h3>
        <div class="badge-list">
            <?php foreach ($badge_levels as $badge): ?>
            <div class="badge-item">
                <span class="badge" style="background-color: <?php echo $badge['color']; ?>">
                    <?php echo $badge['name']; ?>
                </span>
                <span class="badge-karma"><?php echo number_format($badge['min_karma']); ?>+ karma</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="leaderboard-table-container">
        <table class="leaderboard-table">
            <thead>
                <tr>
                    <th class="rank-col">Rank</th>
                    <th class="user-col">User</th>
                    <th class="karma-col">Karma</th>
                    <th class="posts-col">Posts</th>
                    <th class="comments-col">Comments</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                while ($row = mysqli_fetch_assoc($result)):
                    $badge = get_user_badge($row['karma'], $badge_levels);
                ?>
                <tr class="<?php echo isset($_SESSION['user_id']) && $row['user_id'] == $_SESSION['user_id'] ? 'current-user' : ''; ?>">
                    <td class="rank-col">
                        <?php if ($rank <= 3): ?>
                        <div class="top-rank rank-<?php echo $rank; ?>">
                            <i class="fas fa-trophy"></i>
                            <span>#<?php echo $rank; ?></span>
                        </div>
                        <?php else: ?>
                        <span class="rank-number">#<?php echo $rank; ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="user-col">
                        <a href="profile.php?user=<?php echo urlencode($row['username']); ?>" class="user-link">
                            <img src="<?php echo get_avatar_url($row['avatar']); ?>" alt="<?php echo htmlspecialchars($row['username']); ?>" class="user-avatar">
                            <div class="user-info">
                                <span class="username"><?php echo htmlspecialchars($row['username']); ?></span>
                                <span class="badge" style="background-color: <?php echo $badge['color']; ?>">
                                    <?php echo $badge['name']; ?>
                                </span>
                            </div>
                        </a>
                    </td>
                    <td class="karma-col"><?php echo number_format($row['karma']); ?></td>
                    <td class="posts-col"><?php echo number_format($row['post_count']); ?></td>
                    <td class="comments-col"><?php echo number_format($row['comment_count']); ?></td>
                </tr>
                <?php 
                    $rank++; 
                    endwhile; 
                ?>
                
                <?php if (mysqli_num_rows($result) == 0): ?>
                <tr>
                    <td colspan="5" class="no-results">No users found for this time period.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
