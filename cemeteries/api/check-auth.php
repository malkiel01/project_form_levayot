<?php
// api/check-auth.php
session_start();
header('Content-Type: application/json; charset=utf-8');

$response = [
    'logged_in' => false,
    'permission_level' => 0,
    'has_cemetery_access' => false,
    'user_id' => null
];

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $response['logged_in'] = true;
    $response['user_id'] = $_SESSION['user_id'];
    $response['permission_level'] = $_SESSION['permission_level'] ?? 0;
    
    // Check if user is admin (level 4+)
    if ($response['permission_level'] >= 4) {
        $response['has_cemetery_access'] = true;
    } else {
        // Check specific permissions for cemetery module
        require_once '../../config.php';
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_permissions 
            WHERE user_id = ? 
            AND module_name = 'cemeteries' 
            AND can_access = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        $response['has_cemetery_access'] = $stmt->fetchColumn() > 0;
    }
}

echo json_encode($response);
?>