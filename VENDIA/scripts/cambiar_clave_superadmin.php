<?php
/** Uso local exclusivo: C:\xampp\php\php.exe scripts\cambiar_clave_superadmin.php */
if (PHP_SAPI!=='cli') { http_response_code(403); exit('Solo se permite ejecutarlo desde la terminal.'); }
require dirname(__DIR__).'/config/conexion.php';
function pedir(string $label): string {echo $label; $linea=fgets(STDIN);return $linea===false?'':trim($linea);}
$usuario=pedir('Usuario o correo del superadministrador: ');
$query=$pdo->prepare("SELECT id FROM usuarios WHERE rol='superadministrador' AND (usuario=? OR correo=?) LIMIT 1");
$query->execute([$usuario,$usuario]);
$id=$query->fetchColumn();
if(!$id){fwrite(STDERR,"No existe ese superadministrador.\n");exit(1);}
$clave=pedir('Nueva contraseña de al menos 9 caracteres, mayúscula, número y símbolo: ');
$confirma=pedir('Confirma la nueva contraseña: ');
if(strlen($clave)<9 || !preg_match('/[A-Z]/',$clave) || !preg_match('/[0-9]/',$clave) || !preg_match('/[^A-Za-z0-9]/',$clave) || $clave!==$confirma){
    fwrite(STDERR,"La contraseña no cumple las restricciones o no coincide.\n");exit(1);
}
$pdo->prepare("UPDATE usuarios SET contrasena_hash=? WHERE id=? AND rol='superadministrador'")
    ->execute([password_hash($clave,PASSWORD_DEFAULT),$id]);
echo "Contraseña actualizada. Cierra sesiones activas y utiliza la nueva contraseña.\n";
