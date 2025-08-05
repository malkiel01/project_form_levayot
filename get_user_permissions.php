<?php
// get_user_permissions.php
require_once 'config.php';

header('Content-Type: application/json');

// רק מנהלים
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die(json_encode(['error' => 'Access denied']));
}

$userId = $_GET['user_id'] ?? 0;
$db = getDbConnection();

$stmt = $db->prepare("
    SELECT permission_name 
    FROM user_permissions 
    WHERE user_id = ? AND has_permission = 1
");
$stmt->execute([$userId]);

$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode(['permissions' => $permissions]);
?>