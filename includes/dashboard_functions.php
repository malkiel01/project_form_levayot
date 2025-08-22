<?php
// includes/dashboard_functions.php - פונקציות עזר לדשבורדים

/**
 * בדיקה אם למשתמש יש הרשאה לדשבורד ספציפי
 */
function hasDashboardPermission($userId, $dashboardType) {
    try {
        $db = getDbConnection();
        
        // תחילה בדוק אם יש טבלת dashboard_permissions
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'dashboard_permissions'
        ");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            // אם אין טבלה, תן גישה לכולם לדשבורד הראשי
            return ($dashboardType === 'main' || $dashboardType === 'dashboard');
        }
        
        // בדוק הרשאה ספציפית
        $stmt = $db->prepare("
            SELECT has_permission 
            FROM dashboard_permissions 
            WHERE user_id = ? 
            AND dashboard_type = ?
        ");
        $stmt->execute([$userId, $dashboardType]);
        $result = $stmt->fetch();
        
        if ($result) {
            return (bool)$result['has_permission'];
        }
        
        // אם אין רשומה, תן גישה רק לדשבורד ראשי
        return ($dashboardType === 'main' || $dashboardType === 'dashboard');
        
    } catch (Exception $e) {
        error_log("Error checking dashboard permission: " . $e->getMessage());
        // במקרה של שגיאה, תן גישה רק לדשבורד ראשי
        return ($dashboardType === 'main' || $dashboardType === 'dashboard');
    }
}

/**
 * בדיקה אם המשתמש יכול למחוק טפסים
 */
function canDeleteForms($userId, $action = 'delete_forms') {
    try {
        $db = getDbConnection();
        
        // קבל את רמת ההרשאה של המשתמש
        $stmt = $db->prepare("
            SELECT permission_level 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // מנהלים (רמה 3+) יכולים למחוק
        if ($user['permission_level'] >= 3) {
            return true;
        }
        
        // בדוק אם יש טבלת user_permissions עם הרשאות ספציפיות
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'user_permissions'
        ");
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            // בדוק הרשאה ספציפית למחיקה
            $stmt = $db->prepare("
                SELECT permission_value 
                FROM user_permissions 
                WHERE user_id = ? 
                AND permission_type = ?
            ");
            $stmt->execute([$userId, $action]);
            $permission = $stmt->fetch();
            
            if ($permission) {
                return (bool)$permission['permission_value'];
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error checking delete permission: " . $e->getMessage());
        return false;
    }
}

/**
 * תרגום סטטוס לעברית
 */
function translateStatus($status) {
    $statuses = [
        'draft' => 'טיוטה',
        'pending' => 'ממתין',
        'in_progress' => 'בתהליך',
        'completed' => 'הושלם',
        'cancelled' => 'בוטל',
        'approved' => 'אושר',
        'rejected' => 'נדחה',
        'paid' => 'שולם',
        'partial' => 'שולם חלקית'
    ];
    
    return $statuses[$status] ?? $status;
}

/**
 * יצירת מחלקת CSS לסטטוס
 */
function getStatusClass($status) {
    $classes = [
        'draft' => 'secondary',
        'pending' => 'warning',
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'approved' => 'success',
        'rejected' => 'danger',
        'paid' => 'success',
        'partial' => 'warning'
    ];
    
    return $classes[$status] ?? 'secondary';
}

/**
 * קבלת רשימת בתי עלמין שהמשתמש יכול לגשת אליהם
 */
function getUserCemeteries($userId) {
    try {
        $db = getDbConnection();
        
        // קבל את רמת ההרשאה
        $stmt = $db->prepare("SELECT permission_level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return [];
        }
        
        // מנהלים רואים הכל
        if ($user['permission_level'] >= 3) {
            $stmt = $db->query("SELECT * FROM cemeteries WHERE is_active = 1 ORDER BY name");
            return $stmt->fetchAll();
        }
        
        // בדוק אם יש הרשאות ספציפיות לבתי עלמין
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'user_cemetery_permissions'
        ");
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            $stmt = $db->prepare("
                SELECT c.* 
                FROM cemeteries c
                JOIN user_cemetery_permissions ucp ON c.id = ucp.cemetery_id
                WHERE ucp.user_id = ? 
                AND ucp.has_access = 1
                AND c.is_active = 1
                ORDER BY c.name
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        }
        
        // אם אין הרשאות ספציפיות, החזר רשימה ריקה
        return [];
        
    } catch (Exception $e) {
        error_log("Error getting user cemeteries: " . $e->getMessage());
        return [];
    }
}

/**
 * בדיקה אם המשתמש יכול לערוך טופס
 */
function canEditForm($userId, $formId, $formType = 'deceased') {
    try {
        $db = getDbConnection();
        
        // קבל את רמת ההרשאה
        $stmt = $db->prepare("SELECT permission_level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // מנהלים יכולים לערוך הכל
        if ($user['permission_level'] >= 3) {
            return true;
        }
        
        // בדוק אם המשתמש יצר את הטופס
        $table = $formType === 'purchase' ? 'purchase_forms' : 'deceased_forms';
        $stmt = $db->prepare("
            SELECT created_by 
            FROM $table 
            WHERE form_uuid = ?
        ");
        $stmt->execute([$formId]);
        $form = $stmt->fetch();
        
        if ($form && $form['created_by'] == $userId) {
            return true;
        }
        
        // עורכים (רמה 2) יכולים לערוך כל טופס
        if ($user['permission_level'] >= 2) {
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error checking edit permission: " . $e->getMessage());
        return false;
    }
}

/**
 * רישום פעילות
 */
function logDashboardActivity($action, $details = []) {
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            INSERT INTO activity_log (
                user_id, 
                action, 
                details, 
                ip_address, 
                user_agent, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

/**
 * קבלת סטטיסטיקות מהירות לדשבורד
 */
function getDashboardStats($userId = null) {
    try {
        $db = getDbConnection();
        $stats = [];
        
        // סטטיסטיקות בסיסיות
        $queries = [
            'total_deceased' => "SELECT COUNT(*) FROM deceased_forms",
            'total_purchases' => "SELECT COUNT(*) FROM purchase_forms",
            'today_deceased' => "SELECT COUNT(*) FROM deceased_forms WHERE DATE(created_at) = CURDATE()",
            'today_purchases' => "SELECT COUNT(*) FROM purchase_forms WHERE DATE(created_at) = CURDATE()",
            'pending_deceased' => "SELECT COUNT(*) FROM deceased_forms WHERE status = 'pending'",
            'pending_purchases' => "SELECT COUNT(*) FROM purchase_forms WHERE status = 'pending'"
        ];
        
        foreach ($queries as $key => $query) {
            $stmt = $db->query($query);
            $stats[$key] = $stmt->fetchColumn();
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return [];
    }
}

/**
 * פורמט תאריך לעברית
 */
function formatHebrewDate($date) {
    if (empty($date)) {
        return '-';
    }
    
    $months = [
        1 => 'ינואר',
        2 => 'פברואר',
        3 => 'מרץ',
        4 => 'אפריל',
        5 => 'מאי',
        6 => 'יוני',
        7 => 'יולי',
        8 => 'אוגוסט',
        9 => 'ספטמבר',
        10 => 'אוקטובר',
        11 => 'נובמבר',
        12 => 'דצמבר'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day ב$month, $year";
}

/**
 * קבלת צבע לפי סטטוס
 */
function getStatusColor($status) {
    $colors = [
        'draft' => '#6c757d',
        'pending' => '#ffc107',
        'in_progress' => '#17a2b8',
        'completed' => '#28a745',
        'cancelled' => '#dc3545',
        'approved' => '#28a745',
        'rejected' => '#dc3545',
        'paid' => '#28a745',
        'partial' => '#ffc107'
    ];
    
    return $colors[$status] ?? '#6c757d';
}
?>