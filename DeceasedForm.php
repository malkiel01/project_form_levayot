<?php
// DeceasedForm.php - מחלקת ניהול טופס נפטרים

require_once 'config.php';

class DeceasedForm2 {
    private $db;
    private $formId;
    private $formData;
    private $userPermissionLevel;
    
    public function __construct($formId = null, $userPermissionLevel = 1) {
        $this->db = getDbConnection();
        $this->userPermissionLevel = $userPermissionLevel;
        
        if ($formId) {
            $this->loadForm($formId);
        }
    }
    
    // טעינת טופס קיים
    public function loadForm($formId) {
        $stmt = $this->db->prepare("SELECT * FROM deceased_forms WHERE form_uuid = ?");
        $stmt->execute([$formId]);
        $this->formData = $stmt->fetch();
        
        if ($this->formData) {
            $this->formId = $this->formData['id'];
            return true;
        }
        return false;
    }
    
    // יצירת טופס חדש
    public function createForm($data) {
        $data['form_uuid'] = generateUUID();
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        
        // חישוב אחוז התקדמות
        $data['progress_percentage'] = $this->calculateProgress($data);
        
        // בניית שאילתת INSERT
        $fields = [];
        $values = [];
        $placeholders = [];
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field)) {
                $fields[] = $field;
                $values[] = $value;
                $placeholders[] = '?';
            }
        }
        
        $sql = "INSERT INTO deceased_forms (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        $this->formId = $this->db->lastInsertId();
        $this->loadForm($data['form_uuid']);
        
        return $data['form_uuid'];
    }
    
    // עדכון טופס
    public function updateForm($data) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $data['progress_percentage'] = $this->calculateProgress(array_merge($this->formData, $data));
        
        // בניית שאילתת UPDATE
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field)) {
                $fields[] = "$field = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $this->formId;
        $sql = "UPDATE deceased_forms SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    // שמירת חתימה
    public function saveSignature($signatureData) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        return $this->updateForm(['client_signature' => $signatureData]);
    }
    
    // חישוב אחוז התקדמות
    private function calculateProgress($data) {
        $requiredFields = $this->getRequiredFields();
        $filledFields = 0;
        $totalFields = count($requiredFields);
        
        foreach ($requiredFields as $field) {
            if (!empty($data[$field])) {
                $filledFields++;
            }
        }
        
        return $totalFields > 0 ? round(($filledFields / $totalFields) * 100) : 0;
    }
    
    // קבלת שדות חובה לפי הרשאה
    private function getRequiredFields() {
        $stmt = $this->db->prepare("
            SELECT field_name 
            FROM field_permissions 
            WHERE permission_level = ? AND is_required = 1
        ");
        $stmt->execute([$this->userPermissionLevel]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // בדיקת הרשאת צפייה בשדה
    public function canViewField($fieldName) {
        return hasPermission($fieldName, $this->userPermissionLevel, 'view');
    }
    
    // בדיקת הרשאת עריכת שדה
    public function canEditField($fieldName) {
        return hasPermission($fieldName, $this->userPermissionLevel, 'edit');
    }
    
    // קבלת נתוני הטופס עם סינון לפי הרשאות
    public function getFormData() {
        if (!$this->formData) {
            return null;
        }
        
        $filteredData = [];
        foreach ($this->formData as $field => $value) {
            if ($this->canViewField($field)) {
                $filteredData[$field] = $value;
            }
        }
        
        return $filteredData;
    }
    
    // ולידציה של הטופס
    public function validateForm($data) {
        $errors = [];
        
        // בדיקת שדות חובה
        $requiredFields = $this->getRequiredFields();
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = "שדה חובה";
            }
        }
        
        // ולידציות ספציפיות
        if (!empty($data['identification_type'])) {
            if (in_array($data['identification_type'], ['tz', 'passport'])) {
                if (empty($data['identification_number'])) {
                    $errors['identification_number'] = "מספר זיהוי הוא שדה חובה עבור סוג זיהוי זה";
                } elseif ($data['identification_type'] === 'tz' && !validateIsraeliId($data['identification_number'])) {
                    $errors['identification_number'] = "מספר תעודת זהות לא תקין";
                }
                
                if (empty($data['birth_date'])) {
                    $errors['birth_date'] = "תאריך לידה הוא שדה חובה עבור סוג זיהוי זה";
                }
            }
        }
        
        // בדיקת תאריכים
        if (!empty($data['death_date']) && !empty($data['burial_date'])) {
            if (strtotime($data['burial_date']) < strtotime($data['death_date'])) {
                $errors['burial_date'] = "תאריך הקבורה לא יכול להיות לפני תאריך הפטירה";
            }
        }
        
        return $errors;
    }
    
    // קבלת רשימת בתי עלמין
    public function getCemeteries() {
        if (!$this->canViewField('cemetery_id')) {
            return [];
        }
        
        $stmt = $this->db->query("SELECT id, name FROM cemeteries WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת גושים לפי בית עלמין
    public function getBlocks($cemeteryId) {
        if (!$this->canViewField('block_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM blocks WHERE cemetery_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$cemeteryId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת חלקות לפי גוש
    public function getSections($blockId) {
        if (!$this->canViewField('section_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM sections WHERE block_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$blockId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת שורות לפי חלקה
    public function getRows($sectionId) {
        if (!$this->canViewField('row_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM rows WHERE section_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת קברים לפי שורה
    public function getGraves($rowId) {
        if (!$this->canViewField('grave_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM graves WHERE row_id = ? AND is_available = 1 ORDER BY name");
        $stmt->execute([$rowId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת אחוזות קבר לפי בית עלמין
    public function getPlots($cemeteryId) {
        if (!$this->canViewField('plot_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM plots WHERE cemetery_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$cemeteryId]);
        return $stmt->fetchAll();
    }
    
    // העלאת מסמך
    public function uploadDocument($file, $documentType = null) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        // בדיקת סוג קובץ
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, ALLOWED_FILE_TYPES)) {
            throw new Exception("סוג קובץ לא מורשה");
        }
        
        // בדיקת גודל קובץ
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("הקובץ גדול מדי");
        }
        
        // יצירת שם קובץ ייחודי
        $fileName = uniqid() . '_' . time() . '.' . $fileExt;
        $uploadPath = UPLOAD_PATH . $this->formId . '/';
        
        // יצירת תיקייה אם לא קיימת
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        
        // העלאת הקובץ
        if (move_uploaded_file($file['tmp_name'], $uploadPath . $fileName)) {
            // שמירה בדטהבייס
            $stmt = $this->db->prepare("
                INSERT INTO deceased_documents 
                (form_id, document_type, file_name, file_path, file_size, mime_type, uploaded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->formId,
                $documentType,
                $file['name'],
                $uploadPath . $fileName,
                $file['size'],
                $file['type'],
                $_SESSION['user_id'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        }
        
        throw new Exception("שגיאה בהעלאת הקובץ");
    }
    
    // קבלת רשימת מסמכים
    public function getDocuments() {
        if (!$this->formId) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM deceased_documents 
            WHERE form_id = ? 
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$this->formId]);
        
        return $stmt->fetchAll();
    }
}

class DeceasedForm3 {
    private $db;
    private $formId;
    private $formData;
    private $userPermissionLevel;
    
    public function __construct($formId = null, $userPermissionLevel = 1) {
        $this->db = getDbConnection();
        $this->userPermissionLevel = $userPermissionLevel;
        
        if ($formId) {
            $this->loadForm($formId);
        }
    }
    
    // טעינת טופס קיים
    public function loadForm($formId) {
        $stmt = $this->db->prepare("SELECT * FROM deceased_forms WHERE form_uuid = ?");
        $stmt->execute([$formId]);
        $this->formData = $stmt->fetch();
        
        if ($this->formData) {
            $this->formId = $this->formData['id'];
            return true;
        }
        return false;
    }
    
    // יצירת טופס חדש
    public function createForm($data) {
        // וודא שיש UUID תקין
        if (empty($data['form_uuid'])) {
            $data['form_uuid'] = generateUUID();
        }
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        
        // חישוב אחוז התקדמות
        $data['progress_percentage'] = $this->calculateProgress($data);
        
        // בניית שאילתת INSERT
        $fields = [];
        $values = [];
        $placeholders = [];
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field)) {
                $fields[] = $field;
                $values[] = $value;
                $placeholders[] = '?';
            }
        }
        
        $sql = "INSERT INTO deceased_forms (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        $this->formId = $this->db->lastInsertId();
        $this->loadForm($data['form_uuid']);
        
        return $data['form_uuid'];
    }
    
    // עדכון טופס
    public function updateForm($data) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $data['progress_percentage'] = $this->calculateProgress(array_merge($this->formData, $data));
        
        // בניית שאילתת UPDATE
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field)) {
                $fields[] = "$field = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $this->formId;
        $sql = "UPDATE deceased_forms SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    // שמירת חתימה
    public function saveSignature($signatureData) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        return $this->updateForm(['client_signature' => $signatureData]);
    }
    
    // חישוב אחוז התקדמות
    private function calculateProgress($data) {
        $requiredFields = $this->getRequiredFields();
        $filledFields = 0;
        $totalFields = count($requiredFields);
        
        foreach ($requiredFields as $field) {
            if (!empty($data[$field])) {
                $filledFields++;
            }
        }
        
        return $totalFields > 0 ? round(($filledFields / $totalFields) * 100) : 0;
    }
    
    // קבלת שדות חובה לפי הרשאה
    private function getRequiredFields() {
        $stmt = $this->db->prepare("
            SELECT field_name 
            FROM field_permissions 
            WHERE permission_level = ? AND is_required = 1
        ");
        $stmt->execute([$this->userPermissionLevel]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // בדיקת הרשאת צפייה בשדה
    public function canViewField($fieldName) {
        return hasPermission($fieldName, $this->userPermissionLevel, 'view');
    }
    
    // בדיקת הרשאת עריכת שדה
    public function canEditField($fieldName) {
        return hasPermission($fieldName, $this->userPermissionLevel, 'edit');
    }
    
    // קבלת נתוני הטופס עם סינון לפי הרשאות
    public function getFormData() {
        if (!$this->formData) {
            return null;
        }
        
        $filteredData = [];
        foreach ($this->formData as $field => $value) {
            if ($this->canViewField($field)) {
                $filteredData[$field] = $value;
            }
        }
        
        return $filteredData;
    }
    
    // ולידציה של הטופס
    public function validateForm($data) {
        $errors = [];
        
        // בדיקת שדות חובה
        $requiredFields = $this->getRequiredFields();
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = "שדה חובה";
            }
        }
        
        // ולידציות ספציפיות
        if (!empty($data['identification_type'])) {
            if (in_array($data['identification_type'], ['tz', 'passport'])) {
                if (empty($data['identification_number'])) {
                    $errors['identification_number'] = "מספר זיהוי הוא שדה חובה עבור סוג זיהוי זה";
                } elseif ($data['identification_type'] === 'tz' && !validateIsraeliId($data['identification_number'])) {
                    $errors['identification_number'] = "מספר תעודת זהות לא תקין";
                }
                
                if (empty($data['birth_date'])) {
                    $errors['birth_date'] = "תאריך לידה הוא שדה חובה עבור סוג זיהוי זה";
                }
            }
        }
        
        // בדיקת תאריכים
        if (!empty($data['death_date']) && !empty($data['burial_date'])) {
            if (strtotime($data['burial_date']) < strtotime($data['death_date'])) {
                $errors['burial_date'] = "תאריך הקבורה לא יכול להיות לפני תאריך הפטירה";
            }
        }
        
        return $errors;
    }
    
    // קבלת רשימת בתי עלמין
    public function getCemeteries() {
        if (!$this->canViewField('cemetery_id')) {
            return [];
        }
        
        $stmt = $this->db->query("SELECT id, name FROM cemeteries WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת גושים לפי בית עלמין
    public function getBlocks($cemeteryId) {
        if (!$this->canViewField('block_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM blocks WHERE cemetery_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$cemeteryId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת חלקות לפי גוש
    public function getSections($blockId) {
        if (!$this->canViewField('section_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM sections WHERE block_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$blockId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת שורות לפי חלקה
    public function getRows($sectionId) {
        if (!$this->canViewField('row_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM rows WHERE section_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת קברים לפי שורה
    public function getGraves($rowId) {
        if (!$this->canViewField('grave_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM graves WHERE row_id = ? AND is_available = 1 ORDER BY name");
        $stmt->execute([$rowId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת אחוזות קבר לפי בית עלמין
    public function getPlots($cemeteryId) {
        if (!$this->canViewField('plot_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM plots WHERE cemetery_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$cemeteryId]);
        return $stmt->fetchAll();
    }
    
    // העלאת מסמך
    public function uploadDocument($file, $documentType = null) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        // בדיקת סוג קובץ
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, ALLOWED_FILE_TYPES)) {
            throw new Exception("סוג קובץ לא מורשה");
        }
        
        // בדיקת גודל קובץ
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("הקובץ גדול מדי");
        }
        
        // יצירת שם קובץ ייחודי
        $fileName = uniqid() . '_' . time() . '.' . $fileExt;
        $uploadPath = UPLOAD_PATH . $this->formId . '/';
        
        // יצירת תיקייה אם לא קיימת
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        
        // העלאת הקובץ
        if (move_uploaded_file($file['tmp_name'], $uploadPath . $fileName)) {
            // שמירה בדטהבייס
            $stmt = $this->db->prepare("
                INSERT INTO deceased_documents 
                (form_id, document_type, file_name, file_path, file_size, mime_type, uploaded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->formId,
                $documentType,
                $file['name'],
                $uploadPath . $fileName,
                $file['size'],
                $file['type'],
                $_SESSION['user_id'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        }
        
        throw new Exception("שגיאה בהעלאת הקובץ");
    }
    
    // קבלת רשימת מסמכים
    public function getDocuments() {
        if (!$this->formId) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM deceased_documents 
            WHERE form_id = ? 
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$this->formId]);
        
        return $stmt->fetchAll();
    }
}

class DeceasedForm {
    private $db;
    private $formId;
    private $formData;
    private $userPermissionLevel;
    
    public function __construct($formId = null, $userPermissionLevel = 1) {
        $this->db = getDbConnection();
        $this->userPermissionLevel = $userPermissionLevel;
        
        if ($formId) {
            $this->loadForm($formId);
        }
    }
    
    // טעינת טופס קיים
    public function loadForm($formId) {
        $stmt = $this->db->prepare("SELECT * FROM deceased_forms WHERE form_uuid = ?");
        $stmt->execute([$formId]);
        $this->formData = $stmt->fetch();
        
        if ($this->formData) {
            $this->formId = $this->formData['id'];
            return true;
        }
        return false;
    }
    
    // יצירת טופס חדש
    public function createForm2($data) {
        // וודא שיש UUID תקין
        if (empty($data['form_uuid'])) {
            $data['form_uuid'] = generateUUID();
        }
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        
        // חישוב אחוז התקדמות
        $data['progress_percentage'] = $this->calculateProgress($data);
        
        // בניית שאילתת INSERT
        $fields = [];
        $values = [];
        $placeholders = [];
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field)) {
                $fields[] = $field;
                $values[] = $value;
                $placeholders[] = '?';
            }
        }
        
        $sql = "INSERT INTO deceased_forms (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        $this->formId = $this->db->lastInsertId();
        $this->loadForm($data['form_uuid']);
        
        return $data['form_uuid'];
    }
    public function createForm($data) {
        // וודא שיש UUID תקין
        if (empty($data['form_uuid'])) {
            $data['form_uuid'] = generateUUID();
        }
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        
        // נקה שדות ריקים שעלולים לגרום לבעיות Foreign Key
        $fieldsToClean = ['cemetery_id', 'block_id', 'section_id', 'row_id', 'grave_id', 'plot_id'];
        foreach ($fieldsToClean as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                unset($data[$field]);
            }
        }
        
        // חישוב אחוז התקדמות
        $data['progress_percentage'] = $this->calculateProgress($data);
        
        // בניית שאילתת INSERT
        $fields = [];
        $values = [];
        $placeholders = [];
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field)) {
                $fields[] = $field;
                $values[] = $value;
                $placeholders[] = '?';
            }
        }
        
        if (empty($fields)) {
            throw new Exception("אין שדות לשמירה");
        }
        
        $sql = "INSERT INTO deceased_forms (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            $this->formId = $this->db->lastInsertId();
            $this->loadForm($data['form_uuid']);
            
            return $data['form_uuid'];
        } catch (PDOException $e) {
            error_log("Error creating form: " . $e->getMessage());
            throw new Exception("שגיאה ביצירת הטופס: " . $e->getMessage());
        }
    }
    
    // עדכון טופס
    public function updateForm2($data) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $data['progress_percentage'] = $this->calculateProgress(array_merge($this->formData, $data));
        
        // בניית שאילתת UPDATE
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field)) {
                $fields[] = "$field = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $this->formId;
        $sql = "UPDATE deceased_forms SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }
    public function updateForm($data) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        
        // נקה שדות ריקים שעלולים לגרום לבעיות Foreign Key
        $fieldsToClean = ['cemetery_id', 'block_id', 'section_id', 'row_id', 'grave_id', 'plot_id'];
        foreach ($fieldsToClean as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null; // המר לNULL במקום מחרוזת ריקה
            }
        }
        
        $data['progress_percentage'] = $this->calculateProgress(array_merge($this->formData, $data));
        
        // בניית שאילתת UPDATE
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field)) {
                $fields[] = "$field = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $this->formId;
        $sql = "UPDATE deceased_forms SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Error updating form: " . $e->getMessage());
            throw new Exception("שגיאה בעדכון הטופס: " . $e->getMessage());
        }
    }
    
    // שמירת חתימה
    public function saveSignature($signatureData) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        return $this->updateForm(['client_signature' => $signatureData]);
    }
    
    // חישוב אחוז התקדמות
    private function calculateProgress($data) {
        $requiredFields = $this->getRequiredFields();
        $filledFields = 0;
        $totalFields = count($requiredFields);
        
        foreach ($requiredFields as $field) {
            if (!empty($data[$field])) {
                $filledFields++;
            }
        }
        
        return $totalFields > 0 ? round(($filledFields / $totalFields) * 100) : 0;
    }
    
    // קבלת שדות חובה לפי הרשאה
    private function getRequiredFields() {
        $stmt = $this->db->prepare("
            SELECT field_name 
            FROM field_permissions 
            WHERE permission_level = ? AND is_required = 1
        ");
        $stmt->execute([$this->userPermissionLevel]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // בדיקת הרשאת צפייה בשדה
    public function canViewField($fieldName) {
        return hasPermission($fieldName, $this->userPermissionLevel, 'view');
    }
    
    // בדיקת הרשאת עריכת שדה
    public function canEditField($fieldName) {
        return hasPermission($fieldName, $this->userPermissionLevel, 'edit');
    }
    
    // קבלת נתוני הטופס עם סינון לפי הרשאות
    public function getFormData() {
        if (!$this->formData) {
            return null;
        }
        
        $filteredData = [];
        foreach ($this->formData as $field => $value) {
            if ($this->canViewField($field)) {
                $filteredData[$field] = $value;
            }
        }
        
        return $filteredData;
    }
    
    // ולידציה של הטופס
    public function validateForm($data) {
        $errors = [];
        
        // בדיקת שדות חובה
        $requiredFields = $this->getRequiredFields();
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = "שדה חובה";
            }
        }
        
        // ולידציות ספציפיות
        if (!empty($data['identification_type'])) {
            if (in_array($data['identification_type'], ['tz', 'passport'])) {
                if (empty($data['identification_number'])) {
                    $errors['identification_number'] = "מספר זיהוי הוא שדה חובה עבור סוג זיהוי זה";
                } elseif ($data['identification_type'] === 'tz' && !validateIsraeliId($data['identification_number'])) {
                    $errors['identification_number'] = "מספר תעודת זהות לא תקין";
                }
                
                if (empty($data['birth_date'])) {
                    $errors['birth_date'] = "תאריך לידה הוא שדה חובה עבור סוג זיהוי זה";
                }
            }
        }
        
        // בדיקת תאריכים
        if (!empty($data['death_date']) && !empty($data['burial_date'])) {
            if (strtotime($data['burial_date']) < strtotime($data['death_date'])) {
                $errors['burial_date'] = "תאריך הקבורה לא יכול להיות לפני תאריך הפטירה";
            }
        }
        
        return $errors;
    }
    
    // קבלת רשימת בתי עלמין
    public function getCemeteries() {
        if (!$this->canViewField('cemetery_id')) {
            return [];
        }
        
        $stmt = $this->db->query("SELECT id, name FROM cemeteries WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת גושים לפי בית עלמין
    public function getBlocks($cemeteryId) {
        if (!$this->canViewField('block_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM blocks WHERE cemetery_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$cemeteryId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת חלקות לפי גוש
    public function getSections($blockId) {
        if (!$this->canViewField('section_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM sections WHERE block_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$blockId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת שורות לפי חלקה
    public function getRows($sectionId) {
        if (!$this->canViewField('row_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM rows WHERE section_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת קברים לפי שורה
    public function getGraves($rowId) {
        if (!$this->canViewField('grave_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM graves WHERE row_id = ? AND is_available = 1 ORDER BY name");
        $stmt->execute([$rowId]);
        return $stmt->fetchAll();
    }
    
    // קבלת רשימת אחוזות קבר לפי בית עלמין
    public function getPlots($cemeteryId) {
        if (!$this->canViewField('plot_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM plots WHERE cemetery_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$cemeteryId]);
        return $stmt->fetchAll();
    }
    
    // העלאת מסמך
    public function uploadDocument($file, $documentType = null) {
        if (!$this->formId) {
            throw new Exception("No form loaded");
        }
        
        // בדיקת סוג קובץ
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, ALLOWED_FILE_TYPES)) {
            throw new Exception("סוג קובץ לא מורשה");
        }
        
        // בדיקת גודל קובץ
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("הקובץ גדול מדי");
        }
        
        // יצירת שם קובץ ייחודי
        $fileName = uniqid() . '_' . time() . '.' . $fileExt;
        $uploadPath = UPLOAD_PATH . $this->formId . '/';
        
        // יצירת תיקייה אם לא קיימת
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        
        // העלאת הקובץ
        if (move_uploaded_file($file['tmp_name'], $uploadPath . $fileName)) {
            // שמירה בדטהבייס
            $stmt = $this->db->prepare("
                INSERT INTO deceased_documents 
                (form_id, document_type, file_name, file_path, file_size, mime_type, uploaded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->formId,
                $documentType,
                $file['name'],
                $uploadPath . $fileName,
                $file['size'],
                $file['type'],
                $_SESSION['user_id'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        }
        
        throw new Exception("שגיאה בהעלאת הקובץ");
    }
    
    // קבלת רשימת מסמכים
    public function getDocuments() {
        if (!$this->formId) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM deceased_documents 
            WHERE form_id = ? 
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$this->formId]);
        
        return $stmt->fetchAll();
    }
}

// החלף את פונקציית createForm ב-DeceasedForm.php


// החלף גם את פונקציית updateForm

