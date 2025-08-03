<?php
// push-notifications.php - מערכת שליחת התראות Push

require_once 'config.php';

class PushNotificationService {
    private $db;
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * רישום מכשיר לקבלת התראות
     */
    public function registerDevice($userId, $subscription) {
        $stmt = $this->db->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $userId,
            $subscription['endpoint'],
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth']
        ]);
        
        return true;
    }
    
    /**
     * שליחת התראה למשתמש ספציפי
     */
    public function sendToUser($userId, $title, $body, $data = []) {
        $stmt = $this->db->prepare("
            SELECT endpoint, p256dh, auth 
            FROM push_subscriptions 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        $subscriptions = $stmt->fetchAll();
        
        foreach ($subscriptions as $subscription) {
            $this->sendNotification($subscription, $title, $body, $data);
        }
    }
    
    /**
     * שליחת התראה לקבוצת משתמשים
     */
    public function sendToGroup($userIds, $title, $body, $data = []) {
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $stmt = $this->db->prepare("
            SELECT endpoint, p256dh, auth 
            FROM push_subscriptions 
            WHERE user_id IN ($placeholders) AND is_active = 1
        ");
        $stmt->execute($userIds);
        $subscriptions = $stmt->fetchAll();
        
        foreach ($subscriptions as $subscription) {
            $this->sendNotification($subscription, $title, $body, $data);
        }
    }
    
    /**
     * שליחת התראה על יצירת טופס חדש
     */
    public function notifyNewForm($formUuid, $createdBy, $deceasedName) {
        // מצא את כל המנהלים
        $stmt = $this->db->prepare("
            SELECT id FROM users 
            WHERE permission_level >= 3 AND id != ? AND is_active = 1
        ");
        $stmt->execute([$createdBy]);
        $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($adminIds)) {
            $title = 'טופס חדש נוצר';
            $body = "נוצר טופס חדש עבור: $deceasedName";
            $data = [
                'type' => 'new_form',
                'form_uuid' => $formUuid
            ];
            
            $this->sendToGroup($adminIds, $title, $body, $data);
        }
    }
    
    /**
     * שליחת התראה על עדכון טופס
     */
    public function notifyFormUpdate($formUuid, $updatedBy, $deceasedName, $changes) {
        // מצא את יוצר הטופס
        $stmt = $this->db->prepare("
            SELECT created_by FROM deceased_forms 
            WHERE form_uuid = ?
        ");
        $stmt->execute([$formUuid]);
        $createdBy = $stmt->fetchColumn();
        
        if ($createdBy && $createdBy != $updatedBy) {
            $title = 'הטופס עודכן';
            $body = "הטופס של $deceasedName עודכן";
            $data = [
                'type' => 'form_update',
                'form_uuid' => $formUuid,
                'changes' => $changes
            ];
            
            $this->sendToUser($createdBy, $title, $body, $data);
        }
    }
    
    /**
     * שליחת ההתראה בפועל
     */
    private function sendNotification($subscription, $title, $body, $data = []) {
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/icons/icon-192x192.png',
            'badge' => '/icons/badge-72x72.png',
            'data' => $data,
            'timestamp' => time()
        ]);
        
        // כאן תצטרך להשתמש בספריית Web Push
        // composer require minishlink/web-push
        
        try {
            // דוגמה לשימוש בספרייה:
            /*
            $auth = [
                'VAPID' => [
                    'subject' => 'mailto:admin@example.com',
                    'publicKey' => $_ENV['VAPID_PUBLIC_KEY'],
                    'privateKey' => $_ENV['VAPID_PRIVATE_KEY'],
                ],
            ];
            
            $webPush = new \Minishlink\WebPush\WebPush($auth);
            
            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription['endpoint'],
                    'publicKey' => $subscription['p256dh'],
                    'authToken' => $subscription['auth'],
                ]),
                $payload
            );
            */
            
            return true;
        } catch (Exception $e) {
            error_log('Push notification failed: ' . $e->getMessage());
            return false;
        }
    }
}

// דוגמאות לשימוש:

// רישום מכשיר (נקרא מ-JavaScript)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'subscribe') {
    $subscription = json_decode(file_get_contents('php://input'), true);
    $push = new PushNotificationService();
    $result = $push->registerDevice($_SESSION['user_id'], $subscription);
    echo json_encode(['success' => $result]);
}

// שליחת התראה על טופס חדש
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'notify_new_form') {
    $push = new PushNotificationService();
    $push->notifyNewForm(
        $_POST['form_uuid'],
        $_SESSION['user_id'],
        $_POST['deceased_name']
    );
}

// SQL ליצירת טבלת push_subscriptions:
/*
CREATE TABLE push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh VARCHAR(255),
    auth VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME,
    updated_at DATETIME,
    UNIQUE KEY unique_endpoint (endpoint),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
*/