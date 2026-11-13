<?php
if(!logged()){http_response_code(401);exit('Inicia sesión.');}
$q=$pdo->prepare('SELECT * FROM notificaciones WHERE id_usuario=? ORDER BY fecha_creacion DESC,id DESC LIMIT 150');$q->execute([(int)me()['id']]);$avisos=$q->fetchAll();
?>
<div class="page-heading"><div><p class="eyebrow">CENTRO DE AVISOS</p><h1>Notificaciones</h1><p class="muted">Avisos privados de pedidos, ayuda, pagos y entregas. Solo aparecen los dirigidos a tu cuenta.</p></div></div>
<section class="panel">
<div class="notifications-head"><h2>Tus avisos</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="notificaciones_todas"><button class="btn tiny" type="submit">Marcar todas como leídas</button></form></div>
<?php if(!$avisos):?><p class="muted">No tienes notificaciones por ahora.</p><?php endif;?>
<div class="notifications-list">
<?php foreach($avisos as $n): ?>
<article class="notification-item <?=$n['leida']?'':'unread'?>">
<div class="notice-indicator" aria-hidden="true"><?= $n['leida']?'✓':'●' ?></div>
<div><div class="notification-title"><strong><?=h($n['titulo'])?></strong><small><?=h($n['fecha_creacion'])?></small></div><p><?=h($n['mensaje'])?></p><a href="<?=h($n['enlace'])?>">Ver detalles</a></div>
<?php if(!$n['leida']):?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="notificacion_leida"><input type="hidden" name="id_notificacion" value="<?=(int)$n['id']?>"><button class="btn tiny" aria-label="Marcar como leída">Leída ✓</button></form><?php endif;?>
</article>
<?php endforeach;?>
</div>
</section>
