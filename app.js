let currentAlbumId = null;
let currentPhotoId = null;
let deleteTarget = null;

document.addEventListener('DOMContentLoaded', () => {
    initEventListeners();
    loadPhotos();
});

function initEventListeners() {
    document.querySelectorAll('.album-item').forEach(item => {
        item.addEventListener('click', handleAlbumClick);
    });

    document.getElementById('addPhotoBtn').addEventListener('click', () => {
        openPhotoModal();
    });

    document.getElementById('addAlbumBtn').addEventListener('click', () => {
        openAlbumModal();
    });

    const startGameBtn = document.getElementById('startGameBtn');
    if (startGameBtn) {
        startGameBtn.addEventListener('click', () => {
            if (window.friendMemoryGame) {
                window.friendMemoryGame.openGameModal();
            }
        });
    }

    document.getElementById('photoForm').addEventListener('submit', handlePhotoSubmit);

    document.getElementById('albumForm').addEventListener('submit', handleAlbumSubmit);

    document.getElementById('imageFile').addEventListener('change', handleFileSelect);

    const fileWrapper = document.querySelector('.file-upload-wrapper');
    if (fileWrapper) {
        fileWrapper.addEventListener('dragover', handleDragOver);
        fileWrapper.addEventListener('dragleave', handleDragLeave);
        fileWrapper.addEventListener('drop', handleDrop);
    }

    document.querySelectorAll('.modal-close, .modal-cancel, .modal-overlay').forEach(el => {
        el.addEventListener('click', closeAllModals);
    });

    document.querySelectorAll('.modal-content').forEach(el => {
        el.addEventListener('click', e => e.stopPropagation());
    });

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

    document.getElementById('confirmDeleteBtn').addEventListener('click', handleConfirmDelete);

    document.getElementById('mobileMenuBtn').addEventListener('click', toggleSidebar);

    document.querySelectorAll('.edit-album-btn').forEach(btn => {
        btn.addEventListener('click', handleEditAlbumClick);
    });

    document.querySelectorAll('.delete-album-btn').forEach(btn => {
        btn.addEventListener('click', handleDeleteAlbumClick);
    });

    setupImageEditor();
}

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
            const card = document.querySelector(`.photo-card[data-photo-id="${photoId}"]`);
            const cardImage = card.querySelector('.photo-card-image');
            let badge = cardImage.querySelector('.ai-badge');

            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'ai-badge';
                const overlay = cardImage.querySelector('.photo-card-overlay');
                cardImage.insertBefore(badge, overlay);
            }

            badge.textContent = `照片年齡：${data.age_analysis}`;

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

    const aiAge = photoCard.dataset.aiAge;
    const aiExplanation = photoCard.dataset.aiExplanation;

    document.getElementById('viewPhotoImage').src = img.src;
    document.getElementById('viewPhotoCaption').textContent = caption;
    document.getElementById('viewPhotoAlbum').textContent = `📁 ${album}`;
    document.getElementById('viewPhotoDate').textContent = `📅 ${date}`;

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

    const fileInput = document.getElementById('imageFile');
    fileInput.value = '';

    if (photoId) {
        title.textContent = '編輯照片';
        document.getElementById('photoId').value = photoId;

        fileInput.removeAttribute('required');

        const photoCard = document.querySelector(`[data-photo-id="${photoId}"]`);
        if (photoCard) {
            const img = photoCard.querySelector('img');
            const caption = photoCard.querySelector('.photo-card-caption').textContent;
            const albumName = photoCard.querySelector('.photo-card-album').textContent;

            document.getElementById('caption').value = caption !== '無描述' ? caption : '';

            document.getElementById('imagePreview').innerHTML = `<img src="${img.src}" alt="">`;
            document.getElementById('fileName').textContent = '（保留目前圖片，或選擇新圖片替換）';

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
        fileInput.setAttribute('required', 'required');
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

    if (!photoId && (!fileInput.files || fileInput.files.length === 0)) {
        showToast('請選擇要上傳的圖片', 'error');
        return;
    }

    formData.append('action', photoId ? 'update_photo' : 'add_photo');

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

function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        previewFile(file);
    }
}

function previewFile(file) {
    const preview = document.getElementById('imagePreview');
    const fileNameSpan = document.getElementById('fileName');

    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        preview.innerHTML = '<span class="preview-placeholder">不支援的檔案格式</span>';
        fileNameSpan.textContent = '';
        showToast('請選擇 JPG、PNG、GIF 或 WebP 格式的圖片', 'error');
        document.getElementById('imageFile').value = '';
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        preview.innerHTML = '<span class="preview-placeholder">檔案太大</span>';
        fileNameSpan.textContent = '';
        showToast('檔案大小不能超過 10MB', 'error');
        document.getElementById('imageFile').value = '';
        return;
    }

    fileNameSpan.textContent = file.name;

    const reader = new FileReader();
    reader.onload = (e) => {
        preview.innerHTML = '';

        const newImg = document.createElement('img');
        newImg.src = e.target.result;
        newImg.alt = '預覽';

        preview.appendChild(newImg);

        const designBtn = document.getElementById('openDesignBtn');
        if (designBtn) {
            designBtn.style.display = 'inline-block';
        }
    };
    reader.readAsDataURL(file);
}

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

function handleAlbumClick(e) {
    if (e.target.closest('.album-actions')) return;

    const albumItem = e.currentTarget;
    const albumId = albumItem.dataset.albumId;
    const albumName = albumItem.dataset.albumName;

    document.querySelectorAll('.album-item').forEach(item => item.classList.remove('active'));
    albumItem.classList.add('active');

    document.getElementById('currentAlbumTitle').textContent = albumName;

    currentAlbumId = albumId;
    loadPhotos(albumId);

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



function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}





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
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            switchEditorTab(e.target.dataset.tab);
        });
    });

    document.querySelectorAll('.filter-item').forEach(item => {
        item.addEventListener('click', (e) => {
            applyFilter(e.currentTarget.dataset.filter);
        });
    });

    document.querySelectorAll('.sticker-item').forEach(item => {
        item.addEventListener('click', (e) => {
            selectSticker(e.target.dataset.sticker);
        });
    });

    const stickerSizeSlider = document.getElementById('stickerSize');
    const stickerSizeValue = document.getElementById('stickerSizeValue');
    stickerSizeSlider.addEventListener('input', (e) => {
        stickerSizeValue.textContent = e.target.value + 'px';
    });

    setupAdjustmentControls();

    document.getElementById('resetAdjustments').addEventListener('click', resetAdjustments);

    document.getElementById('saveEditedImage').addEventListener('click', saveEditedImage);
}

function openImageEditor(photoId) {
    const photoCard = document.querySelector(`[data-photo-id="${photoId}"]`);
    if (!photoCard) return;

    const img = photoCard.querySelector('img');
    imageEditor.currentPhotoId = photoId;

    const editImage = document.getElementById('editImage');
    editImage.src = img.src;

    resetEditor();

    openModal('imageEditModal');
}

function switchEditorTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    document.querySelectorAll('.editor-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    document.getElementById(`${tabName}Panel`).classList.add('active');
}

function applyFilter(filterValue) {
    imageEditor.currentFilter = filterValue;
    updateImageDisplay();

    document.querySelectorAll('.filter-item').forEach(item => {
        item.classList.remove('selected');
    });
    document.querySelector(`[data-filter="${filterValue}"]`).classList.add('selected');
}

function selectSticker(stickerEmoji) {
    const stickerControls = document.querySelector('.sticker-controls');
    stickerControls.style.display = 'block';

    const overlay = document.getElementById('stickerOverlay');
    const size = document.getElementById('stickerSize').value;

    const stickerElement = document.createElement('div');
    stickerElement.className = 'placed-sticker';
    stickerElement.textContent = stickerEmoji;
    stickerElement.style.fontSize = size + 'px';
    stickerElement.style.left = '50%';
    stickerElement.style.top = '50%';
    stickerElement.style.transform = 'translate(-50%, -50%)';

    makeStickerDraggable(stickerElement);

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

    document.getElementById('stickerOverlay').innerHTML = '';
    document.querySelector('.sticker-controls').style.display = 'none';

    document.getElementById('brightnessSlider').value = 100;
    document.getElementById('contrastSlider').value = 100;
    document.getElementById('saturationSlider').value = 100;
    document.getElementById('brightnessValue').textContent = '100%';
    document.getElementById('contrastValue').textContent = '100%';
    document.getElementById('saturationValue').textContent = '100%';

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
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const editImage = document.getElementById('editImage');

        canvas.width = editImage.naturalWidth;
        canvas.height = editImage.naturalHeight;

        ctx.filter = editImage.style.filter || 'none';
        ctx.drawImage(editImage, 0, 0, canvas.width, canvas.height);

        const stickers = document.querySelectorAll('.placed-sticker');
        stickers.forEach(sticker => {
            const rect = editImage.getBoundingClientRect();
            const stickerRect = sticker.getBoundingClientRect();

            const x = ((stickerRect.left + stickerRect.width / 2 - rect.left) / rect.width) * canvas.width;
            const y = ((stickerRect.top + stickerRect.height / 2 - rect.top) / rect.height) * canvas.height;
            const fontSize = parseInt(sticker.style.fontSize) * (canvas.width / rect.width);

            ctx.filter = 'none';
            ctx.font = `${fontSize}px Arial`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(sticker.textContent, x, y);
        });

        const blob = await new Promise(resolve => {
            canvas.toBlob(resolve, 'image/jpeg', 0.9);
        });

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

        loadPhotos(currentAlbumId);

    } catch (error) {
        console.error('保存修圖失敗:', error);
        showToast('保存失敗', 'error');
    } finally {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    }
}


class FriendMemoryGame {
    constructor() {
        this.gameData = null;
        this.gameSequence = [];
        this.playerSequence = [];
        this.currentBeat = 0;
        this.gamePhase = 'waiting';
        this.difficulty = 8;
        this.fixedBPM = 180;
        this.gameTimer = null;
        this.beatTimer = null;
        this.startTime = null;
        this.audio = null;

        this.initEventListeners();
    }

    initEventListeners() {
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

    }

    async openGameModal() {
        try {
            const response = await fetch('api.php?action=get_game_friends_data');

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
            this.showGameScreen('gamePlayScreen');

            this.generateGameSequence();
            this.startTime = Date.now();

            await this.loadGameMusic();

            if (this.audio && this.customMusicLoaded) {
                try {
                    await this.audio.play();
                } catch (error) {
                    console.warn('無法自動播放音樂，請手動點擊播放');
                }
            }

            this.initGameDisplay();

            this.gamePhase = 'showing';
            this.currentBeat = 1;
            this.updateGameInfo();

            this.startBeatShow();

        } catch (error) {
            console.error('開始遊戲失敗:', error);
            showToast('遊戲啟動失敗', 'error');
        }
    }

    initGameDisplay() {
        const photoDisplay = document.getElementById('photoDisplay');
        if (photoDisplay) {
            photoDisplay.innerHTML = `
                <div class="beat-indicator" id="beatIndicator">♪</div>
                <img id="currentPhoto" src="" alt="" style="display: none;">
                <div class="friend-name" id="currentFriendName" style="display: none;"></div>
            `;
        }

        const nameSelection = document.getElementById('nameSelection');
        if (nameSelection) {
            nameSelection.style.display = 'none';
        }
    }

    generateGameSequence() {
        const shuffled = [...this.gameData].sort(() => Math.random() - 0.5);
        this.gameSequence = shuffled.slice(0, 8);
        this.playerSequence = [];
    }

    async loadGameMusic() {
        if (this.audio) {
            this.audio.pause();
        }

        try {
            const audio = new Audio('game_music.m4a');
            audio.loop = true;
            audio.volume = 0.3;

            this.audio = audio;
            this.customMusicLoaded = true;
        } catch (error) {
            console.warn('無法載入背景音樂，使用節拍聲:', error);
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

            await new Promise((resolve, reject) => {
                audio.addEventListener('loadedmetadata', () => {
                    this.audio = audio;
                    this.customMusicLoaded = true;
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
        return (60 / this.fixedBPM) * 1000;
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
        this.shuffledOptions = null;

        let beatCount = 0;
        const totalBeats = 8 * 3;

        const beatInterval = setInterval(() => {
            this.playBeatSound();
            this.showBeatIndicator();

            beatCount++;
            this.currentBeat = beatCount;

            if (beatCount <= 8) {
                this.gamePhase = 'preparing';
                this.showPreparationPhase(beatCount);
            }
            else if (beatCount <= 16) {
                if (beatCount === 9) {
                    this.gamePhase = 'showing';
                    this.startShowingPhase();
                }
                this.showDisplayPhase(beatCount - 8);
            }
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

        if (beat <= 8) {
            const friend = this.gameSequence[beat - 1];
            if (friend && friend.photo) {
                const img = new Image();
                img.src = friend.photo.startsWith('http') ? friend.photo :
                    friend.photo.startsWith('uploads/') ? friend.photo :
                        `uploads/${friend.id}/${friend.photo}`;
            }
        }
    }

    setupNameSelection() {
        const nameSelection = document.getElementById('nameSelection');
        nameSelection.style.display = 'block';

        this.generateFixedNameOptions();
    }

    generateFixedNameOptions() {
        const nameGrid = document.getElementById('nameGrid');
        nameGrid.innerHTML = '';

        if (!this.shuffledOptions) {
            const correctNames = this.gameSequence.map(friend => friend.username);
            const allFriends = [...this.gameData];
            const distractorNames = allFriends
                .filter(friend => !correctNames.includes(friend.username))
                .map(friend => friend.username)
                .slice(0, 4);

            const allOptions = [...correctNames, ...distractorNames];
            this.shuffledOptions = allOptions.sort(() => Math.random() - 0.5);
        }

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

        this.generateFixedNameOptions();
    }

    showDisplayPhase(beat) {
        const showingGrid = document.getElementById('showingGrid');
        if (!showingGrid) return;

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
        this.playerSequence = [];
        this.playerAnsweredThisBeat = false;
    }

    handlePlayerPhase(beat) {
        if (beat > 1 && !this.playerAnsweredThisBeat) {
            this.playerSequence.push({
                name: '未回答',
                correct: false,
                missed: true
            });
        }

        this.playerAnsweredThisBeat = false;

    }

    showBeatIndicator() {
        let indicator = document.getElementById('beatIndicator');

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
            if (friend.photo.startsWith('uploads/') || friend.photo.startsWith('/') || friend.photo.startsWith('http')) {
                photoElement.src = friend.photo;
            } else {
                photoElement.src = `uploads/${friend.id}/${friend.photo}`;
            }
            photoElement.style.display = 'block';
            photoElement.alt = friend.username;
        } else {
            photoElement.style.display = 'none';
        }

        if (!keepVisible) {
            nameElement.textContent = friend.username;
            nameElement.style.display = 'block';

            setTimeout(() => {
                photoElement.style.display = 'none';
                nameElement.style.display = 'none';
            }, 800);
        }
    }

    selectName(name) {
        if (this.gamePhase !== 'playing' || this.playerAnsweredThisBeat) {
            return;
        }

        const expectedName = this.gameSequence[this.playerSequence.length].username;
        const isCorrect = name === expectedName;

        this.playerAnsweredThisBeat = true;

        const buttons = document.querySelectorAll('.name-option');
        buttons.forEach(btn => {
            if (btn.textContent === name) {
                btn.className = 'name-option ' + (isCorrect ? 'correct' : 'incorrect');
            }
            btn.disabled = true;
        });

        this.playerSequence.push({ name, correct: isCorrect });

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

        this.showGameResults(accuracy, gameTime, correctCount);
    }

    showGameResults(accuracy, gameTime, correctCount) {
        this.showGameScreen('gameResultScreen');

        const resultIcon = document.getElementById('resultIcon');
        const resultTitle = document.getElementById('resultTitle');

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
        const totalBeats = 24;
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

document.addEventListener('DOMContentLoaded', () => {
    window.friendMemoryGame = new FriendMemoryGame();
});
