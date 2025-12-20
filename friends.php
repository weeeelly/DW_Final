<?php
require_once 'includes/init.php';

$pageTitle = '好友 - Photo Rewind';
require_once 'includes/header.php';
?>

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

<?php require_once 'includes/footer.php'; ?>
<script src="js/friends.js"></script>
</body>

</html>