<?php
require_once 'auth.php'; require_admin();
function dashboard_count($conn,$sql) {return (int)mysqli_fetch_row(mysqli_query($conn,$sql))[0];}
$metrics=[
 ['Total Students',dashboard_count($conn,'SELECT COUNT(*) FROM students'),'view_students.php'],
 ['Draft Results',dashboard_count($conn,"SELECT COUNT(*) FROM results WHERE publish_status='draft'"),'view_results.php?status=draft'],
 ['Published Results',dashboard_count($conn,"SELECT COUNT(*) FROM results WHERE publish_status='published' AND approval_status='approved'"),'view_results.php?status=published']
];
if(has_role(['registrar','super_admin'])){
 $metrics[]=['Pending Approvals',dashboard_count($conn,"SELECT COUNT(*) FROM results WHERE approval_status='pending'"),'result_approvals.php'];
 $metrics[]=['PIN Reset Requests',dashboard_count($conn,"SELECT COUNT(*) FROM pin_reset_requests WHERE status='pending'"),'pin_reset_requests.php'];
}
$security=[
 ['PIN Not Set',dashboard_count($conn,"SELECT COUNT(*) FROM students WHERE pin_hash IS NULL OR pin_hash=''"),'view_students.php?pin_status=missing'],
 ['NIC Not Set',dashboard_count($conn,"SELECT COUNT(*) FROM students WHERE nic_hash IS NULL OR nic_hash=''"),'view_students.php?recovery_status=missing']
];
$links=[['Students','Search profiles and security setup.','view_students.php'],['Results','Browse semester results and amendments.','view_results.php']];
if(has_role(['data_entry','super_admin']))$links=array_merge($links,[['Add Student','Create a student account with PIN and NIC.','add_student.php'],['Add Result','Submit a draft for approval.','add_result.php'],['Bulk Upload','Validate and import CSV drafts.','bulk_results.php']]);
if(has_role(['registrar','super_admin']))$links=array_merge($links,[['Approvals','Review pending academic results.','result_approvals.php'],['Publish Results','Release approved semester results.','bulk_publish.php'],['PIN Requests','Review verified recovery requests.','pin_reset_requests.php'],['Subject Master','Maintain subjects, credits and terms.','subject_master.php'],['Academic Rules','Grade scales and semester locks.','academic_settings.php'],['Audit Log','Trace administrative actions.','audit_log.php']]);
if(has_role(['super_admin']))$links=array_merge($links,[['Admin Users','Manage staff roles and access.','admin_users.php'],['Settings & Backup','Manage configuration and download backup.','admin_settings.php']]);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Dashboard</title><link rel="icon" href="favicon.svg"><link rel="stylesheet" href="modern-ui.css?v=20261006-modern2"></head><body><div class="main-wrapper"><?php include 'admin_nav.php';?><main class="content dashboard-page"><section class="dashboard-hero"><div><span class="page-kicker">Administration Workspace</span><h1>Welcome, <?=e($_SESSION['admin_username'])?></h1><p><?=e(role_label(admin_role()))?> · Semester result management</p></div></section><section class="metric-grid security-metrics"><?php foreach($metrics as [$label,$count,$href]):?><a class="metric-card" href="<?=e($href)?>"><span><?=e($label)?></span><strong><?=$count?></strong><small>View records →</small></a><?php endforeach;?></section><section class="workflow-note"><strong>Result workflow</strong><br>Enter / import draft → Registrar approval → Publish<br>For corrections: Reopen with reason → Edit → Approve → Publish</section><section class="feature-card"><h2>Student Security Setup Pending</h2><p>Students without a PIN need staff setup before login. Students without a registered NIC must contact the faculty for PIN recovery.</p><div class="metric-grid security-metrics"><?php foreach($security as [$label,$count,$href]):?><a class="metric-card" href="<?=e($href)?>"><span><?=e($label)?></span><strong><?=$count?></strong><small>Review setup →</small></a><?php endforeach;?></div></section><section class="dashboard-grid"><?php foreach($links as [$title,$text,$href]):?><a class="dashboard-card" href="<?=e($href)?>"><h3><?=e($title)?></h3><p><?=e($text)?></p><span class="card-link">Open →</span></a><?php endforeach;?></section></main><?php include 'footer.php';?></div></body></html>
