<?php
// Ejecutar solo por CLI: C:\xampp\php\php.exe scripts\crear_superadministrador.php
if (PHP_SAPI!=='cli') {http_response_code(403);exit('Solo consola.');}
require dirname(__DIR__).'/config/conexion.php';
require dirname(__DIR__).'/includes/validaciones.php';
echo "Vendia | Creación de superadministrador (solo terminal)\n";
function solicitar(string $texto): string { echo $texto; $linea=fgets(STDIN); return $linea===false?'':trim($linea); }
$usuario=solicitar('Usuario [4-40 caracteres]: ');
$nombre=solicitar('Nombre: ');
$apellido=solicitar('Apellido: ');
$correo=solicitar('Correo: ');
$telefono=solicitar('Celular (10 números): ');
if(!usuarioValido($usuario)||!nombreValido($nombre)||!nombreValido($apellido)||!correoPermitido($correo)||!telefonoValido($telefono)){
 fwrite(STDERR,"Datos inválidos. La cuenta no fue creada.\n");exit(1);
}
// Usuario de consola en Windows: la contraseña se introducirá sin registrar en SQL.
$contrasena=solicitar('Contraseña segura (mín. 9, mayúscula, número y símbolo): ');
if(strlen($contrasena)<9||!preg_match('/[A-Z]/',$contrasena)||!preg_match('/[0-9]/',$contrasena)||!preg_match('/[^a-zA-Z0-9]/',$contrasena)){
 fwrite(STDERR,"Contraseña demasiado débil.\n");exit(1);
}
$existe=$pdo->prepare('SELECT 1 FROM usuarios WHERE usuario=? OR (rol=\'superadministrador\' AND correo=?) LIMIT 1');
$existe->execute([$usuario,$correo]);
if($existe->fetchColumn()){fwrite(STDERR,"El usuario/correo ya existe.\n");exit(1);}
$pdo->prepare("INSERT INTO usuarios(id_negocio,rol,nombre,apellido_paterno,apellido_materno,usuario,correo,telefono,contrasena_hash,activo) VALUES(NULL,'superadministrador',?,?, '', ?,?,?,?,1)")
    ->execute([$nombre,$apellido,$usuario,$correo,$telefono,password_hash($contrasena,PASSWORD_DEFAULT)]);
echo "Cuenta creada correctamente. Inicia sesión desde la opción Superadministrador.\n";
