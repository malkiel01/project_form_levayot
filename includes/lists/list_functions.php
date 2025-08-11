<?php
// includes/lists/list_functions.php - פונקציות משותפות לרשימות

/**
 * שמירת העדפת משתמש
 */
function saveUserPreference($userId, $key, $name, $data, $isDefault = false) {
    $db = getDbConnection();
    
    try {
        // אם זו העדפת ברירת מחדל, בטל את הקודמת
        if ($isDefault) {
            $stmt = $db->prepare("
                UPDATE user_preferences 
                SET is_default = 0 
                WHERE user_id = ? AND preference_key = ?
            ");
            $stmt->execute([$userId, $key]);
        }
        
        $stmt = $db->prepare("
            INSERT INTO user_preferences (user_id, preference_key, preference_name, preference_data, is_default) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                preference_data = VALUES(preference_data),
                is_default = VALUES(is_default),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $userId,
            $key,
            $name,
            json_encode($data),
            $isDefault ? 1 : 0
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error saving preference: " . $e->getMessage());
        return false;
    }
}

/**
 * קבלת העדפות משתמש
 */
function getUserPreferences($userId, $key) {
    $db = getDbConnection();
    
    $stmt = $db->prepare("
        SELECT * FROM user_preferences 
        WHERE user_id = ? AND preference_key = ?
        ORDER BY is_default DESC, updated_at DESC
    ");
    $stmt->execute([$userId, $key]);
    
    $preferences = [];
    while ($row = $stmt->fetch()) {
        $row['preference_data'] = json_decode($row['preference_data'], true);
        $preferences[] = $row;
    }
    
    return $preferences;
}

/**
 * קבלת העדפת ברירת מחדל
 */
function getDefaultPreference($userId, $key) {
    $db = getDbConnection();
    
    $stmt = $db->prepare("
        SELECT preference_data FROM user_preferences 
        WHERE user_id = ? AND preference_key = ? AND is_default = 1
        LIMIT 1
    ");
    $stmt->execute([$userId, $key]);
    
    $result = $stmt->fetch();
    return $result ? json_decode($result['preference_data'], true) : null;
}

/**
 * מחיקת העדפה
 */
function deleteUserPreference($userId, $preferenceId) {
    $db = getDbConnection();
    
    $stmt = $db->prepare("
        DELETE FROM user_preferences 
        WHERE id = ? AND user_id = ?
    ");
    
    return $stmt->execute([$preferenceId, $userId]);
}

/**
 * בניית תנאי WHERE לפי פילטרים
 */
function buildWhereClause2($filters, &$params) {
    $conditions = [];
    
    // פילטר תאריכים
    if (!empty($filters['date_range'])) {
        switch ($filters['date_range']) {
            case 'last_month':
                $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'last_year':
                $conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            case 'custom':
                if (!empty($filters['date_from'])) {
                    $conditions[] = "created_at >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $conditions[] = "created_at <= ?";
                    $params[] = $filters['date_to'] . ' 23:59:59';
                }
                break;
        }
    }
    
    // חיפוש טקסט
    if (!empty($filters['search_text'])) {
        $searchText = '%' . $filters['search_text'] . '%';
        $conditions[] = "(
            CONCAT(IFNULL(deceased_first_name, ''), ' ', IFNULL(deceased_last_name, '')) LIKE ? 
            OR deceased_first_name LIKE ? 
            OR deceased_last_name LIKE ? 
            OR father_name LIKE ? 
            OR mother_name LIKE ?
        )";
        $params[] = $searchText; // לשם המלא
        $params[] = $searchText; // לשם פרטי
        $params[] = $searchText; // לשם משפחה
        $params[] = $searchText; // לשם האב
        $params[] = $searchText; // לשם האם
    }
    
    // פילטר מיקום
    if (!empty($filters['cemetery_id'])) {
        $conditions[] = "cemetery_id = ?";
        $params[] = $filters['cemetery_id'];
    }
    if (!empty($filters['block_id'])) {
        $conditions[] = "block_id = ?";
        $params[] = $filters['block_id'];
    }
    if (!empty($filters['plot_id'])) {
        $conditions[] = "plot_id = ?";
        $params[] = $filters['plot_id'];
    }
    
    // סטטוס
    if (!empty($filters['status'])) {
        $conditions[] = "status = ?";
        $params[] = $filters['status'];
    }
    
    return $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
}
/**
 * בניית תנאי WHERE לפי פילטרים
 */
function buildWhereClause($filters, &$params, $tableAlias = 'df') {
    $conditions = [];
    
    // פילטר תאריכים - הוסף את alias הטבלה
    if (!empty($filters['date_range'])) {
        switch ($filters['date_range']) {
            case 'last_month':
                $conditions[] = "{$tableAlias}.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'last_year':
                $conditions[] = "{$tableAlias}.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            case 'custom':
                if (!empty($filters['date_from'])) {
                    $conditions[] = "{$tableAlias}.created_at >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $conditions[] = "{$tableAlias}.created_at <= ?";
                    $params[] = $filters['date_to'] . ' 23:59:59';
                }
                break;
        }
    }

    // חיפוש טקסט - הוסף את alias הטבלה
    if (!empty($filters['search_text'])) {
        $searchText = '%' . $filters['search_text'] . '%';
        $conditions[] = "(
            CONCAT(IFNULL({$tableAlias}.deceased_first_name, ''), ' ', IFNULL({$tableAlias}.deceased_last_name, '')) LIKE ? 
            OR {$tableAlias}.deceased_first_name LIKE ? 
            OR {$tableAlias}.deceased_last_name LIKE ? 
            OR {$tableAlias}.father_name LIKE ? 
            OR {$tableAlias}.mother_name LIKE ?
        )";
        $params[] = $searchText; // לשם המלא
        $params[] = $searchText; // לשם פרטי
        $params[] = $searchText; // לשם משפחה
        $params[] = $searchText; // לשם האב
        $params[] = $searchText; // לשם האם
    }
    
    // פילטר מיקום - הוסף את alias הטבלה
    if (!empty($filters['cemetery_id'])) {
        $conditions[] = "{$tableAlias}.cemetery_id = ?";
        $params[] = $filters['cemetery_id'];
    }
    if (!empty($filters['block_id'])) {
        $conditions[] = "{$tableAlias}.block_id = ?";
        $params[] = $filters['block_id'];
    }
    if (!empty($filters['plot_id'])) {
        $conditions[] = "{$tableAlias}.plot_id = ?";
        $params[] = $filters['plot_id'];
    }
    
    // סטטוס - הוסף את alias הטבלה
    if (!empty($filters['status'])) {
        $conditions[] = "{$tableAlias}.status = ?";
        $params[] = $filters['status'];
    }
    
    return $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
}

/**
 * יצוא לאקסל
 */
function exportToExcel($data, $filename) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    // כותרות
    echo '<table border="1">';
    echo '<tr>';
    foreach (array_keys($data[0]) as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    // נתונים
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    exit;
}
?>