<?php
// Local QR byte encoder, version 8 / level L / mask 0; no external service.
// Data capacity: 192 bytes. Includes Reed-Solomon error correction and quiet zone at render time.
function result_qr_matrix(string $text): array {
    if(strlen($text)>192)throw new InvalidArgumentException('Verification URL is too long.');
    $bits=[];$append=function($n,$length)use(&$bits){for($i=$length-1;$i>=0;$i--)$bits[]=($n>>$i)&1;};
    $append(4,4);$append(strlen($text),8);foreach(unpack('C*',$text)?:[] as $byte)$append($byte,8);
    $limit=194*8;for($i=0,$n=min(4,$limit-count($bits));$i<$n;$i++)$bits[]=0;
    while(count($bits)%8)$bits[]=0;
    $data=[];for($i=0;$i<count($bits);$i+=8){$v=0;for($j=0;$j<8;$j++)$v=($v<<1)|$bits[$i+$j];$data[]=$v;}
    for($i=0;count($data)<194;$i++)$data[]=($i%2)?0x11:0xec;
    $exp=[];$log=[];$v=1;for($i=0;$i<255;$i++){$exp[$i]=$v;$log[$v]=$i;$v<<=1;if($v&256)$v^=0x11d;}
    $mul=function($a,$b)use($exp,$log){return ($a===0||$b===0)?0:$exp[($log[$a]+$log[$b])%255];};
    $poly=[1];for($i=0;$i<24;$i++){$next=array_fill(0,count($poly)+1,0);foreach($poly as $j=>$v){$next[$j]^=$v;$next[$j+1]^=$mul($v,$exp[$i]);}$poly=$next;}
    $blocks=[];$ecc=[];for($b=0;$b<2;$b++){$block=array_slice($data,$b*97,97);$blocks[]=$block;$work=array_merge($block,array_fill(0,24,0));for($i=0;$i<97;$i++){$factor=$work[$i];if($factor)for($j=0;$j<count($poly);$j++)$work[$i+$j]^=$mul($poly[$j],$factor);}$ecc[]=array_slice($work,-24);}
    $stream=[];for($i=0;$i<97;$i++)for($b=0;$b<2;$b++)$stream[]=$blocks[$b][$i];for($i=0;$i<24;$i++)for($b=0;$b<2;$b++)$stream[]=$ecc[$b][$i];
    $n=49;$m=array_fill(0,$n,array_fill(0,$n,null));
    foreach([[0,0],[0,$n-7],[$n-7,0]] as [$fy,$fx])for($dy=-1;$dy<=7;$dy++)for($dx=-1;$dx<=7;$dx++){if($fy+$dy<0||$fy+$dy>=$n||$fx+$dx<0||$fx+$dx>=$n)continue;$m[$fy+$dy][$fx+$dx]=(($dy>=0&&$dy<=6&&($dx===0||$dx===6))||($dx>=0&&$dx<=6&&($dy===0||$dy===6))||($dy>=2&&$dy<=4&&$dx>=2&&$dx<=4));}
    foreach([6,24,42] as $y)foreach([6,24,42] as $x){if($m[$y][$x]!==null)continue;for($dy=-2;$dy<=2;$dy++)for($dx=-2;$dx<=2;$dx++)$m[$y+$dy][$x+$dx]=(abs($dy)===2||abs($dx)===2||($dy===0&&$dx===0));}
    for($i=8;$i<$n-8;$i++){if($m[$i][6]===null)$m[$i][6]=$i%2===0;if($m[6][$i]===null)$m[6][$i]=$i%2===0;}
    $digit=function($v){$d=0;while($v){$d++;$v>>=1;}return $d;};
    $format=8;$rem=$format<<10;while($digit($rem)>=$digit(0x537))$rem^=0x537<<($digit($rem)-$digit(0x537));$format=(($format<<10)|$rem)^0x5412;
    for($i=0;$i<15;$i++){$dark=(bool)(($format>>$i)&1);$y=$i<6?$i:($i<8?$i+1:$n-15+$i);$m[$y][8]=$dark;$x=$i<8?$n-$i-1:($i===8?7:15-$i-1);$m[8][$x]=$dark;}$m[$n-8][8]=true;
    $rem=8<<12;while($digit($rem)>=$digit(0x1f25))$rem^=0x1f25<<($digit($rem)-$digit(0x1f25));$version=(8<<12)|$rem;
    for($i=0;$i<18;$i++){$dark=(bool)(($version>>$i)&1);$m[intdiv($i,3)][$i%3+$n-11]=$dark;$m[$i%3+$n-11][intdiv($i,3)]=$dark;}
    $bit=0;$y=$n-1;$direction=-1;
    for($x=$n-1;$x>0;$x-=2){if($x===6)$x--;while(true){for($dx=0;$dx<2;$dx++){if($m[$y][$x-$dx]!==null)continue;$dark=$bit<count($stream)*8?(bool)(($stream[intdiv($bit,8)]>>(7-$bit%8))&1):false;$bit++;if(($y+$x-$dx)%2===0)$dark=!$dark;$m[$y][$x-$dx]=$dark;}$y+=$direction;if($y<0||$y>=$n){$y-=$direction;$direction=-$direction;break;}}}
    return $m;
}
