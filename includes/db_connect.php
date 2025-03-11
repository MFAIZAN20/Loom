<?php

$config = [
    'host' => getenv('LOOM_DB_HOST') ?: 'localhost',
    'user' => getenv('LOOM_DB_USER') ?: 'root',
    'pass' => getenv('LOOM_DB_PASS') ?: '',
    'name' => getenv('LOOM_DB_NAME') ?: 'loom',
];

$local_config_path = __DIR__ . '/db_config.local.php';
if (file_exists($local_config_path)) {
    $local_config = require $local_config_path;
    if (is_array($local_config)) {
        $config = array_merge($config, array_intersect_key($local_config, $config));
    }
}

$db_host = $config['host'];
$db_user = $config['user'];
$db_pass = $config['pass'];
$db_name = $config['name'];

$server_name = $_SERVER['SERVER_NAME'] ?? '';
$is_dev = ($server_name === 'localhost' || $server_name === '127.0.0.1' || strpos($server_name, '127.0.0.1') !== false)
    || (getenv('LOOM_APP_ENV') === 'development');

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    mysqli_set_charset($conn, 'utf8mb4');

    try {
        mysqli_query($conn, "SET time_zone = 'Asia/Karachi'");
    } catch (Throwable $e) {
        error_log("Failed to set MySQL timezone: " . $e->getMessage());
    }
} catch (Throwable $e) {
    error_log("Database connection failed: " . $e->getMessage());

    if ($is_dev) {
        echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
    } else {
        echo "Database connection failed. Please try again later.";
    }
    exit;
}
?>
