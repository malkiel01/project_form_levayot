<?php
// ajax/get_area_graves.php - קבלת רשימת אחוזות קבר
require_once '../../config.php';
require_once '../../DeceasedForm.php';

session_start();
$rowId = $_GET['row_id'] ?? 0;
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

$form = new DeceasedForm(null, $userPermissionLevel);
$areaGraves = $form->getAreaGraves($rowId);

echo '<option value="">בחר...</option>';
foreach ($areaGraves as $areaGrave) {
    echo '<option value="' . $areaGrave['id'] . '">' . htmlspecialchars($areaGrave['name']) . '</option>';
}