<?php
// Academic policies, subject resolution and repeat attempts shared by every entry route.
function academic_setting($conn, string $key, string $value): void {
    $st=mysqli_prepare($conn,'INSERT INTO system_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    mysqli_stmt_bind_param($st,'ss',$key,$value); mysqli_stmt_execute($st);
}
function validate_grade_rules(array $rows): void {
    $rank=['A+','A','A-','B+','B','B-','C+','C','C-','D+','D','E'];
    $active=array_values(array_filter($rows,fn($r)=>(int)$r['is_active']===1));
    usort($active,fn($a,$b)=>array_search(normalize_grade($a['grade']),$rank,true)<=>array_search(normalize_grade($b['grade']),$rank,true));
    if(!$active) throw new DomainException('At least one grade rule is required.');
    $lastMark=101; $lastGpa=4; $seen=[];
    foreach($active as $r){
        $name=normalize_grade($r['grade']); $mark=(float)$r['min_mark']; $gpa=(float)$r['gpa'];
        if(!in_array($name,$rank,true)||isset($seen[$name])||!is_finite($mark)||!is_finite($gpa)||$mark<0||$mark>100||$gpa<0||$gpa>4||$mark>=$lastMark||$gpa>$lastGpa)
            throw new DomainException('Grade marks must decrease strictly from A+ to E; GPA must not increase toward lower grades. Duplicate boundaries are not allowed.');
        $seen[$name]=true;$lastMark=$mark;$lastGpa=$gpa;
    }
    if($lastMark!=0) throw new DomainException('The lowest active grade must start at 0 marks.');
}
function validate_class_rules(array $rows): void {
    $active=array_values(array_filter($rows,fn($r)=>(int)$r['is_active']===1));
    usort($active,fn($a,$b)=>(int)$a['sort_order']<=>(int)$b['sort_order']);
    $last=5;$orders=[];
    if(!$active) throw new DomainException('At least one class rule is required.');
    foreach($active as $r){$value=(float)$r['min_gpa'];$order=(int)$r['sort_order'];
        if(!is_finite($value)||$value<0||$value>4||$value>=$last||isset($orders[$order])) throw new DomainException('Class boundaries must decrease strictly in class order. Duplicate boundaries are not allowed.');
        $last=$value;$orders[$order]=true;
    }
    if($last!=0) throw new DomainException('The lowest class must start at GPA 0.');
}
function update_academic_rule($conn,string $type,int $id,$minimum,$gpa=null): void {
    if(!is_numeric($minimum)||!is_finite((float)$minimum)||($type==='grade'&&(!is_numeric($gpa)||!is_finite((float)$gpa)))) throw new DomainException('Enter numeric rule values.');
    if((string)round((float)$minimum,2)!=(string)(float)$minimum||($type==='grade'&&(string)round((float)$gpa,2)!=(string)(float)$gpa)) throw new DomainException('Use at most two decimal places.');
    $table=$type==='grade'?'grade_scales':'class_rules';$column=$type==='grade'?'min_mark':'min_gpa';
    mysqli_begin_transaction($conn);
    try {
        // Serialize all rule writers through one revision row before locking the scale.
        $revision=mysqli_fetch_assoc(mysqli_query($conn,"SELECT setting_value FROM system_settings WHERE setting_key='academic_rules_revision' FOR UPDATE"));
        $rs=mysqli_query($conn,"SELECT * FROM $table ORDER BY sort_order,id FOR UPDATE");$rows=[];$found=false;
        while($r=mysqli_fetch_assoc($rs)){if((int)$r['id']===$id){$r[$column]=(float)$minimum;if($type==='grade')$r['gpa']=(float)$gpa;$found=true;}$rows[]=$r;}
        if(!$found) throw new DomainException('Rule not found.');
        if($type==='grade') {
            validate_grade_rules($rows);
            $configured=app_setting($conn,'repeat_gpa_cap','');
            if($configured!==''&&!array_filter($rows,fn($r)=>(int)$r['is_active']===1&&(float)$r['gpa']===(float)$configured))throw new DomainException('Keep an active grade matching the configured repeat GPA cap, or change the cap first.');
        } else validate_class_rules($rows);
        $st=mysqli_prepare($conn,$type==='grade'?'UPDATE grade_scales SET min_mark=?,gpa=? WHERE id=?':'UPDATE class_rules SET min_gpa=? WHERE id=?');
        $minimum=(float)$minimum;$gpa=(float)$gpa;
        if($type==='grade')mysqli_stmt_bind_param($st,'ddi',$minimum,$gpa,$id);else mysqli_stmt_bind_param($st,'di',$minimum,$id);
        mysqli_stmt_execute($st);
        if(mysqli_stmt_affected_rows($st)>0){
            academic_setting($conn,'academic_rules_revision',(string)((int)($revision['setting_value']??1)+1));
            if($type==='class')mysqli_query($conn,'UPDATE document_verifications SET revoked_at=NOW() WHERE revoked_at IS NULL');
            audit_log($conn,'UPDATE',$table,(string)$id,"Validated boundary=$minimum GPA=$gpa; historical grades retained");
        }
        mysqli_commit($conn);
    }catch(Throwable $e){mysqli_rollback($conn);throw $e;}
}
function effective_result_rows(array $rows, ?string $policy=null): array {
    global $conn;
    $policy=$policy??(isset($conn)?app_setting($conn,'repeat_selection_policy','latest'):'latest');
    if(!in_array($policy,['latest','highest','first_pass'],true))$policy='latest';
    $minimum=isset($conn)?(float)app_setting($conn,'minimum_pass_gpa','2.00'):2.00;
    $groups=[];
    foreach($rows as $r){$key=implode('|',[$r['student_id']??'', $r['year']??'', $r['semester']??'', $r['subject_code']??($r['id']??count($groups))]);$groups[$key][]=$r;}
    $selected=[];
    foreach($groups as $attempts){
        usort($attempts,fn($a,$b)=>[(int)($a['attempt_no']??1),(int)($a['id']??0)]<=>[(int)($b['attempt_no']??1),(int)($b['id']??0)]);
        $row=end($attempts);
        if($policy==='highest')foreach($attempts as $a)if((float)$a['gpa']>(float)$row['gpa'])$row=$a;
        if($policy==='first_pass')foreach($attempts as $a)if((float)$a['gpa']>=$minimum){$row=$a;break;}
        $selected[]=$row;
    }
    usort($selected,fn($a,$b)=>[$a['year']??'', $a['semester']??'', $a['subject_code']??'']<=>[$b['year']??'', $b['semester']??'', $b['subject_code']??'']);
    return $selected;
}
function academic_gpa_summary(array $rows): array {
    $credits=0;$weighted=0;$total=0;
    foreach(effective_result_rows($rows) as $r){$credit=(int)($r['credit']??0);$total+=$credit;if((int)($r['includes_gpa']??1)!==1)continue;$credits+=$credit;$weighted+=$credit*(float)($r['gpa']??0);}
    $gpa=$credits>0?round($weighted/$credits,2):0.0;
    return ['gpa'=>$gpa,'class'=>$credits>0?gpa_class($gpa):'Not applicable','credits'=>$total,'gpa_credits'=>$credits,'has_gpa'=>$credits>0];
}
function gpa_display(array $summary): string {return ($summary['has_gpa']??((int)$summary['credits']>0))?number_format($summary['gpa'],2):'N/A';}
function result_gpa_display(array $row): string {return (int)($row['includes_gpa']??1)===1?number_format((float)$row['gpa'],2):'Non-GPA';}
function result_attempt_label(array $row): string {
    $attempt=(int)($row['attempt_no']??1);$text=$attempt>1?'Repeat '.$attempt:'First attempt';
    if(!empty($row['exam_date']))$text.=' · '.$row['exam_date'];
    return $text;
}
function semester_result_status(array $rows): string {
    global $conn;
    foreach(effective_result_rows($rows) as $r)if((float)$r['gpa']<(float)app_setting($conn,'minimum_pass_gpa','2.00'))return 'Repeat';
    return $rows?'Pass':'Not available';
}
function resolve_subject($conn,array $student,string $code,string $year,string $sem): ?array {
    // Pick batch and stream precedence BEFORE checking active status: an inactive override blocks fallback.
    $st=mysqli_prepare($conn,"SELECT * FROM subjects WHERE subject_code=? AND year=? AND semester=? AND (curriculum_batch='' OR curriculum_batch=?) AND (section='ALL' OR section=?) ORDER BY (curriculum_batch=?) DESC,(section=?) DESC,id DESC LIMIT 1");
    mysqli_stmt_bind_param($st,'sssssss',$code,$year,$sem,$student['batch'],$student['section'],$student['batch'],$student['section']);mysqli_stmt_execute($st);
    $sub=mysqli_fetch_assoc(mysqli_stmt_get_result($st));return $sub&&(int)$sub['is_active']===1?$sub:null;
}
function result_entry_data($conn,string $sid,string $year,string $sem,string $code,$marks,string $kind,string $date='',int $subjectId=0,bool $lock=false): array {
    if(!valid_student_id($sid)||!valid_year_semester($year,$sem)||!valid_subject_code($code)||!is_numeric($marks)||!is_finite((float)$marks)||$marks<0||$marks>100||(float)$marks!==round((float)$marks,2)||!in_array($kind,['original','repeat'],true))throw new DomainException('Check student, subject, term and marks (maximum two decimal places).');
    if($date!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))||$date>date('Y-m-d')))throw new DomainException('Enter a valid examination date that is not in the future.');
    $st=mysqli_prepare($conn,'SELECT * FROM students WHERE student_id=?'.($lock?' FOR UPDATE':''));mysqli_stmt_bind_param($st,'s',$sid);mysqli_stmt_execute($st);$student=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if(!$student||(int)$student['is_active']!==1)throw new DomainException('Student does not exist or is inactive.');
    if(semester_is_locked($conn,$year,$sem,$student['batch'],$student['section']))throw new DomainException('This semester is locked.');
    $st=mysqli_prepare($conn,'SELECT * FROM results WHERE student_id=? AND year=? AND semester=? AND subject_code=? ORDER BY attempt_no DESC LIMIT 1');mysqli_stmt_bind_param($st,'ssss',$sid,$year,$sem,$code);mysqli_stmt_execute($st);$previous=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if($kind==='original'&&$previous)throw new DomainException('This subject result already exists. Select Repeat attempt to record another examination.');
    if($kind==='repeat'){
        if(!$previous||$previous['approval_status']!=='approved'||$previous['publish_status']!=='published')throw new DomainException('A repeat needs an approved and published previous attempt.');
        if($date==='')throw new DomainException('An examination date is required for a repeat.');
        if(!empty($previous['exam_date'])&&$date<=$previous['exam_date'])throw new DomainException('Repeat date must be after the previous examination date.');
        if(app_setting($conn,'allow_repeat_after_pass','0')!=='1'&&(float)$previous['gpa']>=(float)app_setting($conn,'minimum_pass_gpa','2.00'))throw new DomainException('Repeats after a passing result are disabled in Academic Rules.');
        $st=mysqli_prepare($conn,'SELECT * FROM results WHERE student_id=? AND year=? AND semester=? AND subject_code=? AND attempt_no=1');mysqli_stmt_bind_param($st,'ssss',$sid,$year,$sem,$code);mysqli_stmt_execute($st);$base=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        if(!$base)throw new DomainException('First attempt is missing. Contact the Registrar.');
        if($subjectId&&$subjectId!==(int)$base['subject_id'])throw new DomainException('For a repeat select the original subject version shown in the result history.');
        $sub=['id'=>$base['subject_id'],'subject_code'=>$base['subject_code'],'subject_name'=>$base['subject'],'credit'=>$base['credit'],'includes_gpa'=>$base['includes_gpa']];
    }else{
        $sub=resolve_subject($conn,$student,$code,$year,$sem);
        if(!$sub||($subjectId&&(int)$sub['id']!==$subjectId))throw new DomainException('Select the active subject version for this student batch and stream.');
    }
    $attempt=$kind==='repeat'?(int)$previous['attempt_no']+1:1;
    if($attempt>99)throw new DomainException('Maximum attempt number reached.');
    [$grade,$gpa]=grade_from_marks((float)$marks);$cap=null;
    if($attempt>1){$setting=app_setting($conn,'repeat_gpa_cap','');if($setting!==''){$cap=(float)$setting;[$grade,$gpa]=apply_repeat_cap($conn,$grade,$gpa,$cap);}}
    return ['student_id'=>$sid,'year'=>$year,'semester'=>$sem,'subject_id'=>(int)$sub['id'],'subject_code'=>$code,'subject'=>$sub['subject_name'],'credit'=>(int)$sub['credit'],'includes_gpa'=>(int)$sub['includes_gpa'],'marks'=>(float)$marks,'grade'=>$grade,'gpa'=>(float)$gpa,'attempt_no'=>$attempt,'exam_date'=>$date?:null,'repeat_gpa_cap'=>$cap,'grade_rules_revision'=>(int)app_setting($conn,'academic_rules_revision','1')];
}
function apply_repeat_cap($conn,string $grade,float $gpa,?float $cap): array {
    if($cap===null||$gpa<=$cap)return [$grade,$gpa];
    $st=mysqli_prepare($conn,'SELECT grade,gpa FROM grade_scales WHERE is_active=1 AND gpa<=? ORDER BY gpa DESC,min_mark DESC LIMIT 1');mysqli_stmt_bind_param($st,'d',$cap);mysqli_stmt_execute($st);$r=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if(!$r)throw new DomainException('Repeat GPA cap does not match an active grade.');
    return [normalize_grade($r['grade']),(float)$r['gpa']];
}
function create_result_record($conn,array $input): int {
    mysqli_begin_transaction($conn);
    try {
        $r=result_entry_data($conn,$input['student_id'],$input['year'],$input['semester'],$input['subject_code'],$input['marks'],$input['entry_kind'],$input['exam_date']??'',(int)($input['subject_id']??0),true);
        $by=(string)$_SESSION['admin_username'];
        $st=mysqli_prepare($conn,"INSERT INTO results(subject_id,student_id,year,semester,subject_code,subject,marks,grade,gpa,credit,includes_gpa,attempt_no,exam_date,repeat_gpa_cap,grade_rules_revision,publish_status,approval_status,submitted_by,submitted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft','pending',?,NOW())");
        mysqli_stmt_bind_param($st,'isssssdsdiiisdis',$r['subject_id'],$r['student_id'],$r['year'],$r['semester'],$r['subject_code'],$r['subject'],$r['marks'],$r['grade'],$r['gpa'],$r['credit'],$r['includes_gpa'],$r['attempt_no'],$r['exam_date'],$r['repeat_gpa_cap'],$r['grade_rules_revision'],$by);
        mysqli_stmt_execute($st);$id=(int)mysqli_insert_id($conn);revoke_student_verifications($conn,$r['student_id']);audit_log($conn,'CREATE','result',(string)$id,$r['student_id'].' '.$r['subject_code'].' attempt '.$r['attempt_no'].' pending/draft');mysqli_commit($conn);return $id;
    }catch(Throwable $e){mysqli_rollback($conn);throw $e;}
}
