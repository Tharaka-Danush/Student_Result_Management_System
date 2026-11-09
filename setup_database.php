<?php
// Run only in a terminal. Privileged credentials are used once and never written.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function ask(string $label,string $default=''): string {echo $label.($default!==''?' ['.$default.']':'').': ';$line=fgets(STDIN);if($line===false)throw new RuntimeException('Input ended.');$value=trim($line);return $value!==''?$value:$default;}
function secret(string $label): string {
    echo $label.': ';
    if(PHP_OS_FAMILY==='Windows'){
        // PowerShell SecureString keeps the bootstrap password out of terminal echo and command arguments.
        $script='$p=Read-Host -AsSecureString; $b=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); try {[Console]::WriteLine(\'SRS_SECRET:\'+[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes([Runtime.InteropServices.Marshal]::PtrToStringBSTR($b))))} finally {[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)}';
        $encoded=base64_encode(iconv('UTF-8','UTF-16LE',$script));
        $process=proc_open('powershell.exe -NoProfile -EncodedCommand '.$encoded,[0=>STDIN,1=>['pipe','w'],2=>STDERR],$pipes);
        if(!is_resource($process))throw new RuntimeException('Could not open the secure password prompt.');
        $output=stream_get_contents($pipes[1]);fclose($pipes[1]);if(proc_close($process)!==0||!preg_match('~SRS_SECRET:([A-Za-z0-9+/=]*)~',$output,$match))throw new RuntimeException('Secure password prompt failed.');return (string)base64_decode($match[1],true);
    }
    $tty=function_exists('stream_isatty')&&stream_isatty(STDIN);
    if($tty)shell_exec('stty -echo');
    try{$line=fgets(STDIN);if($line===false)throw new RuntimeException('Input ended.');return rtrim($line,"\r\n");}finally{if($tty){shell_exec('stty echo');echo PHP_EOL;}}
}
try{
    $default=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'student_result_private'.DIRECTORY_SEPARATOR.'db-config.php';
    $args=getopt('',['config:']);$path=$args['config']??$default;
    if(file_exists($path))throw new RuntimeException('Configuration already exists. Keep it, or choose a new --config path.');
    $project=realpath(__DIR__);$dir=dirname($path);
    if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Could not create private configuration directory.');
    $real=realpath($dir);$path=$real.DIRECTORY_SEPARATOR.basename($path);
    // Prevent common accidental storage anywhere under the Apache document root.
    $documentRoot=dirname(__DIR__);$htdocs=stripos($documentRoot,'htdocs')!==false;
    if(str_starts_with(strtolower($real.DIRECTORY_SEPARATOR),strtolower($project.DIRECTORY_SEPARATOR))||($htdocs&&str_starts_with(strtolower($real.DIRECTORY_SEPARATOR),strtolower($documentRoot.DIRECTORY_SEPARATOR))))throw new RuntimeException('Choose a configuration directory outside htdocs and outside this project.');
    $host=ask('Database host','localhost');$port=(int)ask('Database port','3306');$database=ask('Database name','student_result');$admin=ask('Database administrator username','root');$password=secret('Database administrator password (blank for default local XAMPP)');$appUser=ask('New application username','srs_app');
    if(!preg_match('/^[A-Za-z0-9_]{1,64}$/',$database)||!preg_match('/^[A-Za-z0-9_]{1,32}$/',$appUser)||strtolower($appUser)==='root')throw new RuntimeException('Use a valid database and non-root application username.');
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$adminConn=mysqli_connect($host,$admin,$password,$database,$port);unset($password);
    $migration=mysqli_query($adminConn,"SHOW COLUMNS FROM results LIKE 'attempt_no'");if(!mysqli_num_rows($migration))throw new RuntimeException('Import upgrade_20261011_academic.sql before creating the application account.');
    $appPassword=bin2hex(random_bytes(24));$escaped=mysqli_real_escape_string($adminConn,$appPassword);
    // Local connection only. TRIGGER is scoped to this database so backup can include its protections.
    mysqli_query($adminConn,"CREATE USER '$appUser'@'localhost' IDENTIFIED BY '$escaped'");
    try{
        mysqli_query($adminConn,"GRANT SELECT,INSERT,UPDATE,DELETE,TRIGGER ON `$database`.* TO '$appUser'@'localhost'");
        $app=mysqli_connect($host,$appUser,$appPassword,$database,$port);mysqli_query($app,'SELECT COUNT(*) FROM results');mysqli_close($app);
        $config=['host'=>$host,'port'=>$port,'user'=>$appUser,'password'=>$appPassword,'database'=>$database];
        $handle=fopen($path,'x');if(!$handle)throw new RuntimeException('Could not save the private configuration.');
        try{$contents="<?php\nreturn ".var_export($config,true).";\n";if(fwrite($handle,$contents)!==strlen($contents))throw new RuntimeException('Could not write configuration.');}finally{fclose($handle);}
        chmod($path,0600);unset($config,$appPassword,$escaped);mysqli_close($adminConn);
        echo 'Application account created. Private configuration: '.$path.PHP_EOL;
        echo 'Open the portal and sign in. If using a custom path, set Apache SRS_DB_CONFIG to that path.'.PHP_EOL;
    }catch(Throwable $ex){if(is_file($path))unlink($path);mysqli_query($adminConn,"DROP USER '$appUser'@'localhost'");throw $ex;}
}catch(Throwable $ex){fwrite(STDERR,'Setup failed. '.$ex->getMessage().PHP_EOL);exit(1);}
