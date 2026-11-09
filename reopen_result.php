<?php
require_once 'auth.php'; require_role(['super_admin','registrar']);
$id=(int)($_GET['id']??$_POST['id']??0);$message='';
$st=mysqli_prepare($conn,'SELECT r.*,s.batch,s.section FROM results r JOIN students s ON s.student_id=r.student_id WHERE r.id=?');
mysqli_stmt_bind_param($st,'i',$id);mysqli_stmt_execute($st);$row=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
if(!$row){http_response_code(404);exit('Result not found.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $reason=trim($_POST['reason']??'');
 if(!verify_csrf())$message='Invalid request.';
 elseif(mb_strlen($reason)<10||mb_strlen($reason)>255)$message='Enter a reason of 10 to 255 characters.';
 else{
  mysqli_begin_transaction($conn);
  try{
   $st=mysqli_prepare($conn,'SELECT r.*,s.batch,s.section FROM results r JOIN students s ON s.student_id=r.student_id WHERE r.id=? FOR UPDATE');mysqli_stmt_bind_param($st,'i',$id);mysqli_stmt_execute($st);$row=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
   if(!$row||!result_is_protected($row))throw new RuntimeException('Only approved or published results need reopening.');
   if(semester_is_locked($conn,$row['year'],$row['semester'],$row['batch'],$row['section']))throw new RuntimeException('Unlock the semester in Academic Rules before reopening.');
   $by=$_SESSION['admin_username'];$st=mysqli_prepare($conn,"UPDATE results SET publish_status='draft',approval_status='pending',approved_by=NULL,approved_at=NULL,approval_note=?,reopened_by=?,reopened_at=NOW(),reopen_reason=? WHERE id=?");mysqli_stmt_bind_param($st,'sssi',$reason,$by,$reason,$id);mysqli_stmt_execute($st);
   revoke_student_verifications($conn,$row['student_id']);audit_log($conn,'RESULT_REOPENED','result',(string)$id,$row['student_id'].' '.$row['subject_code'].' Reason: '.$reason);mysqli_commit($conn);
   header('Location: view_results.php?student_id='.urlencode($row['student_id']).'&year='.$row['year'].'&semester='.$row['semester']);exit;
  }catch(Throwable $ex){mysqli_rollback($conn);$message=$ex instanceof RuntimeException && !($ex instanceof mysqli_sql_exception)?$ex->getMessage():'The result changed or is locked. Refresh and try again.';}
 }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reopen Result</title><link rel="stylesheet" href="modern-ui.css?v=20261006-modern2"></head><body><div class="main-wrapper"><?php include 'admin_nav.php';?><main class="content"><section class="page-heading"><div><span class="page-kicker">Academic Amendment</span><h1>Reopen Result</h1><p><?=e($row['student_id'].' · '.$row['subject_code'].' · Y'.$row['year'].' S'.$row['semester'])?></p></div></section><section class="feature-card"><div class="workflow-note">Reopening removes publication, revokes previous verification codes and requires approval before the corrected result can be published again.</div><?php if($message):?><div class="message error"><?=e($message)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><label>Reason for amendment</label><textarea name="reason" required minlength="10" maxlength="255" rows="4"><?=e($_POST['reason']??'')?></textarea><div class="button-row"><a class="btn-link btn-secondary" href="view_results.php">Back</a><button type="submit">Reopen for Correction</button></div></form></section></main><?php include 'footer.php';?></div></body></html>
