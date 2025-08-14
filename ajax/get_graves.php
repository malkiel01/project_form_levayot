<?php
// ajax/get_graves.php - טעינת קברים לטופס לוויה עם דיבוג

require_once '../config.php';
require_once '../includes/functions.php';

// דיבוג - הוסף את זה זמנית
error_log("=== GET_GRAVES.PHP DEBUG START ===");
error_log("GET params: " . print_r($_GET, true));
error_log("Session user: " . ($_SESSION['user_id'] ?? 'none'));
error_log("Temp access: " . ($_SESSION['temp_access'] ?? 'none'));

// בדיקת הרשאות
if (!isset($_SESSION['user_id']) && !isset($_SESSION['temp_access'])) {
    error_log("No permission - exiting");
    echo '<option value="">אין הרשאה</option>';
    exit;
}

$areaGrave_id = $_GET['areaGrave_id'] ?? null;
$current_form_uuid = $_GET['current_form_uuid'] ?? null;

error_log("areaGrave_id: " . ($areaGrave_id ?? 'null'));
error_log("current_form_uuid: " . ($current_form_uuid ?? 'null'));

if (!$areaGrave_id) {
    error_log("No areaGrave_id - exiting");
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
    
    error_log("Found " . count($graves) . " graves in areaGrave_id: " . $areaGrave_id);
    
    // בניית רשימת האופציות
    $options = '<option value="">בחר קבר</option>';
    $shown_count = 0;
    
    foreach ($graves as $grave) {
        $grave_display = $grave['name'] ?: 'קבר ' . $grave['grave_number'];
        
        error_log("Processing grave ID: " . $grave['id'] . ", form_uuid: " . ($grave['form_uuid'] ?? 'null'));
        
        // קבע אם להציג את הקבר
        $show_grave = false;
        $grave_status = '';
        $is_selected = false;
        
        if (!$grave['form_uuid']) {
            // קבר פנוי - תמיד הצג
            $show_grave = true;
            $grave_status = ' (פנוי)';
            error_log("Grave " . $grave['id'] . " is free");
        } elseif ($current_form_uuid && $grave['form_uuid'] == $current_form_uuid) {
            // הקבר שייך לטופס הנוכחי
            $show_grave = true;
            $grave_status = ' (קבר נוכחי)';
            $is_selected = true;
            error_log("Grave " . $grave['id'] . " belongs to current form");
        } else {
            error_log("Grave " . $grave['id'] . " is occupied by form: " . $grave['form_uuid']);
        }
        
        if ($show_grave) {
            $shown_count++;
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
    
    error_log("Showing " . $shown_count . " graves out of " . count($graves));
    error_log("=== GET_GRAVES.PHP DEBUG END ===");
    
    echo $options;
    
} catch (Exception $e) {
    error_log("ERROR in get_graves.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo '<option value="">שגיאה בטעינת קברים</option>';
}
?>