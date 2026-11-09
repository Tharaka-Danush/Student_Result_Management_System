<?php
require_once __DIR__ . '/session.php';
require_once 'db.php'; require_once 'functions.php';
if (!maintenance_mode_enabled($conn)) { header('Location: index.php'); exit; }
$system=app_setting($conn,'system_name','Student Result Management System');
$faculty=app_setting($conn,'faculty_name','Faculty of Applied Sciences');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maintenance · <?=e($system)?></title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="modern-ui.css?v=20261006-modern2"></head><body class="status-page"><main class="status-shell"><div class="status-card"><div class="status-icon">⚙</div><span class="page-kicker">Scheduled Maintenance</span><h1>We’ll be back shortly.</h1><p>The student portal is temporarily unavailable while system maintenance is in progress. Academic records remain secure.</p><div class="status-meta"><strong><?=e($faculty)?></strong><span><?=e($system)?></span></div><a class="btn-link btn-secondary" href="login.php">Secure Login</a></div></main></body></html>
