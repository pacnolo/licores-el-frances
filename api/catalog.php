<?php
require __DIR__.'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='GET')respond(['ok'=>false,'error'=>'Método no permitido'],405);
respond(['ok'=>true,'store'=>publicStore(readStore())]);
