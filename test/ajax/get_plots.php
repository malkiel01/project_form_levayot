<?php
// ajax/get_plots.php - קבלת רשימת אחוזות קבר

require_once '../config.php';
require_once '../DeceasedForm.php';

$cemeteryId = $_GET['cemetery_id'] ?? 0;
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

$form = new DeceasedForm(null, $userPermissionLevel);
$plots = $form->getPlots($cemeteryId);

echo '<option value="">בחר...</option>';
foreach ($plots as $plot) {
    echo '<option value="' . $plot['id'] . '">' . htmlspecialchars($plot['name']) . '</option>';
}