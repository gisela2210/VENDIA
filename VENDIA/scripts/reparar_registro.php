<?php
/**
 * Vendia V20 - diagnóstico y reparación NO destructiva del esquema de registro.
 * Ejecutar SOLO desde la consola con PHP CLI; nunca accesible por HTTP.
 * php scripts/reparar_registro.php
 * php scripts/reparar_registro.php --aplicar
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Uso exclusivo en consola.'); }
require dirname(__DIR__).'/config/conexion.php';
$aplicar = in_array('--aplicar', $argv, true);
$db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if (strcasecmp($db, 'Vendia') !== 0) { fwrite(STDERR, "Error: la conexion actual usa otra base. Debe ser Vendia.\n"); exit(2); }
$tabla = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND TABLE_TYPE='BASE TABLE'")->fetchColumn();
if (!$tabla) { fwrite(STDERR, "No existe la tabla usuarios. Importa database/database.sql sobre Vendia, sin borrar la base.\n"); exit(2); }
function esquemaVendia(PDO $pdo): array {
    $q=$pdo->query("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios'");
    $cols=[];foreach ($q as $col) $cols[$col['COLUMN_NAME']]=$col;
    return $cols;
}
$columnas=esquemaVendia($pdo);
$operaciones=[];
if (!isset($columnas['id_negocio'])) { fwrite(STDERR, "Falta la columna id_negocio. Revisa la instalacion completa.\n"); exit(2); }
if ($columnas['id_negocio']['IS_NULLABLE'] !== 'YES') {
    $operaciones[]=['Permitir que clientes no tengan local al registrarse', 'ALTER TABLE `usuarios` MODIFY COLUMN `id_negocio` INT NULL DEFAULT NULL'];
}
$agregar=[
    'acepto_terminos_en' => 'ALTER TABLE `usuarios` ADD COLUMN `acepto_terminos_en` DATETIME NULL',
    'aviso_privacidad_visto_en' => 'ALTER TABLE `usuarios` ADD COLUMN `aviso_privacidad_visto_en` DATETIME NULL',
    'consentimiento_salud_en' => 'ALTER TABLE `usuarios` ADD COLUMN `consentimiento_salud_en` DATETIME NULL',
    'edad' => 'ALTER TABLE `usuarios` ADD COLUMN `edad` TINYINT UNSIGNED NULL',
    'antecedentes_medicos' => 'ALTER TABLE `usuarios` ADD COLUMN `antecedentes_medicos` TEXT NULL',
];
foreach ($agregar as $nombre=>$sql) if (!isset($columnas[$nombre]))$operaciones[]=["Agregar columna $nombre",$sql];
if (isset($columnas['rol']) && !str_contains($columnas['rol']['COLUMN_TYPE'], "'cliente'")) {
    // La enumeracion temporal incluye roles antiguos para no perder datos.
    $operaciones[]=['Habilitar el rol Cliente y Superadministrador', "ALTER TABLE `usuarios` MODIFY COLUMN `rol` ENUM('owner','employee','dueño','empleado','cliente','superadministrador') NOT NULL"];
}
echo "DIAGNOSTICO DE REGISTROS VENDIA\nBase de datos: Vendia\n";
if (!$operaciones) {
    echo "La estructura obligatoria de usuarios esta actualizada. No hay modificaciones pendientes.\n";
} else {
    foreach ($operaciones as $i=>$op) echo ($i+1).'. '.$op[0]."\n";
    if (!$aplicar) {
        echo "\nNo se modifico nada. Primero respalda Vendia. Para aplicar: php scripts/reparar_registro.php --aplicar\n";
        exit(0);
    }
    foreach ($operaciones as $op) {
        try { $pdo->exec($op[1]);echo '[OK] '.$op[0]."\n"; }
        catch (PDOException $e) {
            error_log('Vendia reparacion registro: '.$e->getMessage());
            fwrite(STDERR, "Error al aplicar: {$op[0]}. Revisa el registro de errores de PHP y el esquema de tu base.\n");
            exit(3);
        }
    }
    echo "Reparacion terminada. Se conservaron los usuarios, negocios, pedidos y ventas.\n";
}
// Revisiones utiles sin mostrar correos, hashes ni otros datos privados.
$columnas=esquemaVendia($pdo);
foreach (['id_negocio','rol','usuario','correo','contrasena_hash','edad','acepto_terminos_en','aviso_privacidad_visto_en'] as $c) {
    echo '[ESQUEMA] '.$c.': '.(isset($columnas[$c])?'OK':'FALTA')."\n";
}
