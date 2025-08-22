<?php
// includes/dashboard_functions.php - פונקציות עזר לדשבורדים

/**
 * תרגום סטטוס
 */
function translateStatus($status) {
    $translations = [
        'draft' => 'טיוטה',
        'in_progress' => 'בתהליך',
        'completed' => 'הושלם',
        'archived' => 'בארכיון'
    ];
    return $translations[$status] ?? $status;
}

/**
 * תרגום סוג רכישה
 */
function translatePurchaseType($type) {
    $translations = [
        'grave' => 'קבר',
        'plot' => 'חלקה',
        'structure' => 'מבנה',
        'service' => 'שירות'
    ];
    return $translations[$type] ?? $type;
}

/**
 * תרגום אמצעי תשלום
 */
function translatePaymentMethod($method) {
    $translations = [
        'cash' => 'מזומן',
        'check' => 'צ\'ק',
        'credit' => 'אשראי',
        'transfer' => 'העברה בנקאית',
        'installments' => 'תשלומים'
    ];
    return $translations[$method] ?? $method;
}

/**
 * יצירת צבע רנדומלי לגרפים
 */
function generateChartColors($count) {
    $colors = [
        '#667eea', '#764ba2', '#84fab0', '#8fd3f4', 
        '#fa709a', '#fee140', '#30cfd0', '#330867',
        '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
        '#43e97b', '#38f9d7', '#fa709a', '#fee140',
        '#a8edea', '#fed6e3', '#ff9a9e', '#fecfef'
    ];
    
    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $result[] = $colors[$i % count($colors)];
    }
    return $result;
}

/**
 * פורמט תאריך עברי
 */
function formatHebrewDate($date) {
    if (empty($date)) return 'לא צוין';
    
    $timestamp = strtotime($date);
    $hebrewMonths = [
        1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
        5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
        9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
    ];
    
    $day = date('j', $timestamp);
    $month = $hebrewMonths[date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day ב$month $year";
}

/**
 * פורמט סכום כספי
 */
function formatCurrency($amount) {
    return '₪' . number_format($amount, 0, '.', ',');
}

/**
 * חישוב אחוז השלמה
 */
function calculateCompletionPercentage($completed, $total) {
    if ($total == 0) return 0;
    return round(($completed / $total) * 100);
}

/**
 * קבלת צבע לפי סטטוס
 */
function getStatusColor($status) {
    $colors = [
        'draft' => '#6c757d',
        'in_progress' => '#ffc107',
        'completed' => '#28a745',
        'archived' => '#17a2b8'
    ];
    return $colors[$status] ?? '#6c757d';
}

/**
 * קבלת אייקון לפי סטטוס
 */
function getStatusIcon($status) {
    $icons = [
        'draft' => 'fa-file',
        'in_progress' => 'fa-hourglass-half',
        'completed' => 'fa-check-circle',
        'archived' => 'fa-archive'
    ];
    return $icons[$status] ?? 'fa-question';
}

/**
 * יצירת תגית HTML לסטטוס
 */
function createStatusBadge($status) {
    $translatedStatus = translateStatus($status);
    $color = getStatusColor($status);
    $icon = getStatusIcon($status);
    
    return sprintf(
        '<span class="badge" style="background-color: %s; color: white;">
            <i class="fas %s"></i> %s
        </span>',
        $color,
        $icon,
        $translatedStatus
    );
}

/**
 * קבלת תקופת זמן יחסית
 */
function getRelativeTime($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'לפני רגע';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return "לפני $minutes דקות";
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return "לפני $hours שעות";
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return "לפני $days ימים";
    } else {
        return date('d/m/Y', $timestamp);
    }
}

/**
 * יצירת מספר טופס ייחודי
 */
function generateFormNumber($prefix = 'FRM') {
    return $prefix . '-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * בדיקת הרשאות לפי רמה
 */
function checkPermissionLevel($requiredLevel, $currentLevel = null) {
    if ($currentLevel === null) {
        $currentLevel = $_SESSION['permission_level'] ?? 1;
    }
    return $currentLevel >= $requiredLevel;
}

/**
 * יצירת סיכום סטטיסטי
 */
function generateStatsSummary($stats) {
    $summary = [];
    
    // חישוב סך הכל
    $total = array_sum($stats);
    
    // חישוב אחוזים
    foreach ($stats as $key => $value) {
        $percentage = $total > 0 ? round(($value / $total) * 100, 1) : 0;
        $summary[$key] = [
            'value' => $value,
            'percentage' => $percentage
        ];
    }
    
    return $summary;
}

/**
 * יצירת אופציות לרשימה נפתחת מתוך מערך
 */
function createSelectOptions($array, $selectedValue = null, $useKeyAsValue = false) {
    $html = '';
    foreach ($array as $key => $value) {
        $optionValue = $useKeyAsValue ? $key : $value;
        $selected = ($optionValue == $selectedValue) ? 'selected' : '';
        $html .= sprintf('<option value="%s" %s>%s</option>', 
                        htmlspecialchars($optionValue), 
                        $selected, 
                        htmlspecialchars($value));
    }
    return $html;
}

/**
 * הצגת התראה בסגנון Bootstrap
 */
function showAlert($message, $type = 'info', $dismissible = true) {
    $dismissButton = $dismissible ? 
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' : '';
    
    return sprintf(
        '<div class="alert alert-%s %s" role="alert">
            %s
            %s
        </div>',
        $type,
        $dismissible ? 'alert-dismissible fade show' : '',
        $message,
        $dismissButton
    );
}