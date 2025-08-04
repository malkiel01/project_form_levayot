<?php
// PurchaseForm.php - מחלקה לניהול טופס רכישות

class PurchaseForm {
    private $db;
    private $formId;
    private $formUuid;
    private $formData = [];
    
    // שדות חובה
    private $requiredFields = [
        'purchaser_first_name',
        'purchaser_last_name',
        'purchaser_id',
        'purchaser_phone',
        'purchase_date',
        'purchase_type',
        'cemetery_id',
        'block_id',
        'payment_method',
        'payment_amount'
    ];
    
    // סוגי רכישה
    const PURCHASE_TYPES = [
        'new' => 'רכישה חדשה',
        'transfer' => 'העברת בעלות',
        'upgrade' => 'שדרוג חלקה',
        'reservation' => 'הזמנה מראש'
    ];
    
    // אמצעי תשלום
    const PAYMENT_METHODS = [
        'cash' => 'מזומן',
        'check' => 'צ\'ק',
        'credit' => 'כרטיס אשראי',
        'transfer' => 'העברה בנקאית',
        'installments' => 'תשלומים'
    ];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * יצירת טופס רכישה חדש
     */
    public function createForm($userId = null) {
        $this->formUuid = $this->generateUuid();
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO purchase_forms (
                    form_uuid, 
                    user_id, 
                    status, 
                    created_at,
                    updated_at
                ) VALUES (?, ?, 'draft', NOW(), NOW())
            ");
            
            $stmt->execute([$this->formUuid, $userId]);
            $this->formId = $this->db->lastInsertId();
            
            // רישום בלוג
            $this->logActivity('create', 'טופס רכישה חדש נוצר');
            
            return [
                'success' => true,
                'formUuid' => $this->formUuid,
                'formId' => $this->formId
            ];
            
        } catch (Exception $e) {
            error_log("Error creating purchase form: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'שגיאה ביצירת טופס רכישה'
            ];
        }
    }
    
    /**
     * טעינת טופס קיים
     */
    public function loadForm($formUuid) {
        try {
            $stmt = $this->db->prepare("
                SELECT pf.*, 
                       u.username as created_by_name,
                       c.name as cemetery_name,
                       b.name as block_name,
                       s.name as section_name,
                       r.row_number,
                       g.grave_number,
                       p.plot_number
                FROM purchase_forms pf
                LEFT JOIN users u ON pf.user_id = u.id
                LEFT JOIN cemeteries c ON pf.cemetery_id = c.id
                LEFT JOIN blocks b ON pf.block_id = b.id
                LEFT JOIN sections s ON pf.section_id = s.id
                LEFT JOIN rows r ON pf.row_id = r.id
                LEFT JOIN graves g ON pf.grave_id = g.id
                LEFT JOIN plots p ON pf.plot_id = p.id
                WHERE pf.form_uuid = ?
            ");
            
            $stmt->execute([$formUuid]);
            $formData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$formData) {
                return ['success' => false, 'error' => 'טופס לא נמצא'];
            }
            
            $this->formId = $formData['id'];
            $this->formUuid = $formUuid;
            $this->formData = $formData;
            
            // טען הנהנים
            $this->formData['beneficiaries'] = $this->loadBeneficiaries();
            
            // טען תשלומים
            $this->formData['payments'] = $this->loadPayments();
            
            // חשב אחוז התקדמות
            $this->formData['progress_percentage'] = $this->calculateProgress();
            
            return [
                'success' => true,
                'formData' => $this->formData
            ];
            
        } catch (Exception $e) {
            error_log("Error loading purchase form: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'שגיאה בטעינת הטופס'
            ];
        }
    }
    
    /**
     * שמירת נתוני טופס
     */
    public function saveForm($data, $userId = null) {
        try {
            $this->db->beginTransaction();
            
            // בדיקת תקינות
            $validation = $this->validateFormData($data);
            if (!$validation['valid']) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }
            
            // עדכון הטבלה הראשית
            $stmt = $this->db->prepare("
                UPDATE purchase_forms SET
                    purchaser_first_name = ?,
                    purchaser_last_name = ?,
                    purchaser_id = ?,
                    purchaser_phone = ?,
                    purchaser_email = ?,
                    purchaser_address = ?,
                    purchase_date = ?,
                    purchase_type = ?,
                    contract_number = ?,
                    purchase_price = ?,
                    cemetery_id = ?,
                    block_id = ?,
                    section_id = ?,
                    row_id = ?,
                    grave_id = ?,
                    plot_id = ?,
                    payment_method = ?,
                    payment_amount = ?,
                    payment_date = ?,
                    remaining_balance = ?,
                    installments = ?,
                    notes = ?,
                    special_conditions = ?,
                    updated_at = NOW(),
                    updated_by = ?
                WHERE form_uuid = ?
            ");
            
            $stmt->execute([
                $data['purchaser_first_name'] ?? null,
                $data['purchaser_last_name'] ?? null,
                $data['purchaser_id'] ?? null,
                $data['purchaser_phone'] ?? null,
                $data['purchaser_email'] ?? null,
                $data['purchaser_address'] ?? null,
                $data['purchase_date'] ?? null,
                $data['purchase_type'] ?? null,
                $data['contract_number'] ?? null,
                $data['purchase_price'] ?? null,
                $data['cemetery_id'] ?? null,
                $data['block_id'] ?? null,
                $data['section_id'] ?? null,
                $data['row_id'] ?? null,
                $data['grave_id'] ?? null,
                $data['plot_id'] ?? null,
                $data['payment_method'] ?? null,
                $data['payment_amount'] ?? null,
                $data['payment_date'] ?? null,
                $data['remaining_balance'] ?? null,
                $data['installments'] ?? null,
                $data['notes'] ?? null,
                $data['special_conditions'] ?? null,
                $userId,
                $this->formUuid
            ]);
            
            // שמור הנהנים
            if (isset($data['beneficiaries'])) {
                $this->saveBeneficiaries($data['beneficiaries']);
            }
            
            // עדכון סטטוס אם נדרש
            if (isset($data['submit_action'])) {
                $this->updateStatus($data['submit_action']);
            }
            
            $this->db->commit();
            
            // רישום בלוג
            $this->logActivity('update', 'הטופס עודכן');
            
            return [
                'success' => true,
                'message' => 'הטופס נשמר בהצלחה'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error saving purchase form: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'שגיאה בשמירת הטופס'
            ];
        }
    }
    
    /**
     * בדיקת תקינות נתונים
     */
    private function validateFormData($data) {
        $errors = [];
        
        // בדיקת שדות חובה
        foreach ($this->requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = 'שדה חובה';
            }
        }
        
        // בדיקת ת.ז.
        if (!empty($data['purchaser_id']) && !$this->validateIsraeliId($data['purchaser_id'])) {
            $errors['purchaser_id'] = 'ת.ז. לא תקינה';
        }
        
        // בדיקת אימייל
        if (!empty($data['purchaser_email']) && !filter_var($data['purchaser_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['purchaser_email'] = 'כתובת אימייל לא תקינה';
        }
        
        // בדיקת טלפון
        if (!empty($data['purchaser_phone']) && !preg_match('/^[0-9\-\+\s]+$/', $data['purchaser_phone'])) {
            $errors['purchaser_phone'] = 'מספר טלפון לא תקין';
        }
        
        // בדיקת סכומים
        if (!empty($data['purchase_price']) && !is_numeric($data['purchase_price'])) {
            $errors['purchase_price'] = 'מחיר לא תקין';
        }
        
        if (!empty($data['payment_amount']) && !is_numeric($data['payment_amount'])) {
            $errors['payment_amount'] = 'סכום תשלום לא תקין';
        }
        
        // בדיקת זמינות חלקה
        if (!empty($data['plot_id']) && !$this->checkPlotAvailability($data['plot_id'])) {
            $errors['plot_id'] = 'החלקה שנבחרה אינה זמינה';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * בדיקת ת.ז. ישראלית
     */
    private function validateIsraeliId($id) {
        $id = trim($id);
        if (!preg_match('/^\d{9}$/', $id)) {
            return false;
        }
        
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $num = intval($id[$i]);
            $num *= (($i % 2) + 1);
            if ($num > 9) {
                $num = intval($num / 10) + ($num % 10);
            }
            $sum += $num;
        }
        
        return $sum % 10 == 0;
    }
    
    /**
     * בדיקת זמינות חלקה
     */
    private function checkPlotAvailability($plotId) {
        $stmt = $this->db->prepare("
            SELECT status FROM plots 
            WHERE id = ? AND status = 'available'
        ");
        $stmt->execute([$plotId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * טעינת הנהנים
     */
    private function loadBeneficiaries() {
        $stmt = $this->db->prepare("
            SELECT * FROM purchase_beneficiaries 
            WHERE purchase_form_id = ?
            ORDER BY id
        ");
        $stmt->execute([$this->formId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * שמירת הנהנים
     */
    private function saveBeneficiaries($beneficiaries) {
        // מחק הנהנים קיימים
        $stmt = $this->db->prepare("DELETE FROM purchase_beneficiaries WHERE purchase_form_id = ?");
        $stmt->execute([$this->formId]);
        
        // הוסף הנהנים חדשים
        if (!empty($beneficiaries)) {
            $stmt = $this->db->prepare("
                INSERT INTO purchase_beneficiaries (
                    purchase_form_id, name, id_number, relation
                ) VALUES (?, ?, ?, ?)
            ");
            
            foreach ($beneficiaries as $beneficiary) {
                if (!empty($beneficiary['name'])) {
                    $stmt->execute([
                        $this->formId,
                        $beneficiary['name'],
                        $beneficiary['id_number'] ?? null,
                        $beneficiary['relation'] ?? null
                    ]);
                }
            }
        }
    }
    
    /**
     * טעינת תשלומים
     */
    private function loadPayments() {
        $stmt = $this->db->prepare("
            SELECT * FROM purchase_payments 
            WHERE purchase_form_id = ?
            ORDER BY payment_date
        ");
        $stmt->execute([$this->formId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * חישוב אחוז התקדמות
     */
    private function calculateProgress() {
        $filledFields = 0;
        $totalFields = count($this->requiredFields);
        
        foreach ($this->requiredFields as $field) {
            if (!empty($this->formData[$field])) {
                $filledFields++;
            }
        }
        
        return round(($filledFields / $totalFields) * 100);
    }
    
    /**
     * עדכון סטטוס
     */
    private function updateStatus($action) {
        $status = 'draft';
        
        switch ($action) {
            case 'submit':
                $status = 'submitted';
                break;
            case 'approve':
                $status = 'approved';
                break;
            case 'reject':
                $status = 'rejected';
                break;
            case 'complete':
                $status = 'completed';
                break;
        }
        
        $stmt = $this->db->prepare("
            UPDATE purchase_forms 
            SET status = ?, updated_at = NOW()
            WHERE form_uuid = ?
        ");
        $stmt->execute([$status, $this->formUuid]);
        
        $this->logActivity('status_change', "סטטוס שונה ל: $status");
    }
    
    /**
     * רישום פעילות
     */
    private function logActivity($action, $description) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO purchase_form_logs (
                    form_id, action, description, user_id, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $userId = $_SESSION['user_id'] ?? null;
            $stmt->execute([$this->formId, $action, $description, $userId]);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
    
    /**
     * יצירת UUID
     */
    private function generateUuid() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * קבלת שדות חובה
     */
    public function getRequiredFields() {
        return $this->requiredFields;
    }
    
    /**
     * קבלת סוגי רכישה
     */
    public static function getPurchaseTypes() {
        return self::PURCHASE_TYPES;
    }
    
    /**
     * קבלת אמצעי תשלום
     */
    public static function getPaymentMethods() {
        return self::PAYMENT_METHODS;
    }
}