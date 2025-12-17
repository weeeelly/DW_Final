<?php
require_once 'config.php';

// 如果已經登入，直接跳轉到主頁
if (isLoggedIn()) {
    header('Location: home.php');
    exit();
}

$error = '';
$success = '';

// 處理註冊表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '請填寫所有欄位';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = '使用者名稱需在 3-50 個字元之間';
    } elseif (strlen($password) < 6) {
        $error = '密碼至少需要 6 個字元';
    } elseif ($password !== $confirmPassword) {
        $error = '兩次輸入的密碼不一致';
    } else {
        $conn = getDBConnection();
        
        // 檢查使用者名稱是否已存在
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = '此使用者名稱已被使用';
        } else {
            // 建立新使用者
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashedPassword);
            
            if ($stmt->execute()) {
                $userId = $stmt->insert_id;
                
                // 為新使用者建立預設相簿 "Recents"
                $defaultAlbum = "Recents";
                $stmt2 = $conn->prepare("INSERT INTO albums (user_id, name, is_default) VALUES (?, ?, TRUE)");
                $stmt2->bind_param("is", $userId, $defaultAlbum);
                $stmt2->execute();
                $stmt2->close();
                
                $success = '註冊成功！請登入';
            } else {
                $error = '註冊失敗，請稍後再試';
            }
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Retro - 註冊</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <span class="logo-icon">📸</span>
                    <h1>Simple Retro</h1>
                </div>
                <p class="tagline">建立帳號，開始記錄你的故事</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="username">使用者名稱</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="3-50 個字元"
                           value="<?php echo h($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">密碼</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="至少 6 個字元">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">確認密碼</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="再次輸入密碼">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">註冊</button>
            </form>
            
            <div class="auth-footer">
                <p>已經有帳號？ <a href="index.php">立即登入</a></p>
            </div>
        </div>
    </div>
</body>
</html>
