// form/js/form-signature.js - חתימה דיגיטלית

// חתימה דיגיטלית - רק אם לא במצב צפייה
if (!formConfig.isViewOnly) {
    const canvas = document.getElementById('signaturePad');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        
        // התאמת גודל הקנבס
        function resizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width;
            canvas.height = 200;
            
            // טען מחדש חתימה קיימת אם יש
            loadExistingSignature();
        }
        
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        
        // אירועי ציור
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        
        // תמיכה במכשירי מגע
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousedown', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });
        
        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousemove', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });
        
        canvas.addEventListener('touchend', function(e) {
            e.preventDefault();
            const mouseEvent = new MouseEvent('mouseup', {});
            canvas.dispatchEvent(mouseEvent);
        });
        
        function startDrawing(e) {
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            ctx.beginPath();
            ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        }
        
        function draw(e) {
            if (!isDrawing) return;
            const rect = canvas.getBoundingClientRect();
            ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            ctx.stroke();
        }
        
        function stopDrawing() {
            if (isDrawing) {
                isDrawing = false;
                // שמירת החתימה כ-base64
                document.getElementById('client_signature').value = canvas.toDataURL();
            }
        }
        
        window.clearSignature = function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById('client_signature').value = '';
        };
        
        // טעינת חתימה קיימת
        function loadExistingSignature() {
            const existingSignature = document.getElementById('client_signature').value;
            if (existingSignature && existingSignature.startsWith('data:image')) {
                const img = new Image();
                img.onload = function() {
                    ctx.drawImage(img, 0, 0);
                };
                img.src = existingSignature;
            }
        }
        
        // טען חתימה קיימת בטעינה ראשונה
        loadExistingSignature();
    }
}