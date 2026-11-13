<?php
adminOnly();
$negocios=$pdo->query("SELECT n.*,u.nombre AS nombre_dueno,u.apellido_paterno AS apellido_dueno,u.usuario AS usuario_dueno,
    (SELECT COUNT(*) FROM usuarios x WHERE x.id_negocio=n.id) AS numero_usuarios,
    (SELECT COUNT(*) FROM pedidos x WHERE x.id_negocio=n.id AND x.estado<>'entregado') AS pedidos_pendientes
    FROM negocios n LEFT JOIN usuarios u ON u.id=n.id_dueno ORDER BY n.id DESC LIMIT 200")->fetchAll();
$ordenUsuarios=(string)($_GET['orden_usuarios']??'fecha_desc');
$tipoUsuarios=(string)($_GET['tipo_usuario']??'todos');
$ordenUsuariosSql=['fecha_desc'=>'u.fecha_creacion DESC,u.id DESC','fecha_asc'=>'u.fecha_creacion ASC,u.id ASC',
 'nombre_asc'=>'u.nombre ASC,u.apellido_paterno ASC,u.id ASC','nombre_desc'=>'u.nombre DESC,u.apellido_paterno DESC,u.id DESC'];
if(!isset($ordenUsuariosSql[$ordenUsuarios]))$ordenUsuarios='fecha_desc';
if(!in_array($tipoUsuarios,['todos','dueño','empleado','cliente'],true))$tipoUsuarios='todos';
$where="u.rol IN ('dueño','empleado','cliente')";
$params=[];
if($tipoUsuarios!=='todos'){$where.=' AND u.rol=?';$params[]=$tipoUsuarios;}
$qUsuarios=$pdo->prepare("SELECT u.id,u.nombre,u.apellido_paterno,u.usuario,u.correo,u.telefono,u.apellido_materno,
 u.rol,u.activo,u.fecha_creacion,n.nombre AS negocio
 FROM usuarios u LEFT JOIN negocios n ON n.id=u.id_negocio WHERE ".$where." ORDER BY ".$ordenUsuariosSql[$ordenUsuarios]." LIMIT 500");
$qUsuarios->execute($params); $usuarios=$qUsuarios->fetchAll();
$ordenPagos=(string)($_GET['orden_pagos']??'pendientes');
$estadoPagos=(string)($_GET['estado_pagos']??'todos');
$ordenPagosSql=['pendientes'=>"(p.estado='pendiente') DESC,p.id DESC",'importe_desc'=>'p.importe DESC,p.id DESC',
 'importe_asc'=>'p.importe ASC,p.id DESC','fecha_desc'=>'p.fecha_pago DESC,p.id DESC','fecha_asc'=>'p.fecha_pago ASC,p.id ASC'];
if(!isset($ordenPagosSql[$ordenPagos]))$ordenPagos='pendientes';
if(!in_array($estadoPagos,['todos','pendiente','verificado','rechazado'],true))$estadoPagos='todos';
$wherePagos=$estadoPagos==='todos'?'':' WHERE p.estado=?';
$qPagos=$pdo->prepare("SELECT p.*,n.nombre AS negocio,u.usuario AS reportado_por
 FROM pagos p JOIN negocios n ON n.id=p.id_negocio JOIN usuarios u ON u.id=p.id_usuario".$wherePagos." ORDER BY ".$ordenPagosSql[$ordenPagos]." LIMIT 500");
$qPagos->execute($estadoPagos==='todos'?[]:[$estadoPagos]);$pagos=$qPagos->fetchAll();
$eventos=$pdo->query("SELECT e.*,n.nombre AS negocio FROM eventos_negocio e JOIN negocios n ON n.id=e.id_negocio ORDER BY e.id DESC LIMIT 40")->fetchAll();
$altas=$pdo->query("SELECT DATE_FORMAT(fecha_creacion,'%Y-%m') AS mes,COUNT(*) AS cantidad FROM negocios
    WHERE fecha_creacion>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(fecha_creacion,'%Y-%m')")->fetchAll(PDO::FETCH_KEY_PAIR);
$bajas=$pdo->query("SELECT DATE_FORMAT(fecha_creacion,'%Y-%m') AS mes,COUNT(*) AS cantidad FROM eventos_negocio
    WHERE accion='baja' AND fecha_creacion>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(fecha_creacion,'%Y-%m')")->fetchAll(PDO::FETCH_KEY_PAIR);
$total=count($negocios);$inactivos=count(array_filter($negocios,fn($n)=>(int)$n['activo']===0));
$pagosPendientes=count(array_filter($pagos,fn($p)=>$p['estado']==='pendiente'));
$pendientesAyuda=(int)$pdo->query("SELECT COUNT(*) FROM solicitudes_ayuda WHERE estado='abierta'")->fetchColumn();
$meses=[];
for($i=5;$i>=0;$i--)$meses[]=(new DateTimeImmutable('first day of this month'))->modify('-'.$i.' months')->format('Y-m');
$tope=max(1,...array_map(fn($m)=>max((int)($altas[$m]??0),(int)($bajas[$m]??0)),$meses));
?>
<div class="page-heading"><div><p class="eyebrow">ADMINISTRACIÓN GLOBAL</p><h1><?=h($page==='superadmin-usuarios'?'Administración de usuarios':($page==='superadmin-pagos'?'Verificación manual de pagos':'Panel de superadministrador'))?></h1><p class="muted">Negocios, usuarios, pagos y notificaciones organizados en secciones independientes.</p></div></div>
<?php if (!empty($_SESSION['enlace_admin_reset'])): ?>
<section class="notice"><strong>Enlace temporal de recuperación (30 minutos):</strong>
<code><?=h($_SESSION['enlace_admin_reset'])?></code><p>Entrégalo únicamente al titular por un canal seguro. Ya no aparecerá cuando recargues.</p></section>
<?php unset($_SESSION['enlace_admin_reset']); endif; ?>
<?php if($page==='superadmin'): ?>
<div class="admin-cards">
 <div class="admin-card"><small>Negocios registrados</small><strong><?=$total?></strong><span class="pill-estado estado-verde">Altas acumuladas</span></div>
 <div class="admin-card"><small>Negocios suspendidos</small><strong><?=$inactivos?></strong><span class="pill-estado <?= $inactivos?'estado-rojo':'estado-verde' ?>">Bajas reversibles</span></div>
 <div class="admin-card"><small>Pagos por verificar</small><strong><?=$pagosPendientes?></strong><span class="pill-estado <?= $pagosPendientes?'estado-amarillo':'estado-verde' ?>">Revisión manual</span></div>
 <div class="admin-card"><small>Solicitudes abiertas</small><strong><?=$pendientesAyuda?></strong><a href="index.php?p=ayuda">Responder mensajes</a></div>
</div>
<section class="panel"><h2>Altas y bajas recientes</h2><p class="muted">Contadores registrados en Vendia. Las bajas son suspensiones reversibles, no eliminación de datos.</p>
<div style="display:flex;gap:16px;align-items:end;overflow-x:auto;min-height:190px;padding:15px">
<?php foreach($meses as $m): $a=(int)($altas[$m]??0); $b=(int)($bajas[$m]??0); ?>
<div style="flex:1;min-width:72px;text-align:center"><div style="height:130px;display:flex;gap:6px;align-items:end;justify-content:center">
 <div title="<?=$a?> altas" style="width:23px;height:<?=max(5,round($a/$tope*120))?>px;background:#079b72;border-radius:6px 6px 0 0"></div>
 <div title="<?=$b?> bajas" style="width:23px;height:<?=max(5,round($b/$tope*120))?>px;background:#d94f59;border-radius:6px 6px 0 0"></div>
</div><small><?=h(substr($m,5))?> / <?=h(substr($m,2,2))?><br><?=$a?> altas · <?=$b?> bajas</small></div>
<?php endforeach; ?>
</div>
<div class="legend-row"><span class="pill-estado estado-verde">Verde: altas</span><span class="pill-estado estado-rojo">Rojo: bajas</span></div>
<p class="muted"><strong>Recomendación basada en tus registros:</strong>
<?php if($inactivos): ?>revisa los <?=$inactivos?> negocio(s) suspendido(s) y las razones de baja antes de reactivarlos.<?php elseif($pagosPendientes): ?>verifica los <?=$pagosPendientes?> pago(s) pendientes contrastando los comprobantes con tus movimientos bancarios.<?php else: ?>mantén actualizados los mantenimientos y las solicitudes de ayuda.<?php endif; ?>
</p></section>
<section class="panel"><h2>Administración de negocios</h2><p class="muted">Desactivar bloquea el acceso del dueño y sus empleados. Nunca elimina registros.</p><div class="table-scroll"><table class="admin-table"><thead><tr><th>Negocio / dueño</th><th>Estado</th><th>Usuarios / pedidos</th><th>Mantenimiento</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($negocios as $n):
 $dias=$n['proximo_mantenimiento']!==null?(int)floor((strtotime($n['proximo_mantenimiento'].' 23:59:59')-time())/86400):null;
 $semaforo=$dias===null?'amarillo':($dias<0?'rojo':($dias<=7?'amarillo':'verde'));
 $avance=$dias===null?0:($dias<0?100:max(4,min(100,round((30-$dias)/30*100))));
?>
<tr><td><strong><?=h($n['nombre'])?></strong><br><small><?=h($n['categoria'])?> · <?=h($n['nombre_dueno'].' '.$n['apellido_dueno'])?><br><?=h($n['usuario_dueno'])?></small></td>
<td><span class="pill-estado estado-<?= $n['activo']?'verde':'rojo' ?>"><?= $n['activo']?'Activo':'Suspendido' ?></span><br><small><?=h(str_replace('_',' ',$n['estado_suscripcion']))?></small></td>
<td><?= (int)$n['numero_usuarios']?> cuenta(s)<br><?= (int)$n['pedidos_pendientes']?> pedido(s) abiertos</td>
<td><small><?=h($n['proximo_mantenimiento']??'Sin programar')?></small><div class="vendia-progress <?=$semaforo?>" title="<?=h($dias===null?'Sin fecha':($dias<0?'Vencido hace '.abs($dias).' días':$dias.' días restantes'))?>"><span style="width:<?=$avance?>%"></span></div>
<span class="pill-estado estado-<?=$semaforo?>"><?= $dias===null?'Pendiente de fecha':($dias<0?'Mantenimiento vencido':($dias<=7?'Se aproxima':'En plazo'))?></span>
<form method="post" class="vendia-mini-form" style="margin-top:8px"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="admin_mantenimiento"><input type="hidden" name="id_negocio" value="<?=$n['id']?>"><input type="date" name="fecha" value="<?=h($n['proximo_mantenimiento']??'')?>" aria-label="Fecha mantenimiento" required><button class="btn tiny">Guardar fecha</button></form></td>
<td>
<form method="post" class="vendia-mini-form" onsubmit="return confirm('¿Confirmar el cambio de acceso de este negocio?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="admin_negocio_estado"><input type="hidden" name="id_negocio" value="<?=$n['id']?>"><input type="hidden" name="activo" value="<?=$n['activo']?0:1?>"><input name="motivo" maxlength="255" placeholder="Motivo (opcional)" aria-label="Motivo de la decisión"><button class="btn tiny <?=$n['activo']?'':'primary'?>"><?=$n['activo']?'Dar de baja':'Reactivar'?></button></form>
<form method="post" class="vendia-mini-form" style="margin-top:8px"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="admin_suscripcion_estado"><input type="hidden" name="id_negocio" value="<?=$n['id']?>"><select name="estado_suscripcion" aria-label="Estado suscripción"><?php foreach(['al_corriente'=>'Al corriente','pendiente'=>'Pendiente','vencida'=>'Vencida','suspendida'=>'Suspendida'] as $v=>$l): ?><option value="<?=h($v)?>" <?=$n['estado_suscripcion']===$v?'selected':''?>><?=h($l)?></option><?php endforeach; ?></select><button class="btn tiny">Guardar estado</button></form>
</td></tr>
<?php endforeach; ?>
<?php if(!$negocios):?><tr><td colspan="5">No existen negocios registrados.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php if($page==='superadmin-usuarios'): ?>
<section class="panel" id="usuarios-listado"><h2>Usuarios: dueños, empleados y clientes</h2>
<form method="get" action="index.php#usuarios-listado" class="vendia-filter-bar" aria-label="Filtros de usuarios">
 <input type="hidden" name="p" value="superadmin-usuarios">
 <div><label for="filtroTipoUsuario">Mostrar usuarios</label><select id="filtroTipoUsuario" name="tipo_usuario">
 <?php foreach(['todos'=>'Todos los perfiles','cliente'=>'Solo clientes','dueño'=>'Solo dueños','empleado'=>'Solo empleados'] as $k=>$v):?><option value="<?=h($k)?>" <?=$tipoUsuarios===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></div>
 <div><label for="filtroOrdenUsuarios">Ordenar por</label><select id="filtroOrdenUsuarios" name="orden_usuarios">
 <?php foreach(['fecha_desc'=>'Fecha de creación: más recientes','fecha_asc'=>'Fecha de creación: más antiguos','nombre_asc'=>'Alfabético: A a Z','nombre_desc'=>'Alfabético: Z a A'] as $k=>$v):?><option value="<?=h($k)?>" <?=$ordenUsuarios===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></div>
 <button class="btn primary" type="submit">Aplicar filtros</button><a class="btn" href="index.php?p=superadmin-usuarios#usuarios-listado">Limpiar</a>
</form><p class="muted">Mostrando <?=count($usuarios)?> cuenta(s), máximo 500 registros por consulta.</p>
<div class="table-scroll"><table class="admin-table"><thead><tr><th>Nombre</th><th>Usuario</th><th>Tipo</th><th>Fecha de creación</th><th>Negocio</th><th>Estado</th><th>Opciones</th></tr></thead><tbody>
<?php foreach($usuarios as $u): ?><tr id="usuario-<?= (int)$u['id']?>"><td><?=h($u['nombre'].' '.$u['apellido_paterno'])?></td><td><?=h($u['usuario'])?></td><td><?=h(ucfirst($u['rol']))?></td><td><?=h(date('d/m/Y',strtotime($u['fecha_creacion'])))?></td><td><?=h($u['negocio']??'No asignado')?></td><td><span class="pill-estado estado-<?=$u['activo']?'verde':'rojo'?>"><?=$u['activo']?'Activo':'Inactivo'?></span></td><td><div class="vendia-actions">
<form method="post" data-vendia-stay><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="orden_usuarios" value="<?=h($ordenUsuarios)?>"><input type="hidden" name="tipo_usuario" value="<?=h($tipoUsuarios)?>"><input type="hidden" name="action" value="admin_usuario_estado"><input type="hidden" name="id_usuario" value="<?=$u['id']?>"><input type="hidden" name="activo" value="<?=$u['activo']?0:1?>"><button class="btn tiny"><?=$u['activo']?'Desactivar':'Activar'?></button></form>
<form method="post" data-vendia-stay onsubmit="return confirm('¿Generar un enlace temporal de recuperación para esta cuenta?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="orden_usuarios" value="<?=h($ordenUsuarios)?>"><input type="hidden" name="tipo_usuario" value="<?=h($tipoUsuarios)?>"><input type="hidden" name="action" value="admin_reset"><input type="hidden" name="id_usuario" value="<?=$u['id']?>"><button class="btn tiny">Recuperar acceso</button></form></div>
<details style="margin-top:7px"><summary>Editar datos</summary><form method="post" data-vendia-stay class="form-grid" style="min-width:260px;margin-top:10px"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="orden_usuarios" value="<?=h($ordenUsuarios)?>"><input type="hidden" name="tipo_usuario" value="<?=h($tipoUsuarios)?>"><input type="hidden" name="action" value="admin_usuario_editar"><input type="hidden" name="id_usuario" value="<?=$u['id']?>">
<label>Nombre<input name="nombre" value="<?=h($u['nombre'])?>" required maxlength="100" data-solo-letras pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+"></label>
<label>Apellido paterno<input name="apellido_paterno" value="<?=h($u['apellido_paterno'])?>" required maxlength="100" data-solo-letras pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+"></label>
<label>Apellido materno<input name="apellido_materno" value="<?=h($u['apellido_materno'])?>" maxlength="100" data-solo-letras pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+"></label>
<label>Correo<input type="email" name="correo" data-correo-permitido value="<?=h($u['correo'])?>" required maxlength="150"></label>
<label>Celular<input name="telefono" value="<?=h($u['telefono'])?>" required maxlength="10" pattern="[0-9]{10}"></label>
<div><button class="btn tiny primary">Guardar datos</button></div></form></details></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php if($page==='superadmin-pagos'): ?>
<section class="panel" id="pagos-listado"><h2>Verificación manual de pagos</h2><p class="muted">No se consulta ningún banco. Verifica el comprobante por tus propios medios antes de marcar un pago como verificado.</p>
<form method="get" action="index.php#pagos-listado" class="vendia-filter-bar" aria-label="Filtros de pagos"><input type="hidden" name="p" value="superadmin-pagos">
 <div><label for="filtroOrdenPagos">Ordenar pagos</label><select id="filtroOrdenPagos" name="orden_pagos">
 <?php foreach(['pendientes'=>'Pendientes primero','importe_desc'=>'Importe: del más caro al más barato','importe_asc'=>'Importe: del más barato al más caro','fecha_desc'=>'Fecha de pago: más reciente','fecha_asc'=>'Fecha de pago: más antigua'] as $k=>$v):?><option value="<?=h($k)?>" <?=$ordenPagos===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></div>
 <div><label for="filtroEstadoPagos">Estado del pago</label><select id="filtroEstadoPagos" name="estado_pagos">
 <?php foreach(['todos'=>'Todos','pendiente'=>'Pendientes','verificado'=>'Verificados','rechazado'=>'Rechazados'] as $k=>$v):?><option value="<?=h($k)?>" <?=$estadoPagos===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></div>
 <button class="btn primary" type="submit">Aplicar filtros</button><a class="btn" href="index.php?p=superadmin-pagos#pagos-listado">Limpiar</a>
</form><p class="muted">Mostrando <?=count($pagos)?> pago(s), máximo 500 registros por consulta.</p><div class="table-scroll"><table class="admin-table"><thead><tr><th>Negocio</th><th>Importe</th><th>Referencia / fecha</th><th>Estado</th><th>Revisar</th></tr></thead><tbody>
<?php foreach($pagos as $p): ?><tr><td><?=h($p['negocio'])?><br><small><?=h($p['reportado_por'])?></small></td><td>$<?=number_format((float)$p['importe'],2)?></td><td><?=h($p['referencia'])?><br><?=h($p['fecha_pago'])?></td><td><span class="pill-estado estado-<?=$p['estado']==='verificado'?'verde':($p['estado']==='rechazado'?'rojo':'amarillo')?>"><?=h(ucfirst($p['estado']))?></span></td><td><?php if($p['estado']==='pendiente'): ?><form method="post" data-vendia-stay class="vendia-mini-form" onsubmit="return confirm('¿Verificaste el pago contra la cuenta bancaria?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="orden_pagos" value="<?=h($ordenPagos)?>"><input type="hidden" name="estado_pagos" value="<?=h($estadoPagos)?>"><input type="hidden" name="action" value="admin_pago_verificar"><input type="hidden" name="id_pago" value="<?=$p['id']?>"><button name="estado" value="verificado" class="btn tiny primary">Verificar</button><button name="estado" value="rechazado" class="btn tiny">Rechazar</button></form><?php else: ?><small>Revisado</small><?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$pagos):?><tr><td colspan="5">No hay pagos reportados.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php if($page==='superadmin'): ?>
<section class="panel"><h2>Actividad de altas, bajas y cambios</h2><div class="table-scroll"><table><thead><tr><th>Fecha</th><th>Negocio</th><th>Evento</th><th>Descripción</th></tr></thead><tbody>
<?php foreach($eventos as $e): ?><tr><td><?=h($e['fecha_creacion'])?></td><td><?=h($e['negocio'])?></td><td><?=h(ucfirst($e['accion']))?></td><td><?=h($e['descripcion'])?></td></tr><?php endforeach; ?>
<?php if(!$eventos):?><tr><td colspan="4">No hay actividad administrativa registrada.</td></tr><?php endif; ?></tbody></table></div></section>

<?php endif; ?>
