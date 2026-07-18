<?php
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=604800');
$b64 = trim(@file_get_contents(__DIR__.'/h1.txt')).trim(@file_get_contents(__DIR__.'/h2.txt'));
echo base64_decode($b64);
