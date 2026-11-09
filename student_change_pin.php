<?php
require_once 'auth.php'; require_student();
$message=''; $id=(string)$_SESSION['student_id'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!verify_csrf()) { http_response_code(400); $message='Invalid request. Refresh and try again.'; }
 elseif (!consume_reset_attempt($conn,'pin-change:'.$id,'pin-change:'.($_SERVER['REMOTE_ADDR']??''))) { http_response_code(429); $message='Too many attempts. Please wait 15 minutes.'; }
 else {
  $current=is_string($_POST['current_pin']??null)?$_POST['current_pin']:'';
  $new=is_string($_POST['new_pin']??null)?$_POST['new_pin']:'';
  $confirm=is_string($_POST['confirm_pin']??null)?$_POST['confirm_pin']:'';
  if (!preg_match('/^[0-9]{6,8}$/D',$new)) $message='Use a new PIN with 6–8 digits.';
  elseif (!hash_equals($new,$confirm)) $message='The new PIN and confirmation do not match.';
  elseif (hash_equals($current,$new)) $message='Choose a PIN different from your current PIN.';
  else {
   mysqli_begin_transaction($conn);
   try {
    $q=mysqli_prepare($conn,'SELECT pin_hash,is_active FROM students WHERE student_id=? FOR UPDATE'); mysqli_stmt_bind_param($q,'s',$id); mysqli_stmt_execute($q); $account=mysqli_fetch_assoc(mysqli_stmt_get_result($q));
    if (!$account || !(int)$account['is_active'] || !hash_equals(hash('sha256',$account['pin_hash']),$_SESSION['student_auth_version']??'') || !password_verify($current,$account['pin_hash'])) { mysqli_rollback($conn); $message='Your current PIN is incorrect or your session has changed.'; }
    else {
     $hash=password_hash($new,PASSWORD_DEFAULT);
     $q=mysqli_prepare($conn,'UPDATE students SET pin_hash=?,failed_attempts=0,locked_until=NULL WHERE student_id=?'); mysqli_stmt_bind_param($q,'ss',$hash,$id); mysqli_stmt_execute($q);
     audit_log($conn,'STUDENT_PIN_CHANGED','student',$id,'Student changed their own PIN'); mysqli_commit($conn);
     destroy_login_session(); header('Location: login.php?pin_changed=1'); exit;
    }
   } catch (Throwable $ex) { mysqli_rollback($conn); $message='Unable to change your PIN. Please try again.'; }
  }
 }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Change Student PIN</title><link rel="stylesheet" href="modern-ui.css?v=20261008-student1"></head><body class="student-portal"><div class="main-wrapper"><?php include 'public_header.php'; ?><main class="content student-security-content"><section class="feature-card"><span class="page-kicker">Account Security</span><h1>Change your PIN</h1><p>Enter your current PIN to choose a new 6–8 digit PIN. After saving, sign in again. Other sessions will also be signed out.</p><?php if($message): ?><div class="message error" role="alert"><?=e($message)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label for="current-pin">Current PIN</label><input id="current-pin" type="password" name="current_pin" required maxlength="8" inputmode="numeric" autocomplete="current-password"><label for="new-pin">New PIN</label><input id="new-pin" type="password" name="new_pin" required minlength="6" maxlength="8" pattern="[0-9]{6,8}" inputmode="numeric" autocomplete="new-password"><label for="confirm-pin">Confirm New PIN</label><input id="confirm-pin" type="password" name="confirm_pin" required minlength="6" maxlength="8" pattern="[0-9]{6,8}" inputmode="numeric" autocomplete="new-password"><div class="button-row"><a class="btn-link btn-secondary" href="student_result.php">Back to Results</a><button type="submit">Save New PIN</button></div></form></section></main><?php include 'footer.php'; ?></div></body></html>
