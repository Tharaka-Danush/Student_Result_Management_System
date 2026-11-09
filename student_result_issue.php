<?php
require_once 'auth.php'; require_student();
$id=(string)$_SESSION['student_id']; $message=''; $type='error';
$year=is_string($_GET['year']??null)?$_GET['year']:''; $sem=is_string($_GET['semester']??null)?$_GET['semester']:'';
$q=mysqli_prepare($conn,"SELECT DISTINCT year,semester FROM results WHERE student_id=? AND publish_status='published' AND approval_status='approved' ORDER BY year DESC,semester DESC");mysqli_stmt_bind_param($q,'s',$id);mysqli_stmt_execute($q);$terms=mysqli_fetch_all(mysqli_stmt_get_result($q),MYSQLI_ASSOC);
if($_SERVER['REQUEST_METHOD']==='POST') {
 $term=is_string($_POST['term']??null)?$_POST['term']:''; $parts=explode('-',$term); $year=$parts[0]??''; $sem=$parts[1]??'';
 $description=is_string($_POST['description']??null)?trim($_POST['description']):'';
 $allowed=false; foreach($terms as $t) if($t['year']===$year && $t['semester']===$sem)$allowed=true;
 if(!verify_csrf()) { http_response_code(400);$message='Invalid request. Refresh and try again.'; }
 elseif(!$allowed || !valid_year_semester($year,$sem))$message='Choose one of your published semesters.';
 elseif(mb_strlen($description)<10 || mb_strlen($description)>2000)$message='Describe the issue using 10–2000 characters.';
 elseif(!consume_reset_attempt($conn,'result-issue:'.$id,'result-issue:'.($_SERVER['REMOTE_ADDR']??''))) {http_response_code(429);$message='Too many submissions. Please wait 15 minutes.';}
 else {
  mysqli_begin_transaction($conn);
  try {
   $lock=mysqli_prepare($conn,'SELECT student_id FROM students WHERE student_id=? FOR UPDATE');mysqli_stmt_bind_param($lock,'s',$id);mysqli_stmt_execute($lock);mysqli_stmt_get_result($lock)->free();
   $q=mysqli_prepare($conn,"SELECT id FROM student_result_issues WHERE student_id=? AND year=? AND semester=? AND status='pending' LIMIT 1");mysqli_stmt_bind_param($q,'sss',$id,$year,$sem);mysqli_stmt_execute($q);
   if(mysqli_fetch_assoc(mysqli_stmt_get_result($q))) {mysqli_rollback($conn);$message='You already have a pending issue for this semester.';}
   else {
    $q=mysqli_prepare($conn,'INSERT INTO student_result_issues(student_id,year,semester,description) VALUES(?,?,?,?)');mysqli_stmt_bind_param($q,'ssss',$id,$year,$sem,$description);mysqli_stmt_execute($q);
    audit_log($conn,'RESULT_ISSUE_SUBMITTED','student',$id,'Year '.$year.' Semester '.$sem);mysqli_commit($conn);
    header('Location: student_result_issue.php?submitted=1');exit;
   }
  } catch(Throwable $ex) {mysqli_rollback($conn);$message='Unable to submit your issue. Please try again.';}
 }
}
if(isset($_GET['submitted'])){$message='Your issue was submitted for registrar review.';$type='success';}
$q=mysqli_prepare($conn,'SELECT * FROM student_result_issues WHERE student_id=? ORDER BY id DESC LIMIT 50');mysqli_stmt_bind_param($q,'s',$id);mysqli_stmt_execute($q);$issues=mysqli_fetch_all(mysqli_stmt_get_result($q),MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Report a Result Issue</title><link rel="stylesheet" href="modern-ui.css?v=20261008-student1"></head><body class="student-portal"><div class="main-wrapper"><?php include 'public_header.php'; ?><main class="content"><section class="page-heading"><div><span class="page-kicker">Student Support</span><h1>Report a Result Issue</h1><p>Describe the subject and mark that need checking. The registrar will review your request.</p></div><a class="btn-link btn-secondary" href="student_result.php">Back to Results</a></section><?php if($message):?><div class="message <?=e($type)?>" role="status"><?=e($message)?></div><?php endif;?><div class="feature-grid"><section class="feature-card"><h2>Submit an Issue</h2><?php if($terms):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label for="term">Published Semester</label><select id="term" name="term" required><?php foreach($terms as $t):$v=$t['year'].'-'.$t['semester'];?><option value="<?=e($v)?>" <?=$year.'-'.$sem===$v?'selected':''?>>Year <?=e($t['year'])?> · Semester <?=e($t['semester'])?></option><?php endforeach;?></select><label for="description">Issue Description</label><textarea id="description" name="description" rows="6" required minlength="10" maxlength="2000" placeholder="Include the subject code and explain what needs checking."><?=e($_POST['description']??'')?></textarea><div class="button-row"><button type="submit">Submit for Review</button></div></form><?php else:?><p>You can report an issue after your results are published.</p><?php endif;?></section><section class="feature-card"><h2>Your Requests</h2><?php if(!$issues):?><p>No issues submitted yet.</p><?php endif;?><?php foreach($issues as $issue):?><article class="student-issue-card"><div><strong>Year <?=e($issue['year'])?> · Semester <?=e($issue['semester'])?></strong><span class="status-pill <?=e($issue['status'])?>"><?=e(ucfirst($issue['status']))?></span></div><small><?=e($issue['created_at'])?> · Request #<?=(int)$issue['id']?></small><p><?=nl2br(e($issue['description']))?></p><?php if($issue['response']):?><div class="issue-response"><strong>Registrar response</strong><p><?=nl2br(e($issue['response']))?></p></div><?php endif;?></article><?php endforeach;?></section></div></main><?php include 'footer.php';?></div></body></html>
