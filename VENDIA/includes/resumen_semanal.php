<?php
/**
 * Resumen de la semana COMPLETA anterior (lunes 00:00 a lunes 00:00).
 * Cada negocio tiene como máximo un resumen por lunes. La notificación y
 * el registro se escriben en una sola transacción: no se duplican al lanzar
 * la tarea automática o usar la demostración manual.
 */
function nombreResumenCorto(string $nombre): string {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($nombre, 'UTF-8') > 65 ? mb_substr($nombre, 0, 62, 'UTF-8').'…' : $nombre;
    }
    return strlen($nombre) > 65 ? substr($nombre, 0, 62).'...' : $nombre;
}

function lunesVendia(?DateTimeImmutable $reloj = null): DateTimeImmutable {
    $zona = new DateTimeZone('America/Mexico_City');
    $hoy = ($reloj ?? new DateTimeImmutable('now', $zona))->setTimezone($zona);
    return $hoy->setTime(0, 0)->modify('monday this week');
}

function resumenSemanalExistente(PDO $pdo, int $negocioId, DateTimeImmutable $lunes): ?array {
    $consulta = $pdo->prepare('SELECT * FROM resumenes_semanales WHERE id_negocio=? AND fecha_lunes=? LIMIT 1');
    $consulta->execute([$negocioId, $lunes->format('Y-m-d')]);
    return $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return array{nuevo:bool,resumen:array} */
function generarResumenVendia(PDO $pdo, int $negocioId, ?DateTimeImmutable $hoy = null): array {
    if ($negocioId < 1) throw new InvalidArgumentException('Negocio no válido.');
    $lunes = lunesVendia($hoy);
    $inicio = $lunes->modify('-7 days');
    $inicioSql = $inicio->format('Y-m-d H:i:s');
    $finSql = $lunes->format('Y-m-d H:i:s');
    $lunesSql = $lunes->format('Y-m-d');

    $existente = resumenSemanalExistente($pdo, $negocioId, $lunes);
    if ($existente !== null) return ['nuevo'=>false, 'resumen'=>$existente];

    $q = $pdo->prepare("SELECT COUNT(*) AS operaciones, COALESCE(SUM(total),0) AS ingresos
                        FROM ventas WHERE id_negocio=? AND estado='completada'
                         AND fecha_creacion>=? AND fecha_creacion<?");
    $q->execute([$negocioId, $inicioSql, $finSql]);
    $ventas = $q->fetch(PDO::FETCH_ASSOC);
    $cantidad = (int)$ventas['operaciones'];
    $ingresos = (float)$ventas['ingresos'];

    // Los pedidos pendientes se cuentan a la fecha de generación, no se presenta
    // esta cifra como un dato histórico del cierre del domingo.
    $q = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE id_negocio=? AND estado IN ('pendiente','preparacion','listo')");
    $q->execute([$negocioId]);
    $pendientes = (int)$q->fetchColumn();

    $q = $pdo->prepare("SELECT dv.nombre_producto AS nombre, SUM(dv.cantidad) AS unidades
                       FROM detalle_ventas dv JOIN ventas v ON v.id=dv.id_venta
                       WHERE v.id_negocio=? AND v.estado='completada'
                         AND v.fecha_creacion>=? AND v.fecha_creacion<?
                       GROUP BY dv.id_producto,dv.nombre_producto
                       ORDER BY unidades DESC, nombre ASC LIMIT 1");
    $q->execute([$negocioId, $inicioSql, $finSql]);
    $masVendido = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    $q = $pdo->prepare('SELECT nombre,existencias,existencia_minima FROM productos
                        WHERE id_negocio=? AND activo=1 AND existencias<=existencia_minima
                        ORDER BY (existencia_minima-existencias) DESC,existencias ASC,nombre ASC LIMIT 1');
    $q->execute([$negocioId]);
    $baja = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($baja) {
        $recomendacion = 'Reabastece primero '.nombreResumenCorto($baja['nombre']).': quedan '.(int)$baja['existencias']
            .' unidades y su mínimo configurado es '.(int)$baja['existencia_minima'].'.';
    } elseif ($pendientes > 0) {
        $recomendacion = 'Prioriza la preparación y entrega de tus '.$pendientes.' pedido(s) pendiente(s).';
    } elseif ($masVendido) {
        $recomendacion = 'Destaca '.nombreResumenCorto($masVendido['nombre']).' en tu catálogo: fue el artículo más vendido ('
            .(int)$masVendido['unidades'].' unidades).';
    } else {
        $q = $pdo->prepare('SELECT nombre,existencias FROM productos WHERE id_negocio=? AND activo=1 ORDER BY existencias DESC,nombre LIMIT 1');
        $q->execute([$negocioId]);
        $producto = $q->fetch(PDO::FETCH_ASSOC) ?: null;
        $recomendacion = $producto
            ? 'Promociona '.$producto['nombre'].' para impulsar tus ventas: tienes '.(int)$producto['existencias'].' unidades disponibles.'
            : 'Registra al menos un producto activo para comenzar a vender y medir resultados.';
    }

    $intervalo = $inicio->format('d/m/Y').' al '.$lunes->modify('-1 day')->format('d/m/Y');
    $lider = $masVendido ? nombreResumenCorto($masVendido['nombre']).' ('.(int)$masVendido['unidades'].' unidades)' : 'sin ventas registradas';
    $mensaje = 'Resumen semanal del '.$intervalo.': '.$cantidad.' venta(s), $'.number_format($ingresos,2,'.',',')
        .' de ingresos. Pedidos pendientes al generar: '.$pendientes.'. Más vendido: '.$lider
        .'. Recomendación: '.$recomendacion;

    $pdo->beginTransaction();
    try {
        // UNIQUE(id_negocio,fecha_lunes) arbitra ejecuciones concurrentes.
        $insert = $pdo->prepare('INSERT IGNORE INTO resumenes_semanales
          (id_negocio,fecha_lunes,periodo_inicio,periodo_fin,ventas_cantidad,ingresos,pedidos_pendientes,recomendacion,mensaje)
          VALUES (?,?,?,?,?,?,?,?,?)');
        $insert->execute([$negocioId,$lunesSql,$inicio->format('Y-m-d'),$lunes->modify('-1 day')->format('Y-m-d'),
                          $cantidad,number_format($ingresos,2,'.',''),$pendientes,$recomendacion,$mensaje]);
        $nuevo = $insert->rowCount() === 1;
        if ($nuevo) {
            $dueños=$pdo->prepare("SELECT id FROM usuarios WHERE id_negocio=? AND rol='dueño' AND activo=1");
            $dueños->execute([$negocioId]);
            $aviso=$pdo->prepare('INSERT INTO notificaciones(id_usuario,id_negocio,tipo,titulo,mensaje,enlace)
                                  VALUES(?,?,?,?,?,?)');
            foreach ($dueños->fetchAll(PDO::FETCH_COLUMN) as $idDueño) {
                $aviso->execute([(int)$idDueño,$negocioId,'reporte',
                    'Tu resumen semanal · '.$lunes->format('d/m/Y'),$mensaje,'index.php?p=reports#resumen-semanal']);
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $resumen = resumenSemanalExistente($pdo,$negocioId,$lunes);
    if ($resumen === null) throw new RuntimeException('No se pudo recuperar el resumen semanal.');
    return ['nuevo'=>$nuevo, 'resumen'=>$resumen];
}
