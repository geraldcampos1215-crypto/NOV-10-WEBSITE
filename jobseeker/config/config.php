<?php
/* JobSeeker - centralized configuration */
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobseeker_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', '/jobseeker');          // change if your folder name differs
define('ROOT_PATH', dirname(__DIR__));
define('SESSION_TIMEOUT', 1800);           // 30 minutes of inactivity
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCK_MINUTES', 15);
define('MAIL_FROM', 'no-reply@jobseeker.local');

date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', '0');            // set to '1' while developing
ini_set('log_errors', '1');

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (!empty($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
    session_unset(); session_destroy(); session_start();
    $_SESSION['flash'][] = ['warning', 'Your session expired. Please log in again.'];
}
$_SESSION['last_activity'] = time();

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Database connection failed. Check config/config.php and make sure you imported database/jobseeker_db.sql');
}
