<?php
/**
 * 資料庫連線設定
 */

// 簡單的 .env 載入器
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

define('DB_HOST', 'localhost');
define('DB_USER', 'cvml');

define('DB_PASS', 'dwpcvml2025');
define('DB_NAME', 'simple_retro');

// 建立資料庫連線
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("資料庫連線失敗: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// 啟動 Session
session_start();

// 檢查使用者是否已登入
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// 取得當前登入的使用者 ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// 取得當前登入的使用者名稱
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

// 需要登入才能存取的頁面保護
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

// 安全輸出 HTML
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// JSON 回應
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
?>
