<?php
// ajax/get_graves.php - טעינת קברים לטופס לוויה

require_once '../config.php';
require_once '../includes/functions.php';

// בדיקת הרשאות
if (!isset($_SESSION['user_id']) && !isset($_SESSION['temp_access'])) {
    echo '<option value="">אין הרשאה</option>';
    exit;
}

$areaGrave_id = $_GET['areaGrave_id'] ?? null;
$current_form_uuid = $_GET['current_form_uuid'] ?? null;

if (!$areaGrave_id) {
    echo '<option value="">בחר קודם אחוזת קבר</option>';
    exit;
}

try {
    $db = getDbConnection();
    
    // שליפת כל הקברים באחוזת הקבר עם בדיקת תפיסה
    $sql = "SELECT 
            g.id,
            g.grave_number,
            g.name,
            df.form_uuid,
            df.status as form_status,
            CONCAT(IFNULL(df.deceased_first_name, ''), ' ', IFNULL(df.deceased_last_name, '')) as deceased_name
        FROM graves g
        LEFT JOIN deceased_forms df ON g.id = df.grave_id 
            AND df.status NOT IN ('cancelled', 'deleted', 'archived')
        WHERE g.areaGrave_id = ?
        ORDER BY CAST(g.grave_number AS UNSIGNED), g.grave_number";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$areaGrave_id]);
    $graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // בניית רשימת האופציות
    $options = '<option value="">בחר קבר</option>';
    
    foreach ($graves as $grave) {
        $grave_display = $grave['name'] ?: 'קבר ' . $grave['grave_number'];
        
        // קבע אם להציג את הקבר
        $show_grave = false;
        $grave_status = '';
        $is_selected = false;
        
        if (!$grave['form_uuid']) {
            // קבר פנוי - תמיד הצג
            $show_grave = true;
            $grave_status = ' (פנוי)';
        } elseif ($current_form_uuid && $grave['form_uuid'] == $current_form_uuid) {
            // הקבר שייך לטופס הנוכחי
            $show_grave = true;
            $grave_status = ' (קבר נוכחי)';
            $is_selected = true; // סמן אותו כנבחר
        }
        // אחרת - קבר תפוס של טופס אחר, לא מציגים
        
        if ($show_grave) {
            $selected = $is_selected ? 'selected' : '';
            $options .= sprintf(
                '<option value="%s" %s>%s%s</option>',
                htmlspecialchars($grave['id']),
                $selected,
                htmlspecialchars($grave_display),
                $grave_status
            );
        }
    }
    
    echo $options;
    
} catch (Exception $e) {
    error_log("Error in get_graves.php: " . $e->getMessage());
    echo '<option value="">שגיאה בטעינת קברים</option>';
}
?>