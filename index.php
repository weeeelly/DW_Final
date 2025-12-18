<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: home.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '請輸入使用者名稱和密碼';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                header('Location: home.php');
                exit();
            } else {
                $error = '密碼錯誤';
            }
        } else {
            $error = '使用者不存在';
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
    <title>Photo Rewind - 登入</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <span class="logo-icon">📸</span>
                    <h1>Photo Rewind</h1>
                </div>
                <p class="tagline">紀錄生活的每一個美好瞬間</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="username">使用者名稱</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="輸入您的使用者名稱"
                           value="<?php echo h($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">密碼</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="輸入您的密碼">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">登入</button>
            </form>
            
            <div class="auth-footer">
                <p>還沒有帳號？ <a href="register.php">立即註冊</a></p>
            </div>
            
            <div class="demo-info">
                <p><strong>測試帳號：</strong></p>
                <p>帳號：willy / 密碼：willy0310</p>
            </div>
        </div>
    </div>
</body>
</html>
