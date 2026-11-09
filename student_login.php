<?php
// Backward-compatible route: Student and Admin access now share one secure login page.
header('Location: login.php');
exit;
