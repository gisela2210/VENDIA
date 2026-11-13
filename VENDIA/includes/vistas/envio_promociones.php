<?php
/** Envío asistido a compradores del negocio autenticado. No envía mensajes en segundo plano. */
ownerOnly();
$negocioEnvio=(int)me()['store_id'];
$consultaNegocio=$pdo->prepare('SELECT nombre,activo FROM negocios WHERE id=? AND id_dueno=? LIMIT 1');
$consultaNegocio->execute([$negocioEnvio,(int)me()['id']]);
$negocioPublicidad=$consultaNegocio->fetch();
if (!$negocioPublicidad) { http_response_code(403);exit('No tienes acceso a este negocio.'); }

// Lista de CLIENTES que enviaron pedidos a este negocio, incluyendo los que siguen pendientes.
// Las compras pagadas se cuentan aparte. El consentimiento siempre es específico por negocio.
$consultaCompradores=$pdo->prepare("SELECT u.id,u.nombre,u.apellido_paterno,u.apellido_materno,u.telefono,
  COUNT(DISTINCT p.id) AS pedidos_realizados, COUNT(DISTINCT v.id) AS compras,
  MAX(p.fecha_creacion) AS ultima_compra, COALESCE(c.autorizado,0) AS autorizado
  FROM usuarios u
  INNER JOIN pedidos p ON p.id_cliente=u.id AND p.id_negocio=?
  LEFT JOIN ventas v ON v.id_pedido=p.id AND v.id_negocio=? AND v.estado='completada'
  LEFT JOIN consentimientos_whatsapp c ON c.id_cliente=u.id AND c.id_negocio=?
  WHERE u.rol='cliente' AND u.activo=1
  GROUP BY u.id,u.nombre,u.apellido_paterno,u.apellido_materno,u.telefono,c.autorizado
  ORDER BY ultima_compra DESC,u.nombre ASC");
$consultaCompradores->execute([$negocioEnvio,$negocioEnvio,$negocioEnvio]);
$compradoresPublicidad=$consultaCompradores->fetchAll();

// Oferta activa significa autorizada + habilitada + dentro de fechas + producto vigente.
$consultaOfertas=$pdo->prepare("SELECT p.id,p.nombre,p.tipo_beneficio,p.porcentaje_descuento,p.unidades_llevas,p.unidades_pagas,p.fecha_fin,
  (SELECT GROUP_CONCAT(DISTINCT x.nombre ORDER BY x.nombre SEPARATOR ', ')
   FROM promocion_productos pp JOIN productos x ON x.id=pp.id_producto
   WHERE pp.id_promocion=p.id AND x.id_negocio=p.id_negocio AND x.activo=1) AS productos
  FROM promociones p
  WHERE p.id_negocio=? AND p.activo=1 AND p.autorizada=1
    AND p.fecha_inicio<=CURDATE() AND p.fecha_fin>=CURDATE()
    AND EXISTS(SELECT 1 FROM promocion_productos pp JOIN productos x ON x.id=pp.id_producto
      WHERE pp.id_promocion=p.id AND x.id_negocio=p.id_negocio AND x.activo=1)
  ORDER BY p.fecha_fin ASC,p.nombre ASC");
$consultaOfertas->execute([$negocioEnvio]);
$ofertasPublicidad=$consultaOfertas->fetchAll();
$personasAutorizadas=0;
foreach($compradoresPublicidad as &$compradorPublicidad) {
    $soloDigitos=preg_replace('/\D+/','',(string)$compradorPublicidad['telefono']);
    if(strlen($soloDigitos)===10) $soloDigitos='52'.$soloDigitos;
    $telefonoValido=preg_match('/^52\d{10}$/D',$soloDigitos);
    $compradorPublicidad['whatsapp']=(bool)$telefonoValido ? $soloDigitos : '';
    $compradorPublicidad['seleccionable']=((int)$compradorPublicidad['autorizado']===1 && (bool)$telefonoValido);
    if($compradorPublicidad['seleccionable'])$personasAutorizadas++;
}
unset($compradorPublicidad);
?>
<div class="page-heading"><div><p class="eyebrow">PROMOCIONES · DIFUSIÓN</p><h1>Envío de promociones</h1><p class="muted">Selecciona compradores de tu negocio que autorizaron recibir publicidad y prepara un mensaje de WhatsApp con una oferta vigente.</p></div></div>
<div class="marketing-summary-grid">
  <div class="panel"><small>Clientes que han realizado pedidos</small><strong><?=count($compradoresPublicidad)?></strong></div>
  <div class="panel"><small>Con autorización y teléfono válido</small><strong><?=$personasAutorizadas?></strong></div>
  <div class="panel"><small>Ofertas activas</small><strong><?=count($ofertasPublicidad)?></strong></div>
</div>
<section class="panel marketing-controls">
  <h2>1. Selecciona la promoción o descuento</h2>
  <label for="marketingOffer">Promoción o descuento autorizado y vigente</label>
  <select id="marketingOffer" data-negocio="<?=h($negocioPublicidad['nombre'])?>" <?=!$ofertasPublicidad?'disabled':''?>>
    <option value="" <?=$ofertasPublicidad?'':'selected'?>>Selecciona una oferta vigente</option>
    <?php foreach($ofertasPublicidad as $indiceOferta=>$ofertaPublicidad): ?>
      <option <?=$indiceOferta===0?'selected':''?> value="<?=(int)$ofertaPublicidad['id']?>"
        data-titulo="<?=h($ofertaPublicidad['nombre'])?>"
        data-beneficio="<?=h(etiquetaOferta($ofertaPublicidad))?>"
        data-caducidad="<?=h(date('d/m/Y',strtotime($ofertaPublicidad['fecha_fin'])))?>" data-vigencia-fin="<?=h($ofertaPublicidad['fecha_fin'])?>"
        data-productos="<?=h($ofertaPublicidad['productos']??'')?>">
        <?=h($ofertaPublicidad['nombre'].' — '.etiquetaOferta($ofertaPublicidad).' — hasta '.date('d/m/Y',strtotime($ofertaPublicidad['fecha_fin'])))?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php if(!$ofertasPublicidad): ?><p class="hint">No hay promociones vigentes, activas y autorizadas con productos disponibles. Actívalas primero en <a href="index.php?p=promotions">Promociones</a>.</p><?php endif; ?>
  <div class="marketing-message-preview" id="marketingPreview" aria-live="polite">Selecciona una oferta para mostrar la vista previa del mensaje.</div>
</section>
<section class="panel marketing-customers">
  <div class="marketing-toolbar"><div><h2>2. Selecciona a los compradores</h2><p class="muted">Cada persona aparece una sola vez. Selecciona a los clientes autorizados para enviarles una oferta. Puedes abrir cada conversación de forma individual.</p></div><span id="marketingSelectedCount" class="marketing-count">0 seleccionados</span></div>
  <div class="marketing-filters">
    <label for="marketingSearch">Buscar cliente <input id="marketingSearch" type="search" placeholder="Buscar por nombre" autocomplete="off"></label>
    <label class="marketing-select-all"><input id="marketingSelectAll" type="checkbox"> Seleccionar todos los autorizados</label>
  </div>
  <div class="table-scroll"><table class="marketing-table" id="marketingTable">
    <thead><tr><th>Elegir</th><th>Cliente</th><th>Pedidos</th><th>Compras cobradas</th><th>Último pedido</th><th>Autorización</th><th>Enviar mensaje</th></tr></thead>
    <tbody>
    <?php foreach($compradoresPublicidad as $compradorPublicidad):
      $nombreComprador=trim($compradorPublicidad['nombre'].' '.$compradorPublicidad['apellido_paterno'].' '.$compradorPublicidad['apellido_materno']);
      $puedeEnviar=$compradorPublicidad['seleccionable']; ?>
      <tr class="marketing-client-row" data-search="<?=h($nombreComprador)?>">
        <td><input type="checkbox" class="marketing-recipient" value="<?=(int)$compradorPublicidad['id']?>" data-nombre="<?=h($nombreComprador)?>" data-telefono="<?=h($puedeEnviar?$compradorPublicidad['whatsapp']:'')?>" aria-label="Seleccionar <?=h($nombreComprador)?>" <?=$puedeEnviar?'':'disabled'?>></td>
        <td><strong><?=h($nombreComprador)?></strong></td>
        <td><span class="marketing-purchases"><?=(int)$compradorPublicidad['pedidos_realizados']?></span></td><td><?=(int)$compradorPublicidad['compras']?></td>
        <td><?=h(date('d/m/Y',strtotime($compradorPublicidad['ultima_compra'])))?></td>
        <td><?php if($puedeEnviar): ?><span class="marketing-optin-ok">Autorizado</span><?php elseif((int)$compradorPublicidad['autorizado']!==1): ?><span class="marketing-optin-no">Sin consentimiento</span><?php else: ?><span class="marketing-optin-no">Teléfono inválido</span><?php endif; ?></td>
        <td><?php if($puedeEnviar): ?><a class="btn small marketing-direct" href="#" target="_blank" rel="noopener noreferrer" data-nombre="<?=h($nombreComprador)?>" data-telefono="<?=h($compradorPublicidad['whatsapp'])?>" aria-disabled="true">Abrir WhatsApp</a><?php else: ?><span class="muted">No autorizado</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$compradoresPublicidad): ?><tr><td colspan="7">Todavía no hay pedidos registrados por clientes en este negocio.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <div class="marketing-actions"><a id="marketingPrepare" class="btn primary marketing-send-link is-disabled" href="#" target="_blank" rel="noopener noreferrer" aria-disabled="true">Abrir WhatsApp para enviar</a><button type="button" id="marketingCopy" class="btn" disabled>Copiar texto de la oferta</button></div>
  <p class="marketing-status" id="marketingStatus" role="status" aria-live="polite">Selecciona al menos un cliente autorizado. La primera oferta vigente ya está seleccionada.</p>
  <p class="hint">WhatsApp permite abrir chats individuales. Después de preparar cada mensaje, pulsa «Abrir WhatsApp» o «WhatsApp Web» para entrar a la conversación y confirma el envío. Se necesita WhatsApp instalado o una sesión iniciada en WhatsApp Web. Este módulo <strong>no envía mensajes masivos automáticamente</strong>. Para campañas automáticas necesitarías WhatsApp Business Platform, plantillas aprobadas y consentimiento específico.</p>
</section>
<section class="panel" id="marketingQueuePanel" hidden>
  <h2>3. Continuar enviando mensajes</h2>
  <p class="muted">Se abre el chat del primer cliente. Para los demás, usa sus botones de abajo. Revisa y pulsa Enviar dentro de WhatsApp; abrir un chat no significa que el mensaje se haya entregado.</p>
  <div id="marketingQueue" class="marketing-queue" aria-live="polite"></div>
</section>
