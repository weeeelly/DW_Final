let currentPhotoId = null;
let photosData = [];

document.addEventListener('DOMContentLoaded', () => {
    loadPhotos();
    initEventListeners();
});

function initEventListeners() {
    const modalClose = document.querySelector('.modal-close');
    const modalOverlay = document.querySelector('.modal-overlay');

    if (modalClose) modalClose.addEventListener('click', closeModal);
    if (modalOverlay) modalOverlay.addEventListener('click', closeModal);

    const commentForm = document.getElementById('commentForm');
    if (commentForm) commentForm.addEventListener('submit', handleCommentSubmit);
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
        if (empty) empty.style.display = 'flex';
        return;
    }

    if (empty) empty.style.display = 'none';
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
    const modal = document.getElementById('photoModal');
    if (modal) modal.classList.remove('active');
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
