<?php
/** Ejecutar solo desde CMD: C:\xampp\php\php.exe scripts\diagnosticar_modulos.php */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Este diagnóstico solo funciona desde CMD.');}
require dirname(__DIR__).'/config/conexion.php';
$base=$pdo->query('SELECT DATABASE()')->fetchColumn();
echo "VENDIA · Diagnóstico de módulos\nBase de datos: $base\n";
if(strcasecmp((string)$base,'Vendia')!==0){fwrite(STDERR,"Error: la base debe llamarse Vendia.\n");exit(1);}
$checks=['negocios','usuarios','productos','pedidos','detalle_pedidos','notificaciones','solicitudes_ayuda','ventas','pagos'];
$missing=0;
$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
foreach($checks as $table){$q->execute([$table]);$ok=(int)$q->fetchColumn()>0;echo ($ok?'[OK] ':'[FALTA] ').$table."\n";if(!$ok)$missing++;}
$c=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
foreach(['foto_referencia','fecha_reprogramada_en','id_cliente'] as $column){$c->execute(['pedidos',$column]);$ok=(int)$c->fetchColumn()>0;echo ($ok?'[OK] ':'[FALTA] ').'pedidos.'.$column."\n";if(!$ok)$missing++;}
if(!$missing){
    echo 'Usuarios existentes: '.(int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()."\n";
    echo "Diagnóstico terminado: estructura lista.\n";
} else {
    echo "Faltan $missing elementos. Haz un respaldo y reimporta el ÚNICO archivo database/database.sql.\n";
    exit(1);
}
