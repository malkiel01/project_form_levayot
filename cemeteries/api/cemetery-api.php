<?php
// api/cemetery-api.php
// אל תשלח שום פלט לפני טעינת הקונפיג
ob_start();

// נסה לטעון את הקונפיג
$configPath = __DIR__ . '/../../config.php';
if (!file_exists($configPath)) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => 'קובץ הגדרות לא נמצא']));
}

require_once $configPath;
ob_end_clean();

// כעת אפשר לשלוח headers
header('Content-Type: application/json; charset=utf-8');

// בדיקת הרשאות בסיסית
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'לא מחובר למערכת']));
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
    die(json_encode(['error' => 'שגיאה בחיבור למסד נתונים']));
}

// בדיקה האם המשתמש הוא מנהל או יש לו הרשאה ספציפית
$hasAccess = false;
if ($_SESSION['permission_level'] >= 4) {
    $hasAccess = true;
} else {
    // בדוק הרשאה ספציפית למודול
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_permissions 
            WHERE user_id = ? 
            AND module_name = 'cemeteries' 
            AND can_access = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $hasAccess = $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        // אם אין טבלת הרשאות, תן גישה רק למנהלים
        $hasAccess = false;
    }
}

if (!$hasAccess) {
    http_response_code(403);
    die(json_encode(['error' => 'אין הרשאה לגשת למודול זה']));
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'getStats':
            echo json_encode(getStats());
            break;
            
        case 'getCemeteries':
            echo json_encode(getCemeteries());
            break;
        case 'getBlocks':
            echo json_encode(getBlocks());
            break;
            
        case 'getPlots':
            echo json_encode(getPlots());
            break;
            
        case 'getRows':
            echo json_encode(getRows());
            break;
            
        case 'getAreaGraves':
            echo json_encode(getAreaGraves());
            break;
            
        case 'getGraves':
                echo json_encode(getGraves());
                break;   
        case 'getCemeteryDetails':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'message' => 'מזהה בית עלמין חסר']);
                exit;
            }
            
            $cemeteryId = intval($_GET['id']);
            
            try {
                // Get cemetery details
                $stmt = $pdo->prepare("SELECT * FROM cemeteries WHERE id = ?");
                $stmt->execute([$cemeteryId]);
                $cemetery = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$cemetery) {
                    echo json_encode(['success' => false, 'message' => 'בית עלמין לא נמצא']);
                    exit;
                }
                
                // Get blocks with stats - בדוק אם יש טבלת status בכלל
                $stmt = $pdo->prepare("
                    SELECT b.*, 
                        COUNT(DISTINCT p.id) as plots_count,
                        COUNT(DISTINCT g.id) as total_graves,
                        SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_graves,
                        SUM(CASE WHEN g.is_available = 0 THEN 1 ELSE 0 END) as occupied_graves
                    FROM blocks b
                    LEFT JOIN plots p ON b.id = p.block_id
                    LEFT JOIN rows r ON p.id = r.plot_id
                    LEFT JOIN areaGraves ag ON r.id = ag.row_id
                    LEFT JOIN graves g ON ag.id = g.areaGrave_id
                    WHERE b.cemetery_id = ?
                    GROUP BY b.id
                    ORDER BY b.name
                ");
                $stmt->execute([$cemeteryId]);
                $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get plots with stats
                $stmt = $pdo->prepare("
                    SELECT p.*, 
                        b.name as block_name,
                        COUNT(DISTINCT r.id) as rows_count,
                        COUNT(DISTINCT g.id) as total_graves,
                        SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_graves
                    FROM plots p
                    LEFT JOIN blocks b ON p.block_id = b.id
                    LEFT JOIN rows r ON p.id = r.plot_id
                    LEFT JOIN areaGraves ag ON r.id = ag.row_id
                    LEFT JOIN graves g ON ag.id = g.areaGrave_id
                    WHERE b.cemetery_id = ?
                    GROUP BY p.id
                    ORDER BY b.name, p.name
                ");
                $stmt->execute([$cemeteryId]);
                $plots = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get total stats for cemetery - פשוט יותר
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT ag.id) as total_area_graves,
                        COUNT(DISTINCT g.id) as total_graves,
                        SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_graves
                    FROM blocks b
                    LEFT JOIN plots p ON b.id = p.block_id
                    LEFT JOIN rows r ON p.id = r.plot_id
                    LEFT JOIN areaGraves ag ON r.id = ag.row_id
                    LEFT JOIN graves g ON ag.id = g.areaGrave_id
                    WHERE b.cemetery_id = ?
                ");
                $stmt->execute([$cemeteryId]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // אם אין נתונים, תן ערכי ברירת מחדל
                if (!$stats) {
                    $stats = [
                        'total_area_graves' => 0,
                        'total_graves' => 0,
                        'available_graves' => 0,
                        'purchased_graves' => 0,
                        'buried_graves' => 0,
                        'reserved_graves' => 0
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'cemetery' => $cemetery,
                    'blocks' => $blocks ?: [],
                    'plots' => $plots ?: [],
                    'stats' => $stats
                ]);
                
            } catch (PDOException $e) {
                // לוג השגיאה לצורך דיבוג
                error_log('Cemetery Details Error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'שגיאה בקבלת פרטי בית עלמין: ' . $e->getMessage()]);
            }
            break;
        case 'getBlockDetails':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'message' => 'מזהה גוש חסר']);
                exit;
            }
            
            $blockId = intval($_GET['id']);
            
            try {
                // Get block with cemetery info
                $stmt = $pdo->prepare("
                    SELECT b.*, c.name as cemetery_name 
                    FROM blocks b
                    LEFT JOIN cemeteries c ON b.cemetery_id = c.id
                    WHERE b.id = ?
                ");
                $stmt->execute([$blockId]);
                $block = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$block) {
                    echo json_encode(['success' => false, 'message' => 'גוש לא נמצא']);
                    exit;
                }
                
                // Get plots in this block
                $stmt = $pdo->prepare("
                    SELECT p.*,
                        COUNT(DISTINCT r.id) as rows_count,
                        COUNT(DISTINCT g.id) as total_graves,
                        SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_graves
                    FROM plots p
                    LEFT JOIN rows r ON p.id = r.plot_id
                    LEFT JOIN areaGraves ag ON r.id = ag.row_id
                    LEFT JOIN graves g ON ag.id = g.areaGrave_id
                    WHERE p.block_id = ?
                    GROUP BY p.id
                    ORDER BY p.name
                ");
                $stmt->execute([$blockId]);
                $plots = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get stats
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT ag.id) as total_area_graves,
                        COUNT(DISTINCT g.id) as total_graves,
                        SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_graves
                    FROM plots p
                    LEFT JOIN rows r ON p.id = r.plot_id
                    LEFT JOIN areaGraves ag ON r.id = ag.row_id
                    LEFT JOIN graves g ON ag.id = g.areaGrave_id
                    WHERE p.block_id = ?
                ");
                $stmt->execute([$blockId]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // אם אין נתונים, תן ערכי ברירת מחדל
                if (!$stats) {
                    $stats = [
                        'total_area_graves' => 0,
                        'total_graves' => 0,
                        'available_graves' => 0
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'block' => $block,
                    'plots' => $plots ?: [],
                    'stats' => $stats
                ]);
                
            } catch (PDOException $e) {
                error_log('Block Details Error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'שגיאה בקבלת פרטי גוש: ' . $e->getMessage()]);
            }
            break;
        case 'getBlockDetails2':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'message' => 'מזהה גוש חסר']);
                exit;
            }
            
            $blockId = intval($_GET['id']);
            
            try {
                // Get block with cemetery info
                $stmt = $pdo->prepare("
                    SELECT b.*, c.name as cemetery_name 
                    FROM blocks b
                    LEFT JOIN cemeteries c ON b.cemetery_id = c.id
                    WHERE b.id = ?
                ");
                $stmt->execute([$blockId]);
                $block = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$block) {
                    echo json_encode(['success' => false, 'message' => 'גוש לא נמצא']);
                    exit;
                }
                
                // Get plots in this block
                $stmt = $pdo->prepare("
                    SELECT p.*,
                        COUNT(DISTINCT r.id) as rows_count,
                        COUNT(DISTINCT g.id) as total_graves,
                        SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_graves
                    FROM plots p
                    LEFT JOIN rows r ON p.id = r.plot_id
                    LEFT JOIN areaGraves ag ON r.id = ag.row_id
                    LEFT JOIN graves g ON ag.id = g.areaGrave_id
                    WHERE p.block_id = ?
                    GROUP BY p.id
                    ORDER BY p.name
                ");
                $stmt->execute([$blockId]);
                $plots = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get stats
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT ag.id) as total_area_graves,
                        COUNT(DISTINCT g.id) as total_graves,
                        SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_graves,
                        SUM(CASE WHEN g.status = 'purchased' THEN 1 ELSE 0 END) as purchased_graves,
                        SUM(CASE WHEN g.status = 'buried' THEN 1 ELSE 0 END) as buried_graves,
                        SUM(CASE WHEN g.status = 'reserved' THEN 1 ELSE 0 END) as reserved_graves
                    FROM plots p
                    LEFT JOIN rows r ON p.id = r.plot_id
                    LEFT JOIN areaGraves ag ON r.id = ag.row_id
                    LEFT JOIN graves g ON ag.id = g.areaGrave_id
                    WHERE p.block_id = ?
                ");
                $stmt->execute([$blockId]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'block' => $block,
                    'plots' => $plots,
                    'stats' => $stats
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'שגיאה בקבלת פרטי גוש']);
            }
            break;

        case 'getPlotDetails':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'message' => 'מזהה חלקה חסר']);
                exit;
            }
            
            $plotId = intval($_GET['id']);
            
            try {
                // Get plot with block and cemetery info
                $stmt = $pdo->prepare("
                    SELECT p.*, 
                        b.name as block_name,
                        c.name as cemetery_name
                    FROM plots p
                    LEFT JOIN blocks b ON p.block_id = b.id
                    LEFT JOIN cemeteries c ON b.cemetery_id = c.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$plotId]);
                $plot = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$plot) {
                    echo json_encode(['success' => false, 'message' => 'חלקה לא נמצאה']);
                    exit;
                }
                
                // Get rows with area graves
                $stmt = $pdo->prepare("
                    SELECT r.*,
                        COUNT(DISTINCT ag.id) as area_graves_count,
                        COUNT(DISTINCT g.id) as total_graves,
                        SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_graves
                    FROM rows r
                    LEFT JOIN areaGraves ag ON r.id = ag.row_id
                    LEFT JOIN graves g ON ag.id = g.areaGrave_id
                    WHERE r.plot_id = ?
                    GROUP BY r.id
                    ORDER BY r.name
                ");
                $stmt->execute([$plotId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get area graves in this plot
                $stmt = $pdo->prepare("
                    SELECT ag.*,
                        r.name as row_name,
                        COUNT(g.id) as graves_count,
                        SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_count
                    FROM areaGraves ag
                    LEFT JOIN rows r ON ag.row_id = r.id
                    LEFT JOIN graves g ON ag.id = g.areaGrave_id
                    WHERE r.plot_id = ?
                    GROUP BY ag.id
                    ORDER BY r.name, ag.name
                ");
                $stmt->execute([$plotId]);
                $areaGraves = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'plot' => $plot,
                    'rows' => $rows,
                    'areaGraves' => $areaGraves
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'שגיאה בקבלת פרטי חלקה']);
            }
            break;

        case 'getAreaGraveDetails':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'message' => 'מזהה אחוזת קבר חסר']);
                exit;
            }
            
            $areaGraveId = intval($_GET['id']);
            
            try {
                // Get area grave with full hierarchy
                $stmt = $pdo->prepare("
                    SELECT ag.*,
                        r.name as row_name,
                        p.name as plot_name,
                        b.name as block_name,
                        c.name as cemetery_name
                    FROM areaGraves ag
                    LEFT JOIN rows r ON ag.row_id = r.id
                    LEFT JOIN plots p ON r.plot_id = p.id
                    LEFT JOIN blocks b ON p.block_id = b.id
                    LEFT JOIN cemeteries c ON b.cemetery_id = c.id
                    WHERE ag.id = ?
                ");
                $stmt->execute([$areaGraveId]);
                $areaGrave = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$areaGrave) {
                    echo json_encode(['success' => false, 'message' => 'אחוזת קבר לא נמצאה']);
                    exit;
                }
                
                // Get graves in this area
                $stmt = $pdo->prepare("
                    SELECT * FROM graves 
                    WHERE areaGrave_id = ?
                    ORDER BY grave_number, name
                ");
                $stmt->execute([$areaGraveId]);
                $graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'areaGrave' => $areaGrave,
                    'graves' => $graves
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'שגיאה בקבלת פרטי אחוזת קבר']);
            }
            break;

        case 'getGraveDetails':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'message' => 'מזהה קבר חסר']);
                exit;
            }
            
            $graveId = intval($_GET['id']);
            
            try {
                // Get grave with full hierarchy
                $stmt = $pdo->prepare("
                    SELECT g.*,
                        ag.name as area_grave_name,
                        r.name as row_name,
                        p.name as plot_name,
                        b.name as block_name,
                        c.name as cemetery_name,
                        df.deceased_name,
                        df.death_date,
                        df.burial_date
                    FROM graves g
                    LEFT JOIN areaGraves ag ON g.areaGrave_id = ag.id
                    LEFT JOIN rows r ON ag.row_id = r.id
                    LEFT JOIN plots p ON r.plot_id = p.id
                    LEFT JOIN blocks b ON p.block_id = b.id
                    LEFT JOIN cemeteries c ON b.cemetery_id = c.id
                    LEFT JOIN deceased_forms df ON g.id = df.grave_id AND df.status = 'completed'
                    WHERE g.id = ?
                ");
                $stmt->execute([$graveId]);
                $grave = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$grave) {
                    echo json_encode(['success' => false, 'message' => 'קבר לא נמצא']);
                    exit;
                }
                
                echo json_encode([
                    'success' => true,
                    'grave' => $grave
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'שגיאה בקבלת פרטי קבר']);
            }
            break;
        case 'getItem':
            $type = $_GET['type'] ?? '';
            $id = $_GET['id'] ?? 0;
            echo json_encode(getItem($type, $id));
            break;
            
        case 'create':
            echo json_encode(createItem());
            break;
            
        case 'update':
            echo json_encode(updateItem());
            break;
            
        case 'delete':
            echo json_encode(deleteItem());
            break;
            
        case 'getBlocksByCemetery':
            $cemetery_id = $_GET['cemetery_id'] ?? 0;
            echo json_encode(getBlocksByCemetery($cemetery_id));
            break;
            
        case 'getPlotsByBlock':
            $block_id = $_GET['block_id'] ?? 0;
            echo json_encode(getPlotsByBlock($block_id));
            break;
            
        case 'getRowsByPlot':
            $plot_id = $_GET['plot_id'] ?? 0;
            echo json_encode(getRowsByPlot($plot_id));
            break;
            
        case 'getAreaGravesByRow':
            $row_id = $_GET['row_id'] ?? 0;
            echo json_encode(getAreaGravesByRow($row_id));
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

// Functions
function getStats() {
    global $pdo;
    
    $stats = [];
    $tables = ['cemeteries', 'blocks', 'plots', 'rows', 'areaGraves', 'graves'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $stats[$table] = $stmt->fetchColumn();
    }
    
    // קברים פנויים
    $stmt = $pdo->query("SELECT COUNT(*) FROM graves WHERE is_available = 1");
    $stats['available_graves'] = $stmt->fetchColumn();
    
    return $stats;
}

function getCemeteries() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT c.*, 
               COUNT(DISTINCT b.id) as blocks_count
        FROM cemeteries c
        LEFT JOIN blocks b ON b.cemetery_id = c.id
        GROUP BY c.id
        ORDER BY c.name
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBlocks() {
    global $pdo;
    
    // Get blocks with cemetery info
    $stmt = $pdo->query("
        SELECT b.*, 
               c.name as cemetery_name,
               COUNT(DISTINCT p.id) as plots_count
        FROM blocks b
        LEFT JOIN cemeteries c ON c.id = b.cemetery_id
        LEFT JOIN plots p ON p.block_id = b.id
        GROUP BY b.id
        ORDER BY c.name, b.name
    ");
    $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get cemeteries for filter
    $stmt = $pdo->query("SELECT id, name FROM cemeteries WHERE is_active = 1 ORDER BY name");
    $cemeteries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'blocks' => $blocks,
        'cemeteries' => $cemeteries
    ];
}

function getPlots() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT p.*, 
               b.name as block_name,
               c.name as cemetery_name,
               c.id as cemetery_id,
               COUNT(DISTINCT r.id) as rows_count
        FROM plots p
        LEFT JOIN blocks b ON b.id = p.block_id
        LEFT JOIN cemeteries c ON c.id = b.cemetery_id
        LEFT JOIN rows r ON r.plot_id = p.id
        GROUP BY p.id
        ORDER BY c.name, b.name, p.name
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRows() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT r.*, 
               p.name as plot_name,
               b.name as block_name,
               c.name as cemetery_name,
               COUNT(DISTINCT ag.id) as area_graves_count
        FROM rows r
        LEFT JOIN plots p ON p.id = r.plot_id
        LEFT JOIN blocks b ON b.id = p.block_id
        LEFT JOIN cemeteries c ON c.id = b.cemetery_id
        LEFT JOIN areaGraves ag ON ag.row_id = r.id
        GROUP BY r.id
        ORDER BY c.name, b.name, p.name, r.name
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAreaGraves() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT ag.*, 
               r.name as row_name,
               p.name as plot_name,
               b.name as block_name,
               c.name as cemetery_name,
               COUNT(DISTINCT g.id) as graves_count,
               SUM(CASE WHEN g.is_available = 1 THEN 1 ELSE 0 END) as available_count
        FROM areaGraves ag
        LEFT JOIN rows r ON r.id = ag.row_id
        LEFT JOIN plots p ON p.id = r.plot_id
        LEFT JOIN blocks b ON b.id = p.block_id
        LEFT JOIN cemeteries c ON c.id = b.cemetery_id
        LEFT JOIN graves g ON g.areaGrave_id = ag.id
        GROUP BY ag.id
        ORDER BY c.name, b.name, p.name, r.name, ag.name
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGraves() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT g.*, 
               ag.name as area_grave_name,
               r.name as row_name,
               p.name as plot_name,
               b.name as block_name,
               c.name as cemetery_name
        FROM graves g
        LEFT JOIN areaGraves ag ON ag.id = g.areaGrave_id
        LEFT JOIN rows r ON r.id = ag.row_id
        LEFT JOIN plots p ON p.id = r.plot_id
        LEFT JOIN blocks b ON b.id = p.block_id
        LEFT JOIN cemeteries c ON c.id = b.cemetery_id
        ORDER BY c.name, b.name, p.name, r.name, ag.name, g.grave_number
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getItem($type, $id) {
    global $pdo;
    
    $table = getTableName($type);
    if (!$table) {
        throw new Exception('Invalid type');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createItem() {
    global $pdo;
    
    $type = $_POST['type'] ?? '';
    $table = getTableName($type);
    
    if (!$table) {
        return ['success' => false, 'message' => 'סוג רשומה לא תקין'];
    }
    
    // רשימת שדות מותרים לכל טבלה
    $allowedFields = [
        'cemeteries' => ['name', 'code', 'is_active'],
        'blocks' => ['cemetery_id', 'name', 'code', 'is_active'],
        'plots' => ['block_id', 'name', 'code', 'is_active'],
        'rows' => ['plot_id', 'name', 'code', 'is_active'],
        'areaGraves' => ['row_id', 'name', 'code', 'is_active'],
        'graves' => ['areaGrave_id', 'name', 'grave_number', 'code', 'is_available']
    ];
    
    if (!isset($allowedFields[$table])) {
        return ['success' => false, 'message' => 'טבלה לא מוכרת'];
    }
    
    // סנן רק שדות מותרים
    $data = [];
    foreach ($allowedFields[$table] as $field) {
        if (isset($_POST[$field])) {
            // סינון וולידציה לפי סוג השדה
            $value = $_POST[$field];
            
            // וולידציה לשדות מספריים
            if (in_array($field, ['cemetery_id', 'block_id', 'plot_id', 'row_id', 'areaGrave_id'])) {
                if (!is_numeric($value) || $value <= 0) {
                    return ['success' => false, 'message' => "ערך לא תקין בשדה $field"];
                }
                $data[$field] = (int)$value;
            }
            // וולידציה לשדות בוליאניים
            elseif (in_array($field, ['is_active', 'is_available'])) {
                $data[$field] = in_array($value, ['1', '0']) ? (int)$value : 1;
            }
            // וולידציה לשדות טקסט
            else {
                $value = trim($value);
                if ($field === 'name' && empty($value)) {
                    return ['success' => false, 'message' => 'שם הוא שדה חובה'];
                }
                // הגבלת אורך
                if (strlen($value) > 255) {
                    return ['success' => false, 'message' => "השדה $field ארוך מדי"];
                }
                // סינון תווים מיוחדים בקוד
                if ($field === 'code' && !empty($value)) {
                    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                        return ['success' => false, 'message' => 'קוד יכול להכיל רק אותיות, מספרים, מקף וקו תחתון'];
                    }
                }
                $data[$field] = $value;
            }
        }
    }
    
    // בדיקה שיש נתונים
    if (empty($data)) {
        return ['success' => false, 'message' => 'אין נתונים לשמירה'];
    }
    
    // בדיקת תלויות - וודא שההורה קיים
    if (!validateParentExists($table, $data)) {
        return ['success' => false, 'message' => 'הרשומה האב לא קיימת'];
    }
    
    // בניית השאילתה עם prepared statements
    $fields = array_keys($data);
    $placeholders = array_map(function($field) { return ":$field"; }, $fields);
    
    $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    try {
        $stmt = $pdo->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'הרשומה נוספה בהצלחה',
                'id' => $pdo->lastInsertId()
            ];
        }
    } catch (PDOException $e) {
        // בדוק אם זו שגיאת מפתח כפול
        if ($e->getCode() == 23000) {
            return ['success' => false, 'message' => 'קוד זה כבר קיים במערכת'];
        }
        error_log('Database error in createItem: ' . $e->getMessage());
        return ['success' => false, 'message' => 'שגיאה בהוספת הרשומה'];
    }
    
    return ['success' => false, 'message' => 'שגיאה בהוספת הרשומה'];
}

function updateItem() {
    global $pdo;
    
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? 0;
    $table = getTableName($type);
    
    // וולידציה בסיסית
    if (!$table || !is_numeric($id) || $id <= 0) {
        return ['success' => false, 'message' => 'נתונים לא תקינים'];
    }
    
    $id = (int)$id;
    
    // בדוק שהרשומה קיימת
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() == 0) {
        return ['success' => false, 'message' => 'הרשומה לא נמצאה'];
    }
    
    // רשימת שדות מותרים לכל טבלה
    $allowedFields = [
        'cemeteries' => ['name', 'code', 'is_active'],
        'blocks' => ['cemetery_id', 'name', 'code', 'is_active'],
        'plots' => ['block_id', 'name', 'code', 'is_active'],
        'rows' => ['plot_id', 'name', 'code', 'is_active'],
        'areaGraves' => ['row_id', 'name', 'code', 'is_active'],
        'graves' => ['areaGrave_id', 'name', 'grave_number', 'code', 'is_available']
    ];
    
    if (!isset($allowedFields[$table])) {
        return ['success' => false, 'message' => 'טבלה לא מוכרת'];
    }
    
    // סנן רק שדות מותרים
    $data = [];
    foreach ($allowedFields[$table] as $field) {
        if (isset($_POST[$field])) {
            $value = $_POST[$field];
            
            // וולידציה לשדות מספריים
            if (in_array($field, ['cemetery_id', 'block_id', 'plot_id', 'row_id', 'areaGrave_id'])) {
                if (!is_numeric($value) || $value <= 0) {
                    return ['success' => false, 'message' => "ערך לא תקין בשדה $field"];
                }
                $data[$field] = (int)$value;
            }
            // וולידציה לשדות בוליאניים
            elseif (in_array($field, ['is_active', 'is_available'])) {
                $data[$field] = in_array($value, ['1', '0']) ? (int)$value : 1;
            }
            // וולידציה לשדות טקסט
            else {
                $value = trim($value);
                if ($field === 'name' && empty($value)) {
                    return ['success' => false, 'message' => 'שם הוא שדה חובה'];
                }
                if (strlen($value) > 255) {
                    return ['success' => false, 'message' => "השדה $field ארוך מדי"];
                }
                if ($field === 'code' && !empty($value)) {
                    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                        return ['success' => false, 'message' => 'קוד יכול להכיל רק אותיות, מספרים, מקף וקו תחתון'];
                    }
                }
                $data[$field] = $value;
            }
        }
    }
    
    if (empty($data)) {
        return ['success' => false, 'message' => 'אין נתונים לעדכון'];
    }
    
    // בדיקת תלויות
    if (!validateParentExists($table, $data)) {
        return ['success' => false, 'message' => 'הרשומה האב לא קיימת'];
    }
    
    // בניית השאילתה
    $sets = [];
    foreach ($data as $key => $value) {
        $sets[] = "$key = :$key";
    }
    
    $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'הרשומה עודכנה בהצלחה'];
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['success' => false, 'message' => 'קוד זה כבר קיים במערכת'];
        }
        error_log('Database error in updateItem: ' . $e->getMessage());
        return ['success' => false, 'message' => 'שגיאה בעדכון הרשומה'];
    }
    
    return ['success' => false, 'message' => 'שגיאה בעדכון הרשומה'];
}

function deleteItem() {
    global $pdo;
    
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? 0;
    $table = getTableName($type);
    
    // וולידציה בסיסית
    if (!$table || !is_numeric($id) || $id <= 0) {
        return ['success' => false, 'message' => 'נתונים לא תקינים'];
    }
    
    $id = (int)$id;
    
    // בדיקה אם יש רשומות תלויות
    if (hasChildren($type, $id)) {
        return ['success' => false, 'message' => 'לא ניתן למחוק - קיימות רשומות תלויות'];
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
        if ($stmt->execute([$id])) {
            return ['success' => true, 'message' => 'הרשומה נמחקה בהצלחה'];
        }
    } catch (PDOException $e) {
        error_log('Database error in deleteItem: ' . $e->getMessage());
        return ['success' => false, 'message' => 'שגיאה במחיקת הרשומה'];
    }
    
    return ['success' => false, 'message' => 'שגיאה במחיקת הרשומה'];
}

// פונקציה לבדיקת קיום רשומת אב
function validateParentExists($table, $data) {
    global $pdo;
    
    $parentChecks = [
        'blocks' => ['field' => 'cemetery_id', 'table' => 'cemeteries'],
        'plots' => ['field' => 'block_id', 'table' => 'blocks'],
        'rows' => ['field' => 'plot_id', 'table' => 'plots'],
        'areaGraves' => ['field' => 'row_id', 'table' => 'rows'],
        'graves' => ['field' => 'areaGrave_id', 'table' => 'areaGraves']
    ];
    
    if (isset($parentChecks[$table])) {
        $check = $parentChecks[$table];
        if (isset($data[$check['field']])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$check['table']} WHERE id = ?");
            $stmt->execute([$data[$check['field']]]);
            return $stmt->fetchColumn() > 0;
        }
    }
    
    return true;
}

function getTableName($type) {
    $tables = [
        'cemetery' => 'cemeteries',
        'block' => 'blocks',
        'plot' => 'plots',
        'row' => 'rows',
        'areaGrave' => 'areaGraves',
        'grave' => 'graves'
    ];
    
    return $tables[$type] ?? null;
}

function hasChildren($type, $id) {
    global $pdo;
    
    $checks = [
        'cemetery' => ['blocks' => 'cemetery_id'],
        'block' => ['plots' => 'block_id'],
        'plot' => ['rows' => 'plot_id'],
        'row' => ['areaGraves' => 'row_id'],
        'areaGrave' => ['graves' => 'areaGrave_id']
    ];
    
    if (isset($checks[$type])) {
        foreach ($checks[$type] as $table => $field) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                return true;
            }
        }
    }
    
    return false;
}

function getBlocksByCemetery($cemetery_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM blocks 
        WHERE cemetery_id = ? AND is_active = 1 
        ORDER BY name
    ");
    $stmt->execute([$cemetery_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPlotsByBlock($block_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM plots 
        WHERE block_id = ? AND is_active = 1 
        ORDER BY name
    ");
    $stmt->execute([$block_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRowsByPlot($plot_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM rows 
        WHERE plot_id = ? AND is_active = 1 
        ORDER BY name
    ");
    $stmt->execute([$plot_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAreaGravesByRow($row_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM areaGraves 
        WHERE row_id = ? AND is_active = 1 
        ORDER BY name
    ");
    $stmt->execute([$row_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>