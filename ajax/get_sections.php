<?php
// ajax/get_sections.php - קבלת רשימת חלקות
require_once '../config.php';
require_once '../DeceasedForm.php';

$blockId = $_GET['block_id'] ?? 0;
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

$form = new DeceasedForm(null, $userPermissionLevel);
$plots = $form->getSections($blockId);

echo '<option value="">בחר...</option>';
foreach ($plots as $plot) {
    echo '<option value="' . $plot['id'] . '">' . htmlspecialchars($plot['name']) . '</option>';
}