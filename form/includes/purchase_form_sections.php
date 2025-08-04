<?php
// includes/purchase_form_sections.php - סעיפי טופס רכישות

/**
 * רנדור סעיף פרטי הרוכש
 */
function renderPurchaserSection($formData, $form, $requiredFields, $errors, $viewOnly) {
    ?>
    <div class="form-section">
        <h3 class="section-title">
            <i class="fas fa-user"></i>
            פרטי הרוכש
        </h3>
        
        <div class="form-grid">
            <div class="form-group">
                <label for="purchaser_first_name" class="<?= in_array('purchaser_first_name', $requiredFields) ? 'required' : '' ?>">
                    שם פרטי
                </label>
                <input type="text" 
                       id="purchaser_first_name" 
                       name="purchaser_first_name" 
                       value="<?= htmlspecialchars($formData['purchaser_first_name'] ?? '') ?>"
                       class="form-control <?= isset($errors['purchaser_first_name']) ? 'is-invalid' : '' ?>"
                       <?= $viewOnly ? 'disabled' : '' ?>>
                <?php if (isset($errors['purchaser_first_name'])): ?>
                    <div class="invalid-feedback"><?= $errors['purchaser_first_name'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="purchaser_last_name" class="<?= in_array('purchaser_last_name', $requiredFields) ? 'required' : '' ?>">
                    שם משפחה
                </label>
                <input type="text" 
                       id="purchaser_last_name" 
                       name="purchaser_last_name" 
                       value="<?= htmlspecialchars($formData['purchaser_last_name'] ?? '') ?>"
                       class="form-control <?= isset($errors['purchaser_last_name']) ? 'is-invalid' : '' ?>"
                       <?= $viewOnly ? 'disabled' : '' ?>>
                <?php if (isset($errors['purchaser_last_name'])): ?>
                    <div class="invalid-feedback"><?= $errors['purchaser_last_name'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="purchaser_id" class="<?= in_array('purchaser_id', $requiredFields) ? 'required' : '' ?>">
                    תעודת זהות
                </label>
                <input type="text" 
                       id="purchaser_id" 
                       name="purchaser_id" 
                       value="<?= htmlspecialchars($formData['purchaser_id'] ?? '') ?>"
                       class="form-control <?= isset($errors['purchaser_id']) ? 'is-invalid' : '' ?>"
                       maxlength="9"
                       pattern="[0-9]{9}"
                       <?= $viewOnly ? 'disabled' : '' ?>>
                <?php if (isset($errors['purchaser_id'])): ?>
                    <div class="invalid-feedback"><?= $errors['purchaser_id'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="purchaser_phone" class="<?= in_array('purchaser_phone', $requiredFields) ? 'required' : '' ?>">
                    טלפון
                </label>
                <input type="tel" 
                       id="purchaser_phone" 
                       name="purchaser_phone" 
                       value="<?= htmlspecialchars($formData['purchaser_phone'] ?? '') ?>"
                       class="form-control <?= isset($errors['purchaser_phone']) ? 'is-invalid' : '' ?>"
                       <?= $viewOnly ? 'disabled' : '' ?>>
                <?php if (isset($errors['purchaser_phone'])): ?>
                    <div class="invalid-feedback"><?= $errors['purchaser_phone'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="purchaser_email">אימייל</label>
                <input type="email" 
                       id="purchaser_email" 
                       name="purchaser_email" 
                       value="<?= htmlspecialchars($formData['purchaser_email'] ?? '') ?>"
                       class="form-control <?= isset($errors['purchaser_email']) ? 'is-invalid' : '' ?>"
                       <?= $viewOnly ? 'disabled' : '' ?>>
                <?php if (isset($errors['purchaser_email'])): ?>
                    <div class="invalid-feedback"><?= $errors['purchaser_email'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group col-span-2">
                <label for="purchaser_address">כתובת מגורים</label>
                <input type="text" 
                       id="purchaser_address" 
                       name="purchaser_address" 
                       value="<?= htmlspecialchars($formData['purchaser_address'] ?? '') ?>"
                       class="form-control"
                       <?= $viewOnly ? 'disabled' : '' ?>>
            </div>
        </div>
    </div>
    <?php
}

/**
 * רנדור סעיף פרטי הרכישה
 */
function renderPurchaseDetailsSection($formData, $form, $purchaseTypes, $requiredFields, $errors, $viewOnly) {
    ?>
    <div class="form-section">
        <h3 class="section-title">
            <i class="fas fa-file-contract"></i>
            פרטי הרכישה
        </h3>
        
        <div class="form-grid">
            <div class="form-group">
                <label for="purchase_date" class="<?= in_array('purchase_date', $requiredFields) ? 'required' : '' ?>">
                    תאריך רכישה
                </label>
                <input type="date" 
                       id="purchase_date" 
                       name="purchase_date" 
                       value="<?= htmlspecialchars($formData['purchase_date'] ?? '') ?>"
                       class="form-control <?= isset($errors['purchase_date']) ? 'is-invalid' : '' ?>"
                       <?= $viewOnly ? 'disabled' : '' ?>>
                <?php if (isset($errors['purchase_date'])): ?>
                    <div class="invalid-feedback"><?= $errors['purchase_date'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="purchase_type" class="<?= in_array('purchase_type', $requiredFields) ? 'required' : '' ?>">
                    סוג רכישה
                </label>
                <select id="purchase_type" 
                        name="purchase_type" 
                        class="form-control <?= isset($errors['purchase_type']) ? 'is-invalid' : '' ?>"
                        <?= $viewOnly ? 'disabled' : '' ?>>
                    <option value="">בחר סוג רכישה</option>
                    <?php foreach ($purchaseTypes as $key => $value): ?>
                        <option value="<?= $key ?>" <?= ($formData['purchase_type'] ?? '') == $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($value) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['purchase_type'])): ?>
                    <div class="invalid-feedback"><?= $errors['purchase_type'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="contract_number">מספר חוזה</label>
                <input type="text" 
                       id="contract_number" 
                       name="contract_number" 
                       value="<?= htmlspecialchars($formData['contract_number'] ?? '') ?>"
                       class="form-control"
                       <?= $viewOnly ? 'disabled' : '' ?>>
            </div>
            
            <div class="form-group">
                <label for="purchase_price">מחיר רכישה (₪)</label>
                <input type="number" 
                       id="purchase_price" 
                       name="purchase_price" 
                       value="<?= htmlspecialchars($formData['purchase_price'] ?? '') ?>"
                       class="form-control <?= isset($errors['purchase_price']) ? 'is-invalid' : '' ?>"
                       min="0"
                       step="0.01"
                       <?= $viewOnly ? 'disabled' : '' ?>>
                <?php if (isset($errors['purchase_price'])): ?>
                    <div class="invalid-feedback"><?= $errors['purchase_price'] ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * רנדור סעיף פרטי החלקה
 */
function renderPlotSection($formData, $form, $cemeteries, $blocks, $sections, $rows, $graves, $plots, $viewOnly) {
    ?>
    <div class="form-section">
        <h3 class="section-title">
            <i class="fas fa-map-marker-alt"></i>
            פרטי החלקה
        </h3>
        
        <div class="form-grid">
            <div class="form-group">
                <label for="cemetery_id" class="required">בית עלמין</label>
                <select id="cemetery_id" 
                        name="cemetery_id" 
                        class="form-control"
                        <?= $viewOnly ? 'disabled' : '' ?>>
                    <option value="">בחר בית עלמין</option>
                    <?php foreach ($cemeteries as $cemetery): ?>
                        <option value="<?= $cemetery['id'] ?>" 
                                <?= ($formData['cemetery_id'] ?? '') == $cemetery['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cemetery['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="block_id" class="required">גוש</label>
                <select id="block_id" 
                        name="block_id" 
                        class="form-control"
                        <?= $viewOnly ? 'disabled' : '' ?>
                        <?= empty($formData['cemetery_id']) ? 'disabled' : '' ?>>
                    <option value="">בחר גוש</option>
                    <?php if (!empty($blocks)): ?>
                        <?php foreach ($blocks as $block): ?>
                            <option value="<?= $block['id'] ?>" 
                                    <?= ($formData['block_id'] ?? '') == $block['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($block['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="section_id">חלקה</label>
                <select id="section_id" 
                        name="section_id" 
                        class="form-control"
                        <?= $viewOnly ? 'disabled' : '' ?>
                        <?= empty($formData['block_id']) ? 'disabled' : '' ?>>
                    <option value="">בחר חלקה</option>
                    <?php if (!empty($sections)): ?>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= $section['id'] ?>" 
                                    <?= ($formData['section_id'] ?? '') == $section['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($section['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="row_id">שורה</label>
                <select id="row_id" 
                        name="row_id" 
                        class="form-control"
                        <?= $viewOnly ? 'disabled' : '' ?>
                        <?= empty($formData['section_id']) ? 'disabled' : '' ?>>
                    <option value="">בחר שורה</option>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <option value="<?= $row['id'] ?>" 
                                    <?= ($formData['row_id'] ?? '') == $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['row_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="grave_id">קבר</label>
                <select id="grave_id" 
                        name="grave_id" 
                        class="form-control"
                        <?= $viewOnly ? 'disabled' : '' ?>
                        <?= empty($formData['row_id']) ? 'disabled' : '' ?>>
                    <option value="">בחר קבר</option>
                    <?php if (!empty($graves)): ?>
                        <?php foreach ($graves as $grave): ?>
                            <option value="<?= $grave['id'] ?>" 
                                    <?= ($formData['grave_id'] ?? '') == $grave['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($grave['grave_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="plot_id">חלקת קבר</label>
                <select id="plot_id" 
                        name="plot_id" 
                        class="form-control"
                        <?= $viewOnly ? 'disabled' : '' ?>
                        <?= empty($formData['grave_id']) ? 'disabled' : '' ?>>
                    <option value="">בחר חלקת קבר</option>
                    <?php if (!empty($plots)): ?>
                        <?php foreach ($plots as $plot): ?>
                            <option value="<?= $plot['id'] ?>" 
                                    <?= ($formData['plot_id'] ?? '') == $plot['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($plot['plot_number']) ?> 
                                (<?= $plot['status'] == 'available' ? 'פנוי' : 'תפוס' ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        
        <!-- מפת החלקה -->
        <div class="plot-map-container" id="plotMapContainer" style="display: none;">
            <h4>מיקום החלקה במפה</h4>
            <div id="plotMap" class="plot-map"></div>
        </div>
    </div>
    <?php
}

/**
 * רנדור סעיף פרטי תשלום
 */
function renderPaymentSection($formData, $form, $paymentMethods, $requiredFields, $errors, $viewOnly) {
    ?>
    <div class="form-section">
        <h3 class="section-title">
            <i class="fas fa-credit-card"></i>
            פרטי תשלום
        </h3>
        
        <div class="form-grid">
            <div class="form-group">
                <label for="payment_method" class="<?= in_array('payment_method', $requiredFields) ? 'required' : '' ?>">
                    אמצעי תשלום
                </label>
                <select id="payment_method" 
                        name="payment_method" 
                        class="form-control <?= isset($errors['payment_method']) ? 'is-invalid' : '' ?>"
                        <?= $viewOnly ? 'disabled' : '' ?>>
                    <option value="">בחר אמצעי תשלום</option>
                    <?php foreach ($paymentMethods as $key => $value): ?>
                        <option value="<?= $key ?>" <?= ($formData['payment_method'] ?? '') == $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($value) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['payment_method'])): ?>
                    <div class="invalid-feedback"><?= $errors['payment_method'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="payment_amount" class="<?= in_array('payment_amount', $requiredFields) ? 'required' : '' ?>">
                    סכום ששולם (₪)
                </label>
                <input type="number" 
                       id="payment_amount" 
                       name="payment_amount" 
                       value="<?= htmlspecialchars($formData['payment_amount'] ?? '') ?>"
                       class="form-control <?= isset($errors['payment_amount']) ? 'is-invalid' : '' ?>"
                       min="0"
                       step="0.01"
                       <?= $viewOnly ? 'disabled' : '' ?>>
                <?php if (isset($errors['payment_amount'])): ?>
                    <div class="invalid-feedback"><?= $errors['payment_amount'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="payment_date">תאריך תשלום</label>
                <input type="date" 
                       id="payment_date" 
                       name="payment_date" 
                       value="<?= htmlspecialchars($formData['payment_date'] ?? date('Y-m-d')) ?>"
                       class="form-control"
                       <?= $viewOnly ? 'disabled' : '' ?>>
            </div>
            
            <div class="form-group">
                <label for="remaining_balance">יתרה לתשלום (₪)</label>
                <input type="number" 
                       id="remaining_balance" 
                       name="remaining_balance" 
                       value="<?= htmlspecialchars($formData['remaining_balance'] ?? '') ?>"
                       class="form-control"
                       min="0"
                       step="0.01"
                       readonly>
            </div>
            
            <div class="form-group" id="installmentsGroup" style="<?= ($formData['payment_method'] ?? '') == 'installments' ? '' : 'display: none;' ?>">
                <label for="installments">מספר תשלומים</label>
                <input type="number" 
                       id="installments" 
                       name="installments" 
                       value="<?= htmlspecialchars($formData['installments'] ?? '') ?>"
                       class="form-control"
                       min="1"
                       max="36"
                       <?= $viewOnly ? 'disabled' : '' ?>>
            </div>
        </div>
        
        <!-- סיכום תשלומים -->
        <div class="payment-summary">
            <h4>סיכום תשלומים</h4>
            <table class="table">
                <tr>
                    <td>מחיר רכישה:</td>
                    <td class="text-left">₪<span id="summaryPurchasePrice">0</span></td>
                </tr>
                <tr>
                    <td>סכום ששולם:</td>
                    <td class="text-left">₪<span id="summaryPaidAmount">0</span></td>
                </tr>
                <tr class="total-row">
                    <td>יתרה לתשלום:</td>
                    <td class="text-left">₪<span id="summaryRemainingBalance">0</span></td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}

/**
 * רנדור סעיף הנהנים/זכאים
 */
function renderBeneficiariesSection($formData, $form, $requiredFields, $errors, $viewOnly) {
    $beneficiaries = $formData['beneficiaries'] ?? [['name' => '', 'id_number' => '', 'relation' => '']];
    ?>
    <div class="form-section">
        <h3 class="section-title">
            <i class="fas fa-users"></i>
            הנהנים/זכאים לשימוש בחלקה
        </h3>
        
        <div id="beneficiariesContainer">
            <?php foreach ($beneficiaries as $index => $beneficiary): ?>
                <div class="beneficiary-row" data-index="<?= $index ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>שם מלא</label>
                            <input type="text" 
                                   name="beneficiaries[<?= $index ?>][name]" 
                                   value="<?= htmlspecialchars($beneficiary['name'] ?? '') ?>"
                                   class="form-control"
                                   <?= $viewOnly ? 'disabled' : '' ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>תעודת זהות</label>
                            <input type="text" 
                                   name="beneficiaries[<?= $index ?>][id_number]" 
                                   value="<?= htmlspecialchars($beneficiary['id_number'] ?? '') ?>"
                                   class="form-control"
                                   maxlength="9"
                                   <?= $viewOnly ? 'disabled' : '' ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>קרבה לרוכש</label>
                            <input type="text" 
                                   name="beneficiaries[<?= $index ?>][relation]" 
                                   value="<?= htmlspecialchars($beneficiary['relation'] ?? '') ?>"
                                   class="form-control"
                                   <?= $viewOnly ? 'disabled' : '' ?>>
                        </div>
                        
                        <?php if (!$viewOnly && $index > 0): ?>
                            <div class="form-group">
                                <button type="button" class="btn btn-danger btn-sm remove-beneficiary" data-index="<?= $index ?>">
                                    <i class="fas fa-trash"></i> הסר
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!$viewOnly): ?>
            <button type="button" id="addBeneficiary" class="btn btn-secondary btn-sm">
                <i class="fas fa-plus"></i> הוסף הנהנה
            </button>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * רנדור סעיף הערות והתניות
 */
function renderNotesSection($formData, $form, $viewOnly) {
    ?>
    <div class="form-section">
        <h3 class="section-title">
            <i class="fas fa-sticky-note"></i>
            הערות והתניות מיוחדות
        </h3>
        
        <div class="form-group">
            <label for="notes">הערות כלליות</label>
            <textarea id="notes" 
                      name="notes" 
                      class="form-control" 
                      rows="3"
                      <?= $viewOnly ? 'disabled' : '' ?>><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="special_conditions">תנאים מיוחדים</label>
            <textarea id="special_conditions" 
                      name="special_conditions" 
                      class="form-control" 
                      rows="3"
                      placeholder="למשל: זכות שימוש מוגבלת, התניות מיוחדות, הסכמים נוספים..."
                      <?= $viewOnly ? 'disabled' : '' ?>><?= htmlspecialchars($formData['special_conditions'] ?? '') ?></textarea>
        </div>
    </div>
    <?php
}

/**
 * רנדור סעיף חתימה
 */
function renderPurchaseSignatureSection($formData, $viewOnly) {
    ?>
    <div class="form-section">
        <h3 class="section-title">
            <i class="fas fa-signature"></i>
            חתימה דיגיטלית
        </h3>
        
        <div class="signature-container">
            <?php if (!$viewOnly): ?>
                <div class="signature-pad-wrapper">
                    <canvas id="signaturePad" class="signature-pad"></canvas>
                    <div class="signature-actions">
                        <button type="button" id="clearSignature" class="btn btn-secondary btn-sm">
                            <i class="fas fa-undo"></i> נקה חתימה
                        </button>
                    </div>
                </div>
                <input type="hidden" id="signatureData" name="signature_data" value="<?= htmlspecialchars($formData['signature_data'] ?? '') ?>">
            <?php else: ?>
                <?php if (!empty($formData['signature_data'])): ?>
                    <img src="<?= htmlspecialchars($formData['signature_data']) ?>" alt="חתימה" class="signature-image">
                <?php else: ?>
                    <p class="text-muted">לא נמצאה חתימה</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="form-check mt-3">
            <input type="checkbox" 
                   id="agreementCheck" 
                   name="agreement_check" 
                   class="form-check-input"
                   <?= !empty($formData['agreement_check']) ? 'checked' : '' ?>
                   <?= $viewOnly ? 'disabled' : '' ?>>
            <label for="agreementCheck" class="form-check-label">
                אני מאשר/ת שכל הפרטים שמילאתי נכונים ומדויקים, ואני מסכים/ה לתנאי הרכישה
            </label>
        </div>
    </div>
    <?php
}

/**
 * רנדור כפתורי פעולה
 */
function renderPurchaseActionButtons($isNewForm, $formUuid, $viewOnly, $isLinkAccess) {
    ?>
    <div class="form-actions">
        <?php if (!$viewOnly): ?>
            <button type="submit" name="action" value="save" class="btn btn-primary">
                <i class="fas fa-save"></i> שמור טופס
            </button>
            
            <?php if (!$isNewForm): ?>
                <button type="submit" name="action" value="submit" class="btn btn-success">
                    <i class="fas fa-paper-plane"></i> שלח לאישור
                </button>
            <?php endif; ?>
            
            <button type="button" id="previewForm" class="btn btn-info">
                <i class="fas fa-eye"></i> תצוגה מקדימה
            </button>
        <?php endif; ?>
        
        <?php if (!$isNewForm && !$isLinkAccess): ?>
            <a href="purchase_form_pdf.php?uuid=<?= $formUuid ?>" class="btn btn-secondary" target="_blank">
                <i class="fas fa-file-pdf"></i> הורד PDF
            </a>
            
            <?php if (!$viewOnly): ?>
                <button type="button" id="shareForm" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#shareModal">
                    <i class="fas fa-share-alt"></i> שתף טופס
                </button>
            <?php endif; ?>
        <?php endif; ?>
        
        <a href="../purchases.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> חזור לרשימת רכישות
        </a>
    </div>
    <?php
}

/**
 * רנדור מודלים לשיתוף
 */
function renderPurchaseShareModals() {
    ?>
    <!-- מודל שיתוף -->
    <div class="modal fade" id="shareModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">שיתוף טופס רכישה</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>סוג הרשאה</label>
                        <select id="sharePermission" class="form-control">
                            <option value="view">צפייה בלבד</option>
                            <option value="edit">עריכה</option>
                        </select>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label>קישור לשיתוף</label>
                        <div class="input-group">
                            <input type="text" id="shareLink" class="form-control" readonly>
                            <button type="button" id="copyShareLink" class="btn btn-primary">
                                <i class="fas fa-copy"></i> העתק
                            </button>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        הקישור יהיה פעיל למשך 30 יום
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}