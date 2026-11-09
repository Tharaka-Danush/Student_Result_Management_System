<?php
require_once 'auth.php'; require_role(['super_admin','data_entry']); require_once 'db.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){header('Location:view_results.php');exit;}
$id=(int)($_POST['id']??0);$student_id=$_POST['student_id']??'';$year=$_POST['year']??'';$semester=$_POST['semester']??'';
if($id>0){
 $q=mysqli_prepare($conn,'SELECT r.*,s.batch,s.section FROM results r JOIN students s ON s.student_id=r.student_id WHERE r.id=?');mysqli_stmt_bind_param($q,'i',$id);mysqli_stmt_execute($q);$old=mysqli_fetch_assoc(mysqli_stmt_get_result($q));
 if($old && !result_is_protected($old) && !semester_is_locked($conn,$old['year'],$old['semester'],$old['batch'],$old['section'])){$st=mysqli_prepare($conn,"DELETE FROM results WHERE id=? AND approval_status<>'approved' AND publish_status<>'published'");mysqli_stmt_bind_param($st,'i',$id);if(mysqli_stmt_execute($st)){revoke_student_verifications($conn,$old['student_id']);audit_log($conn,'DELETE','result',(string)$id,$old['student_id'].' '.$old['subject_code']);}}
}
header('Location:view_results.php?student_id='.urlencode($student_id).'&year='.urlencode($year).'&semester='.urlencode($semester));exit;
