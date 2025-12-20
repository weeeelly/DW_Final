<?php
require_once 'includes/init.php';

$profileUserId = intval($_GET['id'] ?? $userId);

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

$pageTitle = h($profileUser['username']) . ' - Photo Rewind';
require_once 'includes/header.php';
?>

<div class="profile-page">
    <header class="profile-header-nav">
        <a href="home.php" class="back-btn">← 返回</a>
        <div class="logo">
            <span>Photo Rewind</span>
        </div>
        <a href="friends.php" class="nav-link">好友</a>
    </header>

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
                        照片年齡：<?php echo h($profileUser['ai_estimated_age']); ?>
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
                        分析照片年齡
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

    <div class="profile-content">
        <h2>照片牆</h2>
        <div class="photo-grid" id="photoGrid">
            <div class="loading">載入中...</div>
        </div>
        <div class="empty-state" id="emptyState" style="display: none;">
            <div class="empty-icon"></div>
            <h3>還沒有照片</h3>
        </div>
    </div>
</div>

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

<?php require_once 'includes/footer.php'; ?>

<script>
    const PROFILE_DATA = {
        userId: <?php echo $userId; ?>,
        profileUserId: <?php echo $profileUserId; ?>,
        isSelf: <?php echo $isSelf ? 'true' : 'false'; ?>,
        relation: '<?php echo $relation; ?>'
    };
</script>
<script src="js/profile.js"></script>
</body>

</html>