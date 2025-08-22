<?php
// includes/auth_check.php - קובץ בדיקת הרשאות מרכזי
// כלול קובץ זה בתחילת כל דף שדורש הרשאה

// טען את הקונפיג אם עוד לא נטען
if (!function_exists('getDbConnection')) {
    require_once __DIR__ . '/../config.php';
}

// בדיקה בסיסית - האם המשתמש מחובר
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/' . LOGIN_URL);
    exit;
}

// קבלת פרטי המשתמש
$currentUserId = $_SESSION['user_id'];
$currentUserLevel = $_SESSION['permission_level'] ?? 1;

/**
 * פונקציה לבדיקת הרשאה לדף ספציפי
 * @param string $pageName - שם הדף/דשבורד
 * @param int $minLevel - רמה מינימלית נדרשת (ברירת מחדל 1)
 * @param bool $checkSpecific - האם לבדוק הרשאות ספציפיות
 */
function checkPageAccess($pageName, $minLevel = 1, $checkSpecific = true) {
    global $currentUserId, $currentUserLevel;
    
    // מנהלים ועורכים מתקדמים (רמה 3+) יכולים לגשת לכל דף
    if ($currentUserLevel >= 3) {
        return true;
    }
    
    // בדיקת רמה מינימלית
    if ($currentUserLevel < $minLevel) {
        accessDenied("אין לך הרשאה לגשת לדף זה. נדרשת רמת הרשאה $minLevel לפחות.");
    }
    
    // בדיקת הרשאות ספציפיות לדשבורדים
    if ($checkSpecific && in_array($pageName, ['dashboard', 'dashboard_deceased', 'dashboard_purchases'])) {
        $dashboardType = str_replace('dashboard_', '', $pageName);
        if ($dashboardType === 'dashboard') $dashboardType = 'main';
        
        if (!hasDashboardPermission($currentUserId, $dashboardType)) {
            // הפנה לדשבורד המתאים למשתמש
            $userDashboard = getUserDashboardUrl($currentUserId, $currentUserLevel);
            header('Location: ' . $userDashboard);
            exit;
        }
    }
    
    return true;
}

/**
 * פונקציה להצגת הודעת אין הרשאה
 */
function accessDenied($message = 'אין לך הרשאה לגשת לדף זה') {
    ?>
    <!DOCTYPE html>
    <html lang="he" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>אין הרשאה</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body {
                background-color: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .access-denied-container {
                background: white;
                border-radius: 10px;
                padding: 3rem;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            .icon-container {
                color: #dc3545;
                margin-bottom: 2rem;
            }
        </style>
    </head>
    <body>
        <div class="access-denied-container">
            <div class="icon-container">
                <i class="fas fa-lock fa-5x"></i>
            </div>
            <h2 class="mb-3">אין הרשאה</h2>
            <p class="text-muted mb-4"><?php echo htmlspecialchars($message); ?></p>
            <div class="d-grid gap-2">
                <a href="<?php echo getUserDashboardUrl(); ?>" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>
                    חזרה לדשבורד
                </a>
                <a href="<?php echo SITE_URL . '/' . LOGOUT_URL; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    יציאה
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * פונקציה לבדיקת הרשאה לפעולה ספציפית
 */
function requirePermission($permissionName, $redirectOnFail = true) {
    global $currentUserId, $currentUserLevel;
    
    // מנהלים יכולים הכל
    if ($currentUserLevel >= 4) {
        return true;
    }
    
    // בדוק הרשאה ספציפית
    if (!userHasPermission($currentUserId, $permissionName)) {
        if ($redirectOnFail) {
            accessDenied("אין לך הרשאה לבצע פעולה זו: $permissionName");
        }
        return false;
    }
    
    return true;
}

/**
 * פונקציה לבדיקת הרשאה לעריכת טופס
 */
function canEditForm($formId, $formType = 'deceased') {
    global $currentUserId, $currentUserLevel;
    
    // מנהלים יכולים לערוך הכל
    if ($currentUserLevel >= 4) {
        return true;
    }
    
    $db = getDbConnection();
    
    // בדוק אם המשתמש יצר את הטופס
    $table = $formType === 'deceased' ? 'deceased_forms' : 'purchase_forms';
    $stmt = $db->prepare("SELECT created_by FROM $table WHERE form_uuid = ?");
    $stmt->execute([$formId]);
    $createdBy = $stmt->fetchColumn();
    
    // משתמש יכול לערוך טפסים שיצר
    if ($createdBy == $currentUserId) {
        return true;
    }
    
    // בדוק הרשאות מיוחדות
    return userHasPermission($currentUserId, 'edit_all_forms');
}

/**
 * רישום פעילות עם בדיקת הרשאה
 */
function logSecureActivity($action, $details = [], $formId = null) {
    global $currentUserId;
    
    // הוסף מידע על ההרשאה לפרטים
    $details['user_level'] = $_SESSION['permission_level'] ?? 1;
    $details['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    
    logActivity($action, $details, $formId);
}