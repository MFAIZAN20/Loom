<?php
if (!function_exists('get_user_badge')) {
    function get_user_badge($karma, $badge_levels) {
        $badge = $badge_levels[0]; // Default to lowest badge
        foreach ($badge_levels as $level) {
            if ($karma >= $level['min_karma']) {
                $badge = $level;
            } else {
                break;
            }
        }
        return $badge;
    }
}

// Define badge levels if not already defined
if (!isset($badge_levels)) {
    $badge_levels = get_badge_levels();
}

// Define get_avatar_url function if not already defined
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar = null) {
        if (empty($avatar)) {
            return 'images/avatars/default.png';
        }
        
        if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0) {
            return $avatar;
        }
        
        if (strpos($avatar, 'images/avatars/') === 0) {
            return $avatar;
        }
        
        return 'images/avatars/' . $avatar;
    }
}

$post_user_query = "SELECT karma FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $post_user_query);
mysqli_stmt_bind_param($stmt, "i", $post['user_id']);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($user_result);

$post_user_badge = get_user_badge($user_data['karma'], $badge_levels);

?>
<a href="profile.php?user=<?php echo urlencode($post['username']); ?>" class="post-author">
    <img src="<?php echo get_avatar_url($post['avatar']); ?>" alt="<?php echo htmlspecialchars($post['username']); ?>" class="author-avatar">
    <div class="author-info">
        <span class="author-name"><?php echo htmlspecialchars($post['username']); ?></span>
        <span class="badge user-badge" style="background-color: <?php echo $post_user_badge['color']; ?>">
            <?php echo $post_user_badge['name']; ?>
        </span>
    </div>
</a>