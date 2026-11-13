<?php
// Ejecución por línea de comandos exclusivamente. No exponer en navegador.
if (PHP_SAPI !== 'cli') {http_response_code(403);exit('Solo CMD.');}
require dirname(__DIR__) . '/config/conexion.php';
echo "=== VENDIA | DIAGNÓSTICO DE ACCESOS ===\n";
echo 'PHP: '.PHP_VERSION.' / pdo_mysql: '.(extension_loaded('pdo_mysql')?'Sí':'NO')."\n";
echo 'Base conectada: '.$pdo->query('SELECT DATABASE()')->fetchColumn()."\n";
foreach(['usuarios','negocios','productos','pedidos','ventas','stores','users'] as $name){
 try{$r=$pdo->query("SELECT COUNT(*) FROM `$name`");echo str_pad($name,19).$r->fetchColumn()." registros\n";}
 catch(Throwable $e){echo str_pad($name,19).'ERROR: estructura faltante. Importa database/database.sql'."\n";}
}
$names=['superadmin.vendia','dueno.abarrotes','dueno.taller','dueno.consultorio','dueno.mercado','superlissy','superlissy.demo','empleado1.1','cliente1.1'];
$q=$pdo->prepare('SELECT u.usuario,u.rol,u.activo,u.id_negocio,n.activo AS negocio_activo,n.estado_suscripcion FROM usuarios u LEFT JOIN negocios n ON n.id=u.id_negocio WHERE u.usuario=?');
echo "\nCuentas de referencia (sin mostrar contraseñas):\n";
foreach($names as $username){
 $q->execute([$username]);$u=$q->fetch(PDO::FETCH_ASSOC);
 if(!$u){echo "- $username: NO EXISTE (revisa importación de SQL)\n";continue;}
 $estado=(int)$u['activo']===1?'activa':'inactiva';
 if($u['rol']!=='superadministrador' && ((int)$u['negocio_activo']!==1 || $u['estado_suscripcion']==='suspendida'))$estado.=', negocio bloqueado';
 echo '- '.$u['usuario'].' ('.$u['rol'].'): '.$estado."\n";
}
echo "\nSi faltan cuentas, importa el archivo database/database.sql en tu base Vendia, previa copia de seguridad.\n";
