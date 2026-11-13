<?php
/** Venti: consultas predefinidas basadas solo en registros reales y permisos por rol.
 * No usa servicios externos de IA ni ofrece información financiera a clientes.
 */
require dirname(__DIR__).'/includes/bootstrap.php';
require dirname(__DIR__).'/includes/agente_preguntas.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
function respuestaVenti(string $s, int $http=200): void {
    http_response_code($http);
    echo json_encode(['respuesta'=>$s],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES);
    exit;
}
if (!logged() || !in_array(me()['role'], ['dueño','cliente'], true)) respuestaVenti('Solo Dueño y Cliente pueden usar Venti.',403);
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') respuestaVenti('Envía tu consulta desde Venti.',405);
if (!hash_equals($_SESSION['csrf']??'', (string)($_POST['csrf']??''))) respuestaVenti('Sesión caducada. Actualiza la página.',403);
$pregunta=trim((string)($_POST['pregunta']??''));
if ($pregunta==='' || (function_exists('mb_strlen')?mb_strlen($pregunta,'UTF-8'):strlen($pregunta))>300) respuestaVenti('Escribe una pregunta de hasta 300 caracteres.',422);
$rol=me()['role'];
$preguntas=preguntasVenti($rol);
$intencion=(string)($_POST['consulta_id']??'');
$normalizar=static function(string $s): string {
    $s=function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);
    $s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    return trim(preg_replace('/[^a-z0-9]+/u',' ',$s));
};
if (!isset($preguntas[$intencion]) || $normalizar($preguntas[$intencion]) !== $normalizar($pregunta)) {
    $intencion='';
    foreach($preguntas as $clave=>$p) if ($normalizar($p)===$normalizar($pregunta)) {$intencion=$clave;break;}
}
if ($intencion==='') {
    $q=$normalizar($pregunta);
    if(preg_match('/ayuda|soporte|mensaje|problema/',$q)) respuestaVenti('Abre Ayuda para enviar tu consulta a una persona. También recibirás notificaciones de las respuestas.');
    if(preg_match('/agenda|pedido|entrega/',$q)) respuestaVenti('Revisa Mi agenda para ver los pedidos pendientes, programados y entregados de tu cuenta.');
    if(preg_match('/notificacion|aviso/',$q)) respuestaVenti('Abre Notificaciones para consultar los avisos que corresponden a tu cuenta.');
    if($rol==='dueño') {
        if(preg_match('/vendimos.*hoy|ventas.*hoy/',$q))$intencion='ventas_hoy';
        elseif(preg_match('/semana.*ventas|vendimos.*semana/',$q))$intencion='ventas_semana';
        elseif(preg_match('/mes.*ventas|vendimos.*mes/',$q))$intencion='ventas_mes';
        elseif(preg_match('/existencia|inventario|stock/',$q))$intencion='inventario';
    } else {
        if(preg_match('/promocion|oferta|descuento/',$q))$intencion='promos_hoy';
        elseif(preg_match('/producto|catalogo|comprar/',$q))$intencion='disponibles';
    }
    if($intencion==='') respuestaVenti('Puedo responder las preguntas del selector utilizando los datos registrados. Elige una pregunta de la lista o consulta Ayuda para otro tema.');
}
$consultar=static function(PDO $pdo,string $sql,array $values=[]): array {
    $st=$pdo->prepare($sql);$st->execute($values);return $st->fetchAll(PDO::FETCH_ASSOC);
};
$uno=static function(PDO $pdo,string $sql,array $values=[]) use ($consultar): array {
    return $consultar($pdo,$sql,$values)[0]??[];
};
$pesos=static fn($v): string=>'$'.number_format((float)$v,2,'.',',').' MXN';
$listar=static function(array $rows, callable $render): string {
    if(!$rows) return 'No hay registros que cumplan la consulta.';
    return implode("\n",array_map($render,$rows));
};
try {
    $sid=$rol==='dueño'?(int)me()['store_id']:(int)($_POST['id_negocio']??0);
    // Dueños consultan exclusivamente su negocio; clientes solo negocios públicos habilitados.
    if ($rol==='dueño') {
        $negocio=$uno($pdo,'SELECT id,nombre,horario_comercial FROM negocios WHERE id=? AND id_dueno=? LIMIT 1',[$sid,(int)me()['id']]);
        if(!$negocio) respuestaVenti('No se pudo identificar el negocio de tu cuenta.');
    } else {
        if ($sid<1) respuestaVenti('Primero selecciona el negocio del que quieres consultar productos y ofertas.');
        $negocio=$uno($pdo,"SELECT id,nombre,horario_comercial FROM negocios WHERE id=? AND activo=1 AND estado_suscripcion<>'suspendida' LIMIT 1",[$sid]);
        if(!$negocio) respuestaVenti('El negocio seleccionado ya no está disponible. Elige otro.');
    }
    $nombreNegocio=$negocio['nombre'];
    $sqlPromo="SELECT pr.nombre,pr.porcentaje_descuento,pr.tipo_beneficio,pr.unidades_llevas,pr.unidades_pagas,pr.fecha_fin FROM promociones pr WHERE pr.id_negocio=? AND pr.activo=1 AND pr.autorizada=1 AND pr.fecha_inicio<=CURDATE() AND pr.fecha_fin>=CURDATE() AND EXISTS (SELECT 1 FROM promocion_productos pp JOIN productos p ON p.id=pp.id_producto WHERE pp.id_promocion=pr.id AND p.id_negocio=pr.id_negocio AND p.activo=1 AND p.existencias>0)";
    if ($rol==='dueño') {
        switch($intencion) {
            case 'ventas_hoy':
            case 'ventas_semana':
            case 'ventas_mes':
                if($intencion==='ventas_hoy'){$start=date('Y-m-d 00:00:00');$end=date('Y-m-d 00:00:00',strtotime('+1 day'));$periodo='hoy';}
                elseif($intencion==='ventas_semana'){$start=(new DateTimeImmutable('monday this week'))->format('Y-m-d 00:00:00');$end=(new DateTimeImmutable($start))->modify('+7 days')->format('Y-m-d H:i:s');$periodo='esta semana';}
                else{$start=date('Y-m-01 00:00:00');$end=(new DateTimeImmutable($start))->modify('first day of next month')->format('Y-m-d H:i:s');$periodo='este mes';}
                $r=$uno($pdo,"SELECT COUNT(*) AS operaciones,COALESCE(SUM(total),0) AS ingreso FROM ventas WHERE id_negocio=? AND estado='completada' AND fecha_creacion>=? AND fecha_creacion<?",[$sid,$start,$end]);
                respuestaVenti('En '.$nombreNegocio.', '.$periodo.' tienes '.(int)$r['operaciones'].' venta(s) completada(s) por '.$pesos($r['ingreso']).'. Solo se cuentan ventas registradas en el sistema.');
            case 'inventario':
                $total=$uno($pdo,'SELECT COUNT(*) AS cantidad,COALESCE(SUM(existencias),0) AS unidades FROM productos WHERE id_negocio=? AND activo=1',[$sid]);
                $rows=$consultar($pdo,'SELECT nombre,existencias FROM productos WHERE id_negocio=? AND activo=1 ORDER BY nombre LIMIT 12',[$sid]);
                respuestaVenti('Hay '.(int)$total['cantidad'].' productos activos y '.(int)$total['unidades'].' unidades en el inventario. Primeros artículos:'."\n".$listar($rows,fn($r)=>'• '.$r['nombre'].': '.(int)$r['existencias'].' unidades.'));
            case 'bajas':
            case 'reabastecer':
            case 'prioridad_surtido':
                $rows=$consultar($pdo,'SELECT nombre,existencias,existencia_minima FROM productos WHERE id_negocio=? AND activo=1 AND existencias<=existencia_minima ORDER BY (existencia_minima-existencias) DESC,existencias ASC,nombre LIMIT 12',[$sid]);
                $label=$intencion==='prioridad_surtido'?'Prioridad sugerida según cuánto falta para cubrir el mínimo de inventario:':'Productos con existencias iguales o inferiores al mínimo registrado:';
                respuestaVenti($label."\n".$listar($rows,fn($r)=>'• '.$r['nombre'].': quedan '.(int)$r['existencias'].', mínimo '.(int)$r['existencia_minima'].'.').(!$rows?' No hay alertas de reabastecimiento.':''));
            case 'horario':
                respuestaVenti('Horario registrado de '.$nombreNegocio.': '.(trim((string)$negocio['horario_comercial'])?:'Todavía no se ha configurado un horario.').'.');
            case 'producto_top':
                $r=$uno($pdo,"SELECT dv.nombre_producto AS nombre,SUM(dv.cantidad) AS unidades FROM detalle_ventas dv JOIN ventas v ON v.id=dv.id_venta WHERE v.id_negocio=? AND v.estado='completada' GROUP BY dv.id_producto,dv.nombre_producto ORDER BY unidades DESC,nombre LIMIT 1",[$sid]);
                respuestaVenti($r?'Tu producto más vendido históricamente es '.$r['nombre'].' con '.(int)$r['unidades'].' unidades registradas.':'Todavía no hay ventas completadas para calcular cuál es el más vendido.');
            case 'sin_ventas':
                $rows=$consultar($pdo,"SELECT p.nombre FROM productos p WHERE p.id_negocio=? AND p.activo=1 AND NOT EXISTS(SELECT 1 FROM detalle_ventas dv JOIN ventas v ON v.id=dv.id_venta WHERE dv.id_producto=p.id AND v.id_negocio=p.id_negocio AND v.estado='completada') ORDER BY p.nombre LIMIT 12",[$sid]);
                respuestaVenti('Productos activos sin ventas completadas registradas hasta ahora:'."\n".$listar($rows,fn($r)=>'• '.$r['nombre']));
            case 'pedidos_pendientes':
                $r=$uno($pdo,"SELECT COUNT(*) AS cantidad FROM pedidos WHERE id_negocio=? AND estado IN ('pendiente','preparacion','listo')",[$sid]);
                respuestaVenti('Tienes '.(int)$r['cantidad'].' pedido(s) aún no entregados en '.$nombreNegocio.'.');
            case 'entregados_hoy':
                $r=$uno($pdo,"SELECT COUNT(DISTINCT p.id) AS cantidad FROM pedidos p JOIN ventas v ON v.id_pedido=p.id WHERE p.id_negocio=? AND p.estado='entregado' AND v.estado='completada' AND DATE(v.fecha_creacion)=CURDATE()",[$sid]);
                respuestaVenti('Hoy se cobraron y marcaron como entregados '.(int)$r['cantidad'].' pedido(s) vinculados a una venta. Los pedidos marcados manualmente como entregados sin cobro no pueden fecharse con precisión en la versión actual.');
            case 'empleado_top':
                $r=$uno($pdo,"SELECT u.nombre,u.apellido_paterno,COUNT(*) AS ventas FROM ventas v JOIN usuarios u ON u.id=v.id_usuario WHERE v.id_negocio=? AND v.estado='completada' AND u.rol='empleado' GROUP BY u.id,u.nombre,u.apellido_paterno ORDER BY ventas DESC,u.nombre LIMIT 1",[$sid]);
                respuestaVenti($r?'El empleado con más ventas completadas es '.$r['nombre'].' '.$r['apellido_paterno'].' con '.(int)$r['ventas'].' operaciones (histórico registrado).':'Todavía no hay ventas completadas por empleados.');
            case 'utilidad':
                $v=$uno($pdo,"SELECT COALESCE(SUM(total),0) AS ingreso FROM ventas WHERE id_negocio=? AND estado='completada'",[$sid]);
                $c=$uno($pdo,"SELECT COALESCE(SUM(dv.cantidad*COALESCE(p.costo,0)),0) AS costo FROM detalle_ventas dv JOIN ventas v ON v.id=dv.id_venta LEFT JOIN productos p ON p.id=dv.id_producto WHERE v.id_negocio=? AND v.estado='completada'",[$sid]);
                $m=(float)$v['ingreso']-(float)$c['costo'];
                respuestaVenti('Utilidad bruta ESTIMADA del histórico: '.$pesos($m).'. Ingresos: '.$pesos($v['ingreso']).'. Costos estimados de productos: '.$pesos($c['costo']).'. No incluye gastos operativos, impuestos ni cambios históricos de costo.');
            case 'promociones':
                $rows=$consultar($pdo,$sqlPromo.' ORDER BY pr.fecha_fin,pr.nombre LIMIT 12',[$sid]);
                respuestaVenti('Promociones autorizadas y vigentes:\n'.$listar($rows,fn($r)=>'• '.$r['nombre'].' — '.etiquetaOferta($r).' hasta '.date('d/m/Y',strtotime($r['fecha_fin'])).'.'));
        }
    } else {
        $sqlCatalogo='SELECT nombre,precio,existencias,fecha_creacion,foto FROM productos WHERE id_negocio=? AND activo=1 AND existencias>0';
        $catalogoLista=static fn($rows)=>$listar($rows,fn($r)=>'• '.$r['nombre'].' — '.$pesos($r['precio']).'.');
        switch($intencion) {
            case 'catalogo':
            case 'ver_ofertas':
            case 'fotos':
                $imagenes=$uno($pdo,"SELECT COUNT(*) AS n FROM productos WHERE id_negocio=? AND activo=1 AND existencias>0 AND foto IS NOT NULL AND foto<>''",[$sid]);
                respuestaVenti('En «Hacer pedido» puedes elegir '.$nombreNegocio.', consultar el catálogo con fotografías y solicitar productos. '.(int)$imagenes['n'].' artículo(s) disponibles tienen foto registrada. Las ofertas se consultan aquí o al seleccionar promociones vigentes.');
            case 'promos_hoy':
            case 'descuentos':
            case 'compartir':
                $rows=$consultar($pdo,$sqlPromo.' ORDER BY pr.fecha_fin,pr.nombre LIMIT 12',[$sid]);
                respuestaVenti('Ofertas vigentes y autorizadas de '.$nombreNegocio."\n".$listar($rows,fn($r)=>'• '.$r['nombre'].' — '.etiquetaOferta($r).' (vence '.date('d/m/Y',strtotime($r['fecha_fin'])).').')."\nConsulta directamente al negocio antes de compartirlas; no he creado cupones ni enlaces nuevos.");
            case 'disponibles':
            case 'semana':
            case 'para_negocio':
                $rows=$consultar($pdo,$sqlCatalogo.' ORDER BY nombre LIMIT 12',[$sid]);
                respuestaVenti('Estos productos están disponibles ahora en '.$nombreNegocio.' (la disponibilidad puede cambiar):' ."\n".$catalogoLista($rows));
            case 'presupuesto':
                $rows=$consultar($pdo,$sqlCatalogo.' AND precio<=100 ORDER BY precio,nombre LIMIT 12',[$sid]);
                respuestaVenti('Productos con precio unitario de $100 MXN o menos:'."\n".$catalogoLista($rows).' El presupuesto es por producto, sin calcular combinaciones ni envío.');
            case 'nuevos':
            case 'novedades':
                $rows=$consultar($pdo,$sqlCatalogo.' AND fecha_creacion>=DATE_SUB(NOW(),INTERVAL 30 DAY) ORDER BY fecha_creacion DESC LIMIT 12',[$sid]);
                respuestaVenti('Productos agregados en los últimos 30 días:'."\n".$catalogoLista($rows));
            case 'mas_vendidos':
            case 'recomendar':
            case 'recomendar_hoy':
                $rows=$consultar($pdo,"SELECT p.nombre,p.precio,SUM(dv.cantidad) AS unidades FROM productos p JOIN detalle_ventas dv ON dv.id_producto=p.id JOIN ventas v ON v.id=dv.id_venta WHERE p.id_negocio=? AND p.activo=1 AND p.existencias>0 AND v.id_negocio=p.id_negocio AND v.estado='completada' GROUP BY p.id,p.nombre,p.precio ORDER BY unidades DESC,p.nombre LIMIT 8",[$sid]);
                if($rows)respuestaVenti('Según ventas registradas de '.$nombreNegocio.', estas son opciones populares (no una recomendación personalizada):\n'.$listar($rows,fn($r)=>'• '.$r['nombre'].' — '.$pesos($r['precio']).' ('.(int)$r['unidades'].' vendidas).'));
                $rows=$consultar($pdo,$sqlCatalogo.' ORDER BY nombre LIMIT 8',[$sid]);
                respuestaVenti('Aún no hay estadísticas de ventas de '.$nombreNegocio.'. Estos productos están disponibles:\n'.$catalogoLista($rows));
            case 'descuentos_especiales':
            case 'economicas':
                $rows=$consultar($pdo,"SELECT p.nombre,p.precio,pr.nombre AS promocion,pr.porcentaje_descuento,pr.fecha_fin,ROUND(p.precio*(1-pr.porcentaje_descuento/100),2) AS precio_final FROM productos p JOIN promocion_productos pp ON pp.id_producto=p.id JOIN promociones pr ON pr.id=pp.id_promocion WHERE p.id_negocio=? AND pr.id_negocio=p.id_negocio AND p.activo=1 AND p.existencias>0 AND pr.activo=1 AND pr.autorizada=1 AND pr.fecha_inicio<=CURDATE() AND pr.fecha_fin>=CURDATE() AND pr.tipo_beneficio='descuento' ORDER BY precio_final,p.nombre LIMIT 12",[$sid]);
                respuestaVenti('Artículos con descuentos vigentes, ordenados por precio final:'."\n".$listar($rows,fn($r)=>'• '.$r['nombre'].' — '.$pesos($r['precio_final']).' con '.$r['promocion'].' ('.(float)$r['porcentaje_descuento'].'%).'));
            case 'paquetes':
                $rows=$consultar($pdo,"SELECT pr.nombre,pr.porcentaje_descuento,pr.tipo_beneficio,pr.unidades_llevas,pr.unidades_pagas,COUNT(DISTINCT pp.id_producto) AS productos FROM promociones pr JOIN promocion_productos pp ON pp.id_promocion=pr.id JOIN productos p ON p.id=pp.id_producto AND p.id_negocio=pr.id_negocio AND p.activo=1 AND p.existencias>0 WHERE pr.id_negocio=? AND pr.activo=1 AND pr.autorizada=1 AND pr.fecha_inicio<=CURDATE() AND pr.fecha_fin>=CURDATE() GROUP BY pr.id,pr.nombre,pr.porcentaje_descuento,pr.tipo_beneficio,pr.unidades_llevas,pr.unidades_pagas HAVING productos>=2 OR pr.tipo_beneficio='promocion' ORDER BY productos DESC LIMIT 10",[$sid]);
                respuestaVenti('Promociones por cantidad o que incluyen varios productos:'."\n".$listar($rows,fn($r)=>'• '.$r['nombre'].' ('.(int)$r['productos'].' productos participantes, '.etiquetaOferta($r).').').' La caja calcula cada grupo al cobrar; si combinas productos, se descuentan los más baratos.');
            case 'por_terminar':
                $rows=$consultar($pdo,$sqlPromo.' AND pr.fecha_fin<=DATE_ADD(CURDATE(),INTERVAL 3 DAY) ORDER BY pr.fecha_fin,pr.nombre LIMIT 12',[$sid]);
                respuestaVenti('Promociones que vencen dentro de los próximos 3 días:'."\n".$listar($rows,fn($r)=>'• '.$r['nombre'].' — hasta el '.date('d/m/Y',strtotime($r['fecha_fin'])).'.'));
        }
    }
    respuestaVenti('No encontré esa consulta entre las preguntas disponibles para tu perfil. Elige una de la lista.');
} catch(Throwable $e) {
    error_log('Venti consulta: '.$e->getMessage());
    respuestaVenti('No fue posible consultar los registros del negocio en este momento. Revisa la conexión de Vendia o contacta Ayuda.',500);
}
