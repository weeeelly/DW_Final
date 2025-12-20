<?php
require_once 'config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => '請先登入'], 401);
}

$conn = getDBConnection();
$userId = getCurrentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

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

    case 'analyze_photo':
        analyzePhoto($conn, $userId);
        break;

    case 'update_edited_image':
        updateEditedImage($conn, $userId);
        break;

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

    case 'search_users':
        searchUsers($conn, $userId);
        break;

    case 'get_friends':
        getFriends($conn, $userId);
        break;

    case 'get_photo_roulette':
        getPhotoRoulette($conn, $userId);
        break;

    case 'get_friend_requests':
        getFriendRequests($conn, $userId);
        break;

    case 'send_friend_request':
        sendFriendRequest($conn, $userId);
        break;

    case 'accept_friend_request':
        acceptFriendRequest($conn, $userId);
        break;

    case 'reject_friend_request':
        rejectFriendRequest($conn, $userId);
        break;

    case 'remove_friend':
        removeFriend($conn, $userId);
        break;

    case 'get_user_profile':
        getUserProfile($conn, $userId);
        break;

    case 'get_user_photos':
        getUserPhotos($conn, $userId);
        break;

    case 'analyze_user_profile':
        analyzeUserProfile($conn, $userId);
        break;

    case 'get_game_friends_data':
        getGameFriendsData($conn, $userId);
        break;

    case 'toggle_like':
        toggleLike($conn, $userId);
        break;

    case 'get_likes':
        getLikes($conn, $userId);
        break;

    case 'get_comments':
        getComments($conn, $userId);
        break;

    case 'add_comment':
        addComment($conn, $userId);
        break;

    case 'delete_comment':
        deleteComment($conn, $userId);
        break;

    default:
        jsonResponse(['error' => '無效的操作'], 400);
}

$conn->close();

function getPhotos($conn, $userId)
{
    $albumId = $_GET['album_id'] ?? null;

    if ($albumId) {
        $stmt = $conn->prepare("
            SELECT p.*, a.name as album_name 
            FROM photos p 
            JOIN albums a ON p.album_id = a.id 
            WHERE p.user_id = ? AND p.album_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->bind_param("ii", $userId, $albumId);
    } else {
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

function addPhoto($conn, $userId)
{
    $caption = trim($_POST['caption'] ?? '');
    $albumId = intval($_POST['album_id'] ?? 0);

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => '請選擇要上傳的圖片'], 400);
    }

    $file = $_FILES['image'];
    $imageUrl = handleFileUpload($file, $userId);

    if (!$imageUrl) {
        jsonResponse(['error' => '圖片上傳失敗'], 500);
    }

    $stmt = $conn->prepare("SELECT id FROM albums WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $albumId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        jsonResponse(['error' => '無效的相簿'], 400);
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO photos (user_id, album_id, image_url, caption) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $userId, $albumId, $imageUrl, $caption);

    if ($stmt->execute()) {
        $photoId = $stmt->insert_id;

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

function handleFileUpload($file, $userId)
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 10 * 1024 * 1024;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        jsonResponse(['error' => '不支援的檔案格式，請上傳 JPG、PNG、GIF 或 WebP 圖片'], 400);
    }

    if ($file['size'] > $maxSize) {
        jsonResponse(['error' => '檔案大小超過限制（最大 10MB）'], 400);
    }

    $uploadDir = __DIR__ . '/uploads/' . $userId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = uniqid('img_', true) . '.' . strtolower($extension);
    $targetPath = $uploadDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . $userId . '/' . $newFileName;
    }

    return false;
}

function updatePhoto($conn, $userId)
{
    $photoId = intval($_POST['photo_id'] ?? 0);
    $caption = trim($_POST['caption'] ?? '');
    $albumId = intval($_POST['album_id'] ?? 0);

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

    $stmt = $conn->prepare("SELECT id FROM albums WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $albumId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        jsonResponse(['error' => '無效的相簿'], 400);
    }
    $stmt->close();

    $imageUrl = $oldImageUrl;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $newImageUrl = handleFileUpload($file, $userId);

        if ($newImageUrl) {
            $imageUrl = $newImageUrl;

            if (strpos($oldImageUrl, 'uploads/') === 0) {
                $oldFilePath = __DIR__ . '/' . $oldImageUrl;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }
        }
    }

    $stmt = $conn->prepare("UPDATE photos SET caption = ?, album_id = ?, image_url = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sisii", $caption, $albumId, $imageUrl, $photoId, $userId);

    if ($stmt->execute()) {
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

function deletePhoto($conn, $userId)
{
    $photoId = intval($_POST['photo_id'] ?? 0);

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

    $stmt = $conn->prepare("DELETE FROM photos WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $photoId, $userId);

    if ($stmt->execute()) {
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

function getAlbums($conn, $userId)
{
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

function addAlbum($conn, $userId)
{
    $albumName = trim($_POST['album_name'] ?? '');

    if (empty($albumName)) {
        jsonResponse(['error' => '請輸入相簿名稱'], 400);
    }

    if (strlen($albumName) > 100) {
        jsonResponse(['error' => '相簿名稱過長'], 400);
    }

    $stmt = $conn->prepare("SELECT id FROM albums WHERE user_id = ? AND name = ?");
    $stmt->bind_param("is", $userId, $albumName);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        jsonResponse(['error' => '已存在同名相簿'], 400);
    }
    $stmt->close();

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

function updateAlbum($conn, $userId)
{
    $albumId = intval($_POST['album_id'] ?? 0);
    $albumName = trim($_POST['album_name'] ?? '');

    if (empty($albumName)) {
        jsonResponse(['error' => '請輸入相簿名稱'], 400);
    }

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

    $stmt = $conn->prepare("SELECT id FROM albums WHERE user_id = ? AND name = ? AND id != ?");
    $stmt->bind_param("isi", $userId, $albumName, $albumId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        jsonResponse(['error' => '已存在同名相簿'], 400);
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE albums SET name = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $albumName, $albumId, $userId);

    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'album' => ['id' => $albumId, 'name' => $albumName]]);
    } else {
        jsonResponse(['error' => '更新相簿失敗'], 500);
    }

    $stmt->close();
}

function deleteAlbum($conn, $userId)
{
    $albumId = intval($_POST['album_id'] ?? 0);

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

    $stmt = $conn->prepare("DELETE FROM albums WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $albumId, $userId);

    if ($stmt->execute()) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => '刪除相簿失敗'], 500);
    }

    $stmt->close();
}

function analyzePhoto($conn, $userId)
{
    $photoId = intval($_POST['photo_id'] ?? 0);

    $stmt = $conn->prepare("SELECT image_url FROM photos WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $photoId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['error' => '照片不存在或無權限'], 404);
    }

    $photo = $result->fetch_assoc();
    $imageUrl = $photo['image_url'];
    $stmt->close();

    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        jsonResponse(['error' => '未設定 Gemini API Key'], 500);
    }

    $imageData = '';
    if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        $imageData = file_get_contents($imageUrl);
    } else {
        $localPath = __DIR__ . '/' . $imageUrl;
        if (file_exists($localPath)) {
            $imageData = file_get_contents($localPath);
        }
    }

    if (!$imageData) {
        jsonResponse(['error' => '無法讀取圖片'], 400);
    }

    $base64Image = base64_encode($imageData);
    $mimeType = 'image/jpeg';

    if (strpos($imageUrl, '.png') !== false)
        $mimeType = 'image/png';
    if (strpos($imageUrl, '.webp') !== false)
        $mimeType = 'image/webp';

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}";

    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => 'Analyze this photo to guess the age of the photographer/uploader. 
                    Return a JSON object with two fields: 
                    1. "age": A single precise age number followed by "歲" (e.g., "23歲"). Do NOT provide a range like "20-25歲".
                    2. "reason": A detailed explanation in Traditional Chinese (繁體中文) ONLY of why you guessed this age based on the photo\'s content, style, objects, lighting, and vibe. Be creative and specific.
                    Return ONLY the JSON object, no markdown formatting.'
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $base64Image
                        ]
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        error_log("Gemini API Error: HTTP $httpCode - Response: $response");
        jsonResponse(['error' => 'AI 分析失敗', 'details' => json_decode($response, true) ?? $response], 500);
    }

    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

    $text = str_replace(['```json', '```'], '', $text);
    $jsonResult = json_decode($text, true);

    $age = $jsonResult['age'] ?? '未知';
    $reason = $jsonResult['reason'] ?? '無法分析';

    $updateStmt = $conn->prepare("UPDATE photos SET ai_analysis = ?, ai_explanation = ? WHERE id = ?");
    $updateStmt->bind_param("ssi", $age, $reason, $photoId);
    $updateStmt->execute();
    $updateStmt->close();

    jsonResponse(['success' => true, 'age_analysis' => $age, 'ai_explanation' => $reason]);
}

function updateEditedImage($conn, $userId)
{
    $photoId = intval($_POST['photo_id'] ?? 0);

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

    if (!isset($_FILES['edited_image']) || $_FILES['edited_image']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => '沒有收到編輯後的圖片'], 400);
    }

    $file = $_FILES['edited_image'];

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        jsonResponse(['error' => '不支援的檔案類型'], 400);
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        jsonResponse(['error' => '檔案大小不能超過 10MB'], 400);
    }

    $uploadDir = __DIR__ . "/uploads/{$userId}";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = 'jpg';
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $newFileName = "edited_{$timestamp}_{$random}.{$extension}";
    $newFilePath = "{$uploadDir}/{$newFileName}";

    if (!move_uploaded_file($file['tmp_name'], $newFilePath)) {
        jsonResponse(['error' => '檔案上傳失敗'], 500);
    }

    $newImageUrl = "uploads/{$userId}/{$newFileName}";

    $stmt = $conn->prepare("UPDATE photos SET image_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $newImageUrl, $photoId, $userId);

    if ($stmt->execute()) {
        if (strpos($oldImageUrl, 'uploads/') === 0 && $oldImageUrl !== $newImageUrl) {
            $oldFilePath = __DIR__ . '/' . $oldImageUrl;
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        jsonResponse(['success' => true, 'image_url' => $newImageUrl]);
    } else {
        jsonResponse(['error' => '更新照片失敗'], 500);
    }

    $stmt->close();
}

function searchUsers($conn, $userId)
{
    $query = trim($_GET['query'] ?? '');

    if (strlen($query) < 2) {
        jsonResponse(['users' => []]);
    }

    $searchTerm = "%{$query}%";
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.bio, u.avatar,
               (SELECT status FROM friendships WHERE user_id = ? AND friend_id = u.id) as friendship_status,
               (SELECT status FROM friendships WHERE user_id = u.id AND friend_id = ?) as reverse_status
        FROM users u 
        WHERE u.username LIKE ? AND u.id != ?
        LIMIT 20
    ");
    $stmt->bind_param("iisi", $userId, $userId, $searchTerm, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['friendship_status'] === 'accepted' || $row['reverse_status'] === 'accepted') {
            $row['relation'] = 'friend';
        } elseif ($row['friendship_status'] === 'pending') {
            $row['relation'] = 'pending_sent';
        } elseif ($row['reverse_status'] === 'pending') {
            $row['relation'] = 'pending_received';
        } else {
            $row['relation'] = 'none';
        }
        unset($row['friendship_status'], $row['reverse_status']);
        $users[] = $row;
    }

    $stmt->close();
    jsonResponse(['users' => $users]);
}

function getFriends($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.bio, u.avatar, f.created_at as friends_since
        FROM users u
        JOIN friendships f ON (f.friend_id = u.id AND f.user_id = ?)
        WHERE f.status = 'accepted'
        ORDER BY u.username ASC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $friends = [];
    while ($row = $result->fetch_assoc()) {
        $friends[] = $row;
    }

    $stmt->close();
    jsonResponse(['friends' => $friends]);
}

function getFriendRequests($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.bio, u.avatar, f.created_at as request_date
        FROM users u
        JOIN friendships f ON (f.user_id = u.id AND f.friend_id = ?)
        WHERE f.status = 'pending'
        ORDER BY f.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    $stmt->close();
    jsonResponse(['requests' => $requests]);
}

function sendFriendRequest($conn, $userId)
{
    $friendId = intval($_POST['friend_id'] ?? 0);

    if ($friendId === $userId) {
        jsonResponse(['error' => '不能加自己為好友'], 400);
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $friendId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        jsonResponse(['error' => '使用者不存在'], 404);
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT status FROM friendships WHERE user_id = ? AND friend_id = ?");
    $stmt->bind_param("ii", $userId, $friendId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        if ($existing['status'] === 'accepted') {
            jsonResponse(['error' => '已經是好友了'], 400);
        } elseif ($existing['status'] === 'pending') {
            jsonResponse(['error' => '已發送過好友請求'], 400);
        }
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT status FROM friendships WHERE user_id = ? AND friend_id = ?");
    $stmt->bind_param("ii", $friendId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        if ($existing['status'] === 'pending') {
            $stmt2 = $conn->prepare("UPDATE friendships SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
            $stmt2->bind_param("ii", $friendId, $userId);
            $stmt2->execute();
            $stmt2->close();

            $stmt3 = $conn->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
            $stmt3->bind_param("ii", $userId, $friendId);
            $stmt3->execute();
            $stmt3->close();

            jsonResponse(['success' => true, 'message' => '已成為好友']);
        }
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'pending')");
    $stmt->bind_param("ii", $userId, $friendId);

    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => '好友請求已發送']);
    } else {
        jsonResponse(['error' => '發送請求失敗'], 500);
    }

    $stmt->close();
}

function acceptFriendRequest($conn, $userId)
{
    $friendId = intval($_POST['friend_id'] ?? 0);

    $stmt = $conn->prepare("SELECT id FROM friendships WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $friendId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        jsonResponse(['error' => '沒有待處理的好友請求'], 404);
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE friendships SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
    $stmt->bind_param("ii", $friendId, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'accepted') ON DUPLICATE KEY UPDATE status = 'accepted'");
    $stmt->bind_param("ii", $userId, $friendId);
    $stmt->execute();
    $stmt->close();

    jsonResponse(['success' => true, 'message' => '已接受好友請求']);
}

function rejectFriendRequest($conn, $userId)
{
    $friendId = intval($_POST['friend_id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM friendships WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $friendId, $userId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => '操作失敗'], 400);
    }

    $stmt->close();
}

function removeFriend($conn, $userId)
{
    $friendId = intval($_POST['friend_id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM friendships WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
    $stmt->bind_param("iiii", $userId, $friendId, $friendId, $userId);

    if ($stmt->execute()) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => '移除好友失敗'], 500);
    }

    $stmt->close();
}

function getUserProfile($conn, $userId)
{
    $targetUserId = intval($_GET['user_id'] ?? 0);

    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.bio, u.avatar, u.created_at, u.ai_estimated_age, u.ai_tags,
               (SELECT COUNT(*) FROM photos WHERE user_id = u.id AND is_public = TRUE) as photo_count,
               (SELECT COUNT(*) FROM friendships WHERE user_id = u.id AND status = 'accepted') as friend_count
        FROM users u WHERE u.id = ?
    ");
    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['error' => '使用者不存在'], 404);
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT status FROM friendships WHERE user_id = ? AND friend_id = ?");
    $stmt->bind_param("ii", $userId, $targetUserId);
    $stmt->execute();
    $friendResult = $stmt->get_result();

    if ($friendResult->num_rows > 0) {
        $friendship = $friendResult->fetch_assoc();
        $user['relation'] = $friendship['status'] === 'accepted' ? 'friend' : 'pending_sent';
    } else {
        $stmt2 = $conn->prepare("SELECT status FROM friendships WHERE user_id = ? AND friend_id = ?");
        $stmt2->bind_param("ii", $targetUserId, $userId);
        $stmt2->execute();
        $reverseResult = $stmt2->get_result();
        if ($reverseResult->num_rows > 0) {
            $reverse = $reverseResult->fetch_assoc();
            $user['relation'] = $reverse['status'] === 'accepted' ? 'friend' : 'pending_received';
        } else {
            $user['relation'] = $userId == $targetUserId ? 'self' : 'none';
        }
        $stmt2->close();
    }
    $stmt->close();

    jsonResponse(['user' => $user]);
}

function getUserPhotos($conn, $userId)
{
    $targetUserId = intval($_GET['user_id'] ?? 0);

    $isFriend = false;
    $isSelf = ($userId === $targetUserId);

    if (!$isSelf) {
        $stmt = $conn->prepare("SELECT id FROM friendships WHERE user_id = ? AND friend_id = ? AND status = 'accepted'");
        $stmt->bind_param("ii", $userId, $targetUserId);
        $stmt->execute();
        $isFriend = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }

    if ($isSelf || $isFriend) {
        $stmt = $conn->prepare("
            SELECT p.*, a.name as album_name, u.username,
                   (SELECT COUNT(*) FROM likes WHERE photo_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM comments WHERE photo_id = p.id) as comment_count,
                   (SELECT COUNT(*) FROM likes WHERE photo_id = p.id AND user_id = ?) as is_liked
            FROM photos p 
            JOIN albums a ON p.album_id = a.id 
            JOIN users u ON p.user_id = u.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->bind_param("ii", $userId, $targetUserId);
    } else {
        $stmt = $conn->prepare("
            SELECT p.*, a.name as album_name, u.username,
                   (SELECT COUNT(*) FROM likes WHERE photo_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM comments WHERE photo_id = p.id) as comment_count,
                   (SELECT COUNT(*) FROM likes WHERE photo_id = p.id AND user_id = ?) as is_liked
            FROM photos p 
            JOIN albums a ON p.album_id = a.id 
            JOIN users u ON p.user_id = u.id
            WHERE p.user_id = ? AND p.is_public = TRUE
            ORDER BY p.created_at DESC
        ");
        $stmt->bind_param("ii", $userId, $targetUserId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $photos = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_liked'] = $row['is_liked'] > 0;
        $photos[] = $row;
    }

    $stmt->close();
    jsonResponse(['photos' => $photos, 'is_friend' => $isFriend, 'is_self' => $isSelf]);
}

function toggleLike($conn, $userId)
{
    $photoId = intval($_POST['photo_id'] ?? 0);

    $stmt = $conn->prepare("SELECT user_id, is_public FROM photos WHERE id = ?");
    $stmt->bind_param("i", $photoId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['error' => '照片不存在'], 404);
    }

    $photo = $result->fetch_assoc();
    $stmt->close();

    if ($photo['user_id'] !== $userId && !$photo['is_public']) {
        $stmt = $conn->prepare("SELECT id FROM friendships WHERE user_id = ? AND friend_id = ? AND status = 'accepted'");
        $stmt->bind_param("ii", $userId, $photo['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            jsonResponse(['error' => '無權限'], 403);
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND photo_id = ?");
    $stmt->bind_param("ii", $userId, $photoId);
    $stmt->execute();
    $liked = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($liked) {
        $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND photo_id = ?");
        $stmt->bind_param("ii", $userId, $photoId);
        $stmt->execute();
        $stmt->close();
        $isLiked = false;
    } else {
        $stmt = $conn->prepare("INSERT INTO likes (user_id, photo_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $photoId);
        $stmt->execute();
        $stmt->close();
        $isLiked = true;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE photo_id = ?");
    $stmt->bind_param("i", $photoId);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    jsonResponse(['success' => true, 'is_liked' => $isLiked, 'like_count' => $count]);
}

function getLikes($conn, $userId)
{
    $photoId = intval($_GET['photo_id'] ?? 0);

    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar, l.created_at
        FROM likes l
        JOIN users u ON l.user_id = u.id
        WHERE l.photo_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->bind_param("i", $photoId);
    $stmt->execute();
    $result = $stmt->get_result();

    $likes = [];
    while ($row = $result->fetch_assoc()) {
        $likes[] = $row;
    }

    $stmt->close();
    jsonResponse(['likes' => $likes]);
}

function getComments($conn, $userId)
{
    $photoId = intval($_GET['photo_id'] ?? 0);

    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.avatar
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.photo_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->bind_param("i", $photoId);
    $stmt->execute();
    $result = $stmt->get_result();

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_own'] = ($row['user_id'] == $userId);
        $comments[] = $row;
    }

    $stmt->close();
    jsonResponse(['comments' => $comments]);
}

function addComment($conn, $userId)
{
    $photoId = intval($_POST['photo_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        jsonResponse(['error' => '請輸入留言內容'], 400);
    }

    if (strlen($content) > 1000) {
        jsonResponse(['error' => '留言內容過長'], 400);
    }

    $stmt = $conn->prepare("SELECT user_id, is_public FROM photos WHERE id = ?");
    $stmt->bind_param("i", $photoId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['error' => '照片不存在'], 404);
    }

    $photo = $result->fetch_assoc();
    $stmt->close();

    if ($photo['user_id'] !== $userId && !$photo['is_public']) {
        $stmt = $conn->prepare("SELECT id FROM friendships WHERE user_id = ? AND friend_id = ? AND status = 'accepted'");
        $stmt->bind_param("ii", $userId, $photo['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            jsonResponse(['error' => '無權限'], 403);
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("INSERT INTO comments (user_id, photo_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $userId, $photoId, $content);

    if ($stmt->execute()) {
        $commentId = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT c.*, u.username, u.avatar
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->bind_param("i", $commentId);
        $stmt->execute();
        $comment = $stmt->get_result()->fetch_assoc();
        $comment['is_own'] = true;
        $stmt->close();

        jsonResponse(['success' => true, 'comment' => $comment]);
    } else {
        jsonResponse(['error' => '新增留言失敗'], 500);
    }
}

function deleteComment($conn, $userId)
{
    $commentId = intval($_POST['comment_id'] ?? 0);

    $stmt = $conn->prepare("SELECT id FROM comments WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $commentId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        jsonResponse(['error' => '留言不存在或無權限'], 404);
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->bind_param("i", $commentId);

    if ($stmt->execute()) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => '刪除留言失敗'], 500);
    }

    $stmt->close();
}

function getGameFriendsData($conn, $userId)
{
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT u.id, u.username, u.avatar
            FROM users u
            JOIN friendships f ON (f.friend_id = u.id AND f.user_id = ?)
            WHERE f.status = 'accepted'
            ORDER BY RAND()
            LIMIT 15
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $friends = [];
        while ($row = $result->fetch_assoc()) {
            $friendId = $row['id'];

            $photoStmt = $conn->prepare("
                SELECT image_url, caption 
                FROM photos 
                WHERE user_id = ? AND image_url IS NOT NULL AND image_url != ''
                ORDER BY RAND() 
                LIMIT 1
            ");
            $photoStmt->bind_param("i", $friendId);
            $photoStmt->execute();
            $photoResult = $photoStmt->get_result();

            if ($photoRow = $photoResult->fetch_assoc()) {
                $row['photo'] = $photoRow['image_url'];
                $row['photo_caption'] = $photoRow['caption'];
                $friends[] = $row;
            }
            $photoStmt->close();
        }

        $stmt->close();

        $testPhotos = [
            'https://picsum.photos/300/300?random=1',
            'https://picsum.photos/300/300?random=2',
            'https://picsum.photos/300/300?random=3',
            'https://picsum.photos/300/300?random=4',
            'https://picsum.photos/300/300?random=5',
            'https://picsum.photos/300/300?random=6',
            'https://picsum.photos/300/300?random=7',
            'https://picsum.photos/300/300?random=8'
        ];

        while (count($friends) < 8) {
            $index = count($friends);
            $friends[] = [
                'id' => 'demo_' . ($index + 1),
                'username' => '測試好友' . ($index + 1),
                'avatar' => null,
                'photo' => $testPhotos[$index] ?? 'https://picsum.photos/300/300?random=' . ($index + 10),
                'photo_caption' => '測試照片 ' . ($index + 1)
            ];
        }

        jsonResponse(['friends' => $friends]);
    } catch (Exception $e) {
        jsonResponse(['error' => '載入遊戲數據失敗: ' . $e->getMessage()], 500);
    }
}

function getPhotoRoulette($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT p.id, p.image_url, p.user_id, u.username, u.avatar 
        FROM photos p 
        JOIN users u ON p.user_id = u.id 
        JOIN friendships f ON (f.friend_id = p.user_id AND f.user_id = ?) 
        WHERE f.status = 'accepted' 
        ORDER BY RAND() 
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse(['error' => '沒有足夠的好友照片來進行遊戲'], 404);
    }

    $photo = $result->fetch_assoc();
    $stmt->close();

    $correctUserId = $photo['user_id'];

    $distractors = [];

    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar 
        FROM users u
        JOIN friendships f ON (f.friend_id = u.id AND f.user_id = ?)
        WHERE f.status = 'accepted' AND u.id != ?
        ORDER BY RAND()
        LIMIT 3
    ");
    $stmt->bind_param("ii", $userId, $correctUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $distractors[] = $row;
    }
    $stmt->close();


    if (count($distractors) < 3) {
        $needed = 3 - count($distractors);
        $excludeIds = [$correctUserId, $userId];
        foreach ($distractors as $d) {
            $excludeIds[] = $d['id'];
        }

        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $types = str_repeat('i', count($excludeIds)) . 'i';
        $params = array_merge($excludeIds, [$needed]);

        $sql = "SELECT id, username, avatar FROM users WHERE id NOT IN ($placeholders) ORDER BY RAND() LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $distractors[] = $row;
        }
        $stmt->close();
    }

    $options = $distractors;
    $options[] = [
        'id' => $photo['user_id'],
        'username' => $photo['username'],
        'avatar' => $photo['avatar']
    ];

    shuffle($options);

    jsonResponse([
        'photo' => [
            'id' => $photo['id'],
            'image_url' => $photo['image_url']
        ],
        'options' => $options,
        'correct_user_id' => $correctUserId
    ]);
}

function analyzeUserProfile($conn, $userId)
{
    $stmt = $conn->prepare("SELECT image_url FROM photos WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $imageParts = [];
    while ($row = $result->fetch_assoc()) {
        $imageUrl = $row['image_url'];
        $imageData = '';

        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $imageData = @file_get_contents($imageUrl);
        } else {
            $localPath = __DIR__ . '/' . $imageUrl;
            if (file_exists($localPath)) {
                $imageData = file_get_contents($localPath);
            }
        }

        if ($imageData) {
            $mimeType = 'image/jpeg';
            if (strpos($imageUrl, '.png') !== false)
                $mimeType = 'image/png';
            if (strpos($imageUrl, '.webp') !== false)
                $mimeType = 'image/webp';

            $imageParts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => base64_encode($imageData)
                ]
            ];
        }
    }
    $stmt->close();

    if (empty($imageParts)) {
        jsonResponse(['error' => '請先上傳照片才能進行分析'], 400);
    }

    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        jsonResponse(['error' => '未設定 Gemini API Key'], 500);
    }

    $prompt = "Analyze these photos to estimate the user's age and suggest 5 relevant categories/tags for this user based on their photo content/style.
    Return a JSON object with two fields:
    1. \"age\": A single estimated age number followed by \"歲\" (e.g., \"25歲\").
    2. \"categories\": An array of 5 short strings in Traditional Chinese (繁體中文) ONLY representing the categories/tags (e.g., [\"攝影\", \"美食\", \"旅遊\", \"貓咪\", \"文青\"]).
    Return ONLY the JSON object.";

    $contents = [
        [
            'parts' => array_merge([['text' => $prompt]], $imageParts)
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['contents' => $contents]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        error_log("Gemini API Error: " . $response);
        jsonResponse(['error' => 'AI 分析失敗'], 500);
    }

    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    $text = str_replace(['```json', '```'], '', $text);
    $jsonResult = json_decode($text, true);

    $age = $jsonResult['age'] ?? '未知';
    $categories = isset($jsonResult['categories']) ? json_encode($jsonResult['categories'], JSON_UNESCAPED_UNICODE) : '[]';

    $stmt = $conn->prepare("UPDATE users SET ai_estimated_age = ?, ai_tags = ? WHERE id = ?");
    $stmt->bind_param("ssi", $age, $categories, $userId);

    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'age' => $age, 'categories' => json_decode($categories)]);
    } else {
        jsonResponse(['error' => '更新資料失敗'], 500);
    }
    $stmt->close();
}
?>