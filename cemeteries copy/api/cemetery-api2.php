<?php
// api/cemetery-api2.php
// גירסה שהציגה- ללא יכולת הוספה
require_once '../../config.php';

header('Content-Type: application/json; charset=utf-8');

// בדיקת הרשאות
if (!isset($_SESSION['user_id']) || $_SESSION['permission_level'] < 4) {
    die(json_encode(['error' => 'אין הרשאה']));
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
            
        case 'getHierarchy':
            echo json_encode(getHierarchy());
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
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
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createItem() {
    global $pdo;
    
    $type = $_POST['type'] ?? '';
    $table = getTableName($type);
    
    // הסרת שדות מיותרים
    unset($_POST['action'], $_POST['type'], $_POST['id']);
    
    // בניית השאילתה
    $fields = array_keys($_POST);
    $placeholders = array_map(function($field) { return ":$field"; }, $fields);
    
    $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    
    foreach ($_POST as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'הרשומה נוספה בהצלחה',
            'id' => $pdo->lastInsertId()
        ];
    }
    
    return ['success' => false, 'message' => 'שגיאה בהוספת הרשומה'];
}

function updateItem() {
    global $pdo;
    
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? 0;
    $table = getTableName($type);
    
    // הסרת שדות מיותרים
    unset($_POST['action'], $_POST['type'], $_POST['id']);
    
    // בניית השאילתה
    $sets = [];
    foreach ($_POST as $key => $value) {
        $sets[] = "$key = :$key";
    }
    
    $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    foreach ($_POST as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(":id", $id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'הרשומה עודכנה בהצלחה'];
    }
    
    return ['success' => false, 'message' => 'שגיאה בעדכון הרשומה'];
}

function deleteItem() {
    global $pdo;
    
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? 0;
    $table = getTableName($type);
    
    // בדיקה אם יש רשומות תלויות
    if (hasChildren($type, $id)) {
        return ['success' => false, 'message' => 'לא ניתן למחוק - קיימות רשומות תלויות'];
    }
    
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
    if ($stmt->execute([$id])) {
        return ['success' => true, 'message' => 'הרשומה נמחקה בהצלחה'];
    }
    
    return ['success' => false, 'message' => 'שגיאה במחיקת הרשומה'];
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
    
    return $tables[$type] ?? '';
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

function getHierarchy() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            c.id as cemetery_id,
            c.name as cemetery_name,
            b.id as block_id,
            b.name as block_name,
            p.id as plot_id,
            p.name as plot_name,
            r.id as row_id,
            r.name as row_name,
            ag.id as area_grave_id,
            ag.name as area_grave_name,
            g.id as grave_id,
            g.name as grave_name,
            g.grave_number,
            g.is_available
        FROM cemeteries c
        LEFT JOIN blocks b ON b.cemetery_id = c.id
        LEFT JOIN plots p ON p.block_id = b.id
        LEFT JOIN rows r ON r.plot_id = p.id
        LEFT JOIN areaGraves ag ON ag.row_id = r.id
        LEFT JOIN graves g ON g.areaGrave_id = ag.id
        WHERE c.is_active = 1
        ORDER BY c.name, b.name, p.name, r.name, ag.name, g.grave_number
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>