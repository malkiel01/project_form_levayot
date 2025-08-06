<?php
// ajax/user_preferences.php - ניהול העדפות משתמש

require_once '../config.php';
require_once '../includes/lists/list_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($data['action']) {
        case 'save_preference':
            $result = saveUserPreference(
                $userId,
                $data['preference_key'],
                $data['preference_name'],
                $data['preference_data'],
                $data['is_default'] ?? false
            );
            
            echo json_encode(['success' => $result]);
            break;
            
        case 'delete_preference':
            $result = deleteUserPreference($userId, $data['preference_id']);
            echo json_encode(['success' => $result]);
            break;
    }
} else {
    // GET requests
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get':
            $preferenceId = $_GET['id'] ?? 0;
            $db = getDbConnection();
            
            $stmt = $db->prepare("
                SELECT * FROM user_preferences 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$preferenceId, $userId]);
            $preference = $stmt->fetch();
            
            if ($preference) {
                $preference['preference_data'] = json_decode($preference['preference_data'], true);
                echo json_encode(['success' => true, 'preference' => $preference]);
            } else {
                echo json_encode(['success' => false]);
            }
            break;
            
        case 'list':
            $key = $_GET['key'] ?? '';
            $preferences = getUserPreferences($userId, $key);
            echo json_encode(['success' => true, 'preferences' => $preferences]);
            break;
    }
}
?>