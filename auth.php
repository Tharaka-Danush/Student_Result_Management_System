<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';

function require_admin() {
    global $conn;
    if (!isset($conn)) { require_once __DIR__ . '/db.php'; }
    if (empty($_SESSION['admin_logged_in']) || !enforce_session_timeout($conn,'admin')) {
        header('Location: login.php'); exit;
    }
    $st=mysqli_prepare($conn,'SELECT role,is_active FROM admin WHERE username=? LIMIT 1');
    mysqli_stmt_bind_param($st,'s',$_SESSION['admin_username']); mysqli_stmt_execute($st);
    $account=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if(!$account || !(int)$account['is_active']) { destroy_login_session(); header('Location: login.php'); exit; }
    $_SESSION['admin_role']=$account['role'];
}
function require_student() {
    global $conn;
    if (!isset($conn)) { require_once __DIR__ . '/db.php'; }
    if (maintenance_mode_enabled($conn)) { header('Location: maintenance.php'); exit; }
    if (empty($_SESSION['student_logged_in']) || empty($_SESSION['student_id']) || !enforce_session_timeout($conn,'student')) {
        header('Location: login.php'); exit;
    }
    $st=mysqli_prepare($conn,'SELECT is_active,pin_hash FROM students WHERE student_id=? LIMIT 1');
    mysqli_stmt_bind_param($st,'s',$_SESSION['student_id']); mysqli_stmt_execute($st);
    $account=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if(!$account || !(int)$account['is_active'] || empty($account['pin_hash']) || !hash_equals(hash('sha256',$account['pin_hash']), $_SESSION['student_auth_version'] ?? '')) {
        destroy_login_session(); header('Location: login.php'); exit;
    }
}
function require_role(array $roles) {
    require_admin();
    if (!has_role($roles)) { http_response_code(403); header('Location: unauthorized.php'); exit; }
}
