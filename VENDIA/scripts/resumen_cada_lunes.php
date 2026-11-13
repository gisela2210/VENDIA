<?php
/**
 * Ejecutar con el PHP de XAMPP mediante Programador de tareas de Windows:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\VENDIA\scripts\resumen_cada_lunes.php
 * Solo funciona desde la línea de comandos; no expone envíos públicos en web.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('No disponible desde el navegador.'); }
date_default_timezone_set('America/Mexico_City');
require dirname(__DIR__).'/config/conexion.php';
require dirname(__DIR__).'/includes/resumen_semanal.php';

$hoy=new DateTimeImmutable('now',new DateTimeZone('America/Mexico_City'));
if ($hoy->format('N') !== '1') {
    echo "Hoy no es lunes. No se generaron notificaciones.\n";
    exit(0);
}
$consulta=$pdo->query("SELECT DISTINCT n.id FROM negocios n JOIN usuarios u ON u.id_negocio=n.id
                         WHERE n.activo=1 AND n.estado_suscripcion<>'suspendida' AND u.rol='dueño' AND u.activo=1");
$creados=0;$yaExistentes=0;$errores=0;
foreach($consulta->fetchAll(PDO::FETCH_COLUMN) as $negocioId) {
    try {
        $salida=generarResumenVendia($pdo,(int)$negocioId,$hoy);
        if ($salida['nuevo']) $creados++; else $yaExistentes++;
    } catch (Throwable $e) {
        $errores++;error_log('Vendia resumen del negocio '.$negocioId.': '.$e->getMessage());
    }
}
echo "Vendia | nuevos: $creados; ya existentes: $yaExistentes; errores: $errores.\n";
exit($errores ? 1 : 0);
