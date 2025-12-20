document.addEventListener('DOMContentLoaded', () => {
    loadFriendRequests();
    loadFriends();

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') searchUsers();
        });
    }
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

        if (badge) badge.textContent = data.requests.length || '';

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

        if (badge) badge.textContent = data.friends.length || '';

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
    if (!gameContainer) return;

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
