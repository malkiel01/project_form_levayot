-- הוספת עמודות חדשות לטבלת users לתמיכה ב-Google Auth

-- הוספת עמודת Google ID
ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) NULL AFTER email,
ADD UNIQUE INDEX idx_google_id (google_id);

-- הוספת עמודת תמונת פרופיל
ALTER TABLE users 
ADD COLUMN profile_picture VARCHAR(500) NULL AFTER full_name;

-- הוספת עמודת אימות אימייל
ALTER TABLE users 
ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER is_active;

-- הוספת עמודת טלפון אם לא קיימת
ALTER TABLE users 
ADD COLUMN phone VARCHAR(20) NULL AFTER email;

-- עדכון עמודת password להיות nullable (למשתמשי Google)
ALTER TABLE users 
MODIFY COLUMN password VARCHAR(255) NULL;

-- הוספת אינדקס על האימייל אם לא קיים
ALTER TABLE users 
ADD INDEX idx_email (email);

-- יצירת טבלה לטוקנים של "זכור אותי" אם נרצה להוסיף
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- יצירת טבלה לאיפוס סיסמה
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- עדכון משתמשי הדמו הקיימים עם סיסמאות מוצפנות
UPDATE users 
SET password = '$2y$10$YiOZKpF7qhNmXr7bKeLrYOXf4FgXLZWeYyRSQn8AuR1xKm2h5Abu' -- admin123
WHERE username = 'admin';

UPDATE users 
SET password = '$2y$10$QDrVqBzW8rWnSvZCqEQvX.kAEZN.6PnRdfA9bItVNJfZJPiN0wim' -- editor123
WHERE username = 'editor';

UPDATE users 
SET password = '$2y$10$xCKJbMSLrQZwJJZS5dYAOGvHiLm7LwXH9mQnXkUyoQKEZJKKxbHq' -- viewer123
WHERE username = 'viewer';