// form/js/form-validation.js - ולידציה ובדיקת שדות
$(document).ready(function() {
    // פונקציה לבדיקת שלמות הטופס
    function checkFormCompleteness() {
        if (formConfig.isViewOnly) return true;

        let missingFields = [];
        let isComplete = true;

        // // עבור על כל שדות החובה
        // $('[data-required="true"]:not(:disabled)').each(function() {
        //     const fiel