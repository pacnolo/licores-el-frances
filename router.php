<?php
/* Para pruebas con php -S: php -S localhost:8000 router.php */
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('#^/(data|config|docs)(/|$)#i',$path)||preg_match('#/\.[^/]+#',$path)){http_response_code(403);echo 'No autorizado';return true;}
if($path==='/'||$path===''){$path='/index.html';}
$full=realpath(__DIR__.urldecode($path));$root=realpath(__DIR__);
if($full===false||!str_starts_with($full,$root.DIRECTORY_SEPARATOR)){http_response_code(404);return true;}
if(is_file($full)){return false;}
http_response_code(404);echo 'No encontrado';return true;
