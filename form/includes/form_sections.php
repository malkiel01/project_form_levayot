<?php
// form/includes/form_sections.php - סקציות הטופס

function renderDeceasedSection($formData, $form, $requiredFields, $errors = [], $viewOnly = false) {
    ?>
    <div class="section-title">פרטי הנפטר</div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="identification_type" class="form-label <?= in_array('identification_type', $requiredFields) ? 'required' : '' ?>">
                סוג זיהוי
            </label>
            <select class="form-select <?= isset($errors['identification_type']) ? 'is-invalid' : '' ?>" 
                    id="identification_type" name="identification_type" 
                    data-required="<?= in_array('identification_type', $requiredFields) ? 'true' : 'false' ?>"
                    <?= (!$form || !$form->canEditField('identification_type') || $viewOnly) ? 'disabled' : '' ?>>
                <option value="">בחר...</option>
                <option value="tz" <?= ($formData['identification_type'] ?? '') === 'tz' ? 'selected' : '' ?>>תעודת זהות</option>
                <option value="passport" <?= ($formData['identification_type'] ?? '') === 'passport' ? 'selected' : '' ?>>דרכון</option>
                <option value="anonymous" <?= ($formData['identification_type'] ?? '') === 'anonymous' ? 'selected' : '' ?>>אלמוני</option>
                <option value="baby" <?= ($formData['identification_type'] ?? '') === 'baby' ? 'selected' : '' ?>>תינוק</option>
            </select>
        </div>
        
        <div class="col-md-6" id="identificationNumberDiv">
            <label for="identification_number" class="form-label">מספר זיהוי</label>
            <input type="text" class="form-control <?= isset($errors['identification_number']) ? 'is-invalid' : '' ?>" 
                   id="identification_number" name="identification_number" 
                   value="<?= $formData['identification_number'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('identification_number') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="deceased_name" class="form-label <?= in_array('deceased_name', $requiredFields) ? 'required' : '' ?>">
                שם הנפטר
            </label>
            <input type="text" class="form-control" 
                   id="deceased_name" name="deceased_name" 
                   data-required="<?= in_array('deceased_name', $requiredFields) ? 'true' : 'false' ?>"
                   value="<?= $formData['deceased_name'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('deceased_name') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
        
        <div class="col-md-4">
            <label for="father_name" class="form-label">שם האב</label>
            <input type="text" class="form-control" id="father_name" name="father_name" 
                   value="<?= $formData['father_name'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('father_name') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
        
        <div class="col-md-4">
            <label for="mother_name" class="form-label">שם האם</label>
            <input type="text" class="form-control" id="mother_name" name="mother_name" 
                   value="<?= $formData['mother_name'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('mother_name') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6" id="birthDateDiv">
            <label for="birth_date" class="form-label">תאריך לידה</label>
            <input type="date" class="form-control" 
                   id="birth_date" name="birth_date" 
                   value="<?= $formData['birth_date'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('birth_date') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
    </div>
    <?php
}

function renderDeathSection($formData, $form, $requiredFields, $errors = [], $viewOnly = false) {
    ?>
    <div class="section-title">פרטי הפטירה</div>
    <div class="row mb-3">
        <div class="col-md-3">
            <label for="death_date" class="form-label <?= in_array('death_date', $requiredFields) ? 'required' : '' ?>">
                תאריך פטירה
            </label>
            <input type="date" class="form-control" 
                   id="death_date" name="death_date" 
                   data-required="<?= in_array('death_date', $requiredFields) ? 'true' : 'false' ?>"
                   value="<?= $formData['death_date'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('death_date') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
        
        <div class="col-md-3">
            <label for="death_time" class="form-label <?= in_array('death_time', $requiredFields) ? 'required' : '' ?>">
                שעת פטירה
            </label>
            <input type="time" class="form-control" 
                   id="death_time" name="death_time" 
                   data-required="<?= in_array('death_time', $requiredFields) ? 'true' : 'false' ?>"
                   value="<?= $formData['death_time'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('death_time') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
        
        <div class="col-md-3">
            <label for="burial_date" class="form-label <?= in_array('burial_date', $requiredFields) ? 'required' : '' ?>">
                תאריך קבורה
            </label>
            <input type="date" class="form-control" 
                   id="burial_date" name="burial_date" 
                   data-required="<?= in_array('burial_date', $requiredFields) ? 'true' : 'false' ?>"
                   value="<?= $formData['burial_date'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('burial_date') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
        
        <div class="col-md-3">
            <label for="burial_time" class="form-label <?= in_array('burial_time', $requiredFields) ? 'required' : '' ?>">
                שעת קבורה
            </label>
            <input type="time" class="form-control" 
                   id="burial_time" name="burial_time" 
                   data-required="<?= in_array('burial_time', $requiredFields) ? 'true' : 'false' ?>"
                   value="<?= $formData['burial_time'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('burial_time') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="burial_license" class="form-label <?= in_array('burial_license', $requiredFields) ? 'required' : '' ?>">
                רשיון קבורה
            </label>
            <input type="text" class="form-control" 
                   id="burial_license" name="burial_license" 
                   data-required="<?= in_array('burial_license', $requiredFields) ? 'true' : 'false' ?>"
                   value="<?= $formData['burial_license'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('burial_license') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
        
        <div class="col-md-6">
            <label for="death_location" class="form-label">מקום הפטירה</label>
            <input type="text" class="form-control" id="death_location" name="death_location" 
                   value="<?= $formData['death_location'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('death_location') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
    </div>
    <?php
}

function renderCemeterySection($formData, $form, $cemeteries, $blocks, $sections, $rows, $graves, $plots, $viewOnly = false) {
    if (!$form || !$form->canViewField('cemetery_id')) return;
    ?>
    <div class="section-title">מקום הקבורה</div>
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="cemetery_id" class="form-label">בית עלמין</label>
            <select class="form-select" id="cemetery_id" name="cemetery_id" 
                    <?= (!$form->canEditField('cemetery_id') || $viewOnly) ? 'disabled' : '' ?>>
                <option value="">בחר...</option>
                <?php foreach ($cemeteries as $cemetery): ?>
                    <option value="<?= $cemetery['id'] ?>" 
                            <?= ($formData['cemetery_id'] ?? '') == $cemetery['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cemetery['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-4">
            <label for="block_id" class="form-label">גוש</label>
            <select class="form-select" id="block_id" name="block_id" 
                    <?= (!$form->canEditField('block_id') || $viewOnly) ? 'disabled' : '' ?>>
                <option value="">בחר קודם בית עלמין</option>
                <?php foreach ($blocks as $block): ?>
                    <option value="<?= $block['id'] ?>" 
                            <?= ($formData['block_id'] ?? '') == $block['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($block['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-4">
            <label for="section_id" class="form-label">חלקה</label>
            <select class="form-select" id="section_id" name="section_id" 
                    <?= (!$form->canEditField('section_id') || $viewOnly) ? 'disabled' : '' ?>>
                <option value="">בחר קודם גוש</option>
                <?php foreach ($sections as $section): ?>
                    <option value="<?= $section['id'] ?>" 
                            <?= ($formData['section_id'] ?? '') == $section['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($section['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="row_id" class="form-label">שורה</label>
            <select class="form-select" id="row_id" name="row_id" 
                    <?= (!$form->canEditField('row_id') || $viewOnly) ? 'disabled' : '' ?>>
                <option value="">בחר קודם חלקה</option>
                <?php foreach ($rows as $row): ?>
                    <option value="<?= $row['id'] ?>" 
                            <?= ($formData['row_id'] ?? '') == $row['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-4">
            <label for="grave_id" class="form-label">קבר</label>
            <select class="form-select" id="grave_id" name="grave_id" 
                    <?= (!$form->canEditField('grave_id') || $viewOnly) ? 'disabled' : '' ?>>
                <option value="">בחר קודם שורה</option>
                <?php foreach ($graves as $grave): ?>
                    <option value="<?= $grave['id'] ?>" 
                            <?= ($formData['grave_id'] ?? '') == $grave['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($grave['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-4">
            <label for="plot_id" class="form-label">אחוזת קבר</label>
            <select class="form-select" id="plot_id" name="plot_id" 
                    <?= (!$form->canEditField('plot_id') || $viewOnly) ? 'disabled' : '' ?>>
                <option value="">בחר...</option>
                <?php foreach ($plots as $plot): ?>
                    <option value="<?= $plot['id'] ?>" 
                            <?= ($formData['plot_id'] ?? '') == $plot['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($plot['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php
}

function renderInformantSection($formData, $form, $requiredFields, $errors = [], $viewOnly = false) {
    ?>
    <div class="section-title">פרטי המודיע</div>
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="informant_name" class="form-label">שם המודיע</label>
            <input type="text" class="form-control" id="informant_name" name="informant_name" 
                   value="<?= $formData['informant_name'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('informant_name') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
        
        <div class="col-md-4">
            <label for="informant_phone" class="form-label">טלפון</label>
            <input type="tel" class="form-control" id="informant_phone" name="informant_phone" 
                   value="<?= $formData['informant_phone'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('informant_phone') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
        
        <div class="col-md-4">
            <label for="informant_relationship" class="form-label">קרבה משפחתית</label>
            <input type="text" class="form-control" id="informant_relationship" name="informant_relationship" 
                   value="<?= $formData['informant_relationship'] ?? '' ?>"
                   <?= (!$form || !$form->canEditField('informant_relationship') || $viewOnly) ? 'disabled' : '' ?>>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-12">
            <label for="notes" class="form-label">הערות</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"
                      <?= (!$form || !$form->canEditField('notes') || $viewOnly) ? 'disabled' : '' ?>><?= $formData['notes'] ?? '' ?></textarea>
        </div>
    </div>
    <?php
}

function renderSignatureSection($formData, $viewOnly = false) {
    ?>
    <div class="section-title">חתימת לקוח</div>
    <div class="row mb-3">
        <div class="col-12">
            <?php if ($viewOnly): ?>
                <!-- הצגת חתימה קיימת בלבד -->
                <?php if (!empty($formData['client_signature'])): ?>
                    <div class="signature-display">
                        <img src="<?= htmlspecialchars($formData['client_signature']) ?>" alt="חתימת לקוח" style="max-width: 100%; max-height: 180px;">
                    </div>
                <?php else: ?>
                    <div class="text-muted text-center" style="padding: 50px; border: 1px dashed #ddd;">
                        אין חתימה
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- לוח חתימה -->
                <canvas id="signaturePad" class="signature-pad"></canvas>
                <input type="hidden" id="client_signature" name="client_signature" 
                       value="<?= $formData['client_signature'] ?? '' ?>">
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="clearSignature()">
                        <i class="fas fa-eraser"></i> נקה חתימה
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function renderActionButtons($isNewForm, $formUuid, $viewOnly, $isLinkAccess) {
    // בדיקת הרשאות לשיתוף
    $canShare = false;
    if (isset($_SESSION['user_id']) && isset($_SESSION['permission_level'])) {
        // רק משתמשים רשומים עם הרשאת מנהל (4) או עורך (3) יכולים לשתף
        $canShare = $_SESSION['permission_level'] >= 3;
    }
    
    ?>
    <!-- כפתורי פעולה מעוצבים -->
    <div class="form-actions">
        <div class="action-buttons-container">
            <?php if (!$viewOnly): ?>
                <?php if ($isNewForm): ?>
                    <!-- כפתורים לטופס חדש -->
                    <div class="mobile-primary-group">
                        <button type="submit" name="save" class="btn action-btn primary">
                            <i class="fas fa-save"></i>
                            <span class="btn-text">צור טופס</span>
                        </button>
                        <button type="submit" name="save_and_view" value="1" class="btn action-btn secondary">
                            <i class="fas fa-eye"></i>
                            <span class="btn-text">צור וצפה</span>
                        </button>
                    </div>
                <?php else: ?>
                    <!-- כפתורים לטופס קיים -->
                    <div class="mobile-primary-group">
                        <button type="submit" name="save" class="btn action-btn primary">
                            <i class="fas fa-save"></i>
                            <span class="btn-text">שמור שינויים</span>
                        </button>
                        <button type="submit" name="save_and_view" value="1" class="btn action-btn secondary">
                            <i class="fas fa-eye"></i>
                            <span class="btn-text">שמור וצפה</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (!$isNewForm && $canShare): ?>
                    <div class="actions-divider"></div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!$isNewForm && $canShare): ?>
                <!-- קבוצת שיתוף - רק למשתמשים מורשים -->
                <div class="btn-group-custom">
                    <button type="button" class="btn action-btn share" 
                            onclick="shareForm()"
                            data-bs-toggle="tooltip" 
                            data-bs-placement="top" 
                            title="שתף טופס">
                        <i class="fas fa-share-alt"></i>
                        <span class="btn-text">שתף טופס</span>
                    </button>
                    <button type="button" class="btn action-btn share" 
                            onclick="quickShareForm()"
                            data-bs-toggle="tooltip" 
                            data-bs-placement="top" 
                            title="שיתוף מהיר">
                        <i class="fas fa-link"></i>
                        <span class="btn-text">שיתוף מהיר</span>
                    </button>
                </div>
                
                <div class="actions-divider"></div>
            <?php endif; ?>
            
            <!-- כפתורי ניווט -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= DECEASED_LIST_URL ?>" class="btn action-btn outline"
                   data-bs-toggle="tooltip" 
                   data-bs-placement="top" 
                   title="רשימת טפסים">
                    <i class="fas fa-list"></i>
                    <span class="btn-text">רשימת טפסים</span>
                </a>
            <?php else: ?>
                <a href="../<?= LOGIN_URL ?>" class="btn action-btn outline"
                   data-bs-toggle="tooltip" 
                   data-bs-placement="top" 
                   title="התחבר למערכת">
                    <i class="fas fa-sign-in-alt"></i>
                    <span class="btn-text">התחבר</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!$canShare && !$isNewForm && isset($_SESSION['user_id'])): ?>
    <!-- הודעה למשתמש על חוסר הרשאה -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('שיתוף טפסים מוגבל למשתמשים עם הרשאות עורך ומעלה');
        });
    </script>
    <?php endif; ?>
    <?php
}

function renderShareModals() {
    ?>
    <!-- Modal יצירת קישור שיתוף -->
    <div class="modal fade" id="shareFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">יצירת קישור שיתוף</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="shareLinkForm">
                        <!-- סוג גישה -->
                        <div class="mb-3">
                            <label class="form-label">סוג גישה</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="access_type" id="access_public" value="public" checked>
                                <label class="form-check-label" for="access_public">
                                    <i class="fas fa-globe"></i> פתוח לכולם
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="access_type" id="access_users" value="users">
                                <label class="form-check-label" for="access_users">
                                    <i class="fas fa-users"></i> משתמשים ספציפיים
                                </label>
                            </div>
                        </div>

                        <!-- בחירת משתמשים -->
                        <div class="mb-3" id="usersSelectDiv" style="display: none;">
                            <label for="allowed_users" class="form-label">בחר משתמשים מורשים</label>
                            <select class="form-select" id="allowed_users" name="allowed_users[]" multiple size="5">
                                <!-- יטען באמצעות AJAX -->
                            </select>
                            <small class="form-text text-muted">החזק Ctrl לבחירה מרובה</small>
                        </div>

                        <!-- הרשאות -->
                        <div class="mb-3">
                            <label class="form-label">הרשאות</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="permission_mode" id="view_only" value="view" checked>
                                <label class="form-check-label" for="view_only">
                                    <i class="fas fa-eye"></i> צפייה בלבד
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="permission_mode" id="can_edit" value="edit">
                                <label class="form-check-label" for="can_edit">
                                    <i class="fas fa-edit"></i> צפייה ועריכה
                                </label>
                            </div>
                        </div>

                        <!-- רמת הרשאה -->
                        <div class="mb-3">
                            <label for="permission_level" class="form-label">רמת הרשאה למשתמשים לא רשומים</label>
                            <select class="form-select" id="permission_level" name="permission_level">
                                <option value="1">צופה (רמה 1)</option>
                                <option value="2">עורך בסיסי (רמה 2)</option>
                                <option value="3">עורך מתקדם (רמה 3)</option>
                                <option value="4" selected>מנהל (רמה 4)</option>
                            </select>
                        </div>

                        <!-- תוקף -->
                        <div class="mb-3">
                            <label class="form-label">תוקף הקישור</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="expiry_type" id="no_expiry" value="never" checked>
                                <label class="form-check-label" for="no_expiry">
                                    <i class="fas fa-infinity"></i> ללא הגבלת זמן
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="expiry_type" id="custom_expiry" value="custom">
                                <label class="form-check-label" for="custom_expiry">
                                    <i class="fas fa-clock"></i> הגדרת תוקף
                                </label>
                            </div>
                        </div>

                        <!-- בחירת תאריך תפוגה -->
                        <div class="mb-3" id="expiryDateDiv" style="display: none;">
                            <label for="expiry_date" class="form-label">תאריך תפוגה</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                                </div>
                                <div class="col-md-6">
                                    <input type="time" class="form-control" id="expiry_time" name="expiry_time" value="23:59">
                                </div>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setExpiryDays(1)">מחר</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setExpiryDays(7)">שבוע</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setExpiryDays(30)">חודש</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setExpiryDays(90)">3 חודשים</button>
                            </div>
                        </div>

                        <!-- הערות -->
                        <div class="mb-3">
                            <label for="link_description" class="form-label">תיאור/הערות (אופציונלי)</label>
                            <input type="text" class="form-control" id="link_description" name="link_description" 
                                placeholder="לדוגמה: קישור למשפחה">
                        </div>
                    </form>

                    <!-- הודעות -->
                    <div id="shareLinkAlert" class="alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                    <button type="button" class="btn btn-primary" onclick="createShareLink()">
                        <i class="fas fa-link"></i> צור קישור
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal הצגת קישור שנוצר -->
    <div class="modal fade" id="showLinkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">קישור השיתוף נוצר בהצלחה</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">קישור לשיתוף:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="generatedLink" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyLink()">
                                <i class="fas fa-copy"></i> העתק
                            </button>
                        </div>
                    </div>
                    <div id="linkDetails" class="alert alert-info">
                        <!-- פרטי הקישור יוצגו כאן -->
                    </div>
                    <div class="text-center">
                        <div id="qrcode"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">סגור</button>
                    <button type="button" class="btn btn-primary" onclick="shareViaWhatsApp()">
                        <i class="fab fa-whatsapp"></i> שתף בוואטסאפ
                    </button>
                    <button type="button" class="btn btn-info" onclick="shareViaEmail()">
                        <i class="fas fa-envelope"></i> שלח במייל
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
}