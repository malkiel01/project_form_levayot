<?php
// ajax/get_blocks.php - קבלת רשימת גושים
require_once '../config.php';
require_once '../DeceasedForm.php';

$cemeteryId = $_GET['cemetery_id'] ?? 0;  // ✅ נכון
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

$form = new DeceasedForm(null, $userPermissionLevel);
$blocks = $form->getBlocks($cemeteryId);

echo '<option value="">בחר...</option>';
foreach ($blocks as $block) {
    echo '<option value="' . $block['id'] . '">' . htmlspecialchars($block['name']) . '</option>';
}