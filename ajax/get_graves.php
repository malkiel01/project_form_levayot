<?php
// ajax/get_graves.php - קבלת רשימת קברים
require_once '../config.php';
require_once '../DeceasedForm.php';

$rowId = $_GET['row_id'] ?? 0;
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

$form = new DeceasedForm(null, $userPermissionLevel);
$graves = $form->getGraves($rowId);

echo '<option value="">בחר...</option>';
foreach ($graves as $grave) {
    echo '<option value="' . $grave['id'] . '">' . htmlspecialchars($grave['name']) . '</option>';
}