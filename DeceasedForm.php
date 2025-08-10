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
        
        // הסר שדות שלא שייכים לטבלה
        unset($data['csrf_token']);
        unset($data['save']);
        unset($data['save_and_view']);
        
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
        $fieldsToClean = ['cemetery_id', 'block_id', 'plot_id', 'row_id', 'areaGrave_id', 'grave_id'];
        foreach ($fieldsToClean as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                unset($data[$field]);
            }
        }
        
        // בניית שאילתת INSERT
        $fields = [];
        $values = [];
        $placeholders = [];
        
        // רשימת השדות התקינים בטבלה
        $validFields = [
            'form_uuid', 'status', 'progress_percentage', 'identification_type',
            'identification_number', 'deceased_first_name', 'deceased_last_name', 'father_name', 'mother_name',
            'birth_date', 'death_date', 'death_time', 'burial_date', 'burial_time',
            'burial_license', 'death_location', 'cemetery_id', 'block_id', 'plot_id',
            'row_id', 'areaGrave_id', 'grave_id', 'informant_name', 'informant_phone',
            'informant_relationship', 'notes', 'client_signature', 'created_by', 'updated_by'
        ];
        
        // סנן רק שדות תקינים
        foreach ($data as $field => $value) {
            if (in_array($field, $validFields)) {
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
        
        // הסר שדות שלא שייכים לטבלה
        unset($data['csrf_token']);
        unset($data['save']);
        unset($data['save_and_view']);
        unset($data['form_uuid']); // לא מעדכנים את ה-UUID
        
        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        
        // נקה שדות ריקים שעלולים לגרום לבעיות Foreign Key
        $fieldsToClean = ['cemetery_id', 'block_id', 'plot_id', 'row_id', 'areaGrave_id', 'grave_id'];
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
        
        // רשימת השדות התקינים בטבלה (ללא form_uuid)
        $validFields = [
            'status', 'progress_percentage', 'identification_type',
            'identification_number', 'deceased_first_name', 'deceased_last_name', 'father_name', 'mother_name',
            'birth_date', 'death_date', 'death_time', 'burial_date', 'burial_time',
            'burial_license', 'death_location', 'cemetery_id', 'block_id', 'plot_id',
            'row_id', 'areaGrave_id', 'grave_id', 'informant_name', 'informant_phone',
            'informant_relationship', 'notes', 'client_signature', 'updated_by'
        ];
        
        // בניית שאילתת UPDATE
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $validFields)) {
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
     * קבלת סטטיסטיקות על קישור
     *
     * @param string $linkUuid מזהה הקישור
     * @return array|false מחזיר מערך עם סטטיסטיקות או false אם הקישור לא נמצא
     */
    function getLinkStats($linkUuid) {
        $db = getDbConnection();
        
        // קבלת נתוני הקישור
        $linkStmt = $db->prepare("
            SELECT fl.*, 
                CONCAT(IFNULL(df.deceased_first_name, ''), ' ', IFNULL(df.deceased_last_name, '')) as deceased_name,
                u.full_name as created_by_name
            FROM form_links fl
            LEFT JOIN deceased_forms df ON fl.form_uuid = df.form_uuid
            LEFT JOIN users u ON fl.created_by = u.id
            WHERE fl.link_uuid = ?
        ");
        $linkStmt->execute([$linkUuid]);
        $linkData = $linkStmt->fetch();
        
        if (!$linkData) {
            return false;
        }
        
        // קבלת היסטוריית גישות
        $accessStmt = $db->prepare("
            SELECT fla.*, u.full_name as user_name
            FROM form_link_access_log fla
            LEFT JOIN users u ON fla.user_id = u.id
            WHERE fla.link_uuid = ?
            ORDER BY fla.accessed_at DESC
            LIMIT 100
        ");
        $accessStmt->execute([$linkUuid]);
        $accessHistory = $accessStmt->fetchAll();
        
        // סטטיסטיקות מצטברות
        $statsStmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(*) as total_views,
                MIN(accessed_at) as first_access,
                MAX(accessed_at) as last_access
            FROM form_link_access_log
            WHERE link_uuid = ?
        ");
        $statsStmt->execute([$linkUuid]);
        $stats = $statsStmt->fetch();
        
        return [
            'link' => $linkData,
            'stats' => $stats,
            'history' => $accessHistory
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
    
    private function getRequiredFields() {
        // שלב 1: קבל את כל השדות המוגדרים כחובה
        $stmt = $this->db->prepare("
            SELECT field_name, view_permission_levels 
            FROM field_permissions 
            WHERE form_type = 'deceased' 
            AND is_required = 1
        ");
        $stmt->execute();
        
        $requiredFields = [];
        
        // שלב 2: סנן רק את השדות שהמשתמש הנוכחי רשאי לראות
        while ($row = $stmt->fetch()) {
            $viewLevels = json_decode($row['view_permission_levels'], true);
            
            // בדוק אם רמת ההרשאה של המשתמש כלולה ב-JSON
            if (is_array($viewLevels) && in_array($this->userPermissionLevel, $viewLevels)) {
                $requiredFields[] = $row['field_name'];
            }
        }
        
        return $requiredFields;
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
    public function getPlots($blockId) {  // ✓ תוקן
        if (!$this->canViewField('plot_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM plots WHERE block_id = ? AND is_active = 1 ORDER BY name");  // ✓ תוקן
        $stmt->execute([$blockId]);  // ✓ תוקן
        return $stmt->fetchAll();
    }

    // קבלת רשימת שורות לפי חלקה
    public function getRows($plotId) {
        if (!$this->canViewField('row_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM rows WHERE plot_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$plotId]);
        return $stmt->fetchAll();
    }

    // קבלת רשימת אחוזות קבר לפי שורה
    public function getAreaGraves($rowId) {
        if (!$this->canViewField('areaGrave_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM areaGraves WHERE row_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$rowId]);
        return $stmt->fetchAll();
    }

    // קבלת רשימת קברים לפי אחוזת קבר
    public function getGraves($areaGraveId) {  // ✓ תוקן
        if (!$this->canViewField('grave_id')) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM graves WHERE areaGrave_id = ? AND is_available = 1 ORDER BY name");  // ✓ תוקן
        $stmt->execute([$areaGraveId]);  // ✓ תוקן
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

