# מערכת ניהול טפסי נפטרים

## תיאור המערכת
מערכת מקיפה לניהול טפסי נפטרים עבור בתי עלמין וחברות קדישא. המערכת כוללת ניהול משתמשים, הרשאות מתקדמות, חתימה דיגיטלית, ומעקב אחר התקדמות מילוי הטפסים.

## דרישות מערכת
- PHP 7.4 ומעלה
- MySQL 5.7 ומעלה / MariaDB 10.3 ומעלה
- Apache 2.4 עם mod_rewrite
- PHP Extensions:
  - PDO
  - PDO_MySQL
  - GD או ImageMagick
  - OpenSSL
  - JSON
  - Session
  - Fileinfo

## התקנה

### 1. הורדת הקבצים
```bash
git clone https://github.com/yourusername/cemetery-management.git
cd cemetery-management
```

### 2. התקנת dependencies (אם משתמשים ב-Composer)
```bash
composer install
```

### 3. יצירת מסד הנתונים
```sql
CREATE DATABASE cemetery_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. ייבוא טבלאות
```bash
mysql -u your_username -p cemetery_db < database_schema.sql
mysql -u your_username -p cemetery_db < users_tables.sql
```

### 5. הגדרת קובץ סביבה
```bash
cp .env.example .env
```
ערוך את קובץ `.env` והזן את פרטי החיבור למסד הנתונים והגדרות נוספות.

### 6. הרשאות תיקיות
```bash
chmod 755 .
chmod 777 uploads/
chmod 777 logs/
chmod 644 .htaccess
```

### 7. הגדרת Virtual Host (Apache)
```apache
<VirtualHost *:80>
    ServerName cemetery.yourdomain.com
    DocumentRoot /var/www/cemetery-management
    
    <Directory /var/www/cemetery-management>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/cemetery-error.log
    CustomLog ${APACHE_LOG_DIR}/cemetery-access.log combined
</VirtualHost>
```

### 8. הפעלת SSL (מומלץ מאוד)
```bash
sudo certbot --apache -d cemetery.yourdomain.com
```

## משתמשי ברירת מחדל

| תפקיד | שם משתמש | סיסמה |
|-------|-----------|--------|
| מנהל | admin | admin123 |
| עורך | editor | editor123 |
| צופה | viewer | viewer123 |

**חשוב:** יש לשנות את הסיסמאות מיד לאחר ההתקנה!

## מבנה התיקיות
```
cemetery-management/
├── ajax/                  # קבצי AJAX
│   ├── get_blocks.php
│   ├── get_sections.php
│   ├── get_rows.php
│   ├── get_graves.php
│   ├── get_plots.php
│   ├── save_draft.php
│   ├── search_forms.php
│   ├── delete_form.php
│   └── mark_notification_read.php
├── admin/                 # אזור ניהול
│   ├── users.php
│   ├── cemeteries.php
│   ├── permissions.php
│   └── reports.php
├── uploads/              # תיקיית העלאות
├── logs/                 # תיקיית לוגים
├── vendor/               # ספריות חיצוניות
├── config.php            # הגדרות ראשיות
├── DeceasedForm.php      # מחלקת טפסים
├── dashboard.php         # דף ראשי
├── login.php            # התחברות
├── logout.php           # יציאה
├── form.php             # טופס ראשי
├── forms_list.php       # רשימת טפסים
├── view_form.php        # צפייה בטופס
├── export_pdf.php       # ייצוא PDF
├── .htaccess            # הגדרות Apache
├── .env.example         # דוגמת קובץ סביבה
└── README.md            # קובץ זה
```

## תכונות עיקריות

### 1. ניהול משתמשים והרשאות
- 4 רמות הרשאה: צופה, עורך, עורך מתקדם, מנהל
- ניהול הרשאות ברמת שדה
- מעקב אחר פעילות משתמשים
- נעילת חשבון לאחר ניסיונות כושלים

### 2. ניהול טפסים
- יצירה ועריכת טפסי נפטרים
- חישוב אוטומטי של אחוז התקדמות
- שמירה אוטומטית כטיוטה
- חיפוש מתקדם וסינון
- ייצוא ל-PDF

### 3. חתימה דיגיטלית
- לוח חתימה אינטראקטיבי
- תמיכה במכשירי מגע
- שמירת חתימה כתמונה

### 4. ניהול מיקום קבורה
- היררכיה מלאה: בית עלמין > גוש > חלקה > שורה > קבר
- אחוזות קבר
- טעינה דינמית של נתונים

### 5. מסמכים ונספחים
- העלאת מסמכים מרובים
- תמיכה בפורמטים: PDF, DOC, DOCX, JPG, PNG
- הגבלת גודל קובץ: 10MB

### 6. דוחות וסטטיסטיקות
- דשבורד עם נתונים בזמן אמת
- גרפים אינטראקטיביים
- ייצוא נתונים לאקסל

### 7. אבטחה
- הצפנת סיסמאות
- CSRF Protection
- XSS Protection
- SQL Injection Prevention
- Rate Limiting
- Session Security

## API

המערכת כוללת REST API לאינטגרציה עם מערכות חיצוניות.

### אימות
```bash
curl -X POST https://cemetery.yourdomain.com/api/auth \
  -H "Content-Type: application/json" \
  -d '{"username":"your_username","password":"your_password"}'
```

### קבלת רשימת טפסים
```bash
curl -X GET https://cemetery.yourdomain.com/api/forms \
  -H "X-API-KEY: your_api_key"
```

### יצירת טופס חדש
```bash
curl -X POST https://cemetery.yourdomain.com/api/forms \
  -H "X-API-KEY: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "identification_type": "tz",
    "identification_number": "123456789",
    "deceased_name": "ישראל ישראלי",
    "death_date": "2024-01-01",
    "burial_date": "2024-01-02"
  }'
```

## תחזוקה

### גיבוי יומי
הוסף ל-crontab:
```bash
0 2 * * * /usr/bin/mysqldump -u username -ppassword cemetery_db > /backup/cemetery_$(date +\%Y\%m\%d).sql
```

### ניקוי לוגים
```bash
0 3 * * 0 find /var/www/cemetery-management/logs -type f -mtime +30 -delete
```

### עדכון מערכת
```bash
git pull origin main
composer update
php migrate.php
```

## פתרון בעיות

### שגיאת 500
1. בדוק את קובץ הלוג: `logs/php_errors.log`
2. וודא שהרשאות התיקיות נכונות
3. בדוק את הגדרות ה-PHP

### בעיות חיבור למסד נתונים
1. וודא שפרטי החיבור ב-.env נכונים
2. בדוק שה-MySQL service פועל
3. וודא שלמשתמש יש הרשאות מתאימות

### בעיות העלאת קבצים
1. בדוק את `upload_max_filesize` ב-php.ini
2. בדוק את `post_max_size` ב-php.ini
3. וודא שלתיקיית uploads יש הרשאות 777

## אבטחה - המלצות חשובות

1. **החלף מיד את הסיסמאות** של משתמשי ברירת המחדל
2. **הפעל HTTPS** בסביבת הייצור
3. **הגבל גישה** לתיקיות admin מ-IP ספציפיים
4. **בצע גיבויים** באופן קבוע
5. **עדכן** את PHP ו-MySQL לגרסאות האחרונות
6. **הגדר** Firewall rules מתאימים
7. **השתמש** ב-WAF (Web Application Firewall)

## תמיכה

- דוא"ל: support@yourdomain.com
- טלפון: 1-800-CEMETERY
- תיעוד מלא: https://docs.yourdomain.com

## רישיון

מערכת זו מוגנת בזכויות יוצרים. אין להעתיק, להפיץ או לשנות ללא אישור בכתב.

© 2024 Cemetery Management System. All rights reserved.