<?php
require __DIR__.'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']==='GET'){$users=json_decode(file_get_contents(LF_DATA.'/users.json'),true)?:[];respond(['ok'=>true,'configured'=>count($users)>0,'user'=>$_SESSION['user']??null,'csrf'=>csrf()]);}
needPost();sameOrigin();$b=req();$action=$b['action']??'';$users=json_decode(file_get_contents(LF_DATA.'/users.json'),true)?:[];
if($action==='setup'){
 if(count($users))respond(['ok'=>false,'error'=>'La instalación ya fue configurada.'],409);
 if(!hash_equals(LF_SETUP_KEY,cleanStr($b['setupKey']??'',150)))respond(['ok'=>false,'error'=>'Clave de instalación incorrecta.'],403);
 $username=cleanStr($b['username']??'',50);$password=(string)($b['password']??'');if(!preg_match('/^[a-zA-Z0-9._-]{4,50}$/',$username)||strlen($password)<12)respond(['ok'=>false,'error'=>'Usuario de 4 caracteres o más y contraseña de 12 o más.'],422);
 atomicWrite(LF_DATA.'/users.json',[['username'=>$username,'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'admin']]);session_regenerate_id(true);$_SESSION['user']=['username'=>$username,'role'=>'admin'];respond(['ok'=>true,'user'=>$_SESSION['user'],'csrf'=>csrf()]);
}
if($action==='login'){
 $fail=$_SESSION['login_fails']??0;$time=$_SESSION['login_last']??0;if($fail>=5&&time()-$time<180)respond(['ok'=>false,'error'=>'Demasiados intentos. Intenta después de 3 minutos.'],429);
 $u=cleanStr($b['username']??'',50);$p=(string)($b['password']??'');$found=null;foreach($users as $user){if(hash_equals($user['username'],$u)&&password_verify($p,$user['hash'])){$found=$user;break;}}
 if(!$found){$_SESSION['login_fails']=$fail+1;$_SESSION['login_last']=time();respond(['ok'=>false,'error'=>'Usuario o contraseña incorrectos.'],401);}session_regenerate_id(true);$_SESSION['user']=['username'=>$u,'role'=>$found['role']];$_SESSION['login_fails']=0;respond(['ok'=>true,'user'=>$_SESSION['user'],'csrf'=>csrf()]);
}
if($action==='logout'){requireCsrf($b);$_SESSION=[];session_destroy();respond(['ok'=>true]);}
respond(['ok'=>false,'error'=>'Acción inválida.'],400);
