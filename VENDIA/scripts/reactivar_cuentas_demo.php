<?php
/** Solo DEMOSTRACIÓN. Corrige la contraseña de las cuentas semilla identificadas por ID y usuario.
 *  Nunca modifica cuentas personalizadas. REQUIERE confirmación escrita explícita.
 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Solo CMD.');}
if(!in_array('--confirmar-demo',$argv,true)){
 echo "Uso: php scripts/reactivar_cuentas_demo.php --confirmar-demo\n";
 echo "ADVERTENCIA: restablece contraseñas solo de cuentas SEMILLA de ejemplo, no de cuentas personales. Haz copia de seguridad.\n";exit(1);
}
require dirname(__DIR__).'/config/conexion.php';
$records=[
 [900001,'superadmin.vendia','Vendia#Admin26!'],
 [920002,'dueno.abarrotes','Dueno#Demo26'],[920003,'dueno.taller','Dueno#Demo26'],
 [920004,'dueno.consultorio','Dueno#Demo26'],[920005,'dueno.mercado','Dueno#Demo26'],
];
for($business=1;$business<=5;$business++)for($i=1;$i<=5;$i++){
 $records[]=[930000+$business*10+$i,"empleado$business.$i",'Empleado#Demo26'];
 $records[]=[940000+$business*10+$i,"cliente$business.$i",'Cliente#Demo26'];
}
$find=$pdo->prepare('SELECT rol FROM usuarios WHERE id=? AND usuario=?');
$update=$pdo->prepare('UPDATE usuarios SET contrasena_hash=?,activo=1 WHERE id=? AND usuario=?');
$done=0;
foreach($records as [$id,$username,$pass]){
 $find->execute([$id,$username]);$rol=$find->fetchColumn();
 if(!$rol)continue;
 $expectedRole=$id===900001?'superadministrador':($id<930000?'dueño':($id<940000?'empleado':'cliente'));
 if($rol!==$expectedRole)continue;
 $update->execute([password_hash($pass,PASSWORD_DEFAULT),$id,$username]);$done++;
}
echo "Restablecidas $done cuentas de demostración. Los dueños reales (incluido superlissy) NO se modificaron.\n";
