-- טבלת משתמשים
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100),
  `full_name` VARCHAR(255),
  `phone` VARCHAR(20),
  `permission_level` INT(11) NOT NULL DEFAULT 1,
  `is_active` BOOLEAN DEFAULT TRUE,
  `last_login` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_username` (`username`),
  INDEX `idx_permission_level` (`permission_level`),
  FOREIGN KEY (`permission_level`) REFERENCES `permissions`(`permission_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- טבלת התראות
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `form_id` INT(11),
  `type` ENUM('form_created', 'form_updated', 'form_completed', 'form_assigned', 'system') NOT NULL,
  `title` VARCHAR(255),
  `message` TEXT,
  `is_read` BOOLEAN DEFAULT FALSE,
  `read_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_is_read` (`is_read`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`form_id`) REFERENCES `deceased_forms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- טבלת לוג פעילות
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11),
  `form_id` INT(11),
  `action` VARCHAR(50) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_form_id` (`form_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- טבלת API keys
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `api_key` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(100),
  `permissions` TEXT, -- JSON array של הרשאות
  `is_active` BOOLEAN DEFAULT TRUE,
  `last_used` TIMESTAMP NULL,
  `expires_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_api_key` (`api_key`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- טבלת הגדרות משתמש
CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL UNIQUE,
  `receive_email_notifications` BOOLEAN DEFAULT TRUE,
  `receive_sms_notifications` BOOLEAN DEFAULT FALSE,
  `auto_save_forms` BOOLEAN DEFAULT TRUE,
  `language` VARCHAR(10) DEFAULT 'he',
  `theme` VARCHAR(20) DEFAULT 'light',
  `items_per_page` INT(3) DEFAULT 20,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- טבלת טוקנים לאיפוס סיסמה
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_token` (`token`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- הוספת Foreign Keys לטבלת deceased_forms
ALTER TABLE `deceased_forms` 
ADD CONSTRAINT `fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_cemetery` FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_block` FOREIGN KEY (`block_id`) REFERENCES `blocks`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_section` FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_row` FOREIGN KEY (`row_id`) REFERENCES `rows`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_grave` FOREIGN KEY (`grave_id`) REFERENCES `graves`(`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots`(`id`) ON DELETE SET NULL;

-- הוספת אינדקסים נוספים לשיפור ביצועים
ALTER TABLE `deceased_forms`
ADD INDEX `idx_death_date` (`death_date`),
ADD INDEX `idx_burial_date` (`burial_date`),
ADD INDEX `idx_cemetery_id` (`cemetery_id`),
ADD INDEX `idx_created_at` (`created_at`);

-- הוספת משתמשי דוגמה
-- הסיסמאות הן: admin123 ו-editor123 בהתאמה (מוצפנות ב-bcrypt)
INSERT INTO `users` (`username`, `password`, `email`, `full_name`, `permission_level`) VALUES
('admin', '$2y$10$YiQZKpF7qhNmXr7bKeLrYOXf4FgXLZWxYyRSQn8AuR1xKm/zR6Abu', 'admin@cemetery.co.il', 'מנהל מערכת', 4),
('editor', '$2y$10$QDfVgBzW8rWnSvZCqEQyX.kAEZN.6PnR6fA9bI0VfhJfZJPjN0wjm', 'editor@cemetery.co.il', 'עורך', 2),
('viewer', '$2y$10$xCKJbMSLrOZw.jJZS5dYAOGvHiLm7LwXH9mQnXkUyoOKEZJKKxbHq', 'viewer@cemetery.co.il', 'צופה', 1);

-- הוספת הגדרות ברירת מחדל למשתמשים
INSERT INTO `user_settings` (`user_id`) 
SELECT `id` FROM `users`;