<?php
require_once 'auth.php';require_role(['super_admin','data_entry']);require_once 'db.php';$message='';$type='error';$preview=$_SESSION['bulk_preview']??null;
if(isset($_GET['template'])){header('Content-Type:text/csv');header('Content-Disposition:attachment; filename="result_import_template.csv"');echo "student_id,year,semester,subject_code,marks,publish_status,entry_kind,exam_date\nSEU/IS/25/PS/001,1,1,CSS-11013,78,draft,original,\n";exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try {
  if(!verify_csrf())throw new DomainException('Invalid request.');
  $action=$_POST['action']??'preview';
  if($action==='cancel'){unset($_SESSION['bulk_preview']);$preview=null;$message='Preview cleared.';$type='success';}
  elseif($action==='commit'){
   $preview=$_SESSION['bulk_preview']??null;
   if(!$preview||empty($preview['valid']))throw new DomainException('No valid preview is available.');
   $ok=0;$failed=0;$commitErrors=[];
   foreach($preview['valid'] as $r){
    try{create_result_record($conn,['student_id'=>$r[0],'year'=>$r[1],'semester'=>$r[2],'subject_code'=>$r[3],'marks'=>$r[4],'entry_kind'=>$r[11],'exam_date'=>$r[12]]);$ok++;}
    catch(DomainException $ex){$failed++;$commitErrors[]=$r[0].' '.$r[3].': '.$ex->getMessage();}
    catch(mysqli_sql_exception $ex){$failed++;$commitErrors[]=$r[0].' '.$r[3].': Database rejected the changed or duplicate record.';}
   }
   audit_log($conn,'BULK_IMPORT','result','',"$ok imported, $failed failed after preview");unset($_SESSION['bulk_preview']);
   $preview=$commitErrors?['valid'=>[],'errors'=>$commitErrors]:null;
   $message="$ok results imported successfully; $failed rows failed at commit.";$type=$failed?'error':'success';
  }else{
   if(empty($_FILES['csv_file']['tmp_name'])||!is_uploaded_file($_FILES['csv_file']['tmp_name']))throw new DomainException('Select a CSV file.');
   $fh=fopen($_FILES['csv_file']['tmp_name'],'r');
   try {
    $header=array_map(fn($x)=>strtolower(trim((string)$x)),fgetcsv($fh)?:[]);
    $legacy=['student_id','year','semester','subject_code','marks','publish_status'];$new=array_merge($legacy,['entry_kind','exam_date']);
    if($header!==$legacy&&$header!==$new)throw new DomainException('CSV columns must match the six-column legacy template or the new eight-column template.');
    $valid=[];$errors=[];$seen=[];$line=1;
    while(($row=fgetcsv($fh))!==false){$line++;if(count($row)===1&&trim((string)$row[0])==='')continue;
     if(count($valid)+count($errors)>=2000){$errors[]='Import stopped at 2000 rows.';break;}
     if(count($row)!==count($header)){$errors[]="Row $line: wrong number of columns.";continue;}
     $row=array_map('trim',$row);[$sid,$year,$sem,$code,$marks,$ignoredStatus]=$row;$sid=strtoupper($sid);$code=strtoupper($code);$kind=$row[6]??'original';$date=$row[7]??'';
     $key=implode('|',[$sid,$year,$sem,$code]);
     try {
      if(isset($seen[$key]))throw new DomainException('Only one new attempt per student/subject is allowed in one upload.');
      $r=result_entry_data($conn,$sid,$year,$sem,$code,$marks,$kind,$date);
      $seen[$key]=true;$valid[]=[$sid,$year,$sem,$code,$r['marks'],'draft',$r['subject'],$r['credit'],$r['grade'],$r['gpa'],'pending',$kind,$date,$r['attempt_no'],$r['includes_gpa']];
     }catch(DomainException $ex){$errors[]="Row $line: ".$ex->getMessage();}
    }
   }finally{fclose($fh);}
   $_SESSION['bulk_preview']=['valid'=>$valid,'errors'=>$errors,'file'=>$_FILES['csv_file']['name']??'upload.csv'];$preview=$_SESSION['bulk_preview'];$message='Preview ready. Review validation results before committing.';$type='success';
  }
 }catch(DomainException $ex){$message=$ex->getMessage();}catch(mysqli_sql_exception $ex){$message='Could not process this import. Check the academic database upgrade.';}
}

?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bulk Result Upload</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="modern-ui.css?v=20261006-modern2"></head><body><div class="main-wrapper"><?php include 'admin_nav.php';?><main class="content"><section class="page-heading"><div><span class="page-kicker">Controlled Import</span><h1>Bulk Result Upload</h1><p>Upload → validate → preview → commit. Nothing is inserted during preview.</p></div></section><?php if($message):?><div class="message <?=$type?>"><?=e($message)?></div><?php endif;?><section class="feature-card"><a class="btn-link btn-secondary" href="?template=1">Download CSV Template</a><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="preview"><label>CSV File</label><input type="file" name="csv_file" accept=".csv,text/csv" required><div class="field-hint">Columns: student_id, year, semester, subject_code, marks, publish_status, entry_kind, exam_date. Legacy six-column files are also supported. Repeat dates are required; every row is revalidated at commit.</div><div class="button-row"><input type="submit" value="Validate & Preview"></div></form></section><?php if($preview):?><section class="feature-card" style="margin-top:20px"><h2>Preview Summary</h2><div class="metric-grid"><div class="metric-card"><span>Valid Rows</span><strong><?=count($preview['valid'])?></strong></div><div class="metric-card"><span>Invalid Rows</span><strong><?=count($preview['errors'])?></strong></div></div><?php if($preview['valid']):?><div class="table-wrapper"><table><thead><tr><th>Student</th><th>Term</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Approval</th><th>Publish</th></tr></thead><tbody><?php foreach(array_slice($preview['valid'],0,100) as $r):?><tr><td><?=e($r[0])?></td><td>Y<?=e($r[1])?> S<?=e($r[2])?></td><td><?=e($r[3])?><br><small><?=e($r[11].' · attempt '.$r[13])?><?=((int)$r[14]?'':' · Non-GPA')?></small></td><td><?=e($r[4])?></td><td><?=e($r[8])?></td><td><?=e(approval_status_label($r[10]))?></td><td><?=e(result_status_label($r[5]))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?><?php if($preview['errors']):?><details open><summary>Validation errors</summary><ul><?php foreach(array_slice($preview['errors'],0,100) as $er):?><li><?=e($er)?></li><?php endforeach;?></ul></details><?php endif;?><div class="button-row"><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><button class="btn-link btn-secondary" name="action" value="cancel">Cancel Preview</button></form><?php if($preview['valid']):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><button name="action" value="commit">Commit Valid Rows</button></form><?php endif;?></div></section><?php endif;?></main><?php include 'footer.php';?></div></body></html>