// form/index.php - עדכון לתמיכה בסוגי טפסים
<?php
require_once '../config.php';

// קבלת סוג הטופס
$formType = $_GET['type'] ?? 'deceased';
$formUuid = $_GET['id'] ?? null;

// טעינת המחלקה המתאימה
$formTypeData = $db->prepare("SELECT * FROM form_types WHERE type_key = ?");
$formTypeData->execute([$formType]);
$typeInfo = $formTypeData->fetch();

if (!$typeInfo) {
    die('Invalid form type');
}

// טעינת המחלקה הדינמית
require_once "../{$typeInfo['form_class']}.php";
$formClass = $typeInfo['form_class'];
$form = new $formClass($formUuid, $_SESSION['permission_level']);

// טעינת קובץ הגדרות השדות המתאים
$fieldsConfig = require "form_configs/{$formType}_fields.php";
?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <title><?= $isNewForm ? 'יצירת' : 'עריכת' ?> <?= htmlspecialchars($typeInfo['type_name']) ?></title>
    <!-- ... -->
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas <?= $typeInfo['icon'] ?>" style="color: <?= $typeInfo['color'] ?>"></i>
            <?= $isNewForm ? 'יצירת' : 'עריכת' ?> <?= htmlspecialchars($typeInfo['type_name']) ?>
        </h1>
        
        <form method="POST" id="dynamicForm">
            <!-- רינדור דינמי של השדות לפי הקונפיגורציה -->
            <?php foreach ($fieldsConfig['sections'] as $section): ?>
            <div class="section-title"><?= $section['title'] ?></div>
            <div class="row">
                <?php foreach ($section['fields'] as $field): ?>
                <div class="<?= $field['col_class'] ?>">
                    <?= renderField($field, $formData, $form, $requiredFields, $errors, $viewOnly) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-primary">שמור</button>
        </form>
    </div>
</body>
</html>