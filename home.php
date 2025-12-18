<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$userId = getCurrentUserId();
$username = getCurrentUsername();

$albums = [];
$stmt = $conn->prepare("SELECT * FROM albums WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $albums[] = $row;
}
$stmt->close();

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
    <title>Photo Rewind - 我的照片日記</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="logo-icon">📸</span>
                    <h1>Photo Rewind</h1>
                </div>
            </div>
            
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span class="username"><?php echo h($username); ?></span>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <ul class="nav-links">
                        <li><a href="profile.php" class="nav-link-item">👤 我的主頁</a></li>
                        <li><a href="friends.php" class="nav-link-item">👥 好友</a></li>
                    </ul>
                </div>
                
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
        
        <main class="main-content">
            <header class="main-header">
                <div class="header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
                    <h2 id="currentAlbumTitle">全部照片</h2>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary" id="startGameBtn">
                        <span>🎮</span> 朋友記憶遊戲
                    </button>
                    <button class="btn btn-primary" id="addPhotoBtn">
                        <span>+</span> 新增照片
                    </button>
                </div>
            </header>
            
            <div class="photo-grid" id="photoGrid">
                <div class="loading">載入中...</div>
            </div>
            
            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="empty-icon">📷</div>
                <h3>還沒有照片</h3>
                <p>點擊上方「新增照片」開始記錄你的故事</p>
            </div>
        </main>
    </div>
    
    <div class="modal" id="photoModal">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="photoModalTitle">新增照片</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="photoForm" enctype="multipart/form-data">
                <input type="hidden" id="photoId" name="photo_id">
                
                <div class="form-group">
                    <label for="imageFile">選擇圖片</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="imageFile" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="file-upload-btn">
                            <span class="file-upload-icon">📁</span>
                            <span class="file-upload-text">點擊選擇圖片或拖曳檔案至此</span>
                        </div>
                        <span class="file-name" id="fileName"></span>
                    </div>
                    <small class="form-hint">支援 JPG、PNG、GIF、WebP 格式，最大 10MB</small>
                </div>
                
                <div class="form-group">
                    <label>預覽</label>
                    <div class="image-preview" id="imagePreview">
                        <span class="preview-placeholder">選擇圖片後預覽</span>
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
    
    <div class="modal" id="viewPhotoModal">
        <div class="modal-overlay"></div>
        <div class="modal-content modal-lg">
            <button class="modal-close">&times;</button>
            <div class="photo-viewer">
                <img id="viewPhotoImage" src="" alt="">
                <div class="photo-details">
                    <p id="viewPhotoCaption"></p>
                    <div id="viewPhotoAiResult" class="ai-result-section" style="display: none;">
                        <h4>AI 年齡分析</h4>
                        <p class="ai-age-badge">照片年齡：<span id="viewPhotoAge"></span></p>
                        <p id="viewPhotoExplanation" class="ai-explanation"></p>
                    </div>
                    <span class="photo-album" id="viewPhotoAlbum"></span>
                    <span class="photo-date" id="viewPhotoDate"></span>
                </div>
                <div class="photo-viewer-actions">
                    <button class="btn btn-secondary" id="editPhotoFromView">編輯</button>
                    <button class="btn btn-secondary" id="imageEditFromView">修圖</button>
                    <button class="btn btn-danger" id="deletePhotoFromView">刪除</button>
                </div>
            </div>
        </div>
    </div>
    
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
    
    <div class="modal" id="imageEditModal">
        <div class="modal-overlay"></div>
        <div class="modal-content modal-xl">
            <div class="modal-header">
                <h3>圖片編輯器</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="image-editor">
                <div class="editor-toolbar">
                    <div class="editor-tabs">
                        <button class="tab-btn active" data-tab="filters">濾鏡</button>
                        <button class="tab-btn" data-tab="stickers">貼圖</button>
                        <button class="tab-btn" data-tab="adjust">調整</button>
                    </div>
                </div>
                
                <div class="editor-panel active" id="filtersPanel">
                    <h4>選擇濾鏡效果</h4>
                    <div class="filter-grid">
                        <div class="filter-item" data-filter="none">
                            <div class="filter-preview" style="background-image: url();"></div>
                            <span>原圖</span>
                        </div>
                        <div class="filter-item" data-filter="grayscale(100%)">
                            <div class="filter-preview grayscale"></div>
                            <span>黑白</span>
                        </div>
                        <div class="filter-item" data-filter="sepia(100%)">
                            <div class="filter-preview sepia"></div>
                            <span>復古</span>
                        </div>
                        <div class="filter-item" data-filter="blur(2px)">
                            <div class="filter-preview blur"></div>
                            <span>模糊</span>
                        </div>
                        <div class="filter-item" data-filter="brightness(1.3) contrast(1.2)">
                            <div class="filter-preview bright"></div>
                            <span>鮮豔</span>
                        </div>
                        <div class="filter-item" data-filter="hue-rotate(90deg)">
                            <div class="filter-preview hue"></div>
                            <span>色調</span>
                        </div>
                    </div>
                </div>
                
                <div class="editor-panel" id="stickersPanel">
                    <h4>選擇貼圖</h4>
                    <div class="sticker-grid">
                        <div class="sticker-category">
                            <h5>表情</h5>
                            <div class="sticker-list">
                                <span class="sticker-item" data-sticker="😍">😍</span>
                                <span class="sticker-item" data-sticker="🥰">🥰</span>
                                <span class="sticker-item" data-sticker="😎">😎</span>
                                <span class="sticker-item" data-sticker="🤩">🤩</span>
                                <span class="sticker-item" data-sticker="😘">😘</span>
                                <span class="sticker-item" data-sticker="🔥">🔥</span>
                            </div>
                        </div>
                        <div class="sticker-category">
                            <h5>裝飾</h5>
                            <div class="sticker-list">
                                <span class="sticker-item" data-sticker="⭐">⭐</span>
                                <span class="sticker-item" data-sticker="💖">💖</span>
                                <span class="sticker-item" data-sticker="🌟">🌟</span>
                                <span class="sticker-item" data-sticker="✨">✨</span>
                                <span class="sticker-item" data-sticker="💫">💫</span>
                                <span class="sticker-item" data-sticker="🎉">🎉</span>
                            </div>
                        </div>
                    </div>
                    <div class="sticker-controls" style="display: none;">
                        <label>大小：</label>
                        <input type="range" id="stickerSize" min="20" max="80" value="40">
                        <span id="stickerSizeValue">40px</span>
                    </div>
                </div>
                
                <div class="editor-panel" id="adjustPanel">
                    <h4>調整圖片</h4>
                    <div class="adjust-controls">
                        <div class="control-group">
                            <label>亮度：<span id="brightnessValue">100%</span></label>
                            <input type="range" id="brightnessSlider" min="50" max="200" value="100">
                        </div>
                        <div class="control-group">
                            <label>對比度：<span id="contrastValue">100%</span></label>
                            <input type="range" id="contrastSlider" min="50" max="200" value="100">
                        </div>
                        <div class="control-group">
                            <label>飽和度：<span id="saturationValue">100%</span></label>
                            <input type="range" id="saturationSlider" min="0" max="200" value="100">
                        </div>
                    </div>
                    <div class="adjust-actions">
                        <button class="btn btn-secondary" id="resetAdjustments">重置調整</button>
                    </div>
                </div>
                
                <div class="image-container">
                    <img id="editImage" src="" alt="">
                    <div id="stickerOverlay"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary modal-cancel">取消</button>
                    <button type="button" class="btn btn-primary" id="saveEditedImage">保存修圖</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="gameModal">
        <div class="modal-overlay"></div>
        <div class="modal-content modal-xl">
            <div class="modal-header">
                <h3>🎮 朋友記憶遊戲</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="game-container">
                <div class="game-screen active" id="gameStartScreen">
                    <div class="game-intro">
                        <h2>準備挑戰你的記憶力！</h2>
                        <p>遊戲規則：</p>
                        <ul>
                            <li>🎵 點擊開始後會播放背景音樂</li>
                            <li>📸 預備拍 8 拍，每拍會顯示一張朋友的照片</li>
                            <li>🧠 記住照片出現的順序</li>
                            <li>👆 接下來 8 拍，按順序點擊正確的朋友名稱</li>
                        </ul>
                        <div class="game-settings">
                            <h3>🎯 固定遊戲設定</h3>
                            <div class="settings-info">
                                <div class="setting-item">
                                    <span class="setting-icon">📸</span>
                                    <span class="setting-text">8張照片（固定難度）</span>
                                </div>
                                <div class="setting-item">
                                    <span class="setting-icon">🎵</span>
                                    <span class="setting-text">內建背景音樂</span>
                                </div>
                                <div class="setting-item">
                                    <span class="setting-icon">⚡</span>
                                    <span class="setting-text">180 BPM（快節奏）</span>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-lg" id="startGameButton">
                            🚀 開始遊戲
                        </button>
                    </div>
                </div>
                
                <div class="game-screen" id="gamePlayScreen">
                    <div class="game-info">
                        <div class="game-progress">
                            <span>第 <span id="currentBeat">1</span> 拍</span>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                        </div>
                        <div class="game-phase" id="gamePhase">準備階段</div>
                    </div>
                    
                    <div class="game-display">
                        <div class="photo-display" id="photoDisplay">
                            <div class="beat-indicator" id="beatIndicator">♪</div>
                            <img id="currentPhoto" src="" alt="" style="display: none;">
                            <div class="friend-name" id="currentFriendName" style="display: none;"></div>
                        </div>
                        
                        <div class="name-selection" id="nameSelection" style="display: none;">
                            <h3>點擊朋友的名稱 (按照剛才照片出現的順序)</h3>
                            <div class="name-grid" id="nameGrid"></div>
                        </div>
                    </div>
                    
                    <div class="game-controls">
                        <button class="btn btn-secondary" id="pauseGameBtn" style="display: none;">⏸️ 暫停</button>
                        <button class="btn btn-danger" id="stopGameBtn">⏹️ 結束遊戲</button>
                    </div>
                </div>
                
                <div class="game-screen" id="gameResultScreen">
                    <div class="game-result">
                        <div class="result-icon" id="resultIcon">🎉</div>
                        <h2 id="resultTitle">恭喜過關！</h2>
                        <div class="result-stats">
                            <div class="stat-item">
                                <span class="stat-label">正確率</span>
                                <span class="stat-value" id="accuracyRate">100%</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">遊戲時間</span>
                                <span class="stat-value" id="gameTime">30秒</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">成績</span>
                                <span class="stat-value" id="gameDifficulty">普通</span>
                            </div>
                        </div>
                        <div class="result-actions">
                            <button class="btn btn-primary" id="playAgainBtn">🔄 再玩一次</button>
                            <button class="btn btn-secondary modal-cancel">返回</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script>
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
