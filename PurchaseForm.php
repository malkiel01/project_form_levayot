// PurchaseForm.php
require_once 'BaseForm.php';

class PurchaseForm extends BaseForm {
    protected $formType = 'purchase';
    protected $tableName = 'purchase_forms';
    
    protected function getRequiredFields() {
        $stmt = $this->db->prepare("
            SELECT field_name FROM field_permissions 
            WHERE form_type = ? AND permission_level = ? AND is_required = 1
        ");
        $stmt->execute([$this->formType, $this->userPermissionLevel]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    protected function getFormFields() {
        return [
            'buyer_type', 'buyer_id_type', 'buyer_id_number', 'buyer_name',
            'buyer_phone', 'buyer_email', 'buyer_address', 'purchase_type',
            'purchase_date', 'payment_method', 'total_amount', 'paid_amount',
            'installments_count', 'cemetery_id', 'block_id', 'section_id',
            'row_id', 'grave_id', 'plot_id', 'contract_number', 'notes',
            'special_conditions', 'buyer_signature', 'seller_signature'
        ];
    }
    
    protected function validateFormData($data) {
        $errors = [];
        
        // בדיקת שדות חובה
        if (empty($data['buyer_id_number'])) {
            $errors['buyer_id_number'] = 'מספר זיהוי הוא שדה חובה';
        }
        
        // ולידציה של ת.ז.
        if ($data['buyer_id_type'] === 'tz' && !$this->validateIsraeliID($data['buyer_id_number'])) {
            $errors['buyer_id_number'] = 'מספר תעודת זהות לא תקין';
        }
        
        // בדיקת סכומים
        if (isset($data['paid_amount']) && isset($data['total_amount'])) {
            if ($data['paid_amount'] > $data['total_amount']) {
                $errors['paid_amount'] = 'הסכום ששולם לא יכול להיות גדול מהסכום הכולל';
            }
        }
        
        return $errors;
    }
    
    private function validateIsraeliID($id) {
        $id = trim($id);
        if (strlen($id) > 9 || !is_numeric($id)) return false;
        
        $id = str_pad($id, 9, '0', STR_PAD_LEFT);
        $sum = 0;
        
        for ($i = 0; $i < 9; $i++) {
            $num = intval($id[$i]);
            $num *= ($i % 2) + 1;
            $sum += $num > 9 ? $num - 9 : $num;
        }
        
        return $sum % 10 === 0;
    }
}