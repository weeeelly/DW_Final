<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$userId = getCurrentUserId();
$username = getCurrentUsername();

// 取得使用者的所有相簿
$albums = [];
$stmt = $conn->prepare("SELECT * FROM albums WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $albums[] = $row;
}
$stmt->close();

// 取得預設相簿 ID
$defaultAlbumId = null;
foreach ($albums as $album) {
    if ($album['is_default']) {
        $defaultAlbumId = $album['id'];
        break;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Retro - 我的照片日記</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- 側邊欄 -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="logo-icon">📸</span>
                    <h1>Simple Retro</h1>
                </div>
            </div>
            
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span class="username"><?php echo h($username); ?></span>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <h3>相簿</h3>
                    <ul class="album-list" id="albumList">
                        <?php foreach ($albums as $album): ?>
                        <li class="album-item <?php echo $album['is_default'] ? 'default-album' : ''; ?>" 
                            data-album-id="<?php echo $album['id']; ?>"
                            data-album-name="<?php echo h($album['name']); ?>"
                            data-is-default="<?php echo $album['is_default'] ? '1' : '0'; ?>">
                            <span class="album-icon"><?php echo $album['is_default'] ? '📁' : '📂'; ?></span>
                            <span class="album-name"><?php echo h($album['name']); ?></span>
                            <?php if (!$album['is_default']): ?>
                            <div class="album-actions">
                                <button class="btn-icon edit-album-btn" title="編輯相簿">✏️</button>
                                <button class="btn-icon delete-album-btn" title="刪除相簿">🗑️</button>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <button class="btn btn-secondary btn-sm add-album-btn" id="addAlbumBtn">
                        <span>+</span> 新增相簿
                    </button>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <a href="logout.php" class="btn btn-outline btn-block logout-btn">登出</a>
            </div>
        </aside>
        
        <!-- 主內容區 -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
                    <h2 id="currentAlbumTitle">全部照片</h2>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" id="addPhotoBtn">
                        <span>+</span> 新增照片
                    </button>
                </div>
            </header>
            
            <div class="photo-grid" id="photoGrid">
                <!-- 照片將由 JavaScript 動態載入 -->
                <div class="loading">載入中...</div>
            </div>
            
            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="empty-icon">📷</div>
                <h3>還沒有照片</h3>
                <p>點擊上方「新增照片」開始記錄你的故事</p>
            </div>
        </main>
    </div>
    
    <!-- 新增/編輯照片 Modal -->
    <div class="modal" id="photoModal">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="photoModalTitle">新增照片</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="photoForm">
                <input type="hidden" id="photoId" name="photo_id">
                
                <div class="form-group">
                    <label for="imageUrl">圖片網址</label>
                    <input type="url" id="imageUrl" name="image_url" required 
                           placeholder="貼上圖片連結（例如：https://...）">
                    <small class="form-hint">支援 jpg, png, gif, webp 格式的圖片網址</small>
                </div>
                
                <div class="form-group">
                    <label for="imagePreview">預覽</label>
                    <div class="image-preview" id="imagePreview">
                        <span class="preview-placeholder">輸入網址後預覽圖片</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="caption">照片描述</label>
                    <textarea id="caption" name="caption" rows="3" 
                              placeholder="寫下這張照片的故事..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="albumSelect">選擇相簿</label>
                    <select id="albumSelect" name="album_id">
                        <?php foreach ($albums as $album): ?>
                        <option value="<?php echo $album['id']; ?>" 
                                <?php echo $album['is_default'] ? 'selected' : ''; ?>>
                            <?php echo h($album['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary modal-cancel">取消</button>
                    <button type="submit" class="btn btn-primary">儲存</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 新增/編輯相簿 Modal -->
    <div class="modal" id="albumModal">
        <div class="modal-overlay"></div>
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3 id="albumModalTitle">新增相簿</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="albumForm">
                <input type="hidden" id="albumId" name="album_id">
                
                <div class="form-group">
                    <label for="albumName">相簿名稱</label>
                    <input type="text" id="albumName" name="album_name" required 
                           placeholder="例如：旅遊回憶、美食紀錄">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary modal-cancel">取消</button>
                    <button type="submit" class="btn btn-primary">儲存</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 檢視照片 Modal -->
    <div class="modal" id="viewPhotoModal">
        <div class="modal-overlay"></div>
        <div class="modal-content modal-lg">
            <button class="modal-close">&times;</button>
            <div class="photo-viewer">
                <img id="viewPhotoImage" src="" alt="">
                <div class="photo-details">
                    <p id="viewPhotoCaption"></p>
                    <span class="photo-album" id="viewPhotoAlbum"></span>
                    <span class="photo-date" id="viewPhotoDate"></span>
                </div>
                <div class="photo-viewer-actions">
                    <button class="btn btn-secondary" id="editPhotoFromView">編輯</button>
                    <button class="btn btn-danger" id="deletePhotoFromView">刪除</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 確認刪除 Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-overlay"></div>
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>確認刪除</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">確定要刪除嗎？</p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary modal-cancel">取消</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">刪除</button>
            </div>
        </div>
    </div>
    
    <!-- Toast 通知 -->
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        // 傳遞 PHP 資料給 JavaScript
        const APP_DATA = {
            userId: <?php echo $userId; ?>,
            username: '<?php echo h($username); ?>',
            albums: <?php echo json_encode($albums, JSON_UNESCAPED_UNICODE); ?>,
            defaultAlbumId: <?php echo $defaultAlbumId; ?>
        };
    </script>
    <script src="app.js"></script>
</body>
</html>
