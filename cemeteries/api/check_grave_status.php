<?php
// cemeteries/api/check_grave_status.php
// אל תשלח שום פלט לפני טעינת הקונפיג
ob_start();

// נסה לטעון את הקונפיג
$configPath = __DIR__ . '/../../config.php';
if (!file_exists($configPath)) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'קובץ הגדרות לא נמצא']));
}

require_once $configPath;
ob_end_clean();

// כעת אפשר לשלוח headers
header('Content-Type: application/json; charset=utf-8');

// בדיקת הרשאות בסיסית
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'לא מחובר למערכת']));
}

// קבל חיבור למסד נתונים
try {
    if (function_exists('getDbConnection')) {
        $pdo = getDbConnection();
    } elseif (!isset($pdo) && defined('DB_HOST') && defined('DB_NAME')) {
        // נסה להתחבר ישירות
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    
    if (!isset($pdo)) {
        throw new Exception('אין חיבור למסד נתונים');
    }
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'שגיאה בחיבור למסד נתונים']));
}

$action = $_GET['action'] ?? '';
$graveId = $_GET['grave_id'] ?? 0;

if (!$graveId) {
    die(json_encode(['success' => false, 'error' => 'Missing grave ID']));
}

try {
    switch ($action) {
        case 'check_purchase':
            // בדוק אם יש רכישה לקבר זה
            // בדוק קודם אם הטבלה קיימת
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'purchase_forms'");
            if ($tableCheck->rowCount() == 0) {
                // אם אין טבלת רכישות, החזר שאין רכישה
                echo json_encode([
                    'success' => true,
                    'data' => null
                ]);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    form_uuid,
                    buyer_name,
                    buyer_phone,
                    purchase_date,
                    status,
                    total_amount,
                    payment_method
                FROM purchase_forms
                WHERE grave_id = ?
                AND (status != 'cancelled' OR status IS NULL)
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$graveId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data ?: null
            ]);
            break;
            
        case 'check_burial':
            // בדוק אם יש קבורה בקבר זה
            // בדוק קודם אם הטבלה קיימת
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'deceased_forms'");
            if ($tableCheck->rowCount() == 0) {
                // אם אין טבלת נפטרים, החזר שאין קבורה
                echo json_encode([
                    'success' => true,
                    'data' => null
                ]);
                break;
            }
            
            // בדוק אילו עמודות קיימות בטבלה
            $columns = $pdo->query("SHOW COLUMNS FROM deceased_forms")->fetchAll(PDO::FETCH_COLUMN);
            
            // בנה את השם בהתאם לעמודות הקיימות
            $nameField = 'NULL as deceased_name';
            if (in_array('deceased_name', $columns)) {
                $nameField = 'deceased_name';
            } elseif (in_array('deceased_first_name', $columns) && in_array('deceased_last_name', $columns)) {
                $nameField = "CONCAT(IFNULL(deceased_first_name, ''), ' ', IFNULL(deceased_last_name, '')) as deceased_name";
            } elseif (in_array('deceased_first_name', $columns)) {
                $nameField = 'deceased_first_name as deceased_name';
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    form_uuid,
                    $nameField,
                    death_date,
                    burial_date,
                    burial_license,
                    status
                FROM deceased_forms
                WHERE grave_id = ?
                AND status = 'completed'
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$graveId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data ?: null
            ]);
            break;
            
        case 'get_history':
            // קבל היסטוריית פעולות על הקבר
            $history = [];
            
            // בדוק אם יש טבלת רכישות
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'purchase_forms'");
            if ($tableCheck->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    SELECT 
                        'רכישה' as action,
                        pf.created_at,
                        u.full_name as user_name,
                        CONCAT('רכישה על ידי ', pf.buyer_name) as notes
                    FROM purchase_forms pf
                    LEFT JOIN users u ON pf.created_by = u.id
                    WHERE pf.grave_id = ?
                ");
                $stmt->execute([$graveId]);
                $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $history = array_merge($history, $purchases);
            }
            
            // בדוק אם יש טבלת נפטרים
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'deceased_forms'");
            if ($tableCheck->rowCount() > 0) {
                // בדוק אילו עמודות קיימות
                $columns = $pdo->query("SHOW COLUMNS FROM deceased_forms")->fetchAll(PDO::FETCH_COLUMN);
                
                $nameField = "'נפטר'";
                if (in_array('deceased_name', $columns)) {
                    $nameField = 'df.deceased_name';
                } elseif (in_array('deceased_first_name', $columns) && in_array('deceased_last_name', $columns)) {
                    $nameField = "CONCAT(IFNULL(df.deceased_first_name, ''), ' ', IFNULL(df.deceased_last_name, ''))";
                } elseif (in_array('deceased_first_name', $columns)) {
                    $nameField = 'df.deceased_first_name';
                }
                
                $stmt = $pdo->prepare("
                    SELECT 
                        'קבורה' as action,
                        df.created_at,
                        u.full_name as user_name,
                        CONCAT('קבורת ', $nameField) as notes
                    FROM deceased_forms df
                    LEFT JOIN users u ON df.created_by = u.id
                    WHERE df.grave_id = ?
                ");
                $stmt->execute([$graveId]);
                $burials = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $history = array_merge($history, $burials);
            }
            
            // מיין לפי תאריך
            usort($history, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            // הגבל ל-10 אחרונים
            $history = array_slice($history, 0, 10);
            
            echo json_encode([
                'success' => true,
                'data' => $history
            ]);
            break;
            
        case 'get_full_status':
            // קבל סטטוס מלא של הקבר
            $stmt = $pdo->prepare("
                SELECT 
                    g.*,
                    ag.name as areaGrave_name,
                    r.name as row_name,
                    p.name as plot_name,
                    b.name as block_name,
                    c.name as cemetery_name
                FROM graves g
                LEFT JOIN areaGraves ag ON g.areaGrave_id = ag.id
                LEFT JOIN rows r ON ag.row_id = r.id
                LEFT JOIN plots p ON r.plot_id = p.id
                LEFT JOIN blocks b ON p.block_id = b.id
                LEFT JOIN cemeteries c ON b.cemetery_id = c.id
                WHERE g.id = ?
            ");
            $stmt->execute([$graveId]);
            $graveData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$graveData) {
                throw new Exception('Grave not found');
            }
            
            // בדוק רכישה
            $hasPurchase = false;
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'purchase_forms'");
            if ($tableCheck->rowCount() > 0) {
                $purchaseStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM purchase_forms 
                    WHERE grave_id = ? AND (status != 'cancelled' OR status IS NULL)
                ");
                $purchaseStmt->execute([$graveId]);
                $hasPurchase = $purchaseStmt->fetchColumn() > 0;
            }
            
            // בדוק קבורה
            $hasBurial = false;
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'deceased_forms'");
            if ($tableCheck->rowCount() > 0) {
                $burialStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM deceased_forms 
                    WHERE grave_id = ? AND status = 'completed'
                ");
                $burialStmt->execute([$graveId]);
                $hasBurial = $burialStmt->fetchColumn() > 0;
            }
            
            // קבע סטטוס
            $status = 'available';
            if ($hasBurial) {
                $status = 'occupied';
            } elseif ($hasPurchase) {
                $status = 'reserved';
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'grave' => $graveData,
                    'status' => $status,
                    'has_purchase' => $hasPurchase,
                    'has_burial' => $hasBurial
                ]
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>