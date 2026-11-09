<?php
require_once 'auth.php'; require_role(['super_admin','data_entry']); require_once 'db.php';
$message=''; $type='error';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {

    $student_id=strtoupper(trim($_POST['student_id']??''));
    $index=strtoupper(trim($_POST['index_number']??''));
    $name=trim($_POST['name']??'');
    $pin=trim($_POST['pin']??'');
    $nic=normalize_nic($_POST['nic_number']??'');
    if (!verify_csrf()) $message='Invalid request. Please try again.';
    elseif (!valid_student_id($student_id)) $message='Invalid Student ID format.';
    elseif (!valid_index_number($index)) $message='Invalid Index Number format.';
    elseif ($name==='' || mb_strlen($name)>100) $message='Enter a valid student name.';
    elseif (!preg_match('/^\d{4,8}$/',$pin)) $message='PIN must contain 4 to 8 digits.';
    elseif (!valid_nic($nic)) $message='Enter a valid NIC number (old 9 digits + V/X, or new 12 digits).';
    else {
        $parts=student_id_parts($student_id);
        if (substr($index,0,2)!==$parts['section']) $message='Student ID section and Index Number section must match.';
        else {
            $stmt=mysqli_prepare($conn,'SELECT student_id FROM students WHERE student_id=? OR index_number=? LIMIT 1');
            mysqli_stmt_bind_param($stmt,'ss',$student_id,$index); mysqli_stmt_execute($stmt);
            if(mysqli_num_rows(mysqli_stmt_get_result($stmt))>0) $message='Student ID or Index Number already exists.';
            else {
                $pin_hash=password_hash($pin,PASSWORD_DEFAULT);
                $nic_hash=password_hash($nic,PASSWORD_DEFAULT);
                $stmt=mysqli_prepare($conn,'INSERT INTO students (student_id,name,index_number,batch,section,pin_hash,nic_hash,nic_updated_at) VALUES (?,?,?,?,?,?,?,NOW())');
                mysqli_stmt_bind_param($stmt,'sssssss',$student_id,$name,$index,$parts['batch'],$parts['section'],$pin_hash,$nic_hash);
                if(mysqli_stmt_execute($stmt)){
                    audit_log($conn,'CREATE','student',$student_id,'Student record created with PIN and NIC verification enabled');
                    $message='Student added successfully. PIN and NIC verification are configured.';
                    $type='success';
                } else $message='Could not add student.';
            }
        }
    }

 } catch(mysqli_sql_exception $ex) { mysqli_rollback($conn); $message='This change could not be saved. The record may be locked, protected or changed by another user. Refresh and try again.'; $type='error'; }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Add Student</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="modern-ui.css?v=20261006-modern2"></head><body><div class="main-wrapper"><?php include 'admin_nav.php'; ?><main class="content"><section class="feature-card"><h2>Add Student</h2><form method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>Student ID</label><input type="text" name="student_id" required pattern="^SEU/IS/\d{2}/(PS|BS)/\d{3}$" placeholder="SEU/IS/25/PS/001" value="<?=e($_POST['student_id']??'')?>"><label>Index Number</label><input type="text" name="index_number" required pattern="^(PS|BS)-\d{4}$" placeholder="PS-0001" value="<?=e($_POST['index_number']??'')?>"><label>Name</label><input type="text" name="name" maxlength="100" required value="<?=e($_POST['name']??'')?>"><label>NIC Number</label><input type="text" name="nic_number" required maxlength="14" placeholder="200012345678 or 901234567V" value="" autocomplete="off"><div class="field-hint">NIC is normalized and stored only as a secure hash for identity verification.</div><label>Student PIN</label><input type="password" name="pin" required inputmode="numeric" pattern="\d{4,8}" minlength="4" maxlength="8" placeholder="4-8 digit secure PIN" autocomplete="new-password"><div class="field-hint">PIN is stored securely as a password hash.</div><div class="button-row"><a href="admin_dashboard.php" class="btn-link btn-secondary">Back</a><input type="submit" value="Add Student"></div></form><?php if($message):?><div class="message <?=$type?>"><?=e($message)?></div><?php endif;?></section></main><?php include 'footer.php';?></div></body></html>