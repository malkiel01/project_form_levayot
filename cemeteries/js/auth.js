// js/auth.js
const Auth = {
    checkSession() {
        // Check if user is logged in
        $.ajax({
            url: 'api/check-auth.php',
            method: 'GET',
            async: false,
            success: (response) => {
                if (!response.logged_in) {
                    window.location.href = '../login.php';
                } else if (response.permission_level < 4 && !response.has_cemetery_access) {
                    this.showNoPermission();
                }
            },
            error: () => {
                window.location.href = '../login.php';
            }
        });
    },
    
    showNoPermission() {
        $('body').html(`
            <div class="container mt-5">
                <div class="alert alert-danger text-center">
                    <h4>אין לך הרשאה לגשת למערכת זו</h4>
                    <p>פנה למנהל המערכת לקבלת הרשאות</p>
                    <a href="../index.php" class="btn btn-primary">חזרה לדף הבית</a>
                </div>
            </div>
        `);
    }
};