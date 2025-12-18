CREATE DATABASE IF NOT EXISTS photo_rewind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE photo_rewind;

DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS likes;
DROP TABLE IF EXISTS friendships;
DROP TABLE IF EXISTS photos;
DROP TABLE IF EXISTS albums;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(500) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    ai_estimated_age VARCHAR(50) DEFAULT NULL,
    ai_tags TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_album_per_user (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    photo_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, photo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    photo_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password, bio) VALUES 
('willy', '$2y$10$BssL3HBGj.v6NjXU0O.IaOfYrn2DsQb1Q1.cNWfFudD5AReAX32ii', '喜歡攝影和旅遊的工程師 📸'),
('testuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '美食愛好者 🍜'),
('alice', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '熱愛大自然 🌿'),
('bob', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '程式設計師 💻');

INSERT INTO albums (user_id, name, is_default) VALUES 
(1, 'Recents', TRUE),
(1, '第一週', FALSE),
(1, '旅遊回憶', FALSE),
(1, '美食紀錄', FALSE);

INSERT INTO albums (user_id, name, is_default) VALUES 
(2, 'Recents', TRUE),
(2, '日常生活', FALSE),
(3, 'Recents', TRUE),
(3, '自然風景', FALSE),
(4, 'Recents', TRUE),
(4, '工作日常', FALSE);

INSERT INTO photos (user_id, album_id, image_url, caption, is_public) VALUES 
(1, 1, 'uploads/1/photo-1506905925346-21bda4d32df4.jpeg', '美麗的山景，週末爬山時拍的', TRUE),
(1, 1, 'uploads/1/photo-1475070929565-c985b496cb9f.jpeg', '夕陽西下的海邊', TRUE),
(1, 2, 'uploads/1/photo-1488590528505-98d2b5aba04b.jpeg', '第一週開始學習程式設計', TRUE),
(1, 2, 'uploads/1/photo-1517694712202-14dd9538aa97.jpeg', '深夜寫code的桌面', TRUE),
(1, 3, 'uploads/1/photo-1480714378408-67cf0d13bc1b.jpeg', '台北101夜景', TRUE),
(1, 3, 'uploads/1/photo-1493976040374-85c8e12f0c0e.jpeg', '京都的竹林小徑', TRUE),
(1, 3, 'uploads/1/photo-1528164344705-47542687000d.jpeg', '富士山日出', TRUE),
(1, 4, 'uploads/1/photo-1504674900247-0877df9cc836.jpeg', '超好吃的義大利麵', TRUE),
(1, 4, 'uploads/1/photo-1565299624946-b28f40a0ae38.jpeg', '週末在家做披薩', TRUE),

(2, 5, 'uploads/2/photo-1518837695005-2083093ee35b.jpeg', '早晨的咖啡時光', TRUE),
(2, 6, 'uploads/2/photo-1542281286-9e0a16bb7366.jpeg', '今天的讀書筆記', TRUE),

(3, 7, 'uploads/3/photo-1441974231531-c6227db76b6e.jpeg', '森林裡的陽光', TRUE),
(3, 8, 'uploads/3/photo-1469474968028-56623f02e42e.jpeg', '山間的小溪', TRUE),

(4, 9, 'uploads/4/photo-1461749280684-dccba630e2f6.jpeg', '今天的工作環境', TRUE),
(4, 10,'uploads/4/photo-1498050108023-c5249f4df085.jpeg', '新買的機械鍵盤', TRUE);

INSERT INTO friendships (user_id, friend_id, status) VALUES 
(1, 2, 'accepted'),
(2, 1, 'accepted'),
(1, 3, 'accepted'),
(3, 1, 'accepted'),
(4, 1, 'pending');

INSERT INTO likes (user_id, photo_id) VALUES 
(2, 1), (2, 5), (2, 7),
(3, 1), (3, 2), (3, 8),
(1, 10), (1, 11);      

INSERT INTO comments (user_id, photo_id, content) VALUES 
(2, 1, '好美的風景！'),
(3, 1, '這是在哪裡拍的？'),
(1, 10, '看起來好好喝☕'),
(2, 5, '101真的很壯觀'),
(3, 7, '富士山一直是我的夢想！');

SELECT 'Database initialized successfully!' AS status;
SELECT CONCAT('Users: ', COUNT(*)) AS count FROM users;
SELECT CONCAT('Albums: ', COUNT(*)) AS count FROM albums;
SELECT CONCAT('Photos: ', COUNT(*)) AS count FROM photos;
SELECT CONCAT('Friendships: ', COUNT(*)) AS count FROM friendships;
SELECT CONCAT('Likes: ', COUNT(*)) AS count FROM likes;
SELECT CONCAT('Comments: ', COUNT(*)) AS count FROM comments;
