<?php
if(!logged()) {http_response_code(401);exit('Inicia sesión.');}
$esAdmin=me()['role']==='superadministrador';
$esDueno=me()['role']==='dueño';
if($esAdmin) {
 $q=$pdo->query("SELECT a.*,n.nombre AS negocio,u.usuario AS remitente FROM solicitudes_ayuda a JOIN negocios n ON n.id=a.id_negocio JOIN usuarios u ON u.id=a.id_usuario ORDER BY (a.estado='abierta') DESC,a.id DESC LIMIT 100");
} elseif($esDueno) {
 $q=$pdo->prepare("SELECT a.*,n.nombre AS negocio,u.usuario AS remitente FROM solicitudes_ayuda a JOIN negocios n ON n.id=a.id_negocio JOIN usuarios u ON u.id=a.id_usuario WHERE a.id_negocio=? ORDER BY (a.estado='abierta') DESC,a.id DESC LIMIT 100");
 $q->execute([(int)me()['store_id']]);
} else {
 $q=$pdo->prepare('SELECT a.*,n.nombre AS negocio,u.usuario AS remitente FROM solicitudes_ayuda a JOIN negocios n ON n.id=a.id_negocio JOIN usuarios u ON u.id=a.id_usuario WHERE a.id_usuario=? ORDER BY a.id DESC LIMIT 100');
 $q->execute([(int)me()['id']]);
}
$mensajes=$q->fetchAll();
?>
<div class="page-heading"><div><p class="eyebrow">CENTRO DE AYUDA</p><h1>Mensajes de ayuda</h1><p class="muted"><?= $esAdmin?'Responde solicitudes de negocios y da seguimiento a cada caso.':($esDueno?'Responde a las consultas de tus empleados y clientes, o escribe a soporte.':'Envíanos una solicitud de soporte y consulta tus respuestas.')?></p></div></div>
<?php if(!$esAdmin): ?>
<section class="panel"><h2>Solicitar ayuda</h2><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="enviar_ayuda"><?php if(me()['role']==='cliente'): ?><div class="span-2"><label>Negocio al que escribes</label><select name="id_negocio" required><option value="">Selecciona un negocio</option><?php $tiendas=$pdo->query("SELECT id,nombre FROM negocios WHERE activo=1 ORDER BY nombre");foreach($tiendas as $t):?><option value="<?=(int)$t['id']?>"><?=h($t['nombre'])?></option><?php endforeach; ?></select></div><?php endif; ?><div class="span-2"><label>Asunto</label><input name="asunto" required minlength="5" maxlength="140" placeholder="Ej. No puedo agregar productos"></div><div class="span-2"><label>Describe el problema</label><textarea name="mensaje" required minlength="10" maxlength="4000" rows="4" placeholder="Explica qué pasó y en qué pantalla"></textarea></div><div class="span-2"><button class="btn primary">Enviar solicitud</button></div></form></section>
<?php endif; ?>
<section class="panel"><h2><?= $esAdmin?'Solicitudes recibidas':($esDueno?'Mensajes de tu negocio':'Mis solicitudes')?></h2>
<?php if(!$mensajes):?><p class="muted">Todavía no hay mensajes.</p><?php endif;?>
<?php foreach($mensajes as $m): ?>
<div style="padding:16px 0;border-bottom:1px solid #d8e2e9">
 <div class="vendia-actions"><strong>#<?=$m['id']?> <?=h($m['asunto'])?></strong><span class="pill-estado estado-<?=$m['estado']==='abierta'?'amarillo':'verde'?>"><?=h(ucfirst($m['estado']))?></span><small><?=h($m['fecha_creacion'])?></small></div>
 <?php if($esAdmin || $esDueno):?><small><?=h($m['negocio'].' · '.$m['remitente'])?></small><?php endif;?>
 <p><?=nl2br(h($m['mensaje']))?></p>
 <?php if($m['respuesta']):?><div class="notice"><strong>Respuesta de soporte:</strong><p><?=nl2br(h($m['respuesta']))?></p></div><?php endif;?>
 <?php if($esAdmin || ($esDueno && (int)$m['id_usuario']!==(int)me()['id'])):?><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="<?=$esAdmin?'admin_responder_ayuda':'dueno_responder_ayuda'?>"><input type="hidden" name="id_solicitud" value="<?=$m['id']?>"><div class="span-2"><label>Respuesta</label><textarea name="respuesta" required minlength="3" maxlength="4000" rows="2"><?=h($m['respuesta']??'')?></textarea></div><div><label>Estado</label><select name="estado"><option value="atendida">Atendida</option><option value="cerrada">Cerrada</option></select></div><div><button class="btn primary">Guardar respuesta</button></div></form><?php endif; ?>
</div>
<?php endforeach; ?></section>
