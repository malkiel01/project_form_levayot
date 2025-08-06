<?php
// form/includes/form_scripts.php - כל הסקריפטים של הטופס

function renderFormScripts() {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="../js/form-validation.js?v=<?= time() ?>"></script>
    <script src="../js/form-signature.js?v=<?= time() ?>"></script>
    <script src="../js/form-share.js?v=<?= time() ?>"></script>
    <script src="../js/form-fields.js?v=<?= time() ?>"></script>
    <script src="../js/form-autosave.js?v=<?= time() ?>"></script>
    <script src="../js/form-login-check.js?v=<?= time() ?>"></script>
    <script src="../js/file-manager.js?v=<?= time() ?>"></script>
    <script src="../../js/pwa-init.js?v=<?= time() ?>"></script>
    <script>
        // בדיקה שכל הסקריפטים נטענו
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Checking loaded scripts...');
            console.log('FileManager:', typeof window.FileManager);
            
            // אם FileManager לא נטען, נסה לטעון שוב
            if (typeof window.FileManager === 'undefined') {
                console.error('FileManager not loaded! Trying to load again...');
                var script = document.createElement('script');
                script.src = 'js/file-manager.js?v=' + Date.now();
                script.onload = function() {
                    console.log('FileManager reloaded:', typeof window.FileManager);
                };
                document.head.appendChild(script);
            }
        });
    </script>
    <?php
}