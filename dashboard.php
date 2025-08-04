// dashboard.php - הוספת תמיכה בסוגי טפסים מרובים
<?php
require_once 'config.php';

// קבלת סוגי הטפסים הפעילים
$formTypes = $db->query("
    SELECT * FROM form_types WHERE is_active = 1
")->fetchAll();

// סטטיסטיקות לפי סוג טופס
$statsByType = [];
foreach ($formTypes as $type) {
    $stats = [];
    $tableName = $type['table_name'];
    
    $stats['total'] = $db->query("SELECT COUNT(*) FROM $tableName")->fetchColumn();
    $stats['completed'] = $db->query("SELECT COUNT(*) FROM $tableName WHERE status = 'completed'")->fetchColumn();
    $stats['in_progress'] = $db->query("SELECT COUNT(*) FROM $tableName WHERE status = 'in_progress'")->fetchColumn();
    $stats['draft'] = $db->query("SELECT COUNT(*) FROM $tableName WHERE status = 'draft'")->fetchColumn();
    $stats['today'] = $db->query("SELECT COUNT(*) FROM $tableName WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    
    $statsByType[$type['type_key']] = $stats;
}
?>

<!-- הוספת כרטיסי סטטיסטיקה לפי סוג -->
<div class="row mb-4">
    <?php foreach ($formTypes as $type): ?>
    <div class="col-md-6 mb-3">
        <div class="card border-left-primary shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <?= htmlspecialchars($type['type_name']) ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($statsByType[$type['type_key']]['total']) ?>
                        </div>
                        <small class="text-muted">
                            <?= $statsByType[$type['type_key']]['completed'] ?> הושלמו | 
                            <?= $statsByType[$type['type_key']]['today'] ?> היום
                        </small>
                    </div>
                    <div class="col-auto">
                        <i class="fas <?= $type['icon'] ?> fa-2x" style="color: <?= $type['color'] ?>"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="forms_list.php?type=<?= $type['type_key'] ?>" class="btn btn-sm btn-primary">
                        צפה ברשימה
                    </a>
                    <a href="form.php?type=<?= $type['type_key'] ?>" class="btn btn-sm btn-success">
                        יצירת טופס חדש
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>