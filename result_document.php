<?php
// Shared result document rules used by the HTML and PDF renderers.
require_once __DIR__.'/qr_matrix.php';
function result_needs_review(array $row): bool {
    // Below the configured minimum passing grade (C in the default scale).
    global $conn;
    $minimum=(float)app_setting($conn,'minimum_pass_gpa','2.00');
    return (float)$row['gpa']<$minimum;
}
function result_verification_url($conn,string $code): string {
    $base=rtrim(app_setting($conn,'portal_base_url',''),'/');
    if($base==='' || strlen($base)>140 || !filter_var($base,FILTER_VALIDATE_URL))return '';
    $u=parse_url($base);
    if(!in_array(strtolower($u['scheme']??''),['http','https'],true) || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment']))return '';
    return $base.'/verify_result.php?code='.rawurlencode($code);
}
function result_qr_svg(array $matrix): string {
    $size=count($matrix)+8;$path='';
    foreach($matrix as $y=>$row)foreach($row as $x=>$dark)if($dark)$path.='M'.($x+4).' '.($y+4).'h1v1h-1z';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" role="img" aria-label="Scan to verify result sheet"><rect width="100%" height="100%" fill="white"/><path d="'.$path.'" fill="black"/></svg>';
}
