<?php
// Keep database secrets outside the publicly served project. No root/blank-password fallback.
$configPath=getenv('SRS_DB_CONFIG') ?: dirname(__DIR__,2).DIRECTORY_SEPARATOR.'student_result_private'.DIRECTORY_SEPARATOR.'db-config.php';
$config=is_file($configPath)?require $configPath:[];
$config=is_array($config)?$config:[];
$get=function(string $key,string $env,$default='')use($config){$value=getenv($env);return $value!==false?$value:($config[$key]??$default);};
$host=(string)$get('host','SRS_DB_HOST','localhost');$user=(string)$get('user','SRS_DB_USER');$pass=(string)$get('password','SRS_DB_PASSWORD');$db=(string)$get('database','SRS_DB_NAME','student_result');$port=(int)$get('port','SRS_DB_PORT',3306);
if($user===''||strtolower($user)==='root'||$pass===''){
    http_response_code(503);
    exit('Database configuration is required. Run setup_database.php from the XAMPP PHP command line to create the application account.');
}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
try{$conn=mysqli_connect($host,$user,$pass,$db,$port);mysqli_set_charset($conn,'utf8mb4');}
catch(mysqli_sql_exception $ex){http_response_code(503);exit('Database connection failed. Check the private database configuration.');}
