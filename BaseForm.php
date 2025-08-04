// BaseForm.php
abstract class BaseForm {
    protected $db;
    protected $formId;
    protected $formData;
    protected $userPermissionLevel;
    protected $formType;
    protected $tableName;
    
    public function __construct($formId = null, $userPermissionLevel = 1) {
        $this->db = getDbConnection();
        $this->userPermissionLevel = $userPermissionLevel;
        
        if ($formId) {
            $this->loadForm($formId);
        }
    }
    
    abstract protected function getRequiredFields();
    abstract protected function getFormFields();
    abstract protected function validateFormData($data);
    
    public function loadForm($formId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->tableName} WHERE form_uuid = ?");
        $stmt->execute([$formId]);
        $this->formData = $stmt->fetch();
        
        if ($this->formData) {
            $this->formId = $this->formData['id'];
            return true;
        }
        return false;
    }
    
    public function canEditField($fieldName) {
        $stmt = $this->db->prepare("
            SELECT can_edit FROM field_permissions 
            WHERE form_type = ? AND field_name = ? AND permission_level = ?
        ");
        $stmt->execute([$this->formType, $fieldName, $this->userPermissionLevel]);
        $result = $stmt->fetch();
        
        return $result ? $result['can_edit'] : false;
    }
    
    public function calculateProgress($data) {
        $requiredFields = $this->getRequiredFields();
        $filledRequired = 0;
        
        foreach ($requiredFields as $field) {
            if (!empty($data[$field])) {
                $filledRequired++;
            }
        }
        
        return $requiredFields ? round(($filledRequired / count($requiredFields)) * 100) : 0;
    }
    
    // שאר המתודות המשותפות...
}