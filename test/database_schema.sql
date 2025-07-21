-- יצירת טבלת נפטרים
CREATE TABLE IF NOT EXISTS `deceased_forms` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `form_uuid` VARCHAR(36) NOT NULL UNIQUE,
  `status` ENUM('draft', 'in_progress', 'completed', 'archived') DEFAULT 'draft',
  `progress_percentage` INT(3) DEFAULT 0,
  
  -- פרטי הנפטר
  `identification_type` ENUM('tz', 'passport', 'anonymous', 'baby') NOT NULL,
  `identification_number` VARCHAR(20) DEFAULT NULL,
  `deceased_name` VARCHAR(255) NOT NULL,
  `father_name` VARCHAR(255) DEFAULT NULL,
  `mother_name` VARCHAR(255) DEFAULT NULL,
  `birth_date` DATE DEFAULT NULL,
  
  -- פרטי הפטירה
  `death_date` DATE NOT NULL,
  `death_time` TIME NOT NULL,
  `burial_date` DATE NOT NULL,
  `burial_time` TIME NOT NULL,
  `burial_license` VARCHAR(100) NOT NULL,
  `death_location` TEXT DEFAULT NULL,
  
  -- מקום הקבורה
  `cemetery_id` INT(11) DEFAULT NULL,
  `block_id` INT(11) DEFAULT NULL,
  `section_id` INT(11) DEFAULT NULL,
  `row_id` INT(11) DEFAULT NULL,
  `grave_id` INT(11) DEFAULT NULL,
  `plot_id` INT(11) DEFAULT NULL,
  
  -- פרטי המודיע
  `informant_name` VARCHAR(255) DEFAULT NULL,
  `informant_phone` VARCHAR(20) DEFAULT NULL,
  `informant_relationship` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  
  -- חתימה ומטה-דטה
  `client_signature` TEXT DEFAULT NULL, -- Base64 encoded signature
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT(11) DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  INDEX `idx_form_uuid` (`form_uuid`),
  INDEX `idx_status` (`status`),
  INDEX `idx_deceased_name` (`deceased_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- טבלת בתי עלמין
CREATE TABLE IF NOT EXISTS `cemeteries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `is_active` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- טבלת גושים
CREATE TABLE IF NOT EXISTS `blocks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cemetery_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50),
  `is_active` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- טבלת חלקות
CREATE TABLE IF NOT EXISTS `sections` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `block_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50),
  `is_active` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`block_id`) REFERENCES `blocks`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- טבלת שורות
CREATE TABLE IF NOT EXISTS `rows` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `section_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50),
  `is_active` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- טבלת קברים
CREATE TABLE IF NOT EXISTS `graves` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `row_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50),
  `is_available` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`row_id`) REFERENCES `rows`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- טבלת אחוזות קבר
CREATE TABLE IF NOT EXISTS `plots` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cemetery_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50),
  `is_active` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cemetery_id`) REFERENCES `cemeteries`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- טבלת מסמכים
CREATE TABLE IF NOT EXISTS `deceased_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `form_id` INT(11) NOT NULL,
  `document_type` VARCHAR(100),
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT(11),
  `mime_type` VARCHAR(100),
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` INT(11),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`form_id`) REFERENCES `deceased_forms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- טבלת הרשאות
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `permission_level` INT(11) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- טבלת הגדרות שדות לפי הרשאה
CREATE TABLE IF NOT EXISTS `field_permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `field_name` VARCHAR(100) NOT NULL,
  `permission_level` INT(11) NOT NULL,
  `can_view` BOOLEAN DEFAULT TRUE,
  `can_edit` BOOLEAN DEFAULT FALSE,
  `is_required` BOOLEAN DEFAULT FALSE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_field_permission` (`field_name`, `permission_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- הכנסת הרשאות בסיסיות
INSERT INTO `permissions` (`permission_level`, `name`, `description`) VALUES
(1, 'viewer', 'צפייה בלבד'),
(2, 'editor', 'עריכת פרטים בסיסיים'),
(3, 'advanced_editor', 'עריכה מתקדמת'),
(4, 'admin', 'מנהל מערכת');

-- הגדרת הרשאות לשדות
INSERT INTO `field_permissions` (`field_name`, `permission_level`, `can_view`, `can_edit`, `is_required`) VALUES
-- הרשאות לצופה (רמה 1)
('identification_type', 1, TRUE, FALSE, FALSE),
('deceased_name', 1, TRUE, FALSE, FALSE),
('death_date', 1, TRUE, FALSE, FALSE),

-- הרשאות לעורך (רמה 2)
('identification_type', 2, TRUE, TRUE, TRUE),
('identification_number', 2, TRUE, TRUE, FALSE),
('deceased_name', 2, TRUE, TRUE, TRUE),
('father_name', 2, TRUE, TRUE, FALSE),
('mother_name', 2, TRUE, TRUE, FALSE),
('birth_date', 2, TRUE, TRUE, FALSE),
('death_date', 2, TRUE, TRUE, TRUE),
('death_time', 2, TRUE, TRUE, TRUE),
('burial_date', 2, TRUE, TRUE, TRUE),
('burial_time', 2, TRUE, TRUE, TRUE),
('burial_license', 2, TRUE, TRUE, TRUE),
('death_location', 2, TRUE, TRUE, FALSE),
('informant_name', 2, TRUE, TRUE, FALSE),
('informant_phone', 2, TRUE, TRUE, FALSE),
('informant_relationship', 2, TRUE, TRUE, FALSE),
('notes', 2, TRUE, TRUE, FALSE),

-- הרשאות למנהל (רמה 4)
('cemetery_id', 4, TRUE, TRUE, FALSE),
('block_id', 4, TRUE, TRUE, FALSE),
('section_id', 4, TRUE, TRUE, FALSE),
('row_id', 4, TRUE, TRUE, FALSE),
('grave_id', 4, TRUE, TRUE, FALSE),
('plot_id', 4, TRUE, TRUE, FALSE);