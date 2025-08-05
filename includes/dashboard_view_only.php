<?php
// גישה חלופית - בלי UNION
// הטפסים האחרונים שלי
$myRecentForms = [];

// טען טפסי נפטרים
$stmt = $db->prepare("
    SELECT 
        'deceased' as form_type,
        form_uuid,
        deceased_name as name,
        status,
        created_at
    FROM deceased_forms 
    WHERE created_by = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$deceasedForms = $stmt->fetchAll();

// טען טפסי רכישות
$stmt = $db->prepare("
    SELECT 
        'purchase' as form_type,
        form_uuid,
        buyer_name as name,
        status,
        created_at
    FROM purchase_forms 
    WHERE created_by = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$purchaseForms = $stmt->fetchAll();

// שלב את שתי הרשימות
$myRecentForms = array_merge($deceasedForms, $purchaseForms);

// מיין לפי תאריך יצירה
usort($myRecentForms, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// קח רק את ה-10 הראשונים
$myRecentForms = array_slice($myRecentForms, 0, 10);