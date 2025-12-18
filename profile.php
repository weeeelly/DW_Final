<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$userId = getCurrentUserId();
$currentUsername = getCurrentUsername();

// 取得要查看的使用者 ID
$profileUserId = intval($_GET['id'] ?? $userId);

// 取得使用者資料
$stmt = $conn->prepare("SELECT id, username, bio, avatar, created_at, ai_estimated_age, ai_tags FROM users WHERE id = ?");
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: home.php');
    exit();
}

$profileUser = $result->fetch_assoc();
$stmt->close();

// 檢查好友關係
$relation = 'none';
$isSelf = ($userId === $profileUserId);

if (!$isSelf) {
    $stmt = $conn->prepare("SELECT status FROM friendships WHERE user_id = ? AND friend_id = ?");
    $stmt->bind_param("ii", $userId, $profileUserId);
    $stmt->execute();
    $friendResult = $stmt->get_result();
    
    if ($friendResult->num_rows > 0) {
        $friendship = $friendResult->fetch_assoc();
        $relation = $friendship['status'] === 'accepted' ? 'friend' : 'pending_sent';
    } else {
        // 檢查反向
        $stmt2 = $conn->prepare("SELECT status FROM friendships WHERE user_id = ? AND friend_id = ?");
        $stmt2->bind_param("ii", $profileUserId, $userId);
        $stmt2->execute();
        $reverseResult = $stmt2->get_result();
        if ($reverseResult->num_rows > 0) {
            $reverse = $reverseResult->fetch_assoc();
            $relation = $reverse['status'] === 'accepted' ? 'friend' : 'pending_received';
        }
        $stmt2->close();
    }
    $stmt->close();
}

// 取得統計資料
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM photos WHERE user_id = ?");
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$photoCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM friendships WHERE user_id = ? AND status = 'accepted'");
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$friendCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($profileUser['username']); ?> - Photo Rewind</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="profile-page">
        <!-- 頂部導航 -->
        <header class="profile-header-nav">
            <a href="home.php" class="back-btn">← 返回</a>
            <div class="logo">
                <span class="logo-icon">📸</span>
                <span>Photo Rewind</span>
            </div>
            <a href="friends.php" class="nav-link">好友</a>
        </header>
        
        <!-- 個人資料區 -->
        <div class="profile-hero">
            <div class="profile-avatar">
                <?php if ($profileUser['avatar']): ?>
                    <img src="<?php echo h($profileUser['avatar']); ?>" alt="">
                <?php else: ?>
                    <span><?php echo strtoupper(substr($profileUser['username'], 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h1><?php echo h($profileUser['username']); ?></h1>
                <?php if ($profileUser['bio']): ?>
                    <p class="profile-bio"><?php echo h($profileUser['bio']); ?></p>
                <?php endif; ?>

                <?php if (!empty($profileUser['ai_estimated_age'])): ?>
                <div class="ai-profile-info">
                    <div class="ai-age-badge">
                        📷 照片年齡：<?php echo h($profileUser['ai_estimated_age']); ?>
                    </div>
                    <?php 
                    $tags = json_decode($profileUser['ai_tags'], true);
                    if ($tags && is_array($tags)): 
                    ?>
                    <div class="ai-tags">
                        <?php foreach ($tags as $tag): ?>
                            <span class="ai-tag">#<?php echo h($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif ($isSelf): ?>
                <div class="ai-profile-action">
                    <button class="btn btn-primary btn-sm" id="aiAnalyzeBtn" onclick="handleAiAnalyze()">
                        📷 分析照片年齡
                    </button>
                </div>
                <?php endif; ?>

                <div class="profile-stats">
                    <div class="stat">
                        <span class="stat-value"><?php echo $photoCount; ?></span>
                        <span class="stat-label">照片</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value"><?php echo $friendCount; ?></span>
                        <span class="stat-label">好友</span>
                    </div>
                </div>
                
                <?php if (!$isSelf): ?>
                <div class="profile-actions" id="profileActions">
                    <?php if ($relation === 'friend'): ?>
                        <button class="btn btn-secondary" onclick="removeFriend(<?php echo $profileUserId; ?>)">
                            ✓ 好友
                        </button>
                    <?php elseif ($relation === 'pending_sent'): ?>
                        <button class="btn btn-secondary" disabled>
                            已送出請求
                        </button>
                    <?php elseif ($relation === 'pending_received'): ?>
                        <button class="btn btn-primary" onclick="acceptFriend(<?php echo $profileUserId; ?>)">
                            接受請求
                        </button>
                        <button class="btn btn-secondary" onclick="rejectFriend(<?php echo $profileUserId; ?>)">
                            拒絕
                        </button>
                    <?php else: ?>
                        <button class="btn btn-primary" onclick="addFriend(<?php echo $profileUserId; ?>)">
                            + 加為好友
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 照片區 -->
        <div class="profile-content">
            <h2>照片牆</h2>
            <div class="photo-grid" id="photoGrid">
                <div class="loading">載入中...</div>
            </div>
            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="empty-icon">📷</div>
                <h3>還沒有照片</h3>
            </div>
        </div>
    </div>
    
    <!-- 照片檢視 Modal -->
    <div class="modal" id="photoModal">
        <div class="modal-overlay"></div>
        <div class="modal-content modal-lg">
            <button class="modal-close">&times;</button>
            <div class="photo-detail-view">
                <div class="photo-detail-image">
                    <img id="modalImage" src="" alt="">
                </div>
                <div class="photo-detail-sidebar">
                    <div class="photo-detail-header">
                        <div class="photo-author">
                            <div class="author-avatar" id="modalAuthorAvatar"></div>
                            <div class="author-info">
                                <span class="author-name" id="modalAuthorName"></span>
                                <span class="photo-date" id="modalDate"></span>
                            </div>
                        </div>
                    </div>
                    <div class="photo-detail-caption" id="modalCaption"></div>
                    
                    <div class="photo-detail-actions">
                        <button class="like-btn" id="likeBtn" onclick="toggleLike()">
                            <span class="like-icon">♡</span>
                            <span class="like-count" id="likeCount">0</span>
                        </button>
                    </div>
                    
                    <div class="photo-detail-comments">
                        <h4>留言</h4>
                        <div class="comments-list" id="commentsList"></div>
                        <form class="comment-form" id="commentForm">
                            <input type="text" id="commentInput" placeholder="寫下你的留言..." maxlength="1000">
                            <button type="submit" class="btn btn-primary btn-sm">送出</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast -->
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        const PROFILE_DATA = {
            userId: <?php echo $userId; ?>,
            profileUserId: <?php echo $profileUserId; ?>,
            isSelf: <?php echo $isSelf ? 'true' : 'false'; ?>,
            relation: '<?php echo $relation; ?>'
        };
        
        let currentPhotoId = null;
        let photosData = [];
        
        document.addEventListener('DOMContentLoaded', () => {
            loadPhotos();
            initEventListeners();
        });
        
        function initEventListeners() {
            // Modal 關閉
            document.querySelector('.modal-close').addEventListener('click', closeModal);
            document.querySelector('.modal-overlay').addEventListener('click', closeModal);
            
            // 留言表單
            document.getElementById('commentForm').addEventListener('submit', handleCommentSubmit);
        }
        
        async function loadPhotos() {
            try {
                const response = await fetch(`api.php?action=get_user_photos&user_id=${PROFILE_DATA.profileUserId}`);
                const data = await response.json();
                
                if (data.error) {
                    showToast(data.error, 'error');
                    return;
                }
                
                photosData = data.photos;
                renderPhotos(data.photos);
            } catch (error) {
                console.error('載入失敗:', error);
                showToast('載入失敗', 'error');
            }
        }
        
        function renderPhotos(photos) {
            const grid = document.getElementById('photoGrid');
            const empty = document.getElementById('emptyState');
            
            if (photos.length === 0) {
                grid.innerHTML = '';
                empty.style.display = 'flex';
                return;
            }
            
            empty.style.display = 'none';
            grid.innerHTML = photos.map(photo => `
                <div class="photo-card" onclick="openPhoto(${photo.id})">
                    <div class="photo-card-image">
                        <img src="${escapeHtml(photo.image_url)}" alt="">
                        <div class="photo-card-overlay">
                            <div class="photo-card-stats">
                                <span>♥ ${photo.like_count}</span>
                                <span>💬 ${photo.comment_count}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        function openPhoto(photoId) {
            const photo = photosData.find(p => p.id == photoId);
            if (!photo) return;
            
            currentPhotoId = photoId;
            
            document.getElementById('modalImage').src = photo.image_url;
            document.getElementById('modalCaption').textContent = photo.caption || '';
            document.getElementById('modalDate').textContent = formatDate(photo.created_at);
            document.getElementById('modalAuthorName').textContent = photo.username;
            document.getElementById('modalAuthorAvatar').textContent = photo.username.charAt(0).toUpperCase();
            document.getElementById('likeCount').textContent = photo.like_count;
            
            const likeBtn = document.getElementById('likeBtn');
            if (photo.is_liked) {
                likeBtn.classList.add('liked');
                likeBtn.querySelector('.like-icon').textContent = '♥';
            } else {
                likeBtn.classList.remove('liked');
                likeBtn.querySelector('.like-icon').textContent = '♡';
            }
            
            loadComments(photoId);
            
            document.getElementById('photoModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('photoModal').classList.remove('active');
            document.body.style.overflow = '';
            currentPhotoId = null;
        }
        
        async function toggleLike() {
            if (!currentPhotoId) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_like');
                formData.append('photo_id', currentPhotoId);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    const likeBtn = document.getElementById('likeBtn');
                    document.getElementById('likeCount').textContent = data.like_count;
                    
                    if (data.is_liked) {
                        likeBtn.classList.add('liked');
                        likeBtn.querySelector('.like-icon').textContent = '♥';
                    } else {
                        likeBtn.classList.remove('liked');
                        likeBtn.querySelector('.like-icon').textContent = '♡';
                    }
                    
                    // 更新本地資料
                    const photo = photosData.find(p => p.id == currentPhotoId);
                    if (photo) {
                        photo.like_count = data.like_count;
                        photo.is_liked = data.is_liked;
                    }
                }
            } catch (error) {
                showToast('操作失敗', 'error');
            }
        }
        
        async function loadComments(photoId) {
            try {
                const response = await fetch(`api.php?action=get_comments&photo_id=${photoId}`);
                const data = await response.json();
                
                const list = document.getElementById('commentsList');
                if (data.comments.length === 0) {
                    list.innerHTML = '<p class="no-comments">還沒有留言</p>';
                } else {
                    list.innerHTML = data.comments.map(c => `
                        <div class="comment-item">
                            <div class="comment-avatar">${c.username.charAt(0).toUpperCase()}</div>
                            <div class="comment-content">
                                <span class="comment-author">${escapeHtml(c.username)}</span>
                                <p>${escapeHtml(c.content)}</p>
                                <span class="comment-date">${formatDate(c.created_at)}</span>
                            </div>
                            ${c.is_own ? `<button class="delete-comment-btn" onclick="deleteComment(${c.id})">×</button>` : ''}
                        </div>
                    `).join('');
                }
            } catch (error) {
                console.error('載入留言失敗:', error);
            }
        }
        
        async function handleCommentSubmit(e) {
            e.preventDefault();
            
            const input = document.getElementById('commentInput');
            const content = input.value.trim();
            
            if (!content || !currentPhotoId) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'add_comment');
                formData.append('photo_id', currentPhotoId);
                formData.append('content', content);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    input.value = '';
                    loadComments(currentPhotoId);
                    
                    // 更新留言數
                    const photo = photosData.find(p => p.id == currentPhotoId);
                    if (photo) photo.comment_count++;
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('留言失敗', 'error');
            }
        }
        
        async function deleteComment(commentId) {
            if (!confirm('確定要刪除這則留言嗎？')) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_comment');
                formData.append('comment_id', commentId);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    loadComments(currentPhotoId);
                    showToast('留言已刪除', 'success');
                }
            } catch (error) {
                showToast('刪除失敗', 'error');
            }
        }
        
        // 好友功能
        async function addFriend(friendId) {
            try {
                const formData = new FormData();
                formData.append('action', 'send_friend_request');
                formData.append('friend_id', friendId);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    location.reload();
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('操作失敗', 'error');
            }
        }
        
        async function acceptFriend(friendId) {
            try {
                const formData = new FormData();
                formData.append('action', 'accept_friend_request');
                formData.append('friend_id', friendId);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    showToast('已成為好友！', 'success');
                    location.reload();
                }
            } catch (error) {
                showToast('操作失敗', 'error');
            }
        }
        
        async function rejectFriend(friendId) {
            try {
                const formData = new FormData();
                formData.append('action', 'reject_friend_request');
                formData.append('friend_id', friendId);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    showToast('已拒絕請求', 'success');
                    location.reload();
                }
            } catch (error) {
                showToast('操作失敗', 'error');
            }
        }
        
        async function removeFriend(friendId) {
            if (!confirm('確定要移除這位好友嗎？')) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'remove_friend');
                formData.append('friend_id', friendId);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    showToast('已移除好友', 'success');
                    location.reload();
                }
            } catch (error) {
                showToast('操作失敗', 'error');
            }
        }
        
        // 工具函數
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('zh-TW');
        }
        
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        async function handleAiAnalyze() {
            if (!confirm('確定要分析您的所有照片嗎？這可能需要一點時間。')) {
                return;
            }
            
            showToast('正在分析中，請稍候...', 'info');
            
            try {
                const response = await fetch('api.php?action=analyze_user_profile');
                const data = await response.json();
                
                if (data.success) {
                    showToast(`分析完成！預估年齡：${data.age}`, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(data.error || '分析失敗', 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('發生錯誤，請稍後再試', 'error');
            }
        }
    </script>
</body>
</html>
