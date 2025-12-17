# Simple Retro - 簡易照片日記

一個使用 PHP、MySQL、HTML、CSS 和 JavaScript 製作的照片日記網站，模仿 Retro 的介面風格。

## 📋 功能特色

### 使用者認證
- ✅ 註冊新帳號（自動建立預設相簿 "Recents"）
- ✅ 登入/登出功能
- ✅ Session 管理與安全驗證

### 照片管理
- ✅ 從本地端上傳照片（支援拖曳上傳）
- ✅ 支援 JPG、PNG、GIF、WebP 格式
- ✅ 單檔最大 10MB
- ✅ 編輯照片描述 (Caption)
- ✅ 變更照片所屬相簿
- ✅ 更換照片（編輯時可選擇新圖片）
- ✅ 刪除照片（自動清除本地檔案）
- ✅ 照片預覽功能

### 相簿管理
- ✅ 新增相簿
- ✅ 編輯相簿名稱
- ✅ 刪除相簿（連同所有照片）
- ✅ "Recents" 預設相簿保護（不可修改/刪除）

### 社交功能
- ✅ 搜尋使用者
- ✅ 發送/接受/拒絕好友請求
- ✅ 好友列表管理
- ✅ 移除好友
- ✅ 查看好友的個人主頁與照片牆
- ✅ 對照片按讚 ❤️
- ✅ 對照片留言 💬
- ✅ 刪除自己的留言

### UI/UX
- ✅ 深色主題 Retro 風格設計
- ✅ 響應式設計（支援手機/平板/桌面）
- ✅ 網格卡片式照片牆
- ✅ Modal 彈窗操作
- ✅ Toast 通知訊息
- ✅ 圖片即時預覽
- ✅ 拖曳上傳支援

## 🏗️ 系統架構

```
simple-retro/
├── index.php          # 登入頁面（首頁）
├── register.php       # 註冊頁面
├── home.php           # 主頁面（照片日記）
├── profile.php        # 個人主頁（查看照片、按讚、留言）
├── friends.php        # 好友管理頁面
├── logout.php         # 登出處理
├── api.php            # API 端點（處理 AJAX 請求）
├── config.php         # 資料庫設定與共用函數
├── style.css          # 樣式表
├── app.js             # 前端 JavaScript
├── database.sql       # 資料庫初始化腳本
├── uploads/           # 上傳圖片目錄
│   ├── .htaccess      # 安全設定（禁止執行 PHP）
│   └── {user_id}/     # 各使用者的圖片目錄
├── README.md          # 說明文件
```

## 🗃️ 資料庫設計

### users 資料表
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT | 主鍵，自動遞增 |
| username | VARCHAR(50) | 使用者名稱，唯一 |
| password | VARCHAR(255) | 密碼（bcrypt 加密）|
| avatar | VARCHAR(500) | 頭像圖片網址 |
| bio | TEXT | 個人簡介 |
| created_at | TIMESTAMP | 建立時間 |

### albums 資料表
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT | 主鍵，自動遞增 |
| user_id | INT | 外鍵，關聯 users |
| name | VARCHAR(100) | 相簿名稱 |
| is_default | BOOLEAN | 是否為預設相簿 |
| created_at | TIMESTAMP | 建立時間 |

### photos 資料表
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT | 主鍵，自動遞增 |
| user_id | INT | 外鍵，關聯 users |
| album_id | INT | 外鍵，關聯 albums |
| image_url | VARCHAR(500) | 圖片網址 |
| caption | TEXT | 照片描述 |
| is_public | BOOLEAN | 是否公開 |
| created_at | TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | 更新時間 |

### friendships 資料表
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT | 主鍵，自動遞增 |
| user_id | INT | 發送請求的使用者 |
| friend_id | INT | 接收請求的使用者 |
| status | ENUM | pending/accepted/rejected |
| created_at | TIMESTAMP | 建立時間 |

### likes 資料表
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT | 主鍵，自動遞增 |
| user_id | INT | 按讚的使用者 |
| photo_id | INT | 被按讚的照片 |
| created_at | TIMESTAMP | 建立時間 |

### comments 資料表
| 欄位 | 類型 | 說明 |
|------|------|------|
| id | INT | 主鍵，自動遞增 |
| user_id | INT | 留言的使用者 |
| photo_id | INT | 留言的照片 |
| content | TEXT | 留言內容 |
| created_at | TIMESTAMP | 建立時間 |

### ER Diagram
```
users (1) ──────< (N) albums
  │                    │
  │                    │
  └──< (N) photos >────┘
       │      │
       │      └──< (N) comments
       │
       └──< (N) likes

users (N) ──< friendships >── (N) users
```

## 🚀 安裝與設定

### 1. 資料庫設定
```bash
# 登入 MySQL
mysql -u cvml -p

# 執行資料庫腳本
source database.sql
```

或直接在 phpMyAdmin 執行 `database.sql` 內容。

### 2. 設定資料庫連線
編輯 `config.php`，確認連線資訊：
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'cvml');
define('DB_PASS', 'dwpcvml2025');
define('DB_NAME', 'simple_retro');
```

### 3. 部署檔案
將所有檔案上傳至網頁伺服器的 DocumentRoot。

### 4. 測試
開啟瀏覽器，前往網站首頁。

## 👤 測試帳號

| 帳號 | 密碼 | 說明 |
|------|------|------|
| demo | password123 | 有多張照片，與 testuser、alice 是好友 |
| testuser | password123 | 有照片，與 demo 是好友 |
| alice | password123 | 有照片，與 demo 是好友 |
| bob | password123 | 有照片，已向 demo 發送好友請求 |

## 📡 API 端點

所有 API 請求都透過 `api.php` 處理：

### 照片相關
| 動作 | 方法 | 參數 |
|------|------|------|
| get_photos | GET | album_id (可選) |
| get_user_photos | GET | user_id |
| add_photo | POST (multipart/form-data) | image (檔案), caption, album_id |
| update_photo | POST (multipart/form-data) | photo_id, image (可選), caption, album_id |
| delete_photo | POST | photo_id |

### 相簿相關
| 動作 | 方法 | 參數 |
|------|------|------|
| get_albums | GET | - |
| add_album | POST | album_name |
| update_album | POST | album_id, album_name |
| delete_album | POST | album_id |

### 好友相關
| 動作 | 方法 | 參數 |
|------|------|------|
| search_users | GET | query |
| get_friends | GET | - |
| get_friend_requests | GET | - |
| get_user_profile | GET | user_id |
| send_friend_request | POST | friend_id |
| accept_friend_request | POST | friend_id |
| reject_friend_request | POST | friend_id |
| remove_friend | POST | friend_id |

### 按讚相關
| 動作 | 方法 | 參數 |
|------|------|------|
| toggle_like | POST | photo_id |
| get_likes | GET | photo_id |

### 留言相關
| 動作 | 方法 | 參數 |
|------|------|------|
| get_comments | GET | photo_id |
| add_comment | POST | photo_id, content |
| delete_comment | POST | comment_id |

## 🔒 安全機制

1. **密碼加密**：使用 PHP `password_hash()` (bcrypt)
2. **SQL Injection 防護**：使用 Prepared Statements
3. **XSS 防護**：使用 `htmlspecialchars()` 輸出
4. **Session 管理**：驗證使用者權限
5. **API 權限檢查**：確認資料所有權

## 🎨 設計說明

- **配色方案**：深色主題，主色為珊瑚紅 (#ff6b6b)，輔色為青綠色 (#4ecdc4)
- **字體**：Noto Sans TC（支援繁體中文）
- **佈局**：側邊欄 + 主內容區的經典雙欄設計
- **互動**：Hover 效果、漸變動畫、Toast 通知

## 📱 響應式設計

- **桌面** (>992px)：完整側邊欄 + 多欄網格
- **平板** (768-992px)：可收合側邊欄 + 3欄網格
- **手機** (<768px)：隱藏側邊欄 + 2欄網格

## ⚠️ 注意事項

1. 請確保 `uploads/` 目錄有寫入權限（chmod 755 或 777）
2. 支援 JPG、PNG、GIF、WebP 格式，單檔最大 10MB
3. "Recents" 相簿為系統預設，不可刪除或重新命名
4. 刪除相簿時，該相簿內的所有照片也會一併刪除
5. 刪除照片時會自動清除伺服器上的圖片檔案
6. 建議使用 Google Chrome 瀏覽器以獲得最佳體驗
7. 測試資料中的圖片使用 Unsplash 外部連結，新上傳的照片會存放在 `uploads/` 目錄

## 📝 測試資料

資料庫腳本 (`database.sql`) 包含：
- 2 個測試使用者
- 6 個相簿
- 11 張照片

滿足「至少 10 筆測試資料」的要求。

---

Made with ❤️ for Web Development Course
