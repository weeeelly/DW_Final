/**
 * Photo Rewind - 照片日記前端邏輯
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

    // 遊戲按鈕
    const startGameBtn = document.getElementById('startGameBtn');
    if (startGameBtn) {
        startGameBtn.addEventListener('click', () => {
            if (window.friendMemoryGame) {
                window.friendMemoryGame.openGameModal();
            }
        });
    }

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

    document.getElementById('imageEditFromView').addEventListener('click', () => {
        closeModal('viewPhotoModal');
        openImageEditor(currentPhotoId);
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

    // 圖片編輯器事件監聽
    setupImageEditor();
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
                        <button class="btn btn-sm btn-secondary image-edit-btn">修圖</button>
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

        card.querySelector('.image-edit-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            openImageEditor(photoId);
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
        // 完全清空預覽區域，移除任何美編後的樣式
        preview.innerHTML = '';

        // 創建新的圖片元素
        const newImg = document.createElement('img');
        newImg.src = e.target.result;
        newImg.alt = '預覽';

        // 添加到預覽區域
        preview.appendChild(newImg);

        // 顯示美編按鈕
        const designBtn = document.getElementById('openDesignBtn');
        if (designBtn) {
            designBtn.style.display = 'inline-block';
        }
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

// ==================== 圖片編輯器 ====================
let imageEditor = {
    currentPhotoId: null,
    currentFilter: 'none',
    currentAdjustments: {
        brightness: 100,
        contrast: 100,
        saturation: 100
    },
    stickers: []
};

function setupImageEditor() {
    // 標籤切換
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            switchEditorTab(e.target.dataset.tab);
        });
    });

    // 濾鏡選擇
    document.querySelectorAll('.filter-item').forEach(item => {
        item.addEventListener('click', (e) => {
            applyFilter(e.currentTarget.dataset.filter);
        });
    });

    // 貼圖選擇
    document.querySelectorAll('.sticker-item').forEach(item => {
        item.addEventListener('click', (e) => {
            selectSticker(e.target.dataset.sticker);
        });
    });

    // 貼圖大小調整
    const stickerSizeSlider = document.getElementById('stickerSize');
    const stickerSizeValue = document.getElementById('stickerSizeValue');
    stickerSizeSlider.addEventListener('input', (e) => {
        stickerSizeValue.textContent = e.target.value + 'px';
    });

    // 調整控制項
    setupAdjustmentControls();

    // 重置按鈕
    document.getElementById('resetAdjustments').addEventListener('click', resetAdjustments);

    // 保存按鈕
    document.getElementById('saveEditedImage').addEventListener('click', saveEditedImage);
}

function openImageEditor(photoId) {
    const photoCard = document.querySelector(`[data-photo-id="${photoId}"]`);
    if (!photoCard) return;

    const img = photoCard.querySelector('img');
    imageEditor.currentPhotoId = photoId;

    // 載入圖片到編輯器
    const editImage = document.getElementById('editImage');
    editImage.src = img.src;

    // 重置編輯器狀態
    resetEditor();

    openModal('imageEditModal');
}

function switchEditorTab(tabName) {
    // 切換標籤樣式
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // 切換面板
    document.querySelectorAll('.editor-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    document.getElementById(`${tabName}Panel`).classList.add('active');
}

function applyFilter(filterValue) {
    imageEditor.currentFilter = filterValue;
    updateImageDisplay();

    // 更新選中狀態
    document.querySelectorAll('.filter-item').forEach(item => {
        item.classList.remove('selected');
    });
    document.querySelector(`[data-filter="${filterValue}"]`).classList.add('selected');
}

function selectSticker(stickerEmoji) {
    const stickerControls = document.querySelector('.sticker-controls');
    stickerControls.style.display = 'block';

    // 顯示貼圖選擇器在圖片上
    const overlay = document.getElementById('stickerOverlay');
    const size = document.getElementById('stickerSize').value;

    const stickerElement = document.createElement('div');
    stickerElement.className = 'placed-sticker';
    stickerElement.textContent = stickerEmoji;
    stickerElement.style.fontSize = size + 'px';
    stickerElement.style.left = '50%';
    stickerElement.style.top = '50%';
    stickerElement.style.transform = 'translate(-50%, -50%)';

    // 使貼圖可拖曳
    makeStickerDraggable(stickerElement);

    // 添加刪除功能
    stickerElement.addEventListener('dblclick', () => {
        stickerElement.remove();
        updateStickers();
    });

    overlay.appendChild(stickerElement);
    updateStickers();
}

function makeStickerDraggable(element) {
    let isDragging = false;
    let currentX;
    let currentY;
    let initialX;
    let initialY;
    let xOffset = 0;
    let yOffset = 0;

    element.addEventListener('mousedown', dragStart);
    element.addEventListener('touchstart', dragStart);
    document.addEventListener('mousemove', drag);
    document.addEventListener('touchmove', drag);
    document.addEventListener('mouseup', dragEnd);
    document.addEventListener('touchend', dragEnd);

    function dragStart(e) {
        if (e.type === 'touchstart') {
            initialX = e.touches[0].clientX - xOffset;
            initialY = e.touches[0].clientY - yOffset;
        } else {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
        }

        if (e.target === element) {
            isDragging = true;
        }
    }

    function drag(e) {
        if (isDragging) {
            e.preventDefault();

            if (e.type === 'touchmove') {
                currentX = e.touches[0].clientX - initialX;
                currentY = e.touches[0].clientY - initialY;
            } else {
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;
            }

            xOffset = currentX;
            yOffset = currentY;

            element.style.transform = `translate(${currentX}px, ${currentY}px)`;
        }
    }

    function dragEnd() {
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
    }
}

function setupAdjustmentControls() {
    const brightnessSlider = document.getElementById('brightnessSlider');
    const contrastSlider = document.getElementById('contrastSlider');
    const saturationSlider = document.getElementById('saturationSlider');

    const brightnessValue = document.getElementById('brightnessValue');
    const contrastValue = document.getElementById('contrastValue');
    const saturationValue = document.getElementById('saturationValue');

    brightnessSlider.addEventListener('input', (e) => {
        imageEditor.currentAdjustments.brightness = e.target.value;
        brightnessValue.textContent = e.target.value + '%';
        updateImageDisplay();
    });

    contrastSlider.addEventListener('input', (e) => {
        imageEditor.currentAdjustments.contrast = e.target.value;
        contrastValue.textContent = e.target.value + '%';
        updateImageDisplay();
    });

    saturationSlider.addEventListener('input', (e) => {
        imageEditor.currentAdjustments.saturation = e.target.value;
        saturationValue.textContent = e.target.value + '%';
        updateImageDisplay();
    });
}

function updateImageDisplay() {
    const editImage = document.getElementById('editImage');
    const { brightness, contrast, saturation } = imageEditor.currentAdjustments;

    let filter = '';
    if (imageEditor.currentFilter !== 'none') {
        filter += imageEditor.currentFilter + ' ';
    }

    filter += `brightness(${brightness}%) contrast(${contrast}%) saturate(${saturation}%)`;
    editImage.style.filter = filter;
}

function updateStickers() {
    const stickers = document.querySelectorAll('.placed-sticker');
    imageEditor.stickers = Array.from(stickers).map(sticker => ({
        emoji: sticker.textContent,
        x: sticker.style.left || '50%',
        y: sticker.style.top || '50%',
        transform: sticker.style.transform,
        fontSize: sticker.style.fontSize
    }));
}

function resetEditor() {
    imageEditor.currentFilter = 'none';
    imageEditor.currentAdjustments = {
        brightness: 100,
        contrast: 100,
        saturation: 100
    };
    imageEditor.stickers = [];

    // 重置 UI
    document.getElementById('stickerOverlay').innerHTML = '';
    document.querySelector('.sticker-controls').style.display = 'none';

    // 重置滑桿
    document.getElementById('brightnessSlider').value = 100;
    document.getElementById('contrastSlider').value = 100;
    document.getElementById('saturationSlider').value = 100;
    document.getElementById('brightnessValue').textContent = '100%';
    document.getElementById('contrastValue').textContent = '100%';
    document.getElementById('saturationValue').textContent = '100%';

    // 重置濾鏡選擇
    document.querySelectorAll('.filter-item').forEach(item => {
        item.classList.remove('selected');
    });
    document.querySelector('[data-filter="none"]').classList.add('selected');

    updateImageDisplay();
}

function resetAdjustments() {
    imageEditor.currentAdjustments = {
        brightness: 100,
        contrast: 100,
        saturation: 100
    };

    document.getElementById('brightnessSlider').value = 100;
    document.getElementById('contrastSlider').value = 100;
    document.getElementById('saturationSlider').value = 100;
    document.getElementById('brightnessValue').textContent = '100%';
    document.getElementById('contrastValue').textContent = '100%';
    document.getElementById('saturationValue').textContent = '100%';

    updateImageDisplay();
}

async function saveEditedImage() {
    const photoId = imageEditor.currentPhotoId;

    if (!photoId) return;

    const saveBtn = document.getElementById('saveEditedImage');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = '保存中...';
    saveBtn.disabled = true;

    try {
        // 創建 canvas 來合成最終圖片
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const editImage = document.getElementById('editImage');

        // 設置 canvas 尺寸
        canvas.width = editImage.naturalWidth;
        canvas.height = editImage.naturalHeight;

        // 繪製原始圖片
        ctx.filter = editImage.style.filter || 'none';
        ctx.drawImage(editImage, 0, 0, canvas.width, canvas.height);

        // 添加貼圖
        const stickers = document.querySelectorAll('.placed-sticker');
        stickers.forEach(sticker => {
            const rect = editImage.getBoundingClientRect();
            const stickerRect = sticker.getBoundingClientRect();

            // 計算貼圖在圖片上的相對位置
            const x = ((stickerRect.left + stickerRect.width / 2 - rect.left) / rect.width) * canvas.width;
            const y = ((stickerRect.top + stickerRect.height / 2 - rect.top) / rect.height) * canvas.height;
            const fontSize = parseInt(sticker.style.fontSize) * (canvas.width / rect.width);

            ctx.filter = 'none'; // 貼圖不套用濾鏡
            ctx.font = `${fontSize}px Arial`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(sticker.textContent, x, y);
        });

        // 將 canvas 轉換為 Blob
        const blob = await new Promise(resolve => {
            canvas.toBlob(resolve, 'image/jpeg', 0.9);
        });

        // 準備表單數據
        const formData = new FormData();
        formData.append('action', 'update_edited_image');
        formData.append('photo_id', photoId);
        formData.append('edited_image', blob, 'edited.jpg');

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            showToast(data.error, 'error');
            return;
        }

        showToast('修圖已保存', 'success');
        closeModal('imageEditModal');

        // 重新載入照片以顯示更新
        loadPhotos(currentAlbumId);

    } catch (error) {
        console.error('保存修圖失敗:', error);
        showToast('保存失敗', 'error');
    } finally {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    }
}

// ==================== 朋友記憶遊戲 ====================

class FriendMemoryGame {
    constructor() {
        this.gameData = null;
        this.gameSequence = [];
        this.playerSequence = [];
        this.currentBeat = 0;
        this.gamePhase = 'waiting'; // waiting, showing, playing, finished
        this.difficulty = 8; // 固定8張照片
        this.fixedBPM = 180; // 固定180 BPM
        this.gameTimer = null;
        this.beatTimer = null;
        this.startTime = null;
        this.audio = null;
        
        this.initEventListeners();
    }
    
    initEventListeners() {        
        // 遊戲內按鈕
        const startGameButton = document.getElementById('startGameButton');
        if (startGameButton) {
            startGameButton.addEventListener('click', () => {
                this.startGame();
            });
        }
        
        const stopGameBtn = document.getElementById('stopGameBtn');
        if (stopGameBtn) {
            stopGameBtn.addEventListener('click', () => {
                this.stopGame();
            });
        }
        
        const playAgainBtn = document.getElementById('playAgainBtn');
        if (playAgainBtn) {
            playAgainBtn.addEventListener('click', () => {
                this.resetGame();
            });
        }
        
        // 固定難度為8，無需選擇功能
    }
    
    async openGameModal() {
        try {
            // 載入遊戲數據
            const response = await fetch('api.php?action=get_game_friends_data');
            
            // 檢查回應狀態
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            console.log('API Response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON 解析錯誤:', parseError);
                console.error('原始回應:', responseText);
                throw new Error('伺服器回應格式錯誤');
            }
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            if (data.friends && data.friends.length >= 4) {
                this.gameData = data.friends;
                this.showModal('gameModal');
            } else {
                showToast('需要至少4位好友才能開始遊戲', 'warning');
            }
        } catch (error) {
            console.error('載入遊戲數據失敗:', error);
            showToast(`載入失敗: ${error.message}`, 'error');
        }
    }
    
    showModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }
    
    hideModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
    
    async startGame() {
        try {
            // 切換到遊戲畫面
            this.showGameScreen('gamePlayScreen');
            
            // 初始化遊戲
            this.generateGameSequence();
            this.startTime = Date.now();
            
            // 載入背景音樂
            await this.loadGameMusic();
            
            // 播放背景音樂
            if (this.audio && this.customMusicLoaded) {
                try {
                    await this.audio.play();
                } catch (error) {
                    console.warn('無法自動播放音樂，請手動點擊播放');
                }
            }
            
            // 初始化遊戲顯示區域
            this.initGameDisplay();
            
            // 開始顯示階段
            this.gamePhase = 'showing';
            this.currentBeat = 1;
            this.updateGameInfo();
            
            // 開始節拍顯示
            this.startBeatShow();
            
        } catch (error) {
            console.error('開始遊戲失敗:', error);
            showToast('遊戲啟動失敗', 'error');
        }
    }
    
    initGameDisplay() {
        // 初始化遊戲顯示區域
        const photoDisplay = document.getElementById('photoDisplay');
        if (photoDisplay) {
            photoDisplay.innerHTML = `
                <div class="beat-indicator" id="beatIndicator">♪</div>
                <img id="currentPhoto" src="" alt="" style="display: none;">
                <div class="friend-name" id="currentFriendName" style="display: none;"></div>
            `;
        }
        
        // 隱藏選擇區域
        const nameSelection = document.getElementById('nameSelection');
        if (nameSelection) {
            nameSelection.style.display = 'none';
        }
    }
    
    generateGameSequence() {
        // 從好友數據中隨機選擇8張照片
        const shuffled = [...this.gameData].sort(() => Math.random() - 0.5);
        this.gameSequence = shuffled.slice(0, 8); // 固定使用8張
        this.playerSequence = [];
    }
    
    async loadGameMusic() {
        if (this.audio) {
            this.audio.pause();
        }
        
        try {
            // 使用固定的音樂檔案路徑
            const audio = new Audio('game_music.m4a'); // 固定音樂檔案，放在同一目錄
            audio.loop = true;
            audio.volume = 0.3;
            
            this.audio = audio;
            this.customMusicLoaded = true;
        } catch (error) {
            console.warn('無法載入背景音樂，使用節拍聲:', error);
            // 使用 Web Audio API 創建節拍聲作為備用
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                this.audioContext = audioContext;
            } catch (audioError) {
                console.warn('無法初始化音頻:', audioError);
            }
        }
    }
    
    async loadCustomMusic(file) {
        try {
            const audio = new Audio();
            audio.src = URL.createObjectURL(file);
            audio.loop = true;
            audio.volume = 0.5;
            
            // 等待音樂載入
            await new Promise((resolve, reject) => {
                audio.addEventListener('loadedmetadata', () => {
                    this.audio = audio;
                    this.customMusicLoaded = true;
                    // 自動計算節拍（BPM）
                    this.customBPM = parseInt(document.getElementById('bpmInput')?.value) || 120;
                    resolve();
                });
                audio.addEventListener('error', reject);
            });
        } catch (error) {
            console.warn('無法載入自定義音樂:', error);
        }
    }
    
    getBeatInterval() {
        // 固定使用180 BPM
        return (60 / this.fixedBPM) * 1000; // 約333毫秒一拍
    }
    
    playBeatSound() {
        if (this.audioContext) {
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);
            
            oscillator.frequency.setValueAtTime(800, this.audioContext.currentTime);
            gainNode.gain.setValueAtTime(0.1, this.audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + 0.1);
            
            oscillator.start(this.audioContext.currentTime);
            oscillator.stop(this.audioContext.currentTime + 0.1);
        }
    }
    
    startBeatShow() {
        // 重置打亂的選項，確保每次新遊戲都會重新打亂
        this.shuffledOptions = null;
        
        let beatCount = 0;
        const totalBeats = 8 * 3; // 三階段，固定24拍（8張照片）
        
        const beatInterval = setInterval(() => {
            this.playBeatSound();
            this.showBeatIndicator();
            
            beatCount++;
            this.currentBeat = beatCount;
            
            // 第一階段：準備階段 (1-8拍)
            if (beatCount <= 8) {
                this.gamePhase = 'preparing';
                this.showPreparationPhase(beatCount);
            }
            // 第二階段：展示階段 (9-16拍)
            else if (beatCount <= 16) {
                if (beatCount === 9) {
                    this.gamePhase = 'showing';
                    this.startShowingPhase();
                }
                this.showDisplayPhase(beatCount - 8);
            }
            // 第三階段：回答階段 (17-24拍)
            else {
                if (beatCount === 17) {
                    this.gamePhase = 'playing';
                    this.startPlayingPhase();
                }
                this.handlePlayerPhase(beatCount - 16);
            }
            
            this.updateGameInfo();
            
            if (beatCount >= totalBeats) {
                clearInterval(beatInterval);
                setTimeout(() => {
                    this.endGame();
                }, 500);
            }
        }, this.getBeatInterval());
        
        this.beatTimer = beatInterval;
    }
    
    showPreparationPhase(beat) {
        const gameDisplay = document.querySelector('.game-display');
        if (gameDisplay && beat === 1) {
            // 清空所有內容，避免顯示上次的遊戲內容
            gameDisplay.innerHTML = `
                <div class="game-status">
                    <h3>🛠️ 準備階段</h3>
                    <p>正在載入遊戲資料...</p>
                    <div class="preparation-dots">
                        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                    </div>
                </div>
            `;
        }
        
        // 預載入照片資料
        if (beat <= 8) {
            const friend = this.gameSequence[beat - 1];
            if (friend && friend.photo) {
                // 預載入照片
                const img = new Image();
                img.src = friend.photo.startsWith('http') ? friend.photo : 
                          friend.photo.startsWith('uploads/') ? friend.photo : 
                          `uploads/${friend.id}/${friend.photo}`;
            }
        }
    }
    
    setupNameSelection() {
        // 在準備階段就建置好選擇區域和選項
        const nameSelection = document.getElementById('nameSelection');
        nameSelection.style.display = 'block';
        
        this.generateFixedNameOptions();
    }
    
    generateFixedNameOptions() {
        const nameGrid = document.getElementById('nameGrid');
        nameGrid.innerHTML = '';
        
        // 如果選項順序還沒確定，就生成並打亂
        if (!this.shuffledOptions) {
            const correctNames = this.gameSequence.map(friend => friend.username);
            const allFriends = [...this.gameData];
            const distractorNames = allFriends
                .filter(friend => !correctNames.includes(friend.username))
                .map(friend => friend.username)
                .slice(0, 4); // 只取前4個作為干擾項
            
            // 合併所有選項並打亂一次
            const allOptions = [...correctNames, ...distractorNames];
            this.shuffledOptions = allOptions.sort(() => Math.random() - 0.5);
        }
        
        // 使用已經打亂好的固定順序
        this.shuffledOptions.forEach(name => {
            const button = document.createElement('button');
            button.className = 'name-option';
            button.textContent = name;
            button.addEventListener('click', () => this.selectName(name));
            nameGrid.appendChild(button);
        });
    }
    
    startShowingPhase() {
        const gameDisplay = document.querySelector('.game-display');
        if (gameDisplay) {
            gameDisplay.innerHTML = `
                <div class="photo-reference-grid" id="showingGrid"></div>
                <div class="name-selection" id="nameSelection" style="display: block; margin-top: 2rem;">
                    <div class="name-grid" id="nameGrid"></div>
                </div>
            `;
        }
        
        // 確保選項在這個階段就準備好
        this.generateFixedNameOptions();
    }
    
    showDisplayPhase(beat) {
        const showingGrid = document.getElementById('showingGrid');
        if (!showingGrid) return;
        
        // 逐一顯示照片
        if (beat <= 8) {
            const friend = this.gameSequence[beat - 1];
            const photoSrc = friend.photo && friend.photo !== 'null' && friend.photo !== '' ? 
                (friend.photo.startsWith('uploads/') || friend.photo.startsWith('/') || friend.photo.startsWith('http') ? 
                 friend.photo : `uploads/${friend.id}/${friend.photo}`) : '';
            
            const photoItem = document.createElement('div');
            photoItem.className = 'reference-photo-item appear';
            photoItem.innerHTML = `
                <div class="photo-order">${beat}</div>
                ${photoSrc ? `<img src="${photoSrc}" alt="${friend.username}">` : 
                  `<div class="no-photo">${friend.username.charAt(0)}</div>`}
            `;
            showingGrid.appendChild(photoItem);
        }
    }
    
    startPlayingPhase() {
        // 選擇區域已經在準備階段建置好，這裡只需初始化狀態
        this.playerSequence = []; // 重設玩家答案
        this.playerAnsweredThisBeat = false;
    }
    
    handlePlayerPhase(beat) {
        // 檢查上一拍是否有回答（除了第一拍）
        if (beat > 1 && !this.playerAnsweredThisBeat) {
            // 沒有在節拍點回答，記錄為錯誤
            this.playerSequence.push({ 
                name: '未回答', 
                correct: false,
                missed: true 
            });
        }
        
        this.playerAnsweredThisBeat = false; // 重設當前拍的回答狀態
        
        // 選項已經在準備階段生成，不需要再更新
    }
    
    showBeatIndicator() {
        let indicator = document.getElementById('beatIndicator');
        
        // 如果找不到指示器，動態創建一個
        if (!indicator) {
            const photoDisplay = document.getElementById('photoDisplay');
            if (photoDisplay) {
                indicator = document.createElement('div');
                indicator.id = 'beatIndicator';
                indicator.className = 'beat-indicator';
                indicator.textContent = '♪';
                indicator.style.cssText = `
                    font-size: 3rem;
                    opacity: 0.7;
                    transition: all 0.3s ease;
                    position: absolute;
                    z-index: 1;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                `;
                photoDisplay.appendChild(indicator);
            }
        }
        
        if (indicator) {
            indicator.style.transform = 'translate(-50%, -50%) scale(1.2)';
            indicator.style.opacity = '1';
            
            setTimeout(() => {
                if (indicator) {
                    indicator.style.transform = 'translate(-50%, -50%) scale(1)';
                    indicator.style.opacity = '0.7';
                }
            }, 200);
        }
    }
    
    showFriendPhoto(friend, keepVisible = false) {
        const photoElement = document.getElementById('currentPhoto');
        const nameElement = document.getElementById('currentFriendName');
        
        if (friend.photo && friend.photo !== 'null' && friend.photo !== '') {
            // 如果是相對路徑，使用原本的邏輯；如果是絕對路徑，直接使用
            if (friend.photo.startsWith('uploads/') || friend.photo.startsWith('/') || friend.photo.startsWith('http')) {
                photoElement.src = friend.photo;
            } else {
                photoElement.src = `uploads/${friend.id}/${friend.photo}`;
            }
            photoElement.style.display = 'block';
            photoElement.alt = friend.username;
        } else {
            // 如果沒有照片，顯示頭像字母
            photoElement.style.display = 'none';
        }
        
        // 在新模式下，名稱不顯示，只顯示照片
        if (!keepVisible) {
            nameElement.textContent = friend.username;
            nameElement.style.display = 'block';
            
            // 短暫顯示後隱藏
            setTimeout(() => {
                photoElement.style.display = 'none';
                nameElement.style.display = 'none';
            }, 800);
        }
        // keepVisible = true 時，照片保持顯示，名稱不顯示
    }
    
    // startPlayerTurn 和 showPhotoGrid 已整合到新的三階段系統中
    
    // startPlayerBeat 已整合到新的三階段系統中
    
    selectName(name) {
        // 檢查是否在遊戲中且還沒有回答這一拍
        if (this.gamePhase !== 'playing' || this.playerAnsweredThisBeat) {
            return;
        }
        
        const expectedName = this.gameSequence[this.playerSequence.length].username;
        const isCorrect = name === expectedName;
        
        // 記錄這一拍已經回答
        this.playerAnsweredThisBeat = true;
        
        // 視覺反饋
        const buttons = document.querySelectorAll('.name-option');
        buttons.forEach(btn => {
            if (btn.textContent === name) {
                btn.className = 'name-option ' + (isCorrect ? 'correct' : 'incorrect');
            }
            btn.disabled = true;
        });
        
        // 記錄答案
        this.playerSequence.push({ name, correct: isCorrect });
        
        // 簡短的視覺反饋後重新啟用按鈕
        setTimeout(() => {
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.className = 'name-option';
            });
        }, 300);
    }
    
    endGame() {
        this.gamePhase = 'finished';
        
        if (this.beatTimer) {
            clearInterval(this.beatTimer);
        }
        
        const correctCount = this.playerSequence.filter(p => p.correct).length;
        const accuracy = Math.round((correctCount / 8) * 100);
        const gameTime = Math.round((Date.now() - this.startTime) / 1000);
        
        // 顯示結果
        this.showGameResults(accuracy, gameTime, correctCount);
    }
    
    showGameResults(accuracy, gameTime, correctCount) {
        this.showGameScreen('gameResultScreen');
        
        const resultIcon = document.getElementById('resultIcon');
        const resultTitle = document.getElementById('resultTitle');
        
        // 計算錯過的節拍數
        const missedBeats = this.playerSequence.filter(p => p.missed).length;
        const wrongAnswers = this.playerSequence.filter(p => !p.correct && !p.missed).length;
        
        if (accuracy >= 80) {
            resultIcon.textContent = '🏆';
            resultTitle.textContent = '太棒了！節拍感超強！';
        } else if (accuracy >= 60) {
            resultIcon.textContent = '👍';
            resultTitle.textContent = '不錯哦！繼續練習節拍感！';
        } else {
            resultIcon.textContent = '😅';
            resultTitle.textContent = '多練習節拍感，會越來越好！';
        }
        
        document.getElementById('accuracyRate').textContent = `${accuracy}%`;
        document.getElementById('gameTime').textContent = `${gameTime}秒`;
        
        // 顯示詳細統計
        const gameDifficultyElement = document.getElementById('gameDifficulty');
        gameDifficultyElement.innerHTML = `
            <small style="color: var(--text-secondary); font-size: 0.8em;">
                正確: ${correctCount} | 錯誤: ${wrongAnswers} | 錯過: ${missedBeats}
            </small>
        `;
    }
    
    showGameScreen(screenId) {
        document.querySelectorAll('.game-screen').forEach(screen => {
            screen.classList.remove('active');
        });
        document.getElementById(screenId).classList.add('active');
    }
    
    updateGameInfo() {
        const totalBeats = 24; // 固定24拍
        document.getElementById('currentBeat').textContent = this.currentBeat;
        
        const phaseText = {
            'preparing': `準備階段 - 第${this.currentBeat}拍，正在載入資料...`,
            'showing': `展示階段 - 第${this.currentBeat}拍，記住照片順序！`,
            'playing': `回答階段 - 第${this.currentBeat}拍，按節拍點擊名稱！`,
            'finished': '遊戲結束'
        };
        document.getElementById('gamePhase').textContent = phaseText[this.gamePhase] || '準備中';
        
        const progress = (this.currentBeat / totalBeats) * 100;
        document.getElementById('progressFill').style.width = `${progress}%`;
    }
    
    stopGame() {
        if (confirm('確定要結束遊戲嗎？')) {
            if (this.audio) {
                this.audio.pause();
                this.audio.currentTime = 0;
            }
            this.resetGame();
            this.hideModal('gameModal');
        }
    }
    
    resetGame() {
        if (this.beatTimer) {
            clearInterval(this.beatTimer);
        }
        
        if (this.audio) {
            this.audio.pause();
            this.audio.currentTime = 0;
        }
        
        this.gamePhase = 'waiting';
        this.currentBeat = 0;
        this.gameSequence = [];
        this.playerSequence = [];
        this.customMusicLoaded = false;
        
        const nameSelection = document.getElementById('nameSelection');
        if (nameSelection) {
            nameSelection.style.display = 'none';
        }
        
        this.showGameScreen('gameStartScreen');
    }
}

// 初始化遊戲
document.addEventListener('DOMContentLoaded', () => {
    // 初始化遊戲實例
    window.friendMemoryGame = new FriendMemoryGame();
});
