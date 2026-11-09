<?php
if (!isset($conn)) require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
$ftUniversity=app_setting($conn,'university_name','South Eastern University of Sri Lanka');
$ftFaculty=app_setting($conn,'faculty_name','Faculty of Applied Sciences');
$ftWebsite=app_setting($conn,'faculty_website','https://www.seu.ac.lk/fas/');
?>
<?php if(!empty($_SESSION['admin_logged_in'])): ?>
<footer class="footer admin-footer-compact"><div class="footer-bottom"><span>© <?=date('Y')?> <?=e(app_setting($conn,'system_name','Student Result Management System'))?></span><span><?=e(role_label(admin_role()))?> · <?=e(system_version($conn))?></span></div></footer>
<?php else: ?>
<footer class="footer">
    <div class="footer-brand footer-section"><div class="footer-brandline"><span class="footer-logo"><img src="logo.png" alt="SEUSL"></span><div><h3><?=e($ftFaculty)?></h3><p><?=e($ftUniversity)?></p></div></div><p>Sammanthurai, Sri Lanka</p><p>+94 67 2260714</p></div>
    <div class="footer-section"><h3>Departments</h3><p>Computer Science</p><p>Mathematical Sciences</p><p>Physical Sciences</p><p>Biological Sciences</p><p>Chemical Sciences</p></div>
    <div class="footer-section"><h3>Quick Links</h3><a href="index.php">Home</a><a href="login.php">Secure Login</a><a href="verify_result.php">Verify Document</a></div>
    <div class="footer-section"><h3>Student Results</h3><p>Secure access to published academic results and administrative result management.</p><a href="<?=e($ftWebsite)?>" target="_blank" rel="noopener">Faculty Website ↗</a></div>
    <div class="footer-bottom"><span>© <?=date('Y')?> <?=e(app_setting($conn,'system_name','Student Result Management System'))?></span><span><?=e($ftFaculty)?> · <?=e($ftUniversity)?> · <?=e(system_version($conn))?></span></div>
</footer>
<?php endif; ?>
<script src="ui-enhancements.js?v=20261009-frozen2" defer></script>
