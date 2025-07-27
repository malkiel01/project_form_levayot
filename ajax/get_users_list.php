<?php
// ajax/get_users_list.php - קבלת רשימת משתמשים לבחירה

require_once '../config.php';

// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    exit;
}

$db = getDbConnection();

// קבל רק משתמשים פעילים
$stmt = $db->query("
    SELECT id, username, full_name, permission_level 
    FROM users 
    WHERE is_active = 1 
    ORDER BY full_name, username
");

$users = $stmt->fetchAll();

foreach ($users as $user) {
    $displayName = $user['full_name'] ? 
        $user['full_name'] . ' (' . $user['username'] . ')' : 
        $user['username'];
    
    echo '<option value="' . $user['id'] . '">';
    echo htmlspecialchars($displayName);
    echo '</option>';
}
?>