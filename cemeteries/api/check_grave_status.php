<?php
// api/check_grave_status.php
require_once '../../config.php';

header('Content-Type: application/json');

// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

$action = $_GET['action'] ?? '';
$graveId = $_GET['grave_id'] ?? 0;

if (!$graveId) {
    die(json_encode(['error' => 'Missing grave ID']));
}

try {
    switch ($action) {
        case 'check_purchase':
            // בדוק אם יש רכישה לקבר זה
            $stmt = $pdo->prepare("
                SELECT 
                    pf.form_uuid,
                    pf.buyer_name,
                    pf.buyer_phone,
                    pf.purchase_date,
                    pf.status,
                    pf.total_amount,
                    pf.payment_method
                FROM purchase_forms pf
                WHERE pf.grave_id = ?
                AND pf.status != 'cancelled'
                ORDER BY pf.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$graveId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        case 'check_burial':
            // בדוק אם יש קבורה בקבר זה
            $stmt = $pdo->prepare("
                SELECT 
                    df.form_uuid,
                    CONCAT(IFNULL(df.deceased_first_name, ''), ' ', IFNULL(df.deceased_last_name, '')) as deceased_name,
                    df.death_date,
                    df.burial_date,
                    df.burial_license,
                    df.status
                FROM deceased_forms df
                WHERE df.grave_id = ?
                AND df.status = 'completed'
                ORDER BY df.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$graveId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        case 'get_history':
            // קבל היסטוריית פעולות על הקבר
            $stmt = $pdo->prepare("
                SELECT 
                    'רכישה' as action,
                    pf.created_at,
                    u.full_name as user_name,
                    CONCAT('רכישה על ידי ', pf.buyer_name) as notes
                FROM purchase_forms pf
                LEFT JOIN users u ON pf.created_by = u.id
                WHERE pf.grave_id = ?
                
                UNION ALL
                
                SELECT 
                    'קבורה' as action,
                    df.created_at,
                    u.full_name as user_name,
                    CONCAT('קבורת ', IFNULL(df.deceased_first_name, ''), ' ', IFNULL(df.deceased_last_name, '')) as notes
                FROM deceased_forms df
                LEFT JOIN users u ON df.created_by = u.id
                WHERE df.grave_id = ?
                
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$graveId, $graveId]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
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
            $purchaseStmt = $pdo->prepare("
                SELECT COUNT(*) FROM purchase_forms 
                WHERE grave_id = ? AND status != 'cancelled'
            ");
            $purchaseStmt->execute([$graveId]);
            $hasPurchase = $purchaseStmt->fetchColumn() > 0;
            
            // בדוק קבורה
            $burialStmt = $pdo->prepare("
                SELECT COUNT(*) FROM deceased_forms 
                WHERE grave_id = ? AND status = 'completed'
            ");
            $burialStmt->execute([$graveId]);
            $hasBurial = $burialStmt->fetchColumn() > 0;
            
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