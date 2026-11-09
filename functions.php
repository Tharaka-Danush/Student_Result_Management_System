<?php
require_once __DIR__ . "/session.php";
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function valid_student_id($id) {
    return (bool)preg_match('/^SEU\/IS\/\d{2}\/(PS|BS)\/\d{3}$/', $id);
}

function valid_index_number($index) {
    return (bool)preg_match('/^(PS|BS)-\d{4}$/', $index);
}

function valid_subject_code($code) {
    return (bool)preg_match('/^[A-Z]{3}-\d{5}$/', $code);
}

function valid_year_semester($year, $semester) {
    return in_array((string)$year, ['1','2','3','4'], true) && in_array((string)$semester, ['1','2'], true);
}

function student_id_parts($id) {
    if (preg_match('/^SEU\/IS\/(\d{2})\/(PS|BS)\/\d{3}$/', $id, $m)) {
        return ['batch' => $m[1], 'section' => $m[2]];
    }
    return null;
}

function grade_from_marks($marks) {
    $marks = (float)$marks;
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        $q = @mysqli_query($conn, "SELECT grade,gpa FROM grade_scales WHERE is_active=1 AND min_mark <= ".(float)$marks." ORDER BY min_mark DESC LIMIT 1");
        if ($q && ($r=mysqli_fetch_assoc($q))) return [normalize_grade($r['grade']), (float)$r['gpa']];
    }
    if ($marks >= 85) return ['A+', 4.00];
    if ($marks >= 75) return ['A', 4.00];
    if ($marks >= 65) return ['A-', 3.70];
    if ($marks >= 60) return ['B+', 3.30];
    if ($marks >= 55) return ['B', 3.00];
    if ($marks >= 50) return ['B-', 2.70];
    if ($marks >= 45) return ['C+', 2.30];
    if ($marks >= 40) return ['C', 2.00];
    if ($marks >= 35) return ['C-', 1.70];
    if ($marks >= 30) return ['D+', 1.30];
    if ($marks >= 25) return ['D', 1.00];
    return ['E', 0.00];
}

function credit_from_subject_code($code) {
    return valid_subject_code($code) ? (int)substr($code, -1) : 0;
}

function gpa_class($gpa) {
    $gpa=(float)$gpa;
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        $q=@mysqli_query($conn, "SELECT class_name FROM class_rules WHERE is_active=1 AND min_gpa <= ".(float)$gpa." ORDER BY min_gpa DESC LIMIT 1");
        if($q && ($r=mysqli_fetch_assoc($q))) return (string)$r['class_name'];
    }
    if ($gpa >= 3.70) return 'First Class';
    if ($gpa >= 3.30) return 'Second Upper Division';
    if ($gpa >= 2.70) return 'Second Lower Division';
    if ($gpa >= 2.00) return 'Pass';
    return 'Fail';
}

function calculate_gpa_summary(array $rows) {
    return academic_gpa_summary($rows);
}

function csrf_token() {
    require_once __DIR__ . '/session.php';
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verify_csrf() {
    require_once __DIR__ . '/session.php';
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}


function app_setting($conn, $key, $default='') {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = @mysqli_prepare($conn, 'SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1');
    if (!$stmt) return $default;
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $cache[$key] = ($row ? (string)$row['setting_value'] : $default);
}

function audit_log($conn, $action, $entity_type, $entity_id='', $details='') {
    require_once __DIR__ . '/session.php';
    $username = $_SESSION['admin_username'] ?? 'system';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = @mysqli_prepare($conn, 'INSERT INTO audit_logs (admin_username,action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?,?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ssssss', $username, $action, $entity_type, $entity_id, $details, $ip);
        @mysqli_stmt_execute($stmt);
    }
}

function valid_result_status($status) {
    return in_array($status, ['draft','published','withheld'], true);
}

function result_status_label($status) {
    return ['draft'=>'Draft','published'=>'Published','withheld'=>'Withheld'][$status] ?? 'Draft';
}

function admin_role() {
    require_once __DIR__ . '/session.php';
    return $_SESSION['admin_role'] ?? 'data_entry';
}

function role_label($role) {
    return [
        'super_admin' => 'Super Admin',
        'registrar' => 'Registrar',
        'data_entry' => 'Data Entry'
    ][$role] ?? 'Data Entry';
}

function has_role(array $roles) {
    return in_array(admin_role(), $roles, true);
}

function lockout_settings($conn) {
    $max = max(3, min(20, (int)app_setting($conn, 'login_max_attempts', '5')));
    $mins = max(1, min(1440, (int)app_setting($conn, 'login_lock_minutes', '15')));
    return [$max, $mins];
}

function calculate_cgpa_summary(array $rows) {
    return calculate_gpa_summary($rows);
}

function result_history_by_term(array $rows) {
    $terms = [];
    foreach ($rows as $row) {
        $key = (string)$row['year'] . '-' . (string)$row['semester'];
        $terms[$key]['year'] = (string)$row['year'];
        $terms[$key]['semester'] = (string)$row['semester'];
        $terms[$key]['rows'][] = $row;
    }
    foreach ($terms as &$term) {
        $term['summary'] = calculate_gpa_summary($term['rows']);
    }
    unset($term);
    uasort($terms, function($a,$b){
        return [(int)$a['year'],(int)$a['semester']] <=> [(int)$b['year'],(int)$b['semester']];
    });
    return $terms;
}

function generate_verification_code() {
    return strtoupper(bin2hex(random_bytes(6)));
}

function ensure_verification_record($conn, $type, $studentId, $year, $semester, $payload) {
    $hash = hash('sha256', $payload);
    $stmt = mysqli_prepare($conn, 'SELECT verification_code FROM document_verifications WHERE document_type=? AND student_id=? AND COALESCE(academic_year,\'\')=? AND COALESCE(semester,\'\')=? AND payload_hash=? AND revoked_at IS NULL ORDER BY id DESC LIMIT 1');
    $y=(string)$year; $s=(string)$semester;
    mysqli_stmt_bind_param($stmt,'sssss',$type,$studentId,$y,$s,$hash);
    mysqli_stmt_execute($stmt);
    $row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if($row) return $row['verification_code'];
    $code=generate_verification_code();
    $by=$_SESSION['admin_username'] ?? 'system';
    $ins=mysqli_prepare($conn,'INSERT INTO document_verifications(verification_code,document_type,student_id,academic_year,semester,payload_hash,created_by) VALUES(?,?,?,?,?,?,?)');
    mysqli_stmt_bind_param($ins,'sssssss',$code,$type,$studentId,$y,$s,$hash,$by);
    mysqli_stmt_execute($ins);
    return $code;
}

function safe_page_int($value, $default=1) {
    $n=(int)$value;
    return $n>0?$n:$default;
}

function revoke_student_verifications($conn, $studentId) {
    $stmt=@mysqli_prepare($conn,'UPDATE document_verifications SET revoked_at=NOW() WHERE student_id=? AND revoked_at IS NULL');
    if($stmt){mysqli_stmt_bind_param($stmt,'s',$studentId);@mysqli_stmt_execute($stmt);}
}


function approval_status_label($status) {
    return ['pending'=>'Pending Approval','approved'=>'Approved','rejected'=>'Rejected'][$status] ?? 'Pending Approval';
}
function valid_approval_status($status) { return in_array($status,['pending','approved','rejected'],true); }
function semester_is_locked($conn,$year,$semester,$batch=null,$section=null) {
    $year=(string)$year; $semester=(string)$semester;
    $sql="SELECT id FROM semester_locks WHERE year=? AND semester=? AND is_locked=1 AND (batch IS NULL OR batch='' OR batch=?) AND (section='ALL' OR section=? OR ?='ALL') LIMIT 1";
    $st=@mysqli_prepare($conn,$sql); if(!$st) return false;
    $b=(string)($batch??''); $sec=(string)($section??'ALL');
    mysqli_stmt_bind_param($st,'sssss',$year,$semester,$b,$sec,$sec); mysqli_stmt_execute($st);
    return (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($st));
}
function enforce_session_timeout($conn,$scope) {
    require_once __DIR__ . '/session.php';
    $mins=max(5,min(240,(int)app_setting($conn,'session_timeout_minutes','30')));
    $key=$scope.'_last_activity';
    if(!empty($_SESSION[$key]) && time()-(int)$_SESSION[$key] > $mins*60){
        if($scope==='admin'){ unset($_SESSION['admin_logged_in'],$_SESSION['admin_username'],$_SESSION['admin_role']); }
        else { unset($_SESSION['student_logged_in'],$_SESSION['student_id']); }
        $_SESSION['session_timeout_notice']=1;
        return false;
    }
    $_SESSION[$key]=time(); return true;
}
function maintenance_mode_enabled($conn) {
    return app_setting($conn, 'maintenance_mode', '0') === '1';
}

function system_version($conn) {
    return app_setting($conn, 'system_version', 'v1.0');
}



function normalize_nic($nic) {
    return strtoupper(preg_replace('/[\s-]+/', '', trim((string)$nic)));
}

function valid_nic($nic) {
    $nic = normalize_nic($nic);
    return (bool)preg_match('/^(?:\d{9}[VX]|\d{12})$/', $nic);
}

function normalize_grade($grade) { return str_replace(['−','–','—','‒','‑'], '-', trim((string)$grade)); }
function result_is_protected(array $row) { return ($row['approval_status']??'pending')==='approved' || ($row['publish_status']??'draft')==='published'; }
function valid_admin_password($password) { return strlen($password)>=12 && strlen($password)<=72 && preg_match('/[A-Z]/',$password) && preg_match('/[a-z]/',$password) && preg_match('/[0-9]/',$password); }
function destroy_login_session() {
    $_SESSION=[];
    if(ini_get('session.use_cookies')) { $c=session_get_cookie_params(); setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$c['path'],'domain'=>$c['domain'],'secure'=>$c['secure'],'httponly'=>$c['httponly'],'samesite'=>'Lax']); }
    session_destroy();
}
// Database-backed fixed-window limiter: cookies/session resets cannot bypass it.
function consume_reset_attempt($conn, $studentId, $ip) {
    mysqli_begin_transaction($conn);
    try {
        foreach(['ip:'.$ip,'student:'.strtoupper($studentId)] as $value) {
            $key=hash('sha256',$value);
            $st=mysqli_prepare($conn,'INSERT INTO recovery_rate_limits (bucket_key,window_started,attempts) VALUES (?,NOW(),1) ON DUPLICATE KEY UPDATE attempts=IF(window_started < NOW()-INTERVAL 15 MINUTE,1,attempts+1), window_started=IF(window_started < NOW()-INTERVAL 15 MINUTE,NOW(),window_started)');
            mysqli_stmt_bind_param($st,'s',$key); mysqli_stmt_execute($st);
            $st=mysqli_prepare($conn,'SELECT attempts FROM recovery_rate_limits WHERE bucket_key=? FOR UPDATE');
            mysqli_stmt_bind_param($st,'s',$key); mysqli_stmt_execute($st); $row=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            if((int)$row['attempts']>5) { mysqli_commit($conn); return false; }
        }
        mysqli_commit($conn); return true;
    } catch(Throwable $ex) { mysqli_rollback($conn); throw $ex; }
}

function semester_verification_payload($conn,array $student,$year,$semester,array $rows) {
    $rows=effective_result_rows($rows);
    usort($rows,fn($a,$b)=>strcmp($a['subject_code'],$b['subject_code']));
    return json_encode([
        'student'=>[$student['student_id'],$student['name'],$student['index_number'],$student['section']],
        'term'=>[(string)$year,(string)$semester],
        'summary'=>calculate_gpa_summary($rows),
        'institution'=>[app_setting($conn,'university_name'),app_setting($conn,'faculty_name'),app_setting($conn,'registrar_name'),app_setting($conn,'registrar_title')],
        'results'=>array_map(fn($r)=>[$r['subject_code'],$r['subject'],normalize_grade($r['grade']),number_format((float)$r['marks'],2,'.',''),number_format((float)$r['gpa'],2,'.',''),(int)$r['credit'],(int)($r['includes_gpa']??1),(int)($r['attempt_no']??1),$r['exam_date']??null,$r['repeat_gpa_cap']??null,(int)($r['grade_rules_revision']??1)],$rows)
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

require_once __DIR__.'/academic.php';
