<?php
// ajax/get_graves.php - קבלת רשימת קברים
require_once '../../config.php';
require_once '../../DeceasedForm.php';

$areaGraveId = $_GET['area_grave_id'] ?? 0;  // ✅ תוקן מ-row_id ל-area_grave_id
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

$form = new DeceasedForm(null, $userPermissionLevel);
$graves = $form->getGraves($areaGraveId);  // ✅ תוקן

echo '<option value="">בחר...</option>';
foreach ($graves as $grave) {
    $graveName = htmlspecialchars($grave['name']);
    if (!empty($grave['grave_number'])) {
        $graveName .= ' (' . htmlspecialchars($grave['grave_number']) . ')';
    }
    echo '<option value="' . $grave['id'] . '">' . $graveName . '</option>';
}