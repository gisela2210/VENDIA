<?php
if(!logged() || me()['role']!=='cliente'){http_response_code(403);exit('Acceso solo para clientes.');}
$negocios=$pdo->query("SELECT id,nombre,categoria,horario_comercial,imagen_local FROM negocios WHERE activo=1 AND estado_suscripcion<>'suspendida' ORDER BY nombre")->fetchAll();
$elegido=(int)($_GET['negocio']??($_POST['id_negocio']??0));
if(!$elegido && $negocios)$elegido=(int)$negocios[0]['id'];
$validos=array_map('intval',array_column($negocios,'id'));
if(!in_array($elegido,$validos,true))$elegido=0;
$productos=[];$negocioActual=null;
foreach($negocios as $n)if((int)$n['id']===$elegido)$negocioActual=$n;
if($elegido){$q=$pdo->prepare('SELECT id,nombre,categoria,descripcion,precio,existencias,foto FROM productos WHERE id_negocio=? AND activo=1 AND existencias>0 ORDER BY nombre LIMIT 120');$q->execute([$elegido]);$productos=$q->fetchAll();}
$ofertasDescuento=[];$ofertasPromocion=[];
if($elegido) {
  $q=$pdo->prepare("SELECT pr.id,pr.nombre,pr.tipo_beneficio,pr.porcentaje_descuento,pr.unidades_llevas,pr.unidades_pagas,pr.fecha_fin,
   p.id AS id_producto,p.nombre AS producto,p.precio,p.foto
   FROM promociones pr JOIN promocion_productos pp ON pp.id_promocion=pr.id
   JOIN productos p ON p.id=pp.id_producto AND p.id_negocio=pr.id_negocio
   WHERE pr.id_negocio=? AND pr.activo=1 AND pr.autorizada=1
   AND pr.fecha_inicio<=CURDATE() AND pr.fecha_fin>=CURDATE()
   AND p.activo=1 AND p.existencias>0 ORDER BY pr.fecha_fin ASC,pr.nombre,p.nombre LIMIT 120");
  $q->execute([$elegido]);
  foreach($q as $fila) {
    if($fila['tipo_beneficio']==='promocion')$ofertasPromocion[]=$fila;
    else $ofertasDescuento[]=$fila;
  }
}
// Datos mínimos de ofertas para vista previa. PHP recalcula todo al guardar.
$ofertasParaCotizar=[];
if($elegido){
    $cq=$pdo->prepare("SELECT o.id,o.nombre,o.tipo_beneficio,o.porcentaje_descuento,o.unidades_llevas,o.unidades_pagas,pp.id_producto
       FROM promociones o JOIN promocion_productos pp ON pp.id_promocion=o.id
       JOIN productos p ON p.id=pp.id_producto AND p.id_negocio=o.id_negocio AND p.activo=1
       WHERE o.id_negocio=? AND o.activo=1 AND o.autorizada=1
         AND o.fecha_inicio<=CURDATE() AND o.fecha_fin>=CURDATE()");
    $cq->execute([$elegido]);
    foreach($cq as $r){
        $id=(int)$r['id'];
        if(!isset($ofertasParaCotizar[$id]))$ofertasParaCotizar[$id]=[
           'id'=>$id,'name'=>$r['nombre'],'tipo'=>$r['tipo_beneficio'],
           'discount'=>(float)$r['porcentaje_descuento'],'llevas'=>(int)$r['unidades_llevas'],
           'pagas'=>(int)$r['unidades_pagas'],'products'=>[]];
        $ofertasParaCotizar[$id]['products'][]=(int)$r['id_producto'];
    }
}
?>
<div class="page-heading"><div><p class="eyebrow">COMPRAR EN VENDIA</p><h1>Realizar un pedido</h1><p class="muted">Elige un negocio, indica cuántos productos necesitas y envía la solicitud. No se cobra en línea.</p></div></div>
<section class="panel">
 <label for="clienteTienda">¿En qué negocio quieres comprar?</label>
 <select id="clienteTienda" class="client-store-picker" aria-label="Seleccionar negocio">
 <?php foreach($negocios as $n):?><option value="<?=(int)$n['id']?>" <?=$elegido===(int)$n['id']?'selected':''?>><?=h($n['nombre'].' — '.$n['categoria'])?></option><?php endforeach;?>
 </select>
 <?php if($negocioActual):?><p class="muted">Estás comprando en <strong><?=h($negocioActual['nombre'])?></strong> · <?=h($negocioActual['horario_comercial']?:'Horario por confirmar')?></p><?php else:?><p>No hay negocios activos disponibles por el momento.</p><?php endif;?>
</section>
<?php if($negocioActual):?>
<section class="client-offers-area" aria-label="Promociones y descuentos vigentes">
 <section class="panel client-offers-section" id="ofertas-descuentos">
  <div class="client-offers-title"><div><p class="eyebrow">AHORRA AL COMPRAR</p><h2>Productos con descuento</h2></div><span class="offer-count"><?=count($ofertasDescuento)?> artículo(s)</span></div>
  <?php if(!$ofertasDescuento):?><p class="muted">Hoy no hay productos con descuento porcentual en este negocio.</p>
  <?php else:?><div class="client-offer-cards"><?php foreach($ofertasDescuento as $o): $precio=(float)$o['precio'];$rebaja=$precio*(1-(float)$o['porcentaje_descuento']/100); ?>
   <article class="client-offer-card"><span class="offer-sticker"><?=h(number_format((float)$o['porcentaje_descuento'],0))?>% MENOS</span><strong><?=h($o['producto'])?></strong><span class="muted"><?=h($o['nombre'])?></span>
   <div class="offer-price"><del>$<?=number_format($precio,2)?></del><b>$<?=number_format($rebaja,2)?></b></div><small>Válido hasta <?=h(date('d/m/Y',strtotime($o['fecha_fin'])))?></small><a href="#producto-<?= (int)$o['id_producto']?>">Elegir cantidad ↓</a></article>
  <?php endforeach;?></div><?php endif;?>
 </section>
 <section class="panel client-offers-section client-combo-section" id="ofertas-combo">
  <div class="client-offers-title"><div><p class="eyebrow">OFERTAS POR CANTIDAD</p><h2>Productos con promoción</h2></div><span class="offer-count"><?=count($ofertasPromocion)?> artículo(s)</span></div>
  <?php if(!$ofertasPromocion):?><p class="muted">Hoy no hay promociones de productos por cantidad (2x1, 3x2, etc.).</p>
  <?php else:?><div class="client-offer-cards"><?php foreach($ofertasPromocion as $o): $n=(int)$o['unidades_llevas'];$m=(int)$o['unidades_pagas'];if($n<2||$m<1||$m>=$n)continue; ?>
   <article class="client-offer-card"><span class="offer-sticker"><?=$n?>x<?=$m?></span><strong><?=h($o['producto'])?></strong><span class="muted"><?=h($o['nombre'])?></span>
   <div class="offer-price"><del>$<?=number_format($n*(float)$o['precio'],2)?></del><b>$<?=number_format($m*(float)$o['precio'],2)?></b></div><small>Por <?=$n?> unidades iguales · Válido hasta <?=h(date('d/m/Y',strtotime($o['fecha_fin'])))?></small><a href="#producto-<?= (int)$o['id_producto']?>">Elegir cantidad ↓</a></article>
  <?php endforeach;?></div><?php endif;?>
  <p class="client-combo-disclaimer">Las promociones por cantidad pueden combinar productos participantes. Vendia cotiza automáticamente la oferta de mayor ahorro al enviar el pedido; no se acumulan ofertas.</p>
 </section>
</section>
<section class="panel"><h2>Productos disponibles</h2><p class="muted">La estimación incluye automáticamente la mejor promoción o descuento vigente que corresponda a los productos y cantidades seleccionados. Al enviar el pedido guardaremos el total cotizado para respetarlo al cobrar; las existencias se revisan nuevamente. No se ha realizado ningún cargo.</p>
<form method="post" action="index.php?p=cliente-pedidos&amp;negocio=<?=$elegido?>" id="clientOrderForm" data-ofertas="<?=h(json_encode(array_values($ofertasParaCotizar),JSON_UNESCAPED_UNICODE))?>">
 <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="cliente_pedido"><input type="hidden" name="id_negocio" value="<?=$elegido?>">
 <div class="client-products-grid">
 <?php foreach($productos as $p): ?>
  <article class="client-product-card" id="producto-<?=(int)$p['id']?>">
   <?php if($p['foto'] && preg_match('#^uploads/[a-zA-Z0-9_.-]+$#',(string)$p['foto'])): ?><img src="<?=h($p['foto'])?>" alt="Foto de <?=h($p['nombre'])?>" loading="lazy">
   <?php else: ?><div class="client-product-placeholder" aria-hidden="true"><?=icono('tienda')?></div><?php endif;?>
   <div><b><?=h($p['nombre'])?></b><small><?=h($p['categoria'])?> · <?=h($p['descripcion']?:'Disponible para solicitar')?></small><strong>$<?=number_format((float)$p['precio'],2)?></strong></div>
   <label>Cantidad <input type="number" class="client-qty" name="cantidades[<?=(int)$p['id']?>]" min="0" max="<?=min(30,(int)$p['existencias'])?>" value="<?=h((string)(($_POST['action']??'')==='cliente_pedido'?(int)(($_POST['cantidades']??[])[$p['id']]??0):0))?>" data-price="<?=h($p['precio'])?>" aria-label="Cantidad de <?=h($p['nombre'])?>"></label>
  </article>
 <?php endforeach; ?>
 </div>
 <?php if(!$productos):?><div class="notice">Este negocio no tiene productos disponibles para pedir.</div><?php endif;?>
 <div class="client-checkout-grid">
   <div><label>Fecha de entrega solicitada (opcional)</label><input type="datetime-local" name="fecha_entrega_prevista" min="<?=date('Y-m-d\TH:i')?>" value="<?=h((string)(($_POST['action']??'')==='cliente_pedido'?($_POST['fecha_entrega_prevista']??''):''))?>"><small>El negocio podrá confirmar o reprogramar la fecha.</small></div>
   <div class="span-2"><label>Comentarios o instrucciones</label><textarea name="notas" rows="3" maxlength="1500" placeholder="Tamaño, presentación, indicaciones u observaciones"><?=h(($_POST['action']??'')==='cliente_pedido'?($_POST['notas']??''):'')?></textarea></div>
 </div>
 <label class="whatsapp-consent span-2"><input type="checkbox" name="aceptar_whatsapp_promos" value="1" <?=!empty($_POST['aceptar_whatsapp_promos'])?'checked':''?>> <span>Autorizo a <strong><?=h($negocioActual['nombre'])?></strong> a enviarme ofertas por WhatsApp. Es opcional y puedo retirarlo después en Mi configuración.</span></label>
 <div class="client-checkout-footer"><div><small>Estimación del pedido (oferta aplicada automáticamente si corresponde)</small><strong id="clientOrderTotal">$0.00</strong><small id="clientOrderOriginal" hidden>Precio normal: $0.00</small><small id="clientOrderOffer" aria-live="polite">Selecciona productos</small><small id="clientOrderCount">Selecciona productos</small></div><button class="btn primary" type="submit" <?=!$productos?'disabled':''?>>Enviar pedido al negocio</button></div>
</form>
</section>
<?php endif; ?>
