<!-- forms_list.php - תמיכה בסוגי טפסים מרובים -->
<?php
$formType = $_GET['type'] ?? 'all';

// בניית שאילתה דינמית
if ($formType === 'all') {
    // איחוד כל סוגי הטפסים
    $queries = [];
    foreach ($formTypes as $type) {
        $queries[] = "
            SELECT 
                form_uuid,
                '{$type['type_key']}' as form_type,
                '{$type['type_name']}' as form_type_name,
                status,
                created_at,
                CASE 
                    WHEN '{$type['type_key']}' = 'deceased' THEN deceased_name
                    WHEN '{$type['type_key']}' = 'purchase' THEN buyer_name
                END as main_name
            FROM {$type['table_name']}
        ";
    }
    $unionQuery = implode(' UNION ALL ', $queries);
    $query = "($unionQuery) ORDER BY created_at DESC";
} else {
    // טופס ספציפי
    $typeInfo = // ... קבל את המידע על הסוג
    $query = "SELECT * FROM {$typeInfo['table_name']} ORDER BY created_at DESC";
}
?>

<!-- הוספת פילטר סוג טופס -->
<div class="col-md-2">
    <label for="type" class="form-label">סוג טופס</label>
    <select class="form-select" id="type" name="type">
        <option value="all">כל הסוגים</option>
        <?php foreach ($formTypes as $type): ?>
        <option value="<?= $type['type_key'] ?>" <?= $formType === $type['type_key'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($type['type_name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>