<?php
declare(strict_types=1);
const LF_ROOT = __DIR__ . '/..';
const LF_DATA = LF_ROOT . '/data';
require_once LF_ROOT . '/config/private.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name('LFADMIN');
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
    session_start();
}
function respond(array $payload, int $status = 200): never {http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function req(): array { $body=file_get_contents('php://input');if(strlen($body)>200000)respond(['ok'=>false,'error'=>'Solicitud demasiado grande.'],413);$obj=json_decode($body,true);if(!is_array($obj))respond(['ok'=>false,'error'=>'JSON inválido.'],400);return $obj;}
function now(): string{return gmdate('c');}
function sameOrigin(): void {if(isset($_SERVER['HTTP_ORIGIN'])){$origin=parse_url($_SERVER['HTTP_ORIGIN'],PHP_URL_HOST);$host=explode(':',$_SERVER['HTTP_HOST']??'')[0];if(!$origin||strcasecmp((string)$origin,(string)$host)!==0)respond(['ok'=>false,'error'=>'Origen no permitido.'],403);}}
function readStore(): array {$path=LF_DATA.'/store.json';$raw=@file_get_contents($path);$store=$raw?json_decode($raw,true):null;if(!is_array($store))respond(['ok'=>false,'error'=>'No fue posible leer los datos.'],500);return $store;}
function atomicWrite(string $path, array $data): void {$temp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';$json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);if($json===false||file_put_contents($temp,$json,LOCK_EX)===false)respond(['ok'=>false,'error'=>'Error escribiendo JSON. Verifica permisos.'],500);@chmod($temp,0600);if(!rename($temp,$path)){@unlink($temp);respond(['ok'=>false,'error'=>'Error actualizando archivo de datos.'],500);}}
function writeStore(callable $callback): mixed {
 $lock=fopen(LF_DATA.'/.lock','c');if(!$lock||!flock($lock,LOCK_EX))respond(['ok'=>false,'error'=>'No se obtuvo bloqueo de inventario.'],500);
 try{$data=readStore();expireOrders($data);
   $backup=LF_DATA.'/backups/'.gmdate('Y-m-d').'.json';if(!is_file($backup))@copy(LF_DATA.'/store.json',$backup);
   $result=$callback($data);$data['updatedAt']=now();atomicWrite(LF_DATA.'/store.json',$data);return $result;
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function expireOrders(array &$s): void {$now=time();foreach($s['orders'] as &$o){if(($o['status']??'')==='pending'&&!empty($o['expiresAt'])&&strtotime($o['expiresAt'])<$now){$o['status']='expired';$o['updatedAt']=now();}}unset($o);}
function reservations(array $s,?string $exclude=null): array {$r=[];foreach($s['orders']??[] as $o){if($exclude&&$o['id']===$exclude)continue;if(in_array($o['status']??'',['pending','confirmed','preparing','in_transit'],true)){if(($o['status']??'')==='pending'&&strtotime($o['expiresAt']??'1970-01-01')<time())continue;foreach($o['items'] as $item){$id=$item['id'];$r[$id]=($r[$id]??0)+$item['qty'];}}}return $r;}
function publicStore(array $s): array {expireOrders($s);$reserved=reservations($s);$products=[];foreach($s['products'] as $p){unset($p['cost'],$p['barcode']);$p['available']=max(0,(int)$p['stock']-($reserved[$p['id']]??0));$products[]=$p;}return ['products'=>$products,'categories'=>$s['categories'],'banner'=>$s['banner'],'settings'=>['businessName'=>$s['settings']['businessName']??'Licores El Francés','reservationMinutes'=>$s['settings']['reservationMinutes']??45]];}
function csrf():string {if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));return $_SESSION['csrf'];}
function requireCsrf(array $b):void {sameOrigin();if(empty($b['csrf'])||!hash_equals(csrf(),(string)$b['csrf']))respond(['ok'=>false,'error'=>'Sesión expirada. Vuelve a iniciar sesión.'],419);}
function auth():void {if(empty($_SESSION['user']))respond(['ok'=>false,'error'=>'Inicia sesión para continuar.'],401);}
function needPost():void {if($_SERVER['REQUEST_METHOD']!=='POST')respond(['ok'=>false,'error'=>'Método no permitido.'],405);}
function cleanStr(mixed $v,int $max=120):string {$v=trim((string)$v);if(function_exists('mb_substr'))return mb_substr($v,0,$max);return implode('',array_slice(preg_split('//u',$v,-1,PREG_SPLIT_NO_EMPTY)?:[],0,$max));}
function strLength(string $s):int {return function_exists('mb_strlen')?mb_strlen($s):count(preg_split('//u',$s,-1,PREG_SPLIT_NO_EMPTY)?:[]);}
function positive(mixed $v):float {return max(0,round((float)$v,2));}
function findProduct(array &$s,string $id):?int {foreach($s['products'] as $i=>$p){if($p['id']===$id)return $i;}return null;}
