<?php
require_once 'config.php';
requireLogin();

$userId = getCurrentUserId();
$username = getCurrentUsername();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>好友 - Photo Rewind</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="friends-page">
        <header class="profile-header-nav">
            <a href="home.php" class="back-btn">← 返回</a>
            <div class="logo">
                <span>Photo Rewind</span>
            </div>
            <a href="profile.php" class="nav-link">我的主頁</a>
        </header>
        
        <div class="friends-container">
            <div class="roulette-section">
                <h2>Photo Roulette</h2>
                <div id="rouletteGame" class="roulette-game">
                    <div class="roulette-start">
                        <p>猜猜這張照片是哪位好友拍的？</p>
                        <button class="btn btn-primary" onclick="startRoulette()">開始遊戲</button>
                    </div>
                    <div class="roulette-play" style="display: none;">
                        <div class="roulette-photo-container">
                            <img id="rouletteImage" src="" alt="Mystery Photo">
                        </div>
                        <div class="roulette-options" id="rouletteOptions">
                        </div>
                    </div>
                    <div class="roulette-result" style="display: none;">
                        <h3 id="rouletteResultTitle"></h3>
                        <p id="rouletteResultText"></p>
                        <button class="btn btn-secondary" onclick="startRoulette()">再玩一次</button>
                    </div>
                </div>
            </div>

            <div class="search-section">
                <h2>尋找好友</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="輸入使用者名稱搜尋...">
                    <button class="btn btn-primary" onclick="searchUsers()">搜尋</button>
                </div>
                <div class="search-results" id="searchResults"></div>
            </div>
            
            <div class="requests-section">
                <h2>好友請求 <span class="badge" id="requestBadge"></span></h2>
                <div class="requests-list" id="requestsList">
                    <p class="empty-text">載入中...</p>
                </div>
            </div>
            
            <div class="friends-section">
                <h2>我的好友 <span class="badge" id="friendBadge"></span></h2>
                <div class="friends-list" id="friendsList">
                    <p class="empty-text">載入中...</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        const USER_ID = <?php echo $userId; ?>;
        
        document.addEventListener('DOMContentLoaded', () => {
            loadFriendRequests();
            loadFriends();
            
            document.getElementById('searchInput').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') searchUsers();
            });
        });
        
        async function searchUsers() {
            const query = document.getElementById('searchInput').value.trim();
            const results = document.getElementById('searchResults');
            
            if (query.length < 2) {
                results.innerHTML = '<p class="empty-text">請輸入至少 2 個字元</p>';
                return;
            }
            
            try {
                const response = await fetch(`api.php?action=search_users&query=${encodeURIComponent(query)}`);
                const data = await response.json();
                
                if (data.users.length === 0) {
                    results.innerHTML = '<p class="empty-text">找不到符合的使用者</p>';
                    return;
                }
                
                results.innerHTML = data.users.map(user => `
                    <div class="user-card">
                        <a href="profile.php?id=${user.id}" class="user-info">
                            <div class="user-avatar">${user.username.charAt(0).toUpperCase()}</div>
                            <div class="user-details">
                                <span class="user-name">${escapeHtml(user.username)}</span>
                                ${user.bio ? `<span class="user-bio">${escapeHtml(user.bio)}</span>` : ''}
                            </div>
                        </a>
                        <div class="user-actions">
                            ${renderUserAction(user)}
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                results.innerHTML = '<p class="empty-text">搜尋失敗</p>';
            }
        }
        
        function renderUserAction(user) {
            switch (user.relation) {
                case 'friend':
                    return '<span class="relation-badge friend">✓ 好友</span>';
                case 'pending_sent':
                    return '<span class="relation-badge pending">已送出請求</span>';
                case 'pending_received':
                    return `
                        <button class="btn btn-primary btn-sm" onclick="acceptRequest(${user.id})">接受</button>
                        <button class="btn btn-secondary btn-sm" onclick="rejectRequest(${user.id})">拒絕</button>
                    `;
                default:
                    return `<button class="btn btn-primary btn-sm" onclick="sendRequest(${user.id})">+ 加好友</button>`;
            }
        }
        
        async function loadFriendRequests() {
            try {
                const response = await fetch('api.php?action=get_friend_requests');
                const data = await response.json();
                
                const list = document.getElementById('requestsList');
                const badge = document.getElementById('requestBadge');
                
                badge.textContent = data.requests.length || '';
                
                if (data.requests.length === 0) {
                    list.innerHTML = '<p class="empty-text">沒有待處理的好友請求</p>';
                    return;
                }
                
                list.innerHTML = data.requests.map(user => `
                    <div class="user-card" id="request-${user.id}">
                        <a href="profile.php?id=${user.id}" class="user-info">
                            <div class="user-avatar">${user.username.charAt(0).toUpperCase()}</div>
                            <div class="user-details">
                                <span class="user-name">${escapeHtml(user.username)}</span>
                                ${user.bio ? `<span class="user-bio">${escapeHtml(user.bio)}</span>` : ''}
                            </div>
                        </a>
                        <div class="user-actions">
                            <button class="btn btn-primary btn-sm" onclick="acceptRequest(${user.id})">接受</button>
                            <button class="btn btn-secondary btn-sm" onclick="rejectRequest(${user.id})">拒絕</button>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('載入失敗:', error);
            }
        }
        
        async function loadFriends() {
            try {
                const response = await fetch('api.php?action=get_friends');
                const data = await response.json();
                
                const list = document.getElementById('friendsList');
                const badge = document.getElementById('friendBadge');
                
                badge.textContent = data.friends.length || '';
                
                if (data.friends.length === 0) {
                    list.innerHTML = '<p class="empty-text">還沒有好友，快去找朋友吧！</p>';
                    return;
                }
                
                list.innerHTML = data.friends.map(user => `
                    <div class="user-card" id="friend-${user.id}">
                        <a href="profile.php?id=${user.id}" class="user-info">
                            <div class="user-avatar">${user.username.charAt(0).toUpperCase()}</div>
                            <div class="user-details">
                                <span class="user-name">${escapeHtml(user.username)}</span>
                                ${user.bio ? `<span class="user-bio">${escapeHtml(user.bio)}</span>` : ''}
                            </div>
                        </a>
                        <div class="user-actions">
                            <a href="profile.php?id=${user.id}" class="btn btn-secondary btn-sm">查看主頁</a>
                            <button class="btn btn-outline btn-sm" onclick="removeFriend(${user.id})">移除</button>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('載入失敗:', error);
            }
        }
        
        async function sendRequest(friendId) {
            try {
                const formData = new FormData();
                formData.append('action', 'send_friend_request');
                formData.append('friend_id', friendId);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    searchUsers();
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('操作失敗', 'error');
            }
        }
        
        async function acceptRequest(friendId) {
            try {
                const formData = new FormData();
                formData.append('action', 'accept_friend_request');
                formData.append('friend_id', friendId);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    showToast('已成為好友！', 'success');
                    loadFriendRequests();
                    loadFriends();
                }
            } catch (error) {
                showToast('操作失敗', 'error');
            }
        }
        
        async function rejectRequest(friendId) {
            try {
                const formData = new FormData();
                formData.append('action', 'reject_friend_request');
                formData.append('friend_id', friendId);
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    showToast('已拒絕請求', 'success');
                    loadFriendRequests();
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
                    loadFriends();
                }
            } catch (error) {
                showToast('操作失敗', 'error');
            }
        }

        async function startRoulette() {
            const gameContainer = document.getElementById('rouletteGame');
            const startDiv = gameContainer.querySelector('.roulette-start');
            const playDiv = gameContainer.querySelector('.roulette-play');
            const resultDiv = gameContainer.querySelector('.roulette-result');
            
            startDiv.style.display = 'none';
            resultDiv.style.display = 'none';
            playDiv.style.display = 'block';
            playDiv.innerHTML = '<p>載入中...</p>';
            
            try {
                const response = await fetch('api.php?action=get_photo_roulette');
                const data = await response.json();
                
                if (data.error) {
                    playDiv.innerHTML = `<p class="error-text">${data.error}</p>`;
                    setTimeout(() => {
                        playDiv.style.display = 'none';
                        startDiv.style.display = 'block';
                    }, 3000);
                    return;
                }
                
                playDiv.innerHTML = `
                    <div class="roulette-photo-container">
                        <img src="${data.photo.image_url}" alt="Mystery Photo">
                    </div>
                    <div class="roulette-options">
                        ${data.options.map(user => `
                            <button class="btn btn-outline option-btn" onclick="checkRouletteAnswer(${user.id}, ${data.correct_user_id}, '${escapeHtml(user.username).replace(/'/g, "\\'")}')">
                                <div class="user-avatar-small">${user.username.charAt(0).toUpperCase()}</div>
                                <span>${escapeHtml(user.username)}</span>
                            </button>
                        `).join('')}
                    </div>
                `;
                
            } catch (error) {
                console.error(error);
                playDiv.innerHTML = '<p class="error-text">發生錯誤，請稍後再試</p>';
            }
        }

        function checkRouletteAnswer(selectedId, correctId, selectedName) {
            const gameContainer = document.getElementById('rouletteGame');
            const playDiv = gameContainer.querySelector('.roulette-play');
            const resultDiv = gameContainer.querySelector('.roulette-result');
            const resultTitle = document.getElementById('rouletteResultTitle');
            const resultText = document.getElementById('rouletteResultText');
            
            playDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            
            if (selectedId === correctId) {
                resultTitle.textContent = '答對了！';
                resultTitle.className = 'success-text';
                resultText.textContent = `沒錯，這就是 ${selectedName} 的照片！`;
            } else {
                resultTitle.textContent = '答錯了...';
                resultTitle.className = 'error-text';
                resultText.textContent = `可惜，這不是 ${selectedName} 的照片。`;
            }
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
    </script>
</body>
</html>
