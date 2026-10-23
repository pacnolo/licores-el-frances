<?php
require __DIR__.'/bootstrap.php';auth();needPost();sameOrigin();
if(empty($_POST['csrf'])||!hash_equals(csrf(),(string)$_POST['csrf']))respond(['ok'=>false,'error'=>'Sesión inválida.'],419);
if(empty($_FILES['image'])||$_FILES['image']['error']!==UPLOAD_ERR_OK||$_FILES['image']['size']>2500000)respond(['ok'=>false,'error'=>'Elige una imagen JPG, PNG o WEBP menor a 2.5 MB.'],422);
$tmp=$_FILES['image']['tmp_name'];$info=@getimagesize($tmp);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!$info||!isset($types[$info['mime']]))respond(['ok'=>false,'error'=>'Formato de imagen no permitido.'],422);
$ext=$types[$info['mime']];$file='producto-'.bin2hex(random_bytes(8)).'.'.$ext;$path=LF_ROOT.'/assets/uploads/'.$file;if(!move_uploaded_file($tmp,$path))respond(['ok'=>false,'error'=>'Error guardando imagen.'],500);@chmod($path,0644);respond(['ok'=>true,'path'=>'assets/uploads/'.$file]);
