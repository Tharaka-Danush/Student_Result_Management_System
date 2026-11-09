<?php
if (!isset($conn)) require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
$uiSystem = app_setting($conn,'system_name','Student Result Management System');
$uiUniversity = app_setting($conn,'university_name','South Eastern University of Sri Lanka');
$uiFaculty = app_setting($conn,'faculty_name','Faculty of Applied Sciences');
?>
<header class="brandbar">
    <a class="brandmark" href="index.php" aria-label="<?=e($uiSystem)?> home">
        <span class="brand-logo-wrap"><img src="logo.png" alt="University Logo"></span>
        <span class="brand-copy"><span class="brandbar-title"><?=e($uiSystem)?></span><span class="brandbar-subtitle"><?=e($uiFaculty)?> · <?=e($uiUniversity)?></span></span>
    </a>
    <div class="brand-actions"><span class="brand-badge">Academic Portal</span><button class="icon-btn theme-toggle" type="button" aria-label="Toggle dark mode" title="Toggle theme"><span aria-hidden="true">◐</span></button></div>
</header>
