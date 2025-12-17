/**
 * Simple Retro - 照片日記前端邏輯
 */

// ==================== 全域變數 ====================
let currentAlbumId = null;
let currentPhotoId = null;
let deleteTarget = null;

// ==================== 初始化 ====================
document.addEventListener('DOMContentLoaded', () => {
    initEventListeners();
    loadPhotos();
});

function initEventListeners() {
    // 側邊欄相簿點擊
    document.querySelectorAll('.album-item').forEach(item => {
        item.addEventListener('click', handleAlbumClick);
    });

    // 新增照片按鈕
    document.getElementById('addPhotoBtn').addEventListener('click', () => {
        openPhotoModal();
    });

    // 新增相簿按鈕
    document.getElementById('addAlbumBtn').addEventListener('click', () => {
        openAlbumModal();
    });

    // 照片表單提交
    document.getElementById('photoForm').addEventListener('submit', handlePhotoSubmit);

    // 相簿表單提交
    document.getElementById('albumForm').addEventListener('submit', handleAlbumSubmit);

    // 圖片檔案選擇預覽
    document.getElementById('imageFile').addEventListener('change', handleFileSelect);

    // 拖曳上傳
    const fileWrapper = document.querySelector('.file-upload-wrapper');
    if (fileWrapper) {
        fileWrapper.addEventListener('dragover', handleDragOver);
        fileWrapper.addEventListener('dragleave', handleDragLeave);
        fileWrapper.addEventListener('drop', handleDrop);
    }

    // Modal 關閉
    document.querySelectorAll('.modal-close, .modal-cancel, .modal-overlay').forEach(el => {
        el.addEventListener('click', closeAllModals);
    });

    // 防止 modal 內容點擊關閉
    document.querySelectorAll('.modal-content').forEach(el => {
        el.addEventListener('click', e => e.stopPropagation());
    });

    // 檢視照片 Modal 的操作
    document.getElementById('editPhotoFromView').addEventListener('click', () => {
        closeModal('viewPhotoModal');
        openPhotoModal(currentPhotoId);
    });

    document.getElementById('deletePhotoFromView').addEventListener('click', () => {
        closeModal('viewPhotoModal');
        confirmDelete('photo', currentPhotoId);
    });

    // 確認刪除
    document.getElementById('confirmDeleteBtn').addEventListener('click', handleConfirmDelete);

    // 手機版選單
    document.getElementById('mobileMenuBtn').addEventListener('click', toggleSidebar);

    // 編輯/刪除相簿按鈕
    document.querySelectorAll('.edit-album-btn').forEach(btn => {
        btn.addEventListener('click', handleEditAlbumClick);
    });

    document.querySelectorAll('.delete-album-btn').forEach(btn => {
        btn.addEventListener('click', handleDeleteAlbumClick);
    });
}

// ==================== 照片功能 ====================
async function loadPhotos(albumId = null) {
    const photoGrid = document.getElementById('photoGrid');
    const emptyState = document.getElementById('emptyState');

    photoGrid.innerHTML = '<div class="loading">載入中...</div>';
    emptyState.style.display = 'none';

    try {
        let url = 'api.php?action=get_photos';
        if (albumId) {
            url += `&album_id=${albumId}`;
        }

        const response = await fetch(url);
        const data = await response.json();

        if (data.error) {
            showToast(data.error, 'error');
            return;
        }

        if (data.photos.length === 0) {
            photoGrid.innerHTML = '';
            emptyState.style.display = 'flex';
        } else {
            renderPhotos(data.photos);
        }
    } catch (error) {
        console.error('載入照片失敗:', error);
        showToast('載入照片失敗', 'error');
    }
}

function renderPhotos(photos) {
    const photoGrid = document.getElementById('photoGrid');
    const emptyState = document.getElementById('emptyState');

    emptyState.style.display = 'none';

    photoGrid.innerHTML = photos.map(photo => `
        <div class="photo-card" data-photo-id="${photo.id}" 
             data-ai-age="${escapeHtml(photo.ai_analysis || '')}"
             data-ai-explanation="${escapeHtml(photo.ai_explanation || '')}">
            <div class="photo-card-image">
                <img src="${escapeHtml(photo.image_url)}" alt="${escapeHtml(photo.caption || '')}" 
                     onerror="this.src='https://via.placeholder.com/400x400?text=圖片載入失敗'">
                
                ${photo.ai_analysis ? `<div class="ai-badge">照片年齡：${escapeHtml(photo.ai_analysis)}</div>` : ''}

                <div class="photo-card-overlay">
                    <div class="photo-card-actions">
                        <button class="btn btn-sm btn-primary analyze-photo-btn" title="AI 測齡">AI 測齡</button>
                        <button class="btn btn-sm btn-secondary edit-photo-btn">編輯</button>
                        <button class="btn btn-sm btn-danger delete-photo-btn">刪除</button>
                    </div>
                </div>
            </div>
            <div class="photo-card-info">
                <p class="photo-card-caption">${escapeHtml(photo.caption || '無描述')}</p>
                <div class="photo-card-meta">
                    <span class="photo-card-album">${escapeHtml(photo.album_name)}</span>
                    <span class="photo-card-date">${formatDate(photo.created_at)}</span>
                </div>
            </div>
        </div>
    `).join('');

    // 綁定照片卡片事件
    photoGrid.querySelectorAll('.photo-card').forEach(card => {
        const photoId = card.dataset.photoId;

        card.querySelector('.analyze-photo-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            analyzePhoto(photoId);
        });

        card.querySelector('.edit-photo-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            openPhotoModal(photoId);
        });

        card.querySelector('.delete-photo-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            confirmDelete('photo', photoId);
        });

        // 點擊卡片檢視詳細資訊
        card.addEventListener('click', () => viewPhoto(photoId));
    });
}

async function analyzePhoto(photoId) {
    const btn = document.querySelector(`.photo-card[data-photo-id="${photoId}"] .analyze-photo-btn`);
    const originalText = btn.textContent;
    btn.textContent = '分析中...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'analyze_photo');
        formData.append('photo_id', photoId);

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            showToast(data.error, 'error');
        } else {
            // Update UI with analysis result
            const card = document.querySelector(`.photo-card[data-photo-id="${photoId}"]`);
            const cardImage = card.querySelector('.photo-card-image');
            let badge = cardImage.querySelector('.ai-badge');

            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'ai-badge';
                // Insert before overlay
                const overlay = cardImage.querySelector('.photo-card-overlay');
                cardImage.insertBefore(badge, overlay);
            }

            badge.textContent = `照片年齡：${data.age_analysis}`;

            // Update data attributes
            card.dataset.aiAge = data.age_analysis;
            card.dataset.aiExplanation = data.ai_explanation;

            showToast('AI 分析完成', 'success');
        }
    } catch (error) {
        console.error('分析失敗:', error);
        showToast('分析失敗', 'error');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

function viewPhoto(photoId) {
    const photoCard = document.querySelector(`[data-photo-id="${photoId}"]`);
    if (!photoCard) return;

    const img = photoCard.querySelector('img');
    const caption = photoCard.querySelector('.photo-card-caption').textContent;
    const album = photoCard.querySelector('.photo-card-album').textContent;
    const date = photoCard.querySelector('.photo-card-date').textContent;

    // AI Analysis Data
    const aiAge = photoCard.dataset.aiAge;
    const aiExplanation = photoCard.dataset.aiExplanation;

    document.getElementById('viewPhotoImage').src = img.src;
    document.getElementById('viewPhotoCaption').textContent = caption;
    document.getElementById('viewPhotoAlbum').textContent = `📁 ${album}`;
    document.getElementById('viewPhotoDate').textContent = `📅 ${date}`;

    // Show/Hide AI Result Section
    const aiSection = document.getElementById('viewPhotoAiResult');
    if (aiAge && aiExplanation) {
        document.getElementById('viewPhotoAge').textContent = aiAge;
        document.getElementById('viewPhotoExplanation').textContent = aiExplanation;
        aiSection.style.display = 'block';
    } else {
        aiSection.style.display = 'none';
    }

    currentPhotoId = photoId;
    openModal('viewPhotoModal');
}

function openPhotoModal(photoId = null) {
    const modal = document.getElementById('photoModal');
    const title = document.getElementById('photoModalTitle');
    const form = document.getElementById('photoForm');

    form.reset();
    document.getElementById('imagePreview').innerHTML = '<span class="preview-placeholder">選擇圖片後預覽</span>';
    document.getElementById('fileName').textContent = '';

    // 重置檔案輸入
    const fileInput = document.getElementById('imageFile');
    fileInput.value = '';

    if (photoId) {
        title.textContent = '編輯照片';
        document.getElementById('photoId').value = photoId;

        // 編輯時圖片不是必填（保留原圖）
        fileInput.removeAttribute('required');

        // 從 DOM 取得照片資料
        const photoCard = document.querySelector(`[data-photo-id="${photoId}"]`);
        if (photoCard) {
            const img = photoCard.querySelector('img');
            const caption = photoCard.querySelector('.photo-card-caption').textContent;
            const albumName = photoCard.querySelector('.photo-card-album').textContent;

            document.getElementById('caption').value = caption !== '無描述' ? caption : '';

            // 顯示目前圖片預覽
            document.getElementById('imagePreview').innerHTML = `<img src="${img.src}" alt="">`;
            document.getElementById('fileName').textContent = '（保留目前圖片，或選擇新圖片替換）';

            // 選擇相簿
            const albumSelect = document.getElementById('albumSelect');
            for (let option of albumSelect.options) {
                if (option.text === albumName) {
                    option.selected = true;
                    break;
                }
            }
        }
    } else {
        title.textContent = '新增照片';
        document.getElementById('photoId').value = '';
        // 新增時圖片必填
        fileInput.setAttribute('required', 'required');
        // 預設選擇 Recents
        document.getElementById('albumSelect').value = APP_DATA.defaultAlbumId;
    }

    openModal('photoModal');
}

async function handlePhotoSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const photoId = formData.get('photo_id');
    const fileInput = document.getElementById('imageFile');

    // 新增照片時必須有圖片
    if (!photoId && (!fileInput.files || fileInput.files.length === 0)) {
        showToast('請選擇要上傳的圖片', 'error');
        return;
    }

    formData.append('action', photoId ? 'update_photo' : 'add_photo');

    // 顯示上傳中狀態
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '上傳中...';
    submitBtn.disabled = true;

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            showToast(data.error, 'error');
            return;
        }

        showToast(photoId ? '照片已更新' : '照片已新增', 'success');
        closeAllModals();
        loadPhotos(currentAlbumId);
    } catch (error) {
        console.error('操作失敗:', error);
        showToast('操作失敗', 'error');
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

// 處理檔案選擇
function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        previewFile(file);
    }
}

// 預覽選擇的檔案
function previewFile(file) {
    const preview = document.getElementById('imagePreview');
    const fileNameSpan = document.getElementById('fileName');

    // 驗證檔案類型
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        preview.innerHTML = '<span class="preview-placeholder">不支援的檔案格式</span>';
        fileNameSpan.textContent = '';
        showToast('請選擇 JPG、PNG、GIF 或 WebP 格式的圖片', 'error');
        document.getElementById('imageFile').value = '';
        return;
    }

    // 驗證檔案大小 (10MB)
    if (file.size > 10 * 1024 * 1024) {
        preview.innerHTML = '<span class="preview-placeholder">檔案太大</span>';
        fileNameSpan.textContent = '';
        showToast('檔案大小不能超過 10MB', 'error');
        document.getElementById('imageFile').value = '';
        return;
    }

    // 顯示檔名
    fileNameSpan.textContent = file.name;

    // 預覽圖片
    const reader = new FileReader();
    reader.onload = (e) => {
        preview.innerHTML = `<img src="${e.target.result}" alt="預覽">`;
    };
    reader.readAsDataURL(file);
}

// 拖曳上傳處理
function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.add('drag-over');
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('drag-over');

    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const fileInput = document.getElementById('imageFile');
        fileInput.files = files;
        previewFile(files[0]);
    }
}

async function deletePhoto(photoId) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete_photo');
        formData.append('photo_id', photoId);

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            showToast(data.error, 'error');
            return;
        }

        showToast('照片已刪除', 'success');
        loadPhotos(currentAlbumId);
    } catch (error) {
        console.error('刪除失敗:', error);
        showToast('刪除失敗', 'error');
    }
}

// ==================== 相簿功能 ====================
function handleAlbumClick(e) {
    // 如果點擊的是編輯或刪除按鈕，不處理
    if (e.target.closest('.album-actions')) return;

    const albumItem = e.currentTarget;
    const albumId = albumItem.dataset.albumId;
    const albumName = albumItem.dataset.albumName;

    // 更新選中狀態
    document.querySelectorAll('.album-item').forEach(item => item.classList.remove('active'));
    albumItem.classList.add('active');

    // 更新標題
    document.getElementById('currentAlbumTitle').textContent = albumName;

    currentAlbumId = albumId;
    loadPhotos(albumId);

    // 手機版關閉側邊欄
    document.querySelector('.sidebar').classList.remove('active');
}

function openAlbumModal(albumId = null, albumName = '') {
    const modal = document.getElementById('albumModal');
    const title = document.getElementById('albumModalTitle');
    const form = document.getElementById('albumForm');

    form.reset();

    if (albumId) {
        title.textContent = '編輯相簿';
        document.getElementById('albumId').value = albumId;
        document.getElementById('albumName').value = albumName;
    } else {
        title.textContent = '新增相簿';
        document.getElementById('albumId').value = '';
    }

    openModal('albumModal');
}

function handleEditAlbumClick(e) {
    e.stopPropagation();
    const albumItem = e.target.closest('.album-item');
    const albumId = albumItem.dataset.albumId;
    const albumName = albumItem.dataset.albumName;
    openAlbumModal(albumId, albumName);
}

function handleDeleteAlbumClick(e) {
    e.stopPropagation();
    const albumItem = e.target.closest('.album-item');
    const albumId = albumItem.dataset.albumId;
    confirmDelete('album', albumId);
}

async function handleAlbumSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const albumId = formData.get('album_id');
    formData.append('action', albumId ? 'update_album' : 'add_album');

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            showToast(data.error, 'error');
            return;
        }

        showToast(albumId ? '相簿已更新' : '相簿已新增', 'success');
        closeAllModals();

        // 重新載入頁面以更新相簿列表
        window.location.reload();
    } catch (error) {
        console.error('操作失敗:', error);
        showToast('操作失敗', 'error');
    }
}

async function deleteAlbum(albumId) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete_album');
        formData.append('album_id', albumId);

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            showToast(data.error, 'error');
            return;
        }

        showToast('相簿已刪除', 'success');
        window.location.reload();
    } catch (error) {
        console.error('刪除失敗:', error);
        showToast('刪除失敗', 'error');
    }
}

// ==================== 確認刪除 ====================
function confirmDelete(type, id) {
    deleteTarget = { type, id };

    const message = type === 'photo'
        ? '確定要刪除這張照片嗎？'
        : '確定要刪除此相簿嗎？相簿內的所有照片也會一併刪除。';

    document.getElementById('confirmMessage').textContent = message;
    openModal('confirmModal');
}

function handleConfirmDelete() {
    if (!deleteTarget) return;

    if (deleteTarget.type === 'photo') {
        deletePhoto(deleteTarget.id);
    } else if (deleteTarget.type === 'album') {
        deleteAlbum(deleteTarget.id);
    }

    closeModal('confirmModal');
    deleteTarget = null;
}

// ==================== Modal 操作 ====================
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.style.overflow = '';
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('active');
    });
    document.body.style.overflow = '';
}

// ==================== Toast 通知 ====================
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast - ${type} `;
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ==================== 側邊欄 ====================
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}

// ==================== 工具函數 ====================
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}
