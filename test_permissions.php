<?php
// test_permissions.php - בדיקה פשוטה

require_once 'config.php';

// סימולציה של משתמש 3 ברמה 1
$_SESSION['user_id'] = 3;
$_SESSION['permission_level'] = 1;

echo "<h2>בדיקת הרשאות למשתמש 3</h2>";

// בדיקת שדות רגילים
$tests = [
    ['deceased_name', 'view', 'האם יכול לצפות בשם נפטר?'],
    ['deceased_name', 'edit', 'האם יכול לערוך שם נפטר?'],
    ['cemetery_id', 'view', 'האם יכול לצפות בבית עלמין? (צריך להיות כן - הרשאה מיוחדת)'],
    ['cemetery_id', 'edit', 'האם יכול לערוך בית עלמין? (צריך להיות כן - הרשאה מיוחדת)'],
    ['identification_number', 'view', 'האם יכול לצפות במספר זיהוי?'],
];

foreach ($tests as [$field, $action, $desc]) {
    $result = hasPermission($field, 1, $action);
    $status = $result ? '✅ כן' : '❌ לא';
    echo "<p>{$desc}: <strong>{$status}</strong></p>";
}

// בדיקת מה יש בטבלה
echo "<h3>שדות עם הרשאות מיוחדות:</h3>";
$db = getDbConnection();
$stmt = $db->query("
    SELECT 
        field_name, 
        user_specific_view, 
        user_specific_edit 
    FROM field_permissions 
    WHERE user_specific_view IS NOT NULL OR user_specific_edit IS NOT NULL
");
$specialPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($specialPerms as $perm) {
    echo "<p><strong>{$perm['field_name']}:</strong>";
    if ($perm['user_specific_view']) {
        echo " צפייה: " . $perm['user_specific_view'];
    }
    if ($perm['user_specific_edit']) {
        echo " עריכה: " . $perm['user_specific_edit'];
    }
    echo "</p>";
}
?>