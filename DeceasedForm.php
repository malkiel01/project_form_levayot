<?php
// DeceasedForm.php - מחלקת ניהול טופס נפטרים

require_once 'config.php';

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
    public function createForm($data) {
        // וודא שיש UUID תקין
        if (empty($data['form_uuid'])) {
            throw new Exception("UUID חסר");
        }
        
        // וודא שה-UUID לא ריק
        if (trim($data['form_uuid']) === '') {
            throw new Exception("UUID ריק");
        }
        
        // בדוק שה-UUID לא קיים כבר
        $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM deceased_forms WHERE form_uuid = ?");
        $checkStmt->execute([$data['form_uuid']]);
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("UUID כבר קיים במערכת");
        }
        
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        
        // תמיד התחל כטיוטה
        $data['status'] = 'draft';
        
        // חישוב אחוז התקדמות
        $data['progress_percentage'] = $this->calculateProgress($data);
        
        // בדוק אם כל שדות החובה מלאים
        if ($this->areAllRequiredFieldsFilled($data)) {
            $data['status'] = 'completed';
        }
        
        // נקה שדות ריקים שעלולים לגרום לבעיות Foreign Key
        $fieldsToClean = ['cemetery_id', 'block_id', 'section_id', 'row_id', 'grave_id', 'plot_id'];
        foreach ($fieldsToClean as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                unset($data[$field]);
            }
        }
        
        // בניית שאילתת INSERT
        $fields = [];
        $values = [];
        $placeholders = [];
        
        // וודא ש-form_uuid נכלל
        if (!in_array('form_uuid', array_keys($data))) {
            throw new Exception("form_uuid חסר בנתונים");
        }
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field) || $field === 'form_uuid' || $field === 'created_by' || $field === 'status' || $field === 'progress_percentage') {
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
            error_log("Creating new form - UUID: " . $data['form_uuid']);
            error_log("SQL: " . $sql);
            error_log("Values: " . print_r($values, true));
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            $this->formId = $this->db->lastInsertId();
            
            // רישום בלוג
            $logStmt = $this->db->prepare("
                INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
                VALUES (?, ?, 'create_form', ?, ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'] ?? null,
                $this->formId,
                json_encode(['form_uuid' => $data['form_uuid'], 'status' => $data['status']]),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            error_log("Form created successfully - UUID: " . $data['form_uuid'] . ", ID: " . $this->formId . ", Status: " . $data['status']);
            
            return $data['form_uuid'];
        } catch (PDOException $e) {
            error_log("Error creating form: " . $e->getMessage());
            throw new Exception("שגיאה ביצירת הטופס: " . $e->getMessage());
        }
    }

    // עדכון טופס
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
        
        // מיזוג עם הנתונים הקיימים לצורך חישוב אחוז התקדמות
        $mergedData = array_merge($this->formData, $data);
        $data['progress_percentage'] = $this->calculateProgress($mergedData);
        
        // בדוק אם כל שדות החובה מלאים
        if ($this->areAllRequiredFieldsFilled($mergedData)) {
            $data['status'] = 'completed';
        } else {
            $data['status'] = 'draft';
        }
        
        // בניית שאילתת UPDATE
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            if ($this->canEditField($field) || in_array($field, ['updated_by', 'progress_percentage', 'status'])) {
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
            error_log("Updating form - ID: " . $this->formId . ", New Status: " . $data['status']);
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);
            
            // רישום בלוג
            $logStmt = $this->db->prepare("
                INSERT INTO activity_log (user_id, form_id, action, details, ip_address, user_agent) 
                VALUES (?, ?, 'update_form', ?, ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'] ?? null,
                $this->formId,
                json_encode(['updated_fields' => array_keys($data), 'status' => $data['status']]),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error updating form: " . $e->getMessage());
            throw new Exception("שגיאה בעדכון הטופס: " . $e->getMessage());
        }
    }

    /**
     * יצירת קישור חדש לטופס
     * 
     * @param string $formUuid יוניק של הטופס
     * @param int $permissionLevel רמת הרשאה למשתמשים לא רשומים
     * @param array|null $allowedUserIds מזהי משתמשים מורשים (או NULL לכולם)
     * @param bool $canEdit האם ניתן לערוך (true) או רק לצפות (false)
     * @param string|null $expiresAt תאריך תפוגה (פורמט Y-m-d H:i:s) או NULL ללא תפוגה
     * @param int|null $createdBy מזהה המשתמש שיצר את הקישור
     * @return string ה-UUID של הקישור שנוצר
     */
    function createFormLink($formUuid, $permissionLevel = 4, $allowedUserIds = null, $canEdit = false, $expiresAt = null, $createdBy = null) {
        $db = getDbConnection();

        // יצירת link_uuid ייחודי
        $linkUuid = generateUUID();

        // הכנת השאילתה
        $stmt = $db->prepare("
            INSERT INTO form_links 
                (link_uuid, form_uuid, permission_level, allowed_user_ids, can_edit, expires_at, created_by) 
            VALUES 
                (?, ?, ?, ?, ?, ?, ?)
        ");

        // הפיכת מערך מזהי משתמשים ל-JSON אם קיים
        $allowedUserIdsJson = $allowedUserIds ? json_encode($allowedUserIds) : null;

        // ביצוע השאילתה
        $stmt->execute([
            $linkUuid, 
            $formUuid, 
            $permissionLevel, 
            $allowedUserIdsJson, 
            $canEdit, 
            $expiresAt, 
            $createdBy
        ]);

        return $linkUuid;
    }

    /**
     * בדיקת קישור לטופס
     *
     * @param string $linkUuid
     * @return array|false מחזיר מערך עם נתוני הקישור וההרשאות, או false אם הקישור לא תקף
     */
    function checkFormLink($linkUuid) {
        $db = getDbConnection();

        // טעינת הקישור מטבלת form_links
        $stmt = $db->prepare("SELECT * FROM form_links WHERE link_uuid = ?");
        $stmt->execute([$linkUuid]);
        $linkData = $stmt->fetch();

        if (!$linkData) {
            return false; // קישור לא קיים
        }

        // בדיקת תאריך תפוגה
        if ($linkData['expires_at'] && strtotime($linkData['expires_at']) < time()) {
            return false; // הקישור פג תוקף
        }

        // אם יש הגבלה על משתמשים ספציפיים
        if ($linkData['allowed_user_ids']) {
            $allowedUsers = json_decode($linkData['allowed_user_ids'], true);

            if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], $allowedUsers)) {
                return false; // המשתמש לא מורשה
            }
        }

        return [
            'form_uuid' => $linkData['form_uuid'],
            'permission_level' => isset($_SESSION['permission_level']) ? $_SESSION['permission_level'] : $linkData['permission_level'],
            'can_edit' => $linkData['can_edit']
        ];
    }

    // פונקציה חדשה לבדיקה האם כל שדות החובה מלאים
    private function areAllRequiredFieldsFilled($data) {
        $requiredFields = $this->getRequiredFields();
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                // טיפול מיוחד בשדות מותנים
                if (in_array($field, ['identification_number', 'birth_date'])) {
                    // בדוק אם סוג הזיהוי דורש את השדה
                    $idType = $data['identification_type'] ?? '';
                    if ($idType === 'tz' || $idType === 'passport') {
                        return false;
                    }
                    // אם סוג הזיהוי לא דורש את השדה, המשך
                    continue;
                }
                return false;
            }
        }
        
        return true;
    }

    // עדכון הפונקציה validateForm - הסרת החובה לשדות נדרשים
    public function validateForm($data) {
        $errors = [];
        
        // לא לבדוק שדות חובה כאן - רק בדיקות פורמט
        
        // ולידציות ספציפיות
        if (!empty($data['identification_type'])) {
            if (in_array($data['identification_type'], ['tz', 'passport'])) {
                // בדיקת תקינות תעודת זהות אם קיימת
                if (!empty($data['identification_number']) && $data['identification_type'] === 'tz' && !validateIsraeliId($data['identification_number'])) {
                    $errors['identification_number'] = "מספר תעודת זהות לא תקין";
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
        if (!$this->formData && $this->formId) {
            $this->loadForm($this->formData['form_uuid'] ?? null);
        }

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

