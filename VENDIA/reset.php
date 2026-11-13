<?php
session_start(); require __DIR__.'/config/conexion.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function validPass($p){return strlen($p)>=9 && preg_match('/[A-Z]/',$p) && preg_match('/[0-9]/',$p) && preg_match('/[^A-Za-z0-9]/',$p);}
$error='';$ok='';if(empty($_SESSION['csrf_reset']))$_SESSION['csrf_reset']=bin2hex(random_bytes(32));
$uid=(int)($_GET['uid']??0);$token=$_GET['token']??'';
$q=$pdo->prepare('SELECT * FROM password_resets WHERE user_id=? AND used=0 AND expires_at>NOW() ORDER BY id DESC LIMIT 10');$q->execute([$uid]);$valid=false;$reset=null;
foreach($q as $r){if(password_verify($token,$r['token_hash'])){$valid=true;$reset=$r;break;}}
if($_SERVER['REQUEST_METHOD']==='POST'){
  $p=$_POST['password']??'';$c=$_POST['confirm']??'';
  if(!hash_equals($_SESSION['csrf_reset'],(string)($_POST['csrf']??''))) { http_response_code(403);exit('Solicitud inválida.'); }
  if(!$valid)$error='El enlace expiró o ya fue utilizado.';
  elseif(!validPass($p))$error='Usa mínimo 9 caracteres, una mayúscula, un número y un carácter especial.';
  elseif($p!==$c)$error='Las contraseñas no coinciden.';
  else{$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($p,PASSWORD_DEFAULT),$uid]);$pdo->prepare('UPDATE password_resets SET used=1 WHERE user_id=?')->execute([$uid]);$ok='Contraseña actualizada. Ya puedes iniciar sesión.';$valid=false;}
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Restablecer contraseña | VENDIA</title><link rel="stylesheet" href="assets/css/estilos.css"></head><body><main class="auth-wrap"><section class="auth-card narrow"><h1>Restablecer contraseña</h1><?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?><?php if($ok):?><div class="alert success"><?=h($ok)?></div><a href="index.php?p=login" class="btn primary full">Iniciar sesión</a><?php elseif($valid):?><p class="muted">Crea una contraseña nueva.</p><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf_reset'])?>"><label>Nueva contraseña</label><input type="password" name="password" required><label>Confirmar contraseña</label><input type="password" name="confirm" required><button class="btn primary full">Guardar contraseña</button></form><?php else:?><p>Enlace no válido o vencido.</p><a href="index.php?p=forgot">Solicitar otro enlace</a><?php endif;?></section></main></body></html>
