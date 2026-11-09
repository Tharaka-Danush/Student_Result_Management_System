<?php
require_once 'session.php'; require_once 'db.php'; require_once 'functions.php';
audit_log($conn,'LOGOUT',!empty($_SESSION['admin_logged_in'])?'admin':'student',$_SESSION['admin_username']??$_SESSION['student_id']??'','Signed out');
destroy_login_session(); header('Location: index.php'); exit;
