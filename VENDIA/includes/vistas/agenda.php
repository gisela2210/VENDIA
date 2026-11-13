<?php
if (!logged()) { http_response_code(401); exit('Inicia sesión.'); }
$rol=me()['role'];
$mes=(string)($_GET['mes']??date('Y-m'));
if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$mes)) $mes=date('Y-m');
$primero=DateTimeImmutable::createFromFormat('!Y-m-d',$mes.'-01');
$inicio=$primero->modify('-'.((int)$primero->format('N')-1).' days');
$fin=$inicio->modify('+42 days');
$esAdmin=$rol==='superadministrador'; $esDueno=$rol==='dueño';
$sql="SELECT p.id,p.id_negocio,p.id_usuario,p.nombre_cliente,p.estado,p.fecha_entrega_prevista,p.fecha_reprogramada_en,n.nombre AS negocio
      FROM pedidos p JOIN negocios n ON n.id=p.id_negocio
      WHERE p.fecha_entrega_prevista>=? AND p.fecha_entrega_prevista<?";
$bind=[$inicio->format('Y-m-d'),$fin->format('Y-m-d')];
if (!$esAdmin && $rol!=='cliente') { $sql.=' AND p.id_negocio=?';$bind[]=(int)me()['store_id']; }
if ($rol==='empleado') { $sql.=" AND (p.id_usuario=? OR p.origen='web') AND p.estado<>'entregado'";$bind[]=(int)me()['id']; }
if ($rol==='cliente') { $sql.=' AND p.id_cliente=?';$bind[]=(int)me()['id']; }
$sql.=' ORDER BY p.fecha_entrega_prevista,p.id';
$q=$pdo->prepare($sql);$q->execute($bind);$pedidos=$q->fetchAll();
$porDia=[];
foreach($pedidos as $p) $porDia[substr($p['fecha_entrega_prevista'],0,10)][]=$p;
// Pedidos atrasados de cualquier mes, para que no queden ocultos al cambiar calendario.
$sql="SELECT p.id,p.id_usuario,p.nombre_cliente,p.fecha_entrega_prevista,p.estado,n.nombre AS negocio
      FROM pedidos p JOIN negocios n ON n.id=p.id_negocio
      WHERE p.estado<>'entregado' AND p.fecha_entrega_prevista<? AND p.fecha_entrega_prevista IS NOT NULL";
$args=[date('Y-m-d H:i:s')];
if (!$esAdmin && $rol!=='cliente') {$sql.=' AND p.id_negocio=?';$args[]=(int)me()['store_id'];}
if ($rol==='empleado') {$sql.=" AND (p.id_usuario=? OR p.origen='web')";$args[]=(int)me()['id'];}
if ($rol==='cliente') {$sql.=' AND p.id_cliente=?';$args[]=(int)me()['id'];}
$sql.=' ORDER BY p.fecha_entrega_prevista ASC LIMIT 100';
$q=$pdo->prepare($sql);$q->execute($args);$atrasados=$q->fetchAll();

// Promociones SOLO del negocio del empleado, ya autorizadas, vigentes y activas.
// La agenda no expone descuentos o inventario de otras empresas.
$agendaPromociones=[];$agendaPromoDia=[];
if ($rol==='empleado') {
    $pq=$pdo->prepare("SELECT pr.id,pr.nombre,pr.porcentaje_descuento,pr.tipo_beneficio,pr.unidades_llevas,pr.unidades_pagas,pr.fecha_inicio,pr.fecha_fin,
      (SELECT GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR ', ')
       FROM promocion_productos pp JOIN productos p ON p.id=pp.id_producto
       WHERE pp.id_promocion=pr.id AND p.id_negocio=pr.id_negocio AND p.activo=1) AS productos
      FROM promociones pr WHERE pr.id_negocio=? AND pr.autorizada=1 AND pr.activo=1
        AND pr.fecha_inicio<=CURDATE() AND pr.fecha_fin>=CURDATE()
        AND EXISTS (SELECT 1 FROM promocion_productos pp JOIN productos p
                    ON p.id=pp.id_producto WHERE pp.id_promocion=pr.id AND p.id_negocio=pr.id_negocio AND p.activo=1)
      ORDER BY pr.fecha_fin,pr.nombre");
    $pq->execute([(int)me()['store_id']]);$agendaPromociones=$pq->fetchAll();
    foreach ($agendaPromociones as $promo) {
        for ($dia=max(strtotime($promo['fecha_inicio']),$inicio->getTimestamp());
             $dia<=min(strtotime($promo['fecha_fin']),$fin->modify('-1 day')->getTimestamp());
             $dia+=86400) $agendaPromoDia[date('Y-m-d',$dia)][]=$promo;
    }
}
$colores=function($p) {
 if($p['estado']==='entregado') return ['verde','Entregado'];
 if(!empty($p['fecha_entrega_prevista']) && strtotime($p['fecha_entrega_prevista'])<time()) return ['rojo','Atrasado y sin entregar'];
 if(!empty($p['fecha_reprogramada_en'])) return ['naranja','Reprogramado / pendiente'];
 return ['amarillo','Agendado / pendiente'];
};
$mesNombre=['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
?>
<div class="page-heading"><div><p class="eyebrow">CALENDARIO</p><h1>Mi agenda</h1><p class="muted">Pedidos con fecha de entrega, pendientes y atrasados. <?= $rol==='empleado'?'Aquí solo aparecen tus pedidos abiertos.':($rol==='cliente'?'Aquí solo aparecen los pedidos vinculados a tu cuenta.':'') ?></p></div></div>
<div class="legend-row" aria-label="Leyenda de colores">
 <span class="pill-estado estado-verde">Verde: entregado</span>
 <span class="pill-estado estado-amarillo">Amarillo: agendado y pendiente</span>
 <span class="pill-estado estado-rojo">Rojo: atrasado, aún no entregado</span><span class="pill-estado estado-naranja">Naranja: reprogramado / pendiente</span>
</div>
<section class="panel">
<div class="cal-head"><h2><?=h(ucfirst($mesNombre[$primero->format('m')]))?> <?=$primero->format('Y')?></h2><div class="cal-nav"><a class="btn tiny" href="index.php?p=agenda&amp;mes=<?=$primero->modify('-1 month')->format('Y-m')?>">← Anterior</a><a class="btn tiny" href="index.php?p=agenda">Hoy</a><a class="btn tiny" href="index.php?p=agenda&amp;mes=<?=$primero->modify('+1 month')->format('Y-m')?>">Siguiente →</a></div></div>
<div class="cal-grid">
<?php foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $dia): ?><div class="cal-day-name"><?=h($dia)?></div><?php endforeach; ?>
<?php for($i=0;$i<42;$i++):$d=$inicio->modify('+'.$i.' days');$clave=$d->format('Y-m-d'); ?>
 <div class="cal-day <?=$d->format('m')!==$primero->format('m')?'muted':''?> <?=$clave===date('Y-m-d')?'today':''?>">
 <span class="cal-number"><?= $d->format('j') ?></span>
 <?php foreach(($porDia[$clave]??[]) as $p): [$tono,$texto]=$colores($p); ?>
 <span class="cal-event estado-<?=$tono?>" title="<?=h($texto.' · '.($p['negocio']??''))?>">#<?=$p['id']?> <?=h($p['nombre_cliente'])?><br><?=h(substr($p['fecha_entrega_prevista'],11,5))?><?= !empty($p['fecha_reprogramada_en']) && $p['estado']!=='entregado'?' · Reprogramado':'' ?></span>
 <?php endforeach; ?>
 <?php if($rol==='empleado'): foreach(($agendaPromoDia[$clave]??[]) as $promDia): ?>
 <span class="cal-event agenda-promo-chip" title="Promoción vigente hasta <?=h($promDia['fecha_fin'])?>">Oferta: <?=h($promDia['nombre'])?></span>
 <?php endforeach; endif; ?>
 </div>
<?php endfor; ?>
</div>
</section>
<section class="panel"><h2>Pedidos atrasados sin entregar <span class="pill-estado estado-rojo"><?=count($atrasados)?></span></h2><div class="table-scroll"><table><thead><tr><th>Folio</th><th>Cliente</th><?php if($esAdmin):?><th>Negocio</th><?php endif; ?><th>Fecha prevista</th><th>Estado</th><?php if(!$esAdmin && $rol!=='cliente'):?><th>Reprogramar</th><?php endif; ?></tr></thead><tbody>
<?php foreach($atrasados as $p): ?><tr><td>#<?=$p['id']?></td><td><?=h($p['nombre_cliente'])?></td><?php if($esAdmin):?><td><?=h($p['negocio'])?></td><?php endif; ?><td><?=h($p['fecha_entrega_prevista'])?></td><td><span class="pill-estado estado-rojo">Atrasado</span></td>
<?php if(!$esAdmin && $rol!=='cliente'):?><td><form method="post" class="vendia-mini-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="agenda_fecha"><input type="hidden" name="id_pedido" value="<?=$p['id']?>"><input type="datetime-local" name="fecha_entrega_prevista" min="<?=date('Y-m-d\TH:i')?>" value="<?=h(str_replace(' ','T',substr($p['fecha_entrega_prevista'],0,16)))?>" required><button class="btn tiny">Guardar</button></form></td><?php endif; ?></tr><?php endforeach; ?>
<?php if(!$atrasados):?><tr><td colspan="<?= $esAdmin||$rol==='cliente'?5:6 ?>">No hay pedidos atrasados registrados.</td></tr><?php endif; ?></tbody></table></div></section>
<?php if(!$esAdmin && $rol!=='cliente'): ?>
<section class="panel"><h2>Reprogramar cualquier pedido activo</h2><p class="muted">Selecciona un pedido que siga abierto. Los empleados solo pueden cambiar los pedidos que registraron.</p>
<?php $sql="SELECT id,nombre_cliente,fecha_entrega_prevista FROM pedidos WHERE id_negocio=? AND estado<>'entregado'";$args=[(int)me()['store_id']];if($rol==='empleado'){$sql.=" AND (id_usuario=? OR origen='web')";$args[]=(int)me()['id'];}$sql.=' ORDER BY id DESC LIMIT 150';$q=$pdo->prepare($sql);$q->execute($args);$listado=$q->fetchAll(); ?>
<form method="post" class="vendia-mini-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="agenda_fecha"><label>Pedido<br><select name="id_pedido" required><?php foreach($listado as $p):?><option value="<?=$p['id']?>">#<?=$p['id']?> <?=h($p['nombre_cliente'])?></option><?php endforeach; ?></select></label><label>Entrega prevista<br><input type="datetime-local" name="fecha_entrega_prevista" min="<?=date('Y-m-d\TH:i')?>" required></label><button class="btn primary">Agendar entrega</button></form>
<?php if(!$listado):?><p class="muted">No tienes pedidos abiertos para agendar.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php if($rol==='empleado'): ?>
<section class="panel agenda-avisos-azules" id="agenda-avisos-promos" aria-labelledby="avisosPromoTitulo">
 <h2 id="avisosPromoTitulo"><?=icono('porcentaje')?> Promociones activas del negocio</h2>
 <?php if(!$agendaPromociones): ?><p class="agenda-aviso-azul">Por ahora no hay promociones autorizadas y vigentes. En cuanto el dueño active una, aparecerá aquí y en la caja de Pedidos.</p>
 <?php else: ?>
 <?php foreach($agendaPromociones as $promo): ?>
 <div class="agenda-aviso-azul"><strong><?=h($promo['nombre'])?> · <?=h(etiquetaOferta($promo))?></strong>
 <p>Vigente hasta <?=h(date('d/m/Y',strtotime($promo['fecha_fin'])))?>. Productos: <?=h($promo['productos']?:'Consultar en Pedidos')?>.</p>
 </div>
 <?php endforeach; endif; ?>
</section>
<?php endif; ?>
