-- Simple Retro 照片日記 資料庫腳本
-- 請在 MySQL 中執行此腳本來初始化資料庫

-- 建立資料庫（如果不存在）
CREATE DATABASE IF NOT EXISTS simple_retro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simple_retro;

-- 刪除舊資料表（如果存在）
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS likes;
DROP TABLE IF EXISTS friendships;
DROP TABLE IF EXISTS photos;
DROP TABLE IF EXISTS albums;
DROP TABLE IF EXISTS users;

-- 使用者資料表
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(500) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 相簿資料表
CREATE TABLE albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_album_per_user (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 照片資料表
CREATE TABLE photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    album_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    caption TEXT,
    is_public BOOLEAN DEFAULT TRUE,
    ai_analysis TEXT DEFAULT NULL,
    ai_explanation TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 好友關係資料表
CREATE TABLE friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    friend_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_friendship (user_id, friend_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 按讚資料表
CREATE TABLE likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    photo_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, photo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 留言資料表
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    photo_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 測試資料
-- =====================================================

-- 測試使用者 (密碼皆為 "password123"，使用 password_hash 加密)
INSERT INTO users (username, password, bio) VALUES 
('willy', '$2y$10$BssL3HBGj.v6NjXU0O.IaOfYrn2DsQb1Q1.cNWfFudD5AReAX32ii', '喜歡攝影和旅遊的工程師 📸'),
('testuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '美食愛好者 🍜'),
('alice', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '熱愛大自然 🌿'),
('bob', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '程式設計師 💻');

-- 為 demo 使用者建立相簿
INSERT INTO albums (user_id, name, is_default) VALUES 
(1, 'Recents', TRUE),
(1, '第一週', FALSE),
(1, '旅遊回憶', FALSE),
(1, '美食紀錄', FALSE);

-- 為其他使用者建立相簿
INSERT INTO albums (user_id, name, is_default) VALUES 
(2, 'Recents', TRUE),
(2, '日常生活', FALSE),
(3, 'Recents', TRUE),
(3, '自然風景', FALSE),
(4, 'Recents', TRUE),
(4, '工作日常', FALSE);

-- 測試照片資料（至少 10 筆）
INSERT INTO photos (user_id, album_id, image_url, caption, is_public) VALUES 
-- demo 使用者的照片
(1, 1, 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400', '美麗的山景，週末爬山時拍的', TRUE),
(1, 1, 'https://images.unsplash.com/photo-1475070929565-c985b496cb9f?w=400', '夕陽西下的海邊', TRUE),
(1, 2, 'https://images.unsplash.com/photo-1488590528505-98d2b5aba04b?w=400', '第一週開始學習程式設計', TRUE),
(1, 2, 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=400', '深夜寫code的桌面', TRUE),
(1, 3, 'https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?w=400', '台北101夜景', TRUE),
(1, 3, 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?w=400', '京都的竹林小徑', TRUE),
(1, 3, 'https://images.unsplash.com/photo-1528164344705-47542687000d?w=400', '富士山日出', TRUE),
(1, 4, 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=400', '超好吃的義大利麵', TRUE),
(1, 4, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=400', '週末在家做披薩', TRUE),

-- testuser 的照片
(2, 5, 'https://images.unsplash.com/photo-1518837695005-2083093ee35b?w=400', '早晨的咖啡時光', TRUE),
(2, 6, 'https://images.unsplash.com/photo-1542281286-9e0a16bb7366?w=400', '今天的讀書筆記', TRUE),

-- alice 的照片
(3, 7, 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=400', '森林裡的陽光', TRUE),
(3, 8, 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=400', '山間的小溪', TRUE),

-- bob 的照片
(4, 9, 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=400', '今天的工作環境', TRUE),
(4, 10, 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400', '新買的機械鍵盤', TRUE);

-- 好友關係測試資料
INSERT INTO friendships (user_id, friend_id, status) VALUES 
(1, 2, 'accepted'),  -- demo 和 testuser 是好友
(2, 1, 'accepted'),
(1, 3, 'accepted'),  -- demo 和 alice 是好友
(3, 1, 'accepted'),
(4, 1, 'pending');   -- bob 向 demo 發送好友請求

-- 按讚測試資料
INSERT INTO likes (user_id, photo_id) VALUES 
(2, 1), (2, 5), (2, 7),  -- testuser 按讚
(3, 1), (3, 2), (3, 8),  -- alice 按讚
(1, 10), (1, 11);        -- demo 按讚

-- 留言測試資料
INSERT INTO comments (user_id, photo_id, content) VALUES 
(2, 1, '好美的風景！'),
(3, 1, '這是在哪裡拍的？'),
(1, 10, '看起來好好喝☕'),
(2, 5, '101真的很壯觀'),
(3, 7, '富士山一直是我的夢想！');

-- 顯示建立結果
SELECT 'Database initialized successfully!' AS status;
SELECT CONCAT('Users: ', COUNT(*)) AS count FROM users;
SELECT CONCAT('Albums: ', COUNT(*)) AS count FROM albums;
SELECT CONCAT('Photos: ', COUNT(*)) AS count FROM photos;
SELECT CONCAT('Friendships: ', COUNT(*)) AS count FROM friendships;
SELECT CONCAT('Likes: ', COUNT(*)) AS count FROM likes;
SELECT CONCAT('Comments: ', COUNT(*)) AS count FROM comments;
