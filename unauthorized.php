<?php
require_once __DIR__ . '/session.php'; require_once 'db.php'; require_once 'functions.php';
http_response_code(403);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access Restricted</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="modern-ui.css?v=20261006-modern2"></head><body class="status-page"><main class="status-shell"><div class="status-card"><div class="status-code">403</div><span class="page-kicker">Access Restricted</span><h1>This area needs a different role.</h1><p>Your account is signed in, but it does not have permission to open this page.</p><div class="button-row centered"><a class="btn-link" href="admin_dashboard.php">Back to Dashboard</a><a class="btn-link btn-secondary" href="logout.php">Sign Out</a></div></div></main></body></html>
