<?php
require_once 'auth.php';require_role(['super_admin']);require_once 'db.php';
header('Content-Type: application/sql; charset=utf-8');header('Content-Disposition: attachment; filename="student_result_backup_'.date('Ymd_His').'.sql"');
echo "-- Student Result System backup generated ".date('c')."\nSET FOREIGN_KEY_CHECKS=0;\n\n";
$tables=[];$r=mysqli_query($conn,'SHOW TABLES');while($x=mysqli_fetch_row($r))$tables[]=$x[0];
foreach($tables as $t){$cr=mysqli_fetch_assoc(mysqli_query($conn,'SHOW CREATE TABLE `'.str_replace('`','``',$t).'`'));$create=array_values($cr)[1]??'';echo "DROP TABLE IF EXISTS `{$t}`;\n{$create};\n";$rows=mysqli_query($conn,'SELECT * FROM `'.str_replace('`','``',$t).'`');while($row=mysqli_fetch_assoc($rows)){$cols=array_map(fn($c)=>'`'.str_replace('`','``',$c).'`',array_keys($row));$vals=array_map(fn($v)=>$v===null?'NULL':"'".mysqli_real_escape_string($conn,(string)$v)."'",array_values($row));echo 'INSERT INTO `'.$t.'` ('.implode(',',$cols).') VALUES ('.implode(',',$vals).");\n";}echo "\n";}
echo "SET FOREIGN_KEY_CHECKS=1;\n";audit_log($conn,'BACKUP_DOWNLOADED','database','student_result','Database backup downloaded');
// Include database protections in restorable backups; omit server-specific definers.
echo "\nDELIMITER $$\n";
$triggers=mysqli_query($conn,'SHOW TRIGGERS');
while($tr=mysqli_fetch_assoc($triggers)){
 $name=$tr['Trigger'];
 $definition=mysqli_fetch_assoc(mysqli_query($conn,'SHOW CREATE TRIGGER `'.str_replace('`','``',$name).'`'));
 $sql=$definition['SQL Original Statement']??'';
 $sql=preg_replace('/DEFINER\s*=\s*`[^`]*`@`[^`]*`\s*/i','',$sql);
 echo 'DROP TRIGGER IF EXISTS `'.str_replace('`','``',$name).'`$$'."\n".$sql.'$$'."\n";
}
echo "DELIMITER ;\n";
