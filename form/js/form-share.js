// form/js/form-share.js - פונקציונליות שיתוף טפסים

// פונקציה לשיתוף טופס
function shareForm() {
    // בדיקת הרשאות
    if (!formConfig.canShare) {
        showShareError();
        return;
    }
    
    if (formConfig.isNewForm) {
        alert('יש לשמור את הטופס לפני השיתוף');
        return;
    }
    
    // טען רשימת משתמשים
    loadUsersList();
    
    // פתח את המודל
    $('#shareFormModal').modal('show');
}

// שיתוף מהיר
function quickShareForm() {
    // בדיקת הרשאות
    if (!formConfig.canShare) {
        showShareError();
        return;
    }
    
    if (formConfig.isNewForm) {
        alert('יש לשמור את הטופס לפני השיתוף');
        return;
    }

    const formData = new FormData();
    formData.append('form_uuid', formConfig.formUuid);
    formData.append('csrf_token', formConfig.csrfToken);
    formData.append('access_type', 'public');
    formData.append('permission_mode', 'view');
    formData.append('permission_level', '1');
    formData.append('expiry_type', 'never');
    formData.append('description', 'קישור מהיר');
    
    $.ajax({
        url: 'ajax/share_link.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const formUrl = response.share_url;
                
                if (navigator.share) {
                    navigator.share({
                        title: 'טופס הזנת נפטר',
                        url: formUrl
                    }).catch((err) => {
                        console.log('Error sharing:', err);
                        copyToClipboard(formUrl);
                    });
                } else {
                    copyToClipboard(formUrl);
                }
            } else {
                alert('שגיאה ביצירת קישור: ' + (response.message || 'שגיאה לא ידועה'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('שגיאה ביצירת הקישור');
        }
    });
}

// הצגת הודעת שגיאת הרשאות
function showShareError() {
    if (!formConfig.isUserLoggedIn) {
        if (confirm('עליך להתחבר למערכת כדי לשתף טפסים. האם ברצונך להתחבר כעת?')) {
            window.location.href = '../' + LOGIN_URL;
        }
    } else {
        alert('אין לך הרשאה לשתף טפסים. פעולה זו מוגבלת למשתמשים עם הרשאות עורך (רמה 3) ומעלה.');
    }
}

// טעינת רשימת משתמשים
function loadUsersList() {
    $.get('../ajax/get_users_list.php', function(data) {
        $('#allowed_users').html(data);
    });
}

// הצגה/הסתרה של שדות לפי הבחירה
$(document).ready(function() {
    // גישה למשתמשים ספציפיים
    $('input[name="access_type"]').change(function() {
        if ($(this).val() === 'users') {
            $('#usersSelectDiv').show();
        } else {
            $('#usersSelectDiv').hide();
        }
    });
    
    // תוקף מותאם אישית
    $('input[name="expiry_type"]').change(function() {
        if ($(this).val() === 'custom') {
            $('#expiryDateDiv').show();
            // הגדר ברירת מחדל לעוד שבוע
            setExpiryDays(7);
        } else {
            $('#expiryDateDiv').hide();
        }
    });
});

// הגדרת תאריך תפוגה
function setExpiryDays(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    $('#expiry_date').val(date.toISOString().split('T')[0]);
}

// יצירת קישור השיתוף
function createShareLink() {
    // בדיקת הרשאות נוספת
    if (!formConfig.canShare) {
        showShareError();
        return;
    }
    
    const formData = new FormData();
    formData.append('form_uuid', formConfig.formUuid);
    formData.append('csrf_token', formConfig.csrfToken);
    
    // סוג גישה
    const accessType = $('input[name="access_type"]:checked').val();
    formData.append('access_type', accessType);
    
    if (accessType === 'users') {
        const selectedUsers = $('#allowed_users').val();
        if (!selectedUsers || selectedUsers.length === 0) {
            showAlert('shareLinkAlert', 'danger', 'יש לבחור לפחות משתמש אחד');
            return;
        }
        formData.append('allowed_users', JSON.stringify(selectedUsers));
    }
    
    // הרשאות
    const permissionMode = $('input[name="permission_mode"]:checked').val();
    formData.append('permission_mode', permissionMode);
    
    // רמת הרשאה
    formData.append('permission_level', $('#permission_level').val());
    
    // תוקף
    const expiryType = $('input[name="expiry_type"]:checked').val();
    formData.append('expiry_type', expiryType);
    
    if (expiryType === 'custom') {
        const expiryDate = $('#expiry_date').val();
        const expiryTime = $('#expiry_time').val();
        if (!expiryDate) {
            showAlert('shareLinkAlert', 'danger', 'יש לבחור תאריך תפוגה');
            return;
        }
        formData.append('expiry_date', expiryDate);
        formData.append('expiry_time', expiryTime);
    }
    
    // תיאור
    formData.append('description', $('#link_description').val());
    
    // שלח בקשה
    showAlert('shareLinkAlert', 'info', 'יוצר קישור...');
    
    $.ajax({
        url: 'ajax/share_link.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#shareFormModal').modal('hide');
                showGeneratedLink(response);
            } else {
                showAlert('shareLinkAlert', 'danger', response.message || 'שגיאה ביצירת הקישור');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error creating link:', error);
            showAlert('shareLinkAlert', 'danger', 'שגיאת תקשורת: ' + error);
        }
    });
}

// הצגת הקישור שנוצר
function showGeneratedLink(data) {
    $('#generatedLink').val(data.share_url);
    
    // הצג פרטי קישור
    let details = '<strong>פרטי הקישור:</strong><br>';
    details += '• סוג גישה: ' + (data.access_type === 'restricted' ? 'משתמשים ספציפיים' : 'פתוח לכולם') + '<br>';
    details += '• הרשאות: ' + (data.can_edit ? 'צפייה ועריכה' : 'צפייה בלבד') + '<br>';
    if (data.expires_at) {
        details += '• תוקף עד: ' + new Date(data.expires_at).toLocaleDateString('he-IL') + '<br>';
    } else {
        details += '• תוקף: ללא הגבלה<br>';
    }
    $('#linkDetails').html(details);
    
    // צור QR Code
    $('#qrcode').empty();
    if (typeof QRCode !== 'undefined') {
        new QRCode(document.getElementById("qrcode"), {
            text: data.share_url,
            width: 200,
            height: 200
        });
    }
    
    $('#showLinkModal').modal('show');
}

// העתקת קישור
function copyLink() {
    const linkInput = document.getElementById('generatedLink');
    linkInput.select();
    document.execCommand('copy');
    
    // הצג הודעה
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> הועתק!';
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-success');
    
    setTimeout(() => {
        btn.innerHTML = originalHtml;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline-secondary');
    }, 2000);
}

// שיתוף בוואטסאפ
function shareViaWhatsApp() {
    const link = $('#generatedLink').val();
    const firstName = formData.deceased_first_name || '';
    const lastName = formData.deceased_last_name || '';
    const fullName = (firstName + ' ' + lastName).trim() || 'טופס חדש';
    const text = 'טופס נפטר - ' + fullName + '\n' + link;
    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
}

// שיתוף במייל
function shareViaEmail() {
    const link = $('#generatedLink').val();
    const firstName = formData.deceased_first_name || '';
    const lastName = formData.deceased_last_name || '';
    const fullName = (firstName + ' ' + lastName).trim() || 'טופס חדש';
    const subject = 'טופס נפטר - ' + fullName;
    const body = 'שלום,\n\nמצורף קישור לטופס נפטר:\n' + link + '\n\nבברכה';
    window.location.href = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
}

// פונקציית עזר להצגת הודעות
function showAlert(elementId, type, message) {
    const alert = $('#' + elementId);
    alert.removeClass('alert-success alert-danger alert-info alert-warning');
    alert.addClass('alert-' + type);
    alert.html(message);
    alert.show();
}

// פונקציה להעתקה ללוח
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            alert('הקישור הועתק ללוח');
        }).catch(() => {
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

// פונקציית גיבוי להעתקה
function fallbackCopyToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        alert('הקישור הועתק ללוח');
    } catch (err) {
        alert('לא ניתן להעתיק את הקישור. הקישור הוא: ' + text);
    }
    
    document.body.removeChild(textArea);
}