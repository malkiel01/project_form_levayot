<?php
 require_once '../../config.php';

 header('Content-Type: application/json');

 // בדיקת הרשאות
 if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
     echo json_encode(['success' => false, 'message' => 'אין הרשאה למחוק טפסים']);
     exit;
 }

 $data = json_decode(file_get_contents('php://input'), true);

 // בדיקת CSRF
 if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
     echo json_encode(['success' => false, 'message' => 'שגיאת אבטחה']);
     exit;
 }

 $formUuid = $data['form_uuid'] ?? null;
 $formType = $data['form_type'] ?? null;

 if (!$formUuid || !$formType) {
     echo json_encode(['success' => false, 'message' => 'חסרים פרטים']);
     exit;
 }

 try {
     $db = getDbConnection();
     
     // קביעת הטבלה לפי סוג הטופס
     $table = $formType === 'deceased' ? 'deceased_forms' : 'purchase_forms';
     
     // מחיקת הטופס
     $stmt = $db->prepare("DELETE FROM $table WHERE form_uuid = ?");
     $result = $stmt->execute([$formUuid]);
     
     if ($result) {
         // רישום בלוג
         logActivity('form_deleted', [
             'form_type' => $formType,
             'form_uuid' => $formUuid
         ]);
         
         echo json_encode(['success' => true]);
     } else {
         echo json_encode(['success' => false, 'message' => 'לא ניתן למחוק את הטופס']);
     }
     
 } catch (Exception $e) {
     error_log("Error deleting form: " . $e->getMessage());
     echo json_encode(['success' => false, 'message' => 'שגיאה במחיקת הטופס']);
 }
?>