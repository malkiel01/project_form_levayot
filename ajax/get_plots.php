<?php
// ajax/get_plots.php - קבלת רשימת חלקות
require_once '../../config.php';
require_once '../../DeceasedForm.php';

$blockId = $_GET['block_id'] ?? 0;  // ✅ תוקן מ-cemetery_id ל-block_id
$userPermissionLevel = $_SESSION['permission_level'] ?? 1;

$form = new DeceasedForm(null, $userPermissionLevel);
$plots = $form->getPlots($blockId);  // ✅ תוקן

echo '<option value="">בחר...</option>';
foreach ($plots as $plot) {
    echo '<option value="' . $plot['id'] . '">' . htmlspecialchars($plot['name']) . '</option>';
}