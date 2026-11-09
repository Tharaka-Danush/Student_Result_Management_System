<?php
require_once 'auth.php';require_role(['super_admin','registrar']);
$message='';$type='success';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf()){$message='Invalid request.';$type='error';}
 else{
  $id=(int)($_POST['id']??0);$action=$_POST['action']??'';$newpin=trim($_POST['new_pin']??'');
  mysqli_begin_transaction($conn);
  try{
   $s=mysqli_prepare($conn,'SELECT r.*,s.is_active FROM pin_reset_requests r JOIN students s ON s.student_id=r.student_id WHERE r.id=? FOR UPDATE');mysqli_stmt_bind_param($s,'i',$id);mysqli_stmt_execute($s);$req=mysqli_fetch_assoc(mysqli_stmt_get_result($s));
   if(!$req||$req['status']!=='pending')throw new RuntimeException('This request is no longer pending.');
   $by=$_SESSION['admin_username'];
   if($action==='reset'){
    if(!(int)$req['is_active'])throw new RuntimeException('Activate the student account before resetting its PIN.');
    if(!preg_match('/^\d{4,8}$/',$newpin))throw new RuntimeException('Enter a 4-8 digit PIN.');
    $hash=password_hash($newpin,PASSWORD_DEFAULT);$u=mysqli_prepare($conn,'UPDATE students SET pin_hash=?,failed_attempts=0,locked_until=NULL WHERE student_id=?');mysqli_stmt_bind_param($u,'ss',$hash,$req['student_id']);mysqli_stmt_execute($u);
    $state='resolved';audit_log($conn,'PIN_RESET','student',$req['student_id'],'Verified recovery request #'.$id);$message='PIN reset complete. Provide the new PIN to the student through the faculty identity-verification process.';
   }elseif($action==='reject'){$state='rejected';audit_log($conn,'PIN_RESET_REJECTED','pin_reset',(string)$id,'Request rejected');$message='Request rejected.';}
   else throw new RuntimeException('Invalid action.');
   $u=mysqli_prepare($conn,'UPDATE pin_reset_requests SET status=?,resolved_at=NOW(),resolved_by=? WHERE id=?');mysqli_stmt_bind_param($u,'ssi',$state,$by,$id);mysqli_stmt_execute($u);mysqli_commit($conn);
  }catch(Throwable $ex){mysqli_rollback($conn);$message=$ex instanceof mysqli_sql_exception?'The request could not be updated. Refresh and try again.':$ex->getMessage();$type='error';}
 }
}
$status=$_GET['status']??'pending';if(!in_array($status,['pending','resolved','rejected','all'],true))$status='pending';$where=$status==='all'?'':' WHERE r.status=?';$sql='SELECT r.*,s.name FROM pin_reset_requests r LEFT JOIN students s ON s.student_id=r.student_id'.$where.' ORDER BY r.id DESC LIMIT 300';$st=mysqli_prepare($conn,$sql);if($status!=='all')mysqli_stmt_bind_param($st,'s',$status);mysqli_stmt_execute($st);$rs=mysqli_stmt_get_result($st);$rows=[];while($x=mysqli_fetch_assoc($rs))$rows[]=$x;
?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PIN Reset Requests</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="modern-ui.css?v=20261006-modern2"></head><body><div class="main-wrapper"><?php include 'admin_nav.php';?><main class="content"><section class="page-heading"><div><span class="page-kicker">Student Access</span><h1>PIN Reset Requests</h1><p>Only requests that passed Student ID, Index Number and NIC verification appear here.</p></div></section><?php if($message):?><div class="message <?=$type?>"><?=e($message)?></div><?php endif;?><div class="filter-chips"><?php foreach(['pending','resolved','rejected','all'] as $x):?><a class="filter-chip" href="?status=<?=$x?>"><?=e(ucfirst($x))?></a><?php endforeach;?></div><section class="feature-card wide-card"><div class="table-wrapper"><table class="results-table"><tr><th>Date</th><th>Student</th><th>Index</th><th>Status</th><th>Request IP</th><th>Action</th></tr><?php foreach($rows as $r):?><tr><td><?=e($r['created_at'])?></td><td><strong><?=e($r['student_id'])?></strong><br><small><?=e($r['name']??'')?></small></td><td><?=e($r['index_number'])?></td><td><?=e(ucfirst($r['status']))?></td><td><?=e($r['request_ip']??'—')?></td><td><?php if($r['status']==='pending'):?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="action" value="reset"><input type="password" autocomplete="new-password" name="new_pin" inputmode="numeric" pattern="\d{4,8}" maxlength="8" placeholder="New PIN" required style="max-width:110px"><button type="submit" class="small-btn">Reset</button></form><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="action" value="reject"><button class="btn-danger small-btn" type="submit">Reject</button></form><?php else:?><?=e($r['resolved_by']??'')?><?php endif;?></td></tr><?php endforeach;?></table></div></section></main><?php include 'footer.php';?></div></body></html>
