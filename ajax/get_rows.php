<?php
// ajax/get_rows.php - קבלת רשימת שורות
require_once '../config.php';
require_once '../DeceasedForm.php';

$plotId = $_GET['plot_id'] ?? 0;
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

$form = new DeceasedForm(null, $userPermissionLevel);
$rows = $form->getRows($plotId);

echo '<option value="">בחר...</option>';
foreach ($rows as $row) {
    echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
}