// ajax/save_draft.php - תמיכה בסוגי טפסים
<?php
$formType = $_POST['form_type'] ?? 'deceased';
$formTypeData = $db->prepare("SELECT * FROM form_types WHERE type_key = ?");
$formTypeData->execute([$formType]);
$typeInfo = $formTypeData->fetch();

require_once "../{$typeInfo['form_class']}.php";
$formClass = $typeInfo['form_class'];
$form = new $formClass($formUuid, $userPermissionLevel);

// המשך הלוגיקה...
?>