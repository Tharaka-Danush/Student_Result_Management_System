<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'functions.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin_dashboard.php');
    exit;
}
if (!empty($_SESSION['student_logged_in'])) {
    header('Location: student_result.php');
    exit;
}

$message = !empty($_SESSION['session_timeout_notice'])
    ? 'Session expired due to inactivity. Please sign in again.'
    : '';
unset($_SESSION['session_timeout_notice']);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['pin_changed'])) $message = 'Your PIN was changed. Sign in with your new PIN.';

function is_student_identifier(string $identifier): bool {
    return (bool)preg_match('/^SEU\/IS\/\d{2}\/(PS|BS)\/\d{3}$/i', $identifier);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $secret = (string)($_POST['secret'] ?? '');

    if (!verify_csrf()) {
        $message = 'Invalid request. Please try again.';
    } elseif ($identifier === '' || $secret === '') {
        $message = 'Please enter your ID or username and PIN or password.';
    } elseif (is_student_identifier($identifier)) {
        // Student account: SEU/IS/YY/(PS|BS)/NNN + 4-8 digit PIN.
        $studentId = strtoupper($identifier);

        if (maintenance_mode_enabled($conn)) {
            $message = 'The student portal is temporarily unavailable due to system maintenance.';
        } elseif (!preg_match('/^\d{4,8}$/', $secret)) {
            $message = 'Invalid Student ID or PIN.';
        } else {
            $stmt = mysqli_prepare($conn, 'SELECT student_id,pin_hash,is_active,failed_attempts,locked_until FROM students WHERE student_id=? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 's', $studentId);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            [$maxAttempts, $lockMinutes] = lockout_settings($conn);

            if (!$row) {
                $message = 'Invalid Student ID or PIN.';
            } elseif ((int)$row['is_active'] !== 1) {
                $message = 'Your student account is inactive. Please contact the faculty.';
            } elseif (!empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
                $message = 'Account temporarily locked. Please try again later.';
            } elseif (empty($row['pin_hash'])) {
                $message = 'Your student PIN has not been set yet. Please contact the administrator.';
            } elseif (!password_verify($secret, $row['pin_hash'])) {
                $attempts = (int)$row['failed_attempts'] + 1;
                if ($attempts >= $maxAttempts) {
                    $until = date('Y-m-d H:i:s', time() + ($lockMinutes * 60));
                    $up = mysqli_prepare($conn, 'UPDATE students SET failed_attempts=0,locked_until=? WHERE student_id=?');
                    mysqli_stmt_bind_param($up, 'ss', $until, $studentId);
                    mysqli_stmt_execute($up);
                    $message = 'Too many failed attempts. Account temporarily locked.';
                } else {
                    $up = mysqli_prepare($conn, 'UPDATE students SET failed_attempts=? WHERE student_id=?');
                    mysqli_stmt_bind_param($up, 'is', $attempts, $studentId);
                    mysqli_stmt_execute($up);
                    $message = 'Invalid Student ID or PIN.';
                }
            } else {
                $up = mysqli_prepare($conn, 'UPDATE students SET failed_attempts=0,locked_until=NULL WHERE student_id=?');
                mysqli_stmt_bind_param($up, 's', $studentId);
                mysqli_stmt_execute($up);

                session_regenerate_id(true);
                $_SESSION['student_logged_in'] = true;
                $_SESSION['student_id'] = $studentId;
                $_SESSION['student_auth_version'] = hash('sha256',$row['pin_hash']);
                audit_log($conn,'LOGIN','student',$studentId,'Student signed in');
                $_SESSION['student_last_activity'] = time();
                header('Location: student_result.php');
                exit;
            }
        }
    } else {
        // Admin account: any non-student identifier is treated as an admin username.
        $username = $identifier;
        $stmt = mysqli_prepare($conn, 'SELECT * FROM admin WHERE username=? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        [$maxAttempts, $lockMinutes] = lockout_settings($conn);
        $ok = false;

        if ($row && (int)($row['is_active'] ?? 1) !== 1) {
            $message = 'This administrator account is inactive.';
        } elseif ($row && !empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
            $message = 'Account temporarily locked. Please try again later.';
        } else {
            if ($row) {
                $stored = (string)$row['password'];
                $info = password_get_info($stored);
                $ok = !empty($info['algo']) && password_verify($secret, $stored);

                // Backward compatibility only: immediately upgrade any old plain-text password.
                if (!$ok && empty($info['algo']) && hash_equals($stored, $secret)) {
                    $ok = true;
                    $hash = password_hash($secret, PASSWORD_DEFAULT);
                    $up = mysqli_prepare($conn, 'UPDATE admin SET password=? WHERE username=?');
                    mysqli_stmt_bind_param($up, 'ss', $hash, $username);
                    mysqli_stmt_execute($up);
                }
            }

            if ($ok) {
                $up = mysqli_prepare($conn, 'UPDATE admin SET failed_attempts=0,locked_until=NULL WHERE username=?');
                mysqli_stmt_bind_param($up, 's', $username);
                mysqli_stmt_execute($up);

                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_role'] = $row['role'] ?? 'super_admin';
                $_SESSION['admin_last_activity'] = time();
                audit_log($conn, 'LOGIN', 'admin', $username, 'Admin login successful through unified login');
                header('Location: admin_dashboard.php');
                exit;
            }

            if ($row) {
                $attempts = (int)($row['failed_attempts'] ?? 0) + 1;
                if ($attempts >= $maxAttempts) {
                    $until = date('Y-m-d H:i:s', time() + ($lockMinutes * 60));
                    $up = mysqli_prepare($conn, 'UPDATE admin SET failed_attempts=0,locked_until=? WHERE username=?');
                    mysqli_stmt_bind_param($up, 'ss', $until, $username);
                    mysqli_stmt_execute($up);
                    $message = 'Too many failed attempts. Account temporarily locked.';
                } else {
                    $up = mysqli_prepare($conn, 'UPDATE admin SET failed_attempts=? WHERE username=?');
                    mysqli_stmt_bind_param($up, 'is', $attempts, $username);
                    mysqli_stmt_execute($up);
                    $message = 'Invalid username or password.';
                }
            } else {
                $message = 'Invalid ID/username or PIN/password.';
            }
        }
    }
    if($message!=='') audit_log($conn,'LOGIN_FAILED','authentication','', 'Sign-in attempt rejected');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Secure Login</title>
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="modern-ui.css?v=20261006-modern2">
</head>
<body class="auth-page unified-auth-page">
<div class="main-wrapper">
<?php include 'public_header.php'; ?>
<main class="auth-shell unified-auth-shell">
    <section class="auth-side unified-auth-side">
        <span class="page-kicker">Academic Portal</span>
        <h1>Welcome to your<br>academic workspace.</h1>
        <p>Use your Student ID and PIN or your administrator username and password. The system automatically recognizes your account type.</p>
        <div class="auth-points"><span>Automatic Detection</span><span>Secure Access</span><span>Role-aware Redirect</span></div>
        <div class="unified-account-guide">
            <div><strong>Student</strong><span>SEU/IS/YY/PS/000 + PIN</span></div>
            <div><strong>Staff</strong><span>Admin username + password</span></div>
        </div>
    </section>

    <section class="auth-card unified-auth-card">
        <div class="auth-card-head">
            <div class="auth-icon unified-auth-icon">↗</div>
            <div><span>Academic portal</span><h2>Secure Login</h2></div>
        </div>
        <p class="unified-login-intro">Enter your account details below. You do not need to choose Student or Admin.</p>

        <form method="post" class="auth-form" autocomplete="off" id="unifiedLoginForm">
            <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">

            <div class="login-detection" id="loginDetection" aria-live="polite">
                <span class="login-detection-dot"></span>
                <span id="loginDetectionText">Account type will be detected automatically</span>
            </div>

            <label for="login_identifier" id="identifierLabel">Student ID or Admin Username</label>
            <input type="text" name="identifier" id="login_identifier" value="" required autocomplete="off" autocapitalize="off" placeholder="Student ID or username">

            <label for="login_secret" id="secretLabel">PIN or Password</label>
            <div class="unified-secret-wrap">
                <input type="password" name="secret" id="login_secret" value="" required autocomplete="new-password" placeholder="Enter PIN or password">
                <button type="button" class="secret-toggle" id="secretToggle" aria-label="Show PIN or password">Show</button>
            </div>

            <div class="button-row unified-login-actions">
                <a href="index.php" class="btn-link btn-secondary">Back</a>
                <input type="submit" id="loginSubmit" value="Sign In">
            </div>
        </form>

        <?php if($message):?><div class="message error"><?=e($message)?></div><?php endif;?>
        <div class="unified-login-help"><span>Student account?</span> <a href="student_forgot_pin.php">Forgot PIN</a></div>
    </section>
</main>
<?php include 'footer.php'; ?>
</div>
<script>
(function(){
    var identifier=document.getElementById('login_identifier');
    var secret=document.getElementById('login_secret');
    var detection=document.getElementById('loginDetection');
    var detectionText=document.getElementById('loginDetectionText');
    var secretLabel=document.getElementById('secretLabel');
    var submit=document.getElementById('loginSubmit');
    var toggle=document.getElementById('secretToggle');
    var studentPattern=/^SEU\/IS\/\d{2}\/(PS|BS)\/\d{3}$/i;

    function clearFields(){
        if(identifier) identifier.value='';
        if(secret) secret.value='';
        updateDetection();
    }
    function updateDetection(){
        var value=(identifier.value||'').trim();
        detection.classList.remove('is-student','is-admin');
        if(!value){
            detectionText.textContent='Account type will be detected automatically';
            secretLabel.textContent='PIN or Password';
            secret.placeholder='Enter PIN or password';
            secret.inputMode='';
            secret.removeAttribute('maxlength');
            submit.value='Sign In';
        } else if(studentPattern.test(value)){
            detection.classList.add('is-student');
            detectionText.textContent='Student account detected';
            secretLabel.textContent='Secure PIN';
            secret.placeholder='Enter 4-8 digit PIN';
            secret.inputMode='numeric';
            secret.maxLength=8;
            submit.value='Open My Results';
        } else {
            detection.classList.add('is-admin');
            detectionText.textContent='Administrator account detected';
            secretLabel.textContent='Password';
            secret.placeholder='Enter admin password';
            secret.inputMode='';
            secret.removeAttribute('maxlength');
            submit.value='Login to Dashboard';
        }
    }
    identifier.addEventListener('input',updateDetection);
    toggle.addEventListener('click',function(){
        var show=secret.type==='password';
        secret.type=show?'text':'password';
        toggle.textContent=show?'Hide':'Show';
        toggle.setAttribute('aria-label',(show?'Hide':'Show')+' PIN or password');
    });
    window.addEventListener('pageshow',clearFields);
    document.addEventListener('DOMContentLoaded',clearFields);
})();
</script>
</body>
</html>
