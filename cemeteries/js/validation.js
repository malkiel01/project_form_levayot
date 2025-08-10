// js/validation.js
const Validation = {
    // סינון קלט בסיסי
    sanitizeInput(value) {
        if (typeof value !== 'string') return value;
        
        // הסר תגיות HTML
        value = value.replace(/<[^>]*>/g, '');
        
        // הסר תווים מסוכנים
        value = value.replace(/[<>\"\']/g, '');
        
        return value.trim();
    },
    
    // בדיקת שם (חובה, אורך מקסימלי 255)
    validateName(name) {
        name = this.sanitizeInput(name);
        
        if (!name || name.length === 0) {
            return { valid: false, error: 'שם הוא שדה חובה' };
        }
        
        if (name.length > 255) {
            return { valid: false, error: 'השם ארוך מדי (מקסימום 255 תווים)' };
        }
        
        return { valid: true, value: name };
    },
    
    // בדיקת קוד (אופציונלי, רק אותיות, מספרים, מקף וקו תחתון)
    validateCode(code) {
        if (!code) return { valid: true, value: '' };
        
        code = this.sanitizeInput(code);
        
        if (!/^[a-zA-Z0-9_-]*$/.test(code)) {
            return { valid: false, error: 'קוד יכול להכיל רק אותיות באנגלית, מספרים, מקף וקו תחתון' };
        }
        
        if (code.length > 50) {
            return { valid: false, error: 'הקוד ארוך מדי (מקסימום 50 תווים)' };
        }
        
        return { valid: true, value: code };
    },
    
    // בדיקת מספר
    validateNumber(value, fieldName) {
        if (!value || value === '') {
            return { valid: false, error: `${fieldName} הוא שדה חובה` };
        }
        
        const num = parseInt(value);
        
        if (isNaN(num) || num <= 0) {
            return { valid: false, error: `${fieldName} חייב להיות מספר חיובי` };
        }
        
        return { valid: true, value: num };
    },
    
    // בדיקת כל הטופס לפני שליחה
    validateForm(type, data) {
        const errors = [];
        const cleanData = {};
        
        // בדיקת שם (חובה בכל הטפסים)
        if (data.name !== undefined) {
            const nameValidation = this.validateName(data.name);
            if (!nameValidation.valid) {
                errors.push(nameValidation.error);
            } else {
                cleanData.name = nameValidation.value;
            }
        }
        
        // בדיקת קוד (אופציונלי)
        if (data.code !== undefined) {
            const codeValidation = this.validateCode(data.code);
            if (!codeValidation.valid) {
                errors.push(codeValidation.error);
            } else {
                cleanData.code = codeValidation.value;
            }
        }
        
        // בדיקות ספציפיות לפי סוג
        switch(type) {
            case 'block':
                if (data.cemetery_id) {
                    const validation = this.validateNumber(data.cemetery_id, 'בית עלמין');
                    if (!validation.valid) {
                        errors.push(validation.error);
                    } else {
                        cleanData.cemetery_id = validation.value;
                    }
                }
                break;
                
            case 'plot':
                if (data.block_id) {
                    const validation = this.validateNumber(data.block_id, 'גוש');
                    if (!validation.valid) {
                        errors.push(validation.error);
                    } else {
                        cleanData.block_id = validation.value;
                    }
                }
                break;
                
            case 'row':
                if (data.plot_id) {
                    const validation = this.validateNumber(data.plot_id, 'חלקה');
                    if (!validation.valid) {
                        errors.push(validation.error);
                    } else {
                        cleanData.plot_id = validation.value;
                    }
                }
                break;
                
            case 'areaGrave':
                if (data.row_id) {
                    const validation = this.validateNumber(data.row_id, 'שורה');
                    if (!validation.valid) {
                        errors.push(validation.error);
                    } else {
                        cleanData.row_id = validation.value;
                    }
                }
                break;
                
            case 'grave':
                if (data.areaGrave_id) {
                    const validation = this.validateNumber(data.areaGrave_id, 'אחוזת קבר');
                    if (!validation.valid) {
                        errors.push(validation.error);
                    } else {
                        cleanData.areaGrave_id = validation.value;
                    }
                }
                
                // מספר קבר
                if (data.grave_number !== undefined) {
                    cleanData.grave_number = this.sanitizeInput(data.grave_number);
                    if (cleanData.grave_number.length > 50) {
                        errors.push('מספר קבר ארוך מדי');
                    }
                }
                break;
        }
        
        // העתק שדות בוליאניים
        if (data.is_active !== undefined) {
            cleanData.is_active = data.is_active === '1' || data.is_active === 1 ? 1 : 0;
        }
        
        if (data.is_available !== undefined) {
            cleanData.is_available = data.is_available === '1' || data.is_available === 1 ? 1 : 0;
        }
        
        return {
            valid: errors.length === 0,
            errors: errors,
            data: cleanData
        };
    },
    
    // הצגת שגיאות בטופס
    showFormErrors(errors) {
        // נקה שגיאות קודמות
        $('.form-error').remove();
        $('.is-invalid').removeClass('is-invalid');
        
        if (errors.length > 0) {
            let errorHtml = '<div class="alert alert-danger form-error"><ul class="mb-0">';
            errors.forEach(error => {
                errorHtml += `<li>${error}</li>`;
            });
            errorHtml += '</ul></div>';
            
            $('#formFields').prepend(errorHtml);
            
            // גלול לראש הטופס
            $('#editModal').animate({ scrollTop: 0 }, 'fast');
        }
    }
};