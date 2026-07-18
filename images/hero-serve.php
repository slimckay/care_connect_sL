<?php
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=604800');
$b64='';
for($i=1;$i<=4;$i++){$f=__DIR__."/h$i.txt";if(file_exists($f))$b64.=trim(file_get_contents($f));}
echo base64_decode($b64);
