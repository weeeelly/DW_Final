<?php
require_once 'config.php';

// 確保使用者已登入
if (!isLoggedIn()) {
    jsonResponse(['error' => '請先登入'], 401);
}

$conn = getDBConnection();
$userId = getCurrentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    // ==================== 照片相關 ====================
    
    case 'get_photos':
        getPhotos($conn, $userId);
        break;
        
    case 'add_photo':
        addPhoto($conn, $userId);
        break;
        
    case 'update_photo':
        updatePhoto($conn, $userId);
        break;
        
    case 'delete_photo':
        deletePhoto($conn, $userId);
        break;
    
    // ==================== 相簿相關 ====================
    
    case 'get_albums':
        getAlbums($conn, $userId);
        break;
        
    case 'add_album':
        addAlbum($conn, $userId);
        break;
        
    case 'update_album':
        updateAlbum($conn, $userId);
        break;
        
    case 'delete_album':
        deleteAlbum($conn, $userId);
        break;
        
    default:
        jsonResponse(['error' => '無效的操作'], 400);
}

$conn->close();

// ==================== 照片功能實作 ====================

function getPhotos($conn, $userId) {
    $albumId = $_GET['album_id'] ?? null;
    
    if ($albumId) {
        // 取得特定相簿的照片
        $stmt = $conn->prepare("
            SELECT p.*, a.name as album_name 
            FROM photos p 
            JOIN albums a ON p.album_id = a.id 
            WHERE p.user_id = ? AND p.album_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->bind_param("ii", $userId, $albumId);
    } else {
        // 取得所有照片
        $stmt = $conn->prepare("
            SELECT p.*, a.name as album_name 
            FROM photos p 
            JOIN albums a ON p.album_id = a.id 
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->bind_param("i", $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $photos = [];
    while ($row = $result->fetch_assoc()) {
        $photos[] = $row;
    }
    
    $stmt->close();
    jsonResponse(['photos' => $photos]);
}

function addPhoto($conn, $userId) {
    $caption = trim($_POST['caption'] ?? '');
    $albumId = intval($_POST['album_id'] ?? 0);
    
    // 處理檔案上傳
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => '請選擇要上傳的圖片'], 400);
    }
    
    $file = $_FILES['image'];
    $imageUrl = handleFileUpload($file, $userId);
    
    if (!$imageUrl) {
        jsonResponse(['error' => '圖片上傳失敗'], 500);
    }
    
    // 驗證相簿是否屬於該使用者
    $stmt = $conn->prepare("SELECT id FROM albums WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $albumId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        jsonResponse(['error' => '無效的相簿'], 400);
    }
    $stmt->close();
    
    // 新增照片
    $stmt = $conn->prepare("INSERT INTO photos (user_id, album_id, image_url, caption) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $userId, $albumId, $imageUrl, $caption);
    
    if ($stmt->execute()) {
        $photoId = $stmt->insert_id;
        
        // 取得完整的照片資料
        $stmt2 = $conn->prepare("
            SELECT p.*, a.name as album_name 
            FROM photos p 
            JOIN albums a ON p.album_id = a.id 
            WHERE p.id = ?
        ");
        $stmt2->bind_param("i", $photoId);
        $stmt2->execute();
        $photo = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        
        jsonResponse(['success' => true, 'photo' => $photo]);
    } else {
        jsonResponse(['error' => '新增照片失敗'], 500);
    }
    
    $stmt->close();
}

// 處理檔案上傳
function handleFileUpload($file, $userId) {
    // 允許的檔案類型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    // 驗證檔案類型
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        jsonResponse(['error' => '不支援的檔案格式，請上傳 JPG、PNG、GIF 或 WebP 圖片'], 400);
    }
    
    // 驗證檔案大小
    if ($file['size'] > $maxSize) {
        jsonResponse(['error' => '檔案大小超過限制（最大 10MB）'], 400);
    }
    
    // 建立使用者專屬目錄
    $uploadDir = __DIR__ . '/uploads/' . $userId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 生成唯一檔名
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = uniqid('img_', true) . '.' . strtolower($extension);
    $targetPath = $uploadDir . $newFileName;
    
    // 移動檔案
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . $userId . '/' . $newFileName;
    }
    
    return false;
}

function updatePhoto($conn, $userId) {
    $photoId = intval($_POST['photo_id'] ?? 0);
    $caption = trim($_POST['caption'] ?? '');
    $albumId = intval($_POST['album_id'] ?? 0);
    
    // 驗證照片是否屬於該使用者，並取得舊圖片路徑
    $stmt = $conn->prepare("SELECT id, image_url FROM photos WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $photoId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        jsonResponse(['error' => '照片不存在或無權限'], 404);
    }
    $oldPhoto = $result->fetch_assoc();
    $oldImageUrl = $oldPhoto['image_url'];
    $stmt->close();
    
    // 驗證相簿是否屬於該使用者
    $stmt = $conn->prepare("SELECT id FROM albums WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $albumId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        jsonResponse(['error' => '無效的相簿'], 400);
    }
    $stmt->close();
    
    // 檢查是否有新圖片上傳
    $imageUrl = $oldImageUrl;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $newImageUrl = handleFileUpload($file, $userId);
        
        if ($newImageUrl) {
            $imageUrl = $newImageUrl;
            
            // 刪除舊圖片（如果是本地檔案）
            if (strpos($oldImageUrl, 'uploads/') === 0) {
                $oldFilePath = __DIR__ . '/' . $oldImageUrl;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }
        }
    }
    
    // 更新照片
    $stmt = $conn->prepare("UPDATE photos SET caption = ?, album_id = ?, image_url = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sisii", $caption, $albumId, $imageUrl, $photoId, $userId);
    
    if ($stmt->execute()) {
        // 取得更新後的照片資料
        $stmt2 = $conn->prepare("
            SELECT p.*, a.name as album_name 
            FROM photos p 
            JOIN albums a ON p.album_id = a.id 
            WHERE p.id = ?
        ");
        $stmt2->bind_param("i", $photoId);
        $stmt2->execute();
        $photo = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        
        jsonResponse(['success' => true, 'photo' => $photo]);
    } else {
        jsonResponse(['error' => '更新照片失敗'], 500);
    }
    
    $stmt->close();
}

function deletePhoto($conn, $userId) {
    $photoId = intval($_POST['photo_id'] ?? 0);
    
    // 驗證照片是否屬於該使用者，並取得圖片路徑
    $stmt = $conn->prepare("SELECT id, image_url FROM photos WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $photoId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        jsonResponse(['error' => '照片不存在或無權限'], 404);
    }
    $photo = $result->fetch_assoc();
    $imageUrl = $photo['image_url'];
    $stmt->close();
    
    // 刪除照片記錄
    $stmt = $conn->prepare("DELETE FROM photos WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $photoId, $userId);
    
    if ($stmt->execute()) {
        // 刪除本地圖片檔案
        if (strpos($imageUrl, 'uploads/') === 0) {
            $filePath = __DIR__ . '/' . $imageUrl;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => '刪除照片失敗'], 500);
    }
    
    $stmt->close();
}

// ==================== 相簿功能實作 ====================

function getAlbums($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT a.*, 
               (SELECT COUNT(*) FROM photos WHERE album_id = a.id) as photo_count
        FROM albums a 
        WHERE a.user_id = ?
        ORDER BY a.is_default DESC, a.created_at ASC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $albums = [];
    while ($row = $result->fetch_assoc()) {
        $albums[] = $row;
    }
    
    $stmt->close();
    jsonResponse(['albums' => $albums]);
}

function addAlbum($conn, $userId) {
    $albumName = trim($_POST['album_name'] ?? '');
    
    if (empty($albumName)) {
        jsonResponse(['error' => '請輸入相簿名稱'], 400);
    }
    
    if (strlen($albumName) > 100) {
        jsonResponse(['error' => '相簿名稱過長'], 400);
    }
    
    // 檢查是否已存在同名相簿
    $stmt = $conn->prepare("SELECT id FROM albums WHERE user_id = ? AND name = ?");
    $stmt->bind_param("is", $userId, $albumName);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        jsonResponse(['error' => '已存在同名相簿'], 400);
    }
    $stmt->close();
    
    // 新增相簿
    $stmt = $conn->prepare("INSERT INTO albums (user_id, name, is_default) VALUES (?, ?, FALSE)");
    $stmt->bind_param("is", $userId, $albumName);
    
    if ($stmt->execute()) {
        $albumId = $stmt->insert_id;
        jsonResponse([
            'success' => true, 
            'album' => [
                'id' => $albumId,
                'name' => $albumName,
                'is_default' => 0,
                'photo_count' => 0
            ]
        ]);
    } else {
        jsonResponse(['error' => '新增相簿失敗'], 500);
    }
    
    $stmt->close();
}

function updateAlbum($conn, $userId) {
    $albumId = intval($_POST['album_id'] ?? 0);
    $albumName = trim($_POST['album_name'] ?? '');
    
    if (empty($albumName)) {
        jsonResponse(['error' => '請輸入相簿名稱'], 400);
    }
    
    // 驗證相簿是否屬於該使用者且不是預設相簿
    $stmt = $conn->prepare("SELECT id, is_default FROM albums WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $albumId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['error' => '相簿不存在或無權限'], 404);
    }
    
    $album = $result->fetch_assoc();
    if ($album['is_default']) {
        jsonResponse(['error' => '預設相簿「Recents」不可被修改'], 400);
    }
    $stmt->close();
    
    // 檢查是否已存在同名相簿（排除自己）
    $stmt = $conn->prepare("SELECT id FROM albums WHERE user_id = ? AND name = ? AND id != ?");
    $stmt->bind_param("isi", $userId, $albumName, $albumId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        jsonResponse(['error' => '已存在同名相簿'], 400);
    }
    $stmt->close();
    
    // 更新相簿
    $stmt = $conn->prepare("UPDATE albums SET name = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $albumName, $albumId, $userId);
    
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'album' => ['id' => $albumId, 'name' => $albumName]]);
    } else {
        jsonResponse(['error' => '更新相簿失敗'], 500);
    }
    
    $stmt->close();
}

function deleteAlbum($conn, $userId) {
    $albumId = intval($_POST['album_id'] ?? 0);
    
    // 驗證相簿是否屬於該使用者且不是預設相簿
    $stmt = $conn->prepare("SELECT id, is_default, name FROM albums WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $albumId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['error' => '相簿不存在或無權限'], 404);
    }
    
    $album = $result->fetch_assoc();
    if ($album['is_default']) {
        jsonResponse(['error' => '預設相簿「Recents」不可被刪除'], 400);
    }
    $stmt->close();
    
    // 刪除相簿（照片會透過外鍵約束自動刪除）
    $stmt = $conn->prepare("DELETE FROM albums WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $albumId, $userId);
    
    if ($stmt->execute()) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => '刪除相簿失敗'], 500);
    }
    
    $stmt->close();
}
?>
