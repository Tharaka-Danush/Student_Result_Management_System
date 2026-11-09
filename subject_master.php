<?php
require_once 'auth.php'; require_role(['super_admin','registrar']); require_once 'db.php';
$message='';$type='error';$edit=null;
if(isset($_GET['edit'])||isset($_GET['version'])){$eid=(int)($_GET['edit']??$_GET['version']);$st=mysqli_prepare($conn,'SELECT * FROM subjects WHERE id=?');mysqli_stmt_bind_param($st,'i',$eid);mysqli_stmt_execute($st);$edit=mysqli_fetch_assoc(mysqli_stmt_get_result($st));if($edit&&isset($_GET['version'])){$edit['id']=0;$edit['curriculum_batch']='';}}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try {

 if(!verify_csrf())$message='Invalid request.';else{
  $action=$_POST['action']??'save';
  if($action==='delete'){
   $id=(int)($_POST['id']??0);$c=mysqli_prepare($conn,'SELECT COUNT(*) c FROM results r JOIN subjects s ON s.id=r.subject_id WHERE s.id=?');mysqli_stmt_bind_param($c,'i',$id);mysqli_stmt_execute($c);$cnt=(int)(mysqli_fetch_assoc(mysqli_stmt_get_result($c))['c']??0);if($cnt>0){$message='This subject has result records. Deactivate it instead of deleting.';}else{$d=mysqli_prepare($conn,'DELETE FROM subjects WHERE id=?');mysqli_stmt_bind_param($d,'i',$id);mysqli_stmt_execute($d);audit_log($conn,'DELETE','subject',(string)$id,'Unused subject deleted');$message='Subject deleted.';$type='success';}
  } elseif($action==='toggle'){
   $id=(int)($_POST['id']??0);$st=mysqli_prepare($conn,'SELECT is_active,subject_code FROM subjects WHERE id=?');mysqli_stmt_bind_param($st,'i',$id);mysqli_stmt_execute($st);$r=mysqli_fetch_assoc(mysqli_stmt_get_result($st));
   if($r){$new=$r['is_active']?0:1;$u=mysqli_prepare($conn,'UPDATE subjects SET is_active=? WHERE id=?');mysqli_stmt_bind_param($u,'ii',$new,$id);mysqli_stmt_execute($u);audit_log($conn,'STATUS','subject',(string)$id,$r['subject_code'].' '.($new?'activated':'deactivated'));$message='Subject status updated.';$type='success';}
  } else {
   $id=(int)($_POST['id']??0);$code=strtoupper(trim($_POST['subject_code']??''));$name=trim($_POST['subject_name']??'');$credit=(int)($_POST['credit']??0);$year=(int)($_POST['year']??0);$semester=(int)($_POST['semester']??0);$section=$_POST['section']??'ALL';$batch=trim($_POST['curriculum_batch']??'');$includes=(string)($_POST['includes_gpa']??'1');
   if(!valid_subject_code($code)||$name===''||mb_strlen($name)>200||($batch!==''&&!preg_match('/^\d{2}$/',$batch))||!in_array($includes,['0','1'],true)||$credit<1||$credit>9||$year<1||$year>4||!in_array($semester,[1,2],true)||!in_array($section,['ALL','PS','BS'],true))$message='Please enter valid subject details.';
   else{
    $d=mysqli_prepare($conn,'SELECT id FROM subjects WHERE subject_code=? AND year=? AND semester=? AND section=? AND curriculum_batch=? AND id<>? LIMIT 1');mysqli_stmt_bind_param($d,'siissi',$code,$year,$semester,$section,$batch,$id);mysqli_stmt_execute($d);
    if(mysqli_num_rows(mysqli_stmt_get_result($d))>0)$message='This subject code already exists for this term, stream and curriculum batch.';else{
     $used=false;if($id){$chk=mysqli_prepare($conn,'SELECT id FROM results WHERE subject_id=? LIMIT 1');mysqli_stmt_bind_param($chk,'i',$id);mysqli_stmt_execute($chk);$used=(bool)mysqli_fetch_assoc(mysqli_stmt_get_result($chk));}
     if($used){$message='Subjects referenced by results cannot be renamed or remapped. Use New batch version to keep the historical subject unchanged.';}else{
     if($id){$u=mysqli_prepare($conn,'UPDATE subjects SET subject_code=?,subject_name=?,credit=?,year=?,semester=?,section=?,curriculum_batch=?,includes_gpa=? WHERE id=?');mysqli_stmt_bind_param($u,'ssiiissii',$code,$name,$credit,$year,$semester,$section,$batch,$includes,$id);$ok=mysqli_stmt_execute($u);$act='UPDATE';}
     else{$u=mysqli_prepare($conn,'INSERT INTO subjects(subject_code,subject_name,credit,year,semester,section,curriculum_batch,includes_gpa) VALUES(?,?,?,?,?,?,?,?)');mysqli_stmt_bind_param($u,'ssiiissi',$code,$name,$credit,$year,$semester,$section,$batch,$includes);$ok=mysqli_stmt_execute($u);$id=mysqli_insert_id($conn);$act='CREATE';}
     if($ok){audit_log($conn,$act,'subject',(string)$id,$code.' - '.$name);$message='Subject saved successfully.';$type='success';$edit=null;}else$message='Could not save subject.';}
    }
   }
  }
 }

 } catch(mysqli_sql_exception $ex) { mysqli_rollback($conn); $message='This change could not be saved. The record may be locked, protected or changed by another user. Refresh and try again.'; $type='error'; }
}
$q=trim($_GET['q']??'');$fy=(string)($_GET['fy']??'');$fs=(string)($_GET['fs']??'');$fsection=$_GET['fsection']??'ALL';$fstatus=$_GET['fstatus']??'';$fb=trim($_GET['fb']??'');$page=safe_page_int($_GET['page']??1);$per=20;$where=[];$params=[];$types='';
if($q!==''){$where[]='(subject_code LIKE ? OR subject_name LIKE ?)';$like='%'.$q.'%';$params[]=$like;$params[]=$like;$types.='ss';}
if(in_array($fy,['1','2','3','4'],true)){$where[]='year=?';$params[]=$fy;$types.='s';}
if(in_array($fs,['1','2'],true)){$where[]='semester=?';$params[]=$fs;$types.='s';}
if(in_array($fsection,['PS','BS'],true)){$where[]="section IN ('ALL',?)";$params[]=$fsection;$types.='s';}
if(in_array($fstatus,['active','inactive'],true)){$where[]='is_active=?';$params[]=$fstatus==='active'?1:0;$types.='i';}
if(preg_match('/^\d{2}$/',$fb)){$where[]='curriculum_batch=?';$params[]=$fb;$types.='s';}
$w=$where?' WHERE '.implode(' AND ',$where):'';$c=mysqli_prepare($conn,'SELECT COUNT(*) c FROM subjects'.$w);if($params)mysqli_stmt_bind_param($c,$types,...$params);mysqli_stmt_execute($c);$total=(int)(mysqli_fetch_assoc(mysqli_stmt_get_result($c))['c']??0);$pages=max(1,(int)ceil($total/$per));$page=min($page,$pages);$offset=($page-1)*$per;$st=mysqli_prepare($conn,'SELECT subjects.*, EXISTS(SELECT 1 FROM results r WHERE r.subject_id=subjects.id) AS has_results FROM subjects'.$w.' ORDER BY year,semester,section,subject_code LIMIT '.$per.' OFFSET '.$offset);if($params)mysqli_stmt_bind_param($st,$types,...$params);mysqli_stmt_execute($st);$res=mysqli_stmt_get_result($st);$subjects=[];while($x=mysqli_fetch_assoc($res))$subjects[]=$x;

$stats=mysqli_fetch_assoc(mysqli_query($conn,'SELECT COUNT(*) AS total,COALESCE(SUM(is_active=1),0) AS active,COALESCE(SUM(is_active=0),0) AS inactive FROM subjects'));
$filters=['q'=>$q,'fy'=>$fy,'fs'=>$fs,'fsection'=>$fsection,'fstatus'=>$fstatus,'fb'=>$fb,'page'=>$page];
$listUrl='subject_master.php?'.http_build_query($filters);
$editorOpen=(bool)$edit || ($message!=='' && $type==='error' && ($_POST['action']??'save')==='save');
$formValues=$edit??[];
if($editorOpen && $_SERVER['REQUEST_METHOD']==='POST' && $type==='error' && ($_POST['action']??'save')==='save'){
 foreach(['id','subject_code','subject_name','credit','year','semester','section','curriculum_batch','includes_gpa'] as $field)$formValues[$field]=$_POST[$field]??'';
}
$isEditing=(int)($formValues['id']??0)>0;
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Subject Master</title><link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="modern-ui.css?v=20261006-modern2"><link rel="stylesheet" href="subject-master.css?v=20261009-6"></head>
<body><div class="main-wrapper"><?php include 'admin_nav.php'; ?>
<main class="content subject-master">
<section class="page-heading"><div><span class="page-kicker">Academic Setup</span><h1>Subject Master</h1><p>Your subject catalog, organised by year, semester and science stream.</p></div>
</section>
<?php if($message):?><div role="status" class="message <?=$type?>"><?=e($message)?></div><?php endif;?>
<div class="subject-stats" aria-label="Subject catalog summary">
<div class="subject-stat"><span>Total subjects</span><strong><?=number_format((int)$stats['total'])?></strong><small>Complete catalog</small></div>
<div class="subject-stat"><span><i class="stat-dot active"></i> Active subjects</span><strong><?=number_format((int)$stats['active'])?></strong><small>Available for result entry</small></div>
<div class="subject-stat"><span><i class="stat-dot inactive"></i> Inactive subjects</span><strong><?=number_format((int)$stats['inactive'])?></strong><small>Retained in the catalog</small></div>
</div>
<section id="subject-editor" class="subject-editor <?=$editorOpen?'is-open':''?>">
<div class="subject-editor-heading"><button id="subject-editor-toggle" class="editor-indicator" type="button" aria-expanded="<?=$editorOpen?'true':'false'?>" aria-controls="subject-editor-form" aria-label="Toggle subject form"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button><span><strong><?=$isEditing?'Edit subject':'Add a new subject'?></strong><small>Code, credits and academic placement</small></span></div>
<form id="subject-editor-form" method="post" action="<?=e($listUrl)?>" class="subject-edit-form" <?=$editorOpen?'':'hidden'?>>
<input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e($formValues['id']??'')?>">
<div class="subject-editor-grid"><div><label for="subject-code">Subject code</label><input id="subject-code" name="subject_code" required pattern="^[A-Z]{3}-\d{5}$" maxlength="20" value="<?=e($formValues['subject_code']??'')?>" placeholder="CSM-11211"><p class="field-hint">Three letters and five digits, e.g. CSM-11211.</p></div>
<div class="subject-name-field"><label for="subject-name">Subject name</label><input id="subject-name" name="subject_name" required maxlength="200" value="<?=e($formValues['subject_name']??'')?>" placeholder="Enter the full subject title"></div>
<div><label for="subject-credit">Credits</label><input id="subject-credit" type="number" name="credit" min="1" max="9" required value="<?=e($formValues['credit']??3)?>"></div>
<div><label for="subject-year">Year</label><select id="subject-year" name="year" required><?php for($i=1;$i<=4;$i++):?><option value="<?=$i?>" <?=($formValues['year']??1)==$i?'selected':''?>>Year <?=$i?></option><?php endfor;?></select></div>
<div><label for="subject-semester">Semester</label><select id="subject-semester" name="semester" required><?php for($i=1;$i<=2;$i++):?><option value="<?=$i?>" <?=($formValues['semester']??1)==$i?'selected':''?>>Semester <?=$i?></option><?php endfor;?></select></div>
<div><label for="subject-section">Science stream</label><select id="subject-section" name="section"><option value="ALL" <?=($formValues['section']??'ALL')==='ALL'?'selected':''?>>Both streams</option><option value="PS" <?=($formValues['section']??'')==='PS'?'selected':''?>>Physical Science</option><option value="BS" <?=($formValues['section']??'')==='BS'?'selected':''?>>Biological Science</option></select></div></div>
<div class="form-grid-2"><div><label for="subject-batch">Curriculum batch</label><input id="subject-batch" name="curriculum_batch" maxlength="2" pattern="\d{2}" value="<?=e($formValues['curriculum_batch']??'')?>" placeholder="All batches, or e.g. 25"><div class="field-hint">A batch version takes priority over the shared subject.</div></div><div><label for="subject-gpa">GPA type</label><select id="subject-gpa" name="includes_gpa"><option value="1" <?=($formValues['includes_gpa']??1)==1?'selected':''?>>GPA subject</option><option value="0" <?=($formValues['includes_gpa']??1)==0?'selected':''?>>Non-GPA / Auxiliary</option></select></div></div><div class="editor-footer"><p>Subjects with result records are protected from editing and deletion.</p><div class="button-row"><a class="btn-link btn-secondary" href="<?=e($listUrl)?>">Cancel</a><button type="submit"><?=$isEditing?'Save Changes':'Save Subject'?></button></div></div>
</form></section>
<section class="subject-catalog" aria-labelledby="catalog-heading">
<div class="catalog-heading"><div><h2 id="catalog-heading">Subject catalog</h2></div><span class="catalog-count"><?=number_format($total)?> matching</span></div>
<form method="get" class="subject-filters"><div class="subject-filter-grid">
<div class="subject-search"><label for="filter-search">Search subjects</label><input id="filter-search" name="q" value="<?=e($q)?>" placeholder="Search by code or subject name"></div>
<div><label for="filter-year">Year</label><select id="filter-year" name="fy"><option value="">All years</option><?php for($i=1;$i<=4;$i++):?><option value="<?=$i?>" <?=$fy===(string)$i?'selected':''?>>Year <?=$i?></option><?php endfor;?></select></div>
<div><label for="filter-semester">Semester</label><select id="filter-semester" name="fs"><option value="">All semesters</option><option value="1" <?=$fs==='1'?'selected':''?>>Semester 1</option><option value="2" <?=$fs==='2'?'selected':''?>>Semester 2</option></select></div>
<div><label for="filter-section">Science stream</label><select id="filter-section" name="fsection"><option value="ALL">Both streams</option><option value="PS" <?=$fsection==='PS'?'selected':''?>>Physical Science</option><option value="BS" <?=$fsection==='BS'?'selected':''?>>Biological Science</option></select></div>
<div><label for="filter-status">Status</label><select id="filter-status" name="fstatus"><option value="">All statuses</option><option value="active" <?=$fstatus==='active'?'selected':''?>>Active</option><option value="inactive" <?=$fstatus==='inactive'?'selected':''?>>Inactive</option></select></div></div>
<label for="filter-batch">Curriculum batch</label><input id="filter-batch" name="fb" value="<?=e($fb)?>" maxlength="2" pattern="\d{2}" placeholder="All versions, or e.g. 25"><div class="filter-footer"><div class="button-row"><a class="btn-link btn-secondary" href="subject_master.php">Clear</a><button type="submit">Apply Filters</button></div></div></form>
<div class="table-wrapper subject-table-wrap"><table class="results-table subject-table"><thead><tr><th scope="col">Subject code</th><th scope="col">Subject name</th><th scope="col" class="credit-cell">Credits</th><th scope="col">Academic term</th><th scope="col">Stream</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach($subjects as $s):?><tr><td class="subject-code-cell"><strong><?=e($s['subject_code'])?></strong></td><td class="subject-title-cell"><?=e($s['subject_name'])?></td><td class="credit-cell"><span class="credit-badge"><?=e($s['credit'])?></span><?php if(!(int)$s['includes_gpa']):?><small>Non-GPA</small><?php endif;?></td><td class="subject-term-cell"><strong>Year <?=e($s['year'])?></strong><small>Semester <?=e($s['semester'])?></small><small><?=e($s['curriculum_batch']!==''?'Batch '.$s['curriculum_batch']:'Shared curriculum')?></small></td><td><span class="stream-badge stream-<?=e(strtolower($s['section']))?>"><?= $s['section']==='ALL'?'Both':e($s['section']) ?></span></td><td><span class="status-pill <?=$s['is_active']?'published':'withheld'?>"><i aria-hidden="true"></i><?=$s['is_active']?'Active':'Inactive'?></span></td>
<td><div class="subject-actions"><a class="btn-link btn-secondary small-btn" href="<?=e('subject_master.php?'.http_build_query(array_merge($filters,['version'=>(int)$s['id']])).'#subject-editor')?>">New batch version</a><?php if(!$s['has_results']):?><a class="btn-link btn-secondary small-btn" href="<?=e('subject_master.php?'.http_build_query(array_merge($filters,['edit'=>(int)$s['id']])).'#subject-editor')?>">Edit</a><?php else:?><span class="protected-label" title="This subject has result records and cannot be edited or deleted.">Protected</span><?php endif;?>
<form method="post" action="<?=e($listUrl)?>" class="inline-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$s['id']?>"><button class="btn-secondary small-btn" type="submit" aria-label="<?=e(($s['is_active']?'Deactivate ':'Activate ').$s['subject_code'])?>"><?=$s['is_active']?'Deactivate':'Activate'?></button></form>
<?php if(!$s['has_results']):?><form method="post" action="<?=e($listUrl)?>" class="inline-form" onsubmit="return confirm('Delete this unused subject?');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$s['id']?>"><button class="small-btn subject-delete" type="submit" aria-label="<?=e('Delete '.$s['subject_code'])?>">Delete</button></form><?php endif;?></div></td></tr><?php endforeach;?>
<?php if(!$subjects):?><tr><td colspan="7"><div class="subject-empty"><strong>No subjects found</strong><p>Try a different search or clear your filters.</p><a class="btn-link btn-secondary" href="subject_master.php">Clear Filters</a></div></td></tr><?php endif;?></tbody></table></div>
<div class="catalog-footer"><nav class="subject-pagination" aria-label="Subject pages"><?php if($page>1):?><a class="filter-chip" href="<?=e('?'.http_build_query(array_merge($filters,['page'=>$page-1])))?>">Previous</a><?php endif;?><?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++):?><a class="filter-chip <?=$i===$page?'current':''?>" <?=$i===$page?'aria-current="page"':''?> href="<?=e('?'.http_build_query(array_merge($filters,['page'=>$i])))?>"><?=$i?></a><?php endfor;?><?php if($page<$pages):?><a class="filter-chip" href="<?=e('?'.http_build_query(array_merge($filters,['page'=>$page+1])))?>">Next</a><?php endif;?></nav></div>
</section></main><?php include 'footer.php';?></div>
<script>
(function () {
 const button = document.getElementById('subject-editor-toggle');
 const panel = document.getElementById('subject-editor');
 const form = document.getElementById('subject-editor-form');
 button.addEventListener('click', function () {
  const expanded = button.getAttribute('aria-expanded') !== 'true';
  button.setAttribute('aria-expanded', String(expanded));
  form.hidden = !expanded;
  panel.classList.toggle('is-open', expanded);
 });
})();
</script></body></html>
