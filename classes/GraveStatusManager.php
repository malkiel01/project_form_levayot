<?php
// GraveStatusManager.php - מחלקה לניהול סטטוס קברים

class GraveStatusManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * קבלת סטטוס נוכחי של קבר
     */
    public function getGraveStatus($graveId) {
        // בדיקה ראשונה - האם יש טופס לוויה (הכי חשוב)
        $burial = $this->checkBurialForm($graveId);
        if ($burial) {
            return [
                'status' => 'occupied',
                'status_hebrew' => 'קבור',
                'details' => $burial,
                'can_edit' => false,
                'reason' => 'קיים טופס לוויה על הקבר'
            ];
        }
        
        // בדיקה שניה - האם יש טופס רכישה
        $purchase = $this->checkPurchaseForm($graveId);
        if ($purchase) {
            if ($purchase['status'] == 'completed') {
                return [
                    'status' => 'purchased',
                    'status_hebrew' => 'רכישה',
                    'details' => $purchase,
                    'can_edit' => true,
                    'reason' => 'קיים טופס רכישה מושלם'
                ];
            } else {
                return [
                    'status' => 'reserved',
                    'status_hebrew' => 'שמירה',
                    'details' => $purchase,
                    'can_edit' => true,
                    'reason' => 'קיים טופס רכישה בתהליך'
                ];
            }
        }
        
        // ברירת מחדל - פנוי
        return [
            'status' => 'available',
            'status_hebrew' => 'פנוי',
            'details' => null,
            'can_edit' => true,
            'reason' => 'הקבר פנוי'
        ];
    }
    
    /**
     * בדיקת טופס לוויה
     */
    private function checkBurialForm($graveId) {
        $stmt = $this->db->prepare("
            SELECT 
                df.id,
                df.form_uuid,
                CONCAT(IFNULL(df.deceased_first_name, ''), ' ', IFNULL(df.deceased_last_name, '')) as deceased_name,
                df.death_date,
                df.burial_date,
                df.status,
                df.created_at
            FROM deceased_forms df
            WHERE df.grave_id = ?
            AND df.status != 'cancelled'
            ORDER BY df.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$graveId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * בדיקת טופס רכישה
     */
    private function checkPurchaseForm($graveId) {
        // בדוק אם הטבלה קיימת
        $tableCheck = $this->db->query("SHOW TABLES LIKE 'purchase_forms'");
        if ($tableCheck->rowCount() == 0) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                pf.id,
                pf.form_uuid,
                pf.purchaser_name,
                pf.purchase_date,
                pf.status,
                pf.created_at
            FROM purchase_forms pf
            WHERE pf.grave_id = ?
            AND (pf.status != 'cancelled' OR pf.status IS NULL)
            ORDER BY pf.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$graveId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * עדכון סטטוס קבר
     */
    public function updateGraveStatus($graveId) {
        $statusInfo = $this->getGraveStatus($graveId);
        
        $stmt = $this->db->prepare("
            UPDATE graves 
            SET status = ?,
                is_available = ?
            WHERE id = ?
        ");
        
        $isAvailable = ($statusInfo['status'] == 'available') ? 1 : 0;
        $stmt->execute([$statusInfo['status'], $isAvailable, $graveId]);
        
        return $statusInfo;
    }
    
    /**
     * סנכרון כל הקברים במערכת
     */
    public function syncAllGraves() {
        $stmt = $this->db->query("SELECT id FROM graves");
        $graves = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $results = [
            'updated' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        foreach ($graves as $graveId) {
            try {
                $status = $this->updateGraveStatus($graveId);
                $results['updated']++;
                $results['details'][] = [
                    'grave_id' => $graveId,
                    'status' => $status['status']
                ];
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'grave_id' => $graveId,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * בדיקה האם ניתן לשנות קבר
     */
    public function canModifyGrave($graveId) {
        $status = $this->getGraveStatus($graveId);
        return $status['can_edit'];
    }
    
    /**
     * קבלת היסטוריית סטטוסים של קבר
     */
    public function getGraveHistory($graveId) {
        $history = [];
        
        // היסטוריית קבורות
        $stmt = $this->db->prepare("
            SELECT 
                'burial' as type,
                deceased_name as name,
                burial_date as event_date,
                status,
                created_at
            FROM deceased_forms
            WHERE grave_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$graveId]);
        $burials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // היסטוריית רכישות
        $tableCheck = $this->db->query("SHOW TABLES LIKE 'purchase_forms'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $this->db->prepare("
                SELECT 
                    'purchase' as type,
                    purchaser_name as name,
                    purchase_date as event_date,
                    status,
                    created_at
                FROM purchase_forms
                WHERE grave_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$graveId]);
            $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $history = array_merge($burials, $purchases);
        } else {
            $history = $burials;
        }
        
        // מיון לפי תאריך
        usort($history, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $history;
    }
}
?>