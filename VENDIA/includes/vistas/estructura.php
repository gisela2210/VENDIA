<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<script>
/* Una identidad de sesión por pestaña: protege pedidos, ventas y promociones al
   usar simultáneamente los cuatro roles en un mismo navegador. */
(function () {
  try {
    const key = 'vendia_tab_context_v1';
    let id = sessionStorage.getItem(key);
    if (!/^[a-f0-9]{32}$/.test(id || '')) {
      const bytes = new Uint8Array(16); crypto.getRandomValues(bytes);
      id = Array.from(bytes, v => v.toString(16).padStart(2,'0')).join('');
      sessionStorage.setItem(key,id);
    }
    window.VENDIA_TAB_CONTEXT = id;
    // Si se duplica una pestaña, ciertos navegadores copian sessionStorage.
    // BroadcastChannel detecta el duplicado y le asigna un contexto nuevo.
    if ('BroadcastChannel' in window) {
      const channel = new BroadcastChannel('vendia_contextos_tabs_v1');
      const instanceBytes = new Uint8Array(8); crypto.getRandomValues(instanceBytes);
      const instance = Array.from(instanceBytes, v => v.toString(16).padStart(2,'0')).join('');
      channel.onmessage = ({data}) => {
        if (!data || data.id !== id || data.instance === instance) return;
        if (data.kind === 'hello') channel.postMessage({kind:'ocupado',id,target:data.instance,instance});
        if (data.kind === 'ocupado' && data.target === instance) {
          const fresh = new Uint8Array(16); crypto.getRandomValues(fresh);
          const newId = Array.from(fresh,v => v.toString(16).padStart(2,'0')).join('');
          sessionStorage.setItem(key,newId);
          channel.close();
          const target = new URL(location.href); target.searchParams.set('vendia_ctx',newId);
          location.replace(target.href);
        }
      };
      channel.postMessage({kind:'hello',id,instance});
    }
    const url = new URL(location.href);
    if (url.searchParams.get('vendia_ctx') !== id) {
      url.searchParams.set('vendia_ctx',id);
      location.replace(url.href);
    }
  } catch (e) { console.error('Vendia necesita sessionStorage para aislar cada pestaña.'); }
})();
</script>
<title>Vendia | Gestión de negocios</title>
<link rel="icon" type="image/png" href="assets/img/logo_vendia.png">
<link rel="stylesheet" href="assets/css/estilos.css?v=21.0">
</head>
<body>
<?php if(logged()): ?>
<div class="app-shell">
<aside class="sidebar" id="sidebar" aria-label="Menú principal">
  <div class="brand"><img class="brand-logo" src="assets/img/logo_vendia.png" alt="Logotipo Vendia"><span>VENDIA<small>Tu negocio, más fácil</small></span></div>
  <div class="user-mini"><div class="avatar business-avatar"><?php if($businessHasLogo): ?><img src="<?=h($businessLogo)?>" alt="Imagen del negocio"><?php else: ?><?=icono('tienda')?><?php endif; ?></div><div><b><?=h($businessBadge['name']??me()['store_name']??'Mi negocio')?></b><small><?=h($businessBadge['category']??'Negocio')?> · <?=me()['role']==='dueño'?'Dueño':(me()['role']==='empleado'?'Empleado':(me()['role']==='cliente'?'Cliente':'Superadministrador'))?></small></div></div>
  <?php
  // Menú único con iconografía SVG local y accesos autorizados por perfil.
  $menuRol = me()['role'];
  $navSections = [];
  if ($menuRol === 'cliente') {
      $navSections = [['MI CUENTA', [
          ['Inicio', 'cliente-inicio', 'inicio'],
          ['Hacer pedido', 'cliente-pedidos', 'pedidos'],
          ['Mi agenda', 'agenda', 'calendario'],
      ]]];
  } elseif ($menuRol === 'superadministrador') {
      $navSections = [['PLATAFORMA VENDIA', [
          ['Negocios y estadísticas', 'superadmin', 'tienda'],
          ['Usuarios: dueños, empleados y clientes', 'superadmin-usuarios', 'usuarios'],
          ['Verificación manual de pagos', 'superadmin-pagos', 'tarjeta'],
          ['Notificaciones', 'notificaciones', 'campana'],
          ['Mi agenda', 'agenda', 'calendario'],
          ['Mensajes de ayuda', 'ayuda', 'chat'],
      ]]];
  } else {
      $navSections = [['PRINCIPAL', [
          ['Inicio', 'home', 'inicio'],
          [$menuRol === 'dueño' ? 'Productos e inventario' : 'Consultar productos', 'products', 'productos'],
          ['Mi agenda', 'agenda', 'calendario'],
          ['Pedidos', 'orders', 'pedidos'],
      ]]];
      if ($menuRol === 'dueño') {
          $navSections[] = ['ADMINISTRACIÓN', [
              ['Empleados', 'employees', 'usuarios'],
              ['Promociones', 'promotions', 'porcentaje'],
              ['Envío de promociones', 'envio-promociones', 'chat'],
              ['Reportes y análisis', 'reports', 'reporte'],
              ['Auditoría', 'audit', 'auditoria'],
              ['Mi suscripción', 'suscripcion', 'tarjeta'],
          ]];
      }
  }
  ?>
  <nav aria-label="<?=h($menuRol === 'cliente' ? 'Área del cliente' : ($menuRol === 'superadministrador' ? 'Administración global' : 'Secciones de Vendia'))?>">
    <?php foreach ($navSections as [$sectionTitle, $items]): ?>
      <p class="nav-caption"><?=h($sectionTitle)?></p>
      <?php foreach ($items as [$label, $route, $glyph]): ?>
        <a class="<?=($page === $route ? 'active' : '')?>" href="index.php?p=<?=h($route)?>" <?=($page === $route ? 'aria-current="page"' : '')?>>
          <span class="nav-icon"><?=icono($glyph)?></span>
          <span class="nav-label"><?=h($label)?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <p class="sidebar-foot">VENDIA · Versión de prueba</p>
</aside>
<button class="sidebar-overlay" type="button" aria-label="Cerrar menú" data-close-menu></button>
<main class="main-area" id="main-content">
<header class="topbar">
  <div class="topbar-start"><button class="menu-toggle" id="menuToggle" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menú">☰</button>
  <div class="topbar-crumb"><span class="muted">Mi espacio / </span><?=h(['home'=>'Inicio','products'=>'Productos','orders'=>'Pedidos','employees'=>'Empleados','promotions'=>'Promociones','envio-promociones'=>'Envío de promociones','reports'=>'Reportes','audit'=>'Auditoría','settings'=>'Configuración','superadmin'=>'Superadministrador','superadmin-usuarios'=>'Usuarios','superadmin-pagos'=>'Pagos','cliente-pedidos'=>'Hacer pedido','notificaciones'=>'Notificaciones','agenda'=>'Mi agenda','ayuda'=>'Ayuda','suscripcion'=>'Suscripción','mi-cuenta'=>'Mi configuración'][$page]??'Panel')?></div></div>
  <div class="top-right"><a class="top-bell" href="index.php?p=notificaciones" aria-label="Notificaciones<?=contarNotificaciones($pdo,(int)me()['id'])?' sin leer':''?>"><span aria-hidden="true"><?=icono('campana')?></span> <?php $unread=contarNotificaciones($pdo,(int)me()['id']);if($unread):?><span class="top-bell-count"><?=$unread>99?'99+':$unread?></span><?php endif;?></a>
    <div class="account-menu" id="accountMenu">
      <button type="button" id="accountMenuToggle" class="account-menu-toggle" aria-expanded="false" aria-haspopup="true" aria-controls="accountMenuPanel" aria-label="Abrir opciones de mi cuenta">
        <span class="avatar small" aria-hidden="true"><?=h(strtoupper(substr(me()['first_name'],0,1)))?></span>
        <span class="top-user-name"><?=h(trim(($businessBadge['first_name']??me()['first_name']).' '.($businessBadge['last_name1']??'').' '.($businessBadge['last_name2']??'')))?></span>
        <span class="account-menu-chevron" aria-hidden="true">⌄</span>
      </button>
      <div class="account-menu-panel" id="accountMenuPanel" hidden>
        <div class="account-menu-summary"><strong><?=h(me()['username'])?></strong><small><?=h(ucfirst(me()['role']))?></small></div>
        <a href="index.php?p=<?=me()['role']==='dueño'?'settings':'mi-cuenta'?>"><?=icono('ajustes')?> Configuración de mi cuenta</a>
        <a href="index.php?p=ayuda"><?=icono('ayuda')?> Ayuda y soporte</a>
        <a href="index.php?logout=1" class="account-menu-exit"><?=icono('salir')?> Cerrar sesión</a>
      </div>
    </div>
  </div>
</header>
<?php else: ?>
<main class="auth-wrap">
<?php endif; ?>

<?php if($error && empty($fieldErrors)): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>
<?php if($success): ?><div class="alert success"><?=h($success)?></div><?php endif; ?>

<?php if(!logged()): ?>
<?php require __DIR__.'/acceso.php'; ?>

<?php else: ?>
<?php if($page==='cliente-inicio'): ?>
<?php
 // Cada importe sale de las líneas guardadas en el momento del pedido. El importe cobrado,
 // si existe, prevalece sobre la estimación y nunca se presenta como deuda.
 $q=$pdo->prepare("SELECT p.id,p.estado,p.fecha_creacion,p.fecha_entrega_prevista,p.lugar_compra,p.notas,p.total_cotizado,p.subtotal_cotizado,p.descuento_cotizado,
   n.nombre AS negocio, v.id AS id_venta, v.total AS total_cobrado, v.estado AS estado_venta,
   (SELECT SUM(dp.cantidad*dp.precio_unitario) FROM detalle_pedidos dp WHERE dp.id_pedido=p.id) AS total_estimado,
   (SELECT GROUP_CONCAT(CONCAT(dp.nombre_producto,' ×',dp.cantidad) ORDER BY dp.nombre_producto SEPARATOR ', ')
    FROM detalle_pedidos dp WHERE dp.id_pedido=p.id) AS articulos
   FROM pedidos p JOIN negocios n ON n.id=p.id_negocio
   LEFT JOIN ventas v ON v.id_pedido=p.id AND v.id_negocio=p.id_negocio AND v.estado='completada'
   WHERE p.id_cliente=? ORDER BY p.fecha_creacion DESC,p.id DESC LIMIT 80");
 $q->execute([(int)me()['id']]); $misPedidos=$q->fetchAll();
 $abiertos=count(array_filter($misPedidos,static fn($p)=>$p['estado']!=='entregado'));
?>
<div class="page-heading"><div><p class="eyebrow">ÁREA DEL CLIENTE</p><h1>¡Hola, <?=h(me()['first_name'])?>!</h1><p class="muted">Consulta qué pediste, cuándo lo solicitaste, su importe y el estado de entrega.</p></div></div>
<div class="orders-shortcuts"><a href="index.php?p=cliente-pedidos"><strong>Hacer un pedido</strong><small>Explorar negocios activos</small></a><a href="index.php?p=agenda"><strong>Ver mi agenda</strong><small>Entregas programadas y atrasadas</small></a><a href="index.php?p=ayuda"><strong>Contactar soporte</strong><small>Ayuda disponible desde tu perfil</small></a></div>
<section class="panel" id="mis-pedidos"><div class="panel-head"><div><h2>Mis pedidos y sus importes</h2><p class="muted"><?=count($misPedidos)?> pedido(s) reciente(s) · <?=$abiertos?> pendiente(s) de entrega</p></div></div>
<?php if(!$misPedidos): ?><div class="notice">Aún no has realizado pedidos. En «Hacer un pedido» podrás elegir productos de los negocios disponibles.</div>
<?php else: ?><div class="client-order-history">
<?php foreach($misPedidos as $pedido):
  $monto=(float)($pedido['id_venta'] ? $pedido['total_cobrado'] : ($pedido['total_cotizado']??$pedido['total_estimado']));
  $pagado=!!$pedido['id_venta']; $sinPrecio=!$pagado && $pedido['total_estimado']===null && $pedido['total_cotizado']===null;
  $estadoPedido=['pendiente'=>'Pendiente','preparacion'=>'En preparación','listo'=>'Listo','entregado'=>'Entregado'][$pedido['estado']]??ucfirst((string)$pedido['estado']);
?>
<article class="client-order-history-card">
 <div class="client-order-history-head"><div><small>Pedido #<?=(int)$pedido['id']?> · <?=h($pedido['negocio'])?></small><h3><?=h($pedido['articulos'] ?: ($pedido['notas'] ?: 'Pedido sin detalle de artículos'))?></h3></div><span class="pill-estado <?=($pedido['estado']==='entregado'?'estado-verde':($pedido['estado']==='pendiente'?'estado-amarillo':'estado-azul'))?>"><?=h($estadoPedido)?></span></div>
 <div class="client-order-history-data"><div><span>Fecha en que lo pediste</span><strong><?=h(date('d/m/Y · H:i',strtotime($pedido['fecha_creacion'])))?></strong></div><div><span>Entrega prevista</span><strong><?= $pedido['fecha_entrega_prevista'] ? h(date('d/m/Y · H:i',strtotime($pedido['fecha_entrega_prevista']))) : 'Sin programar' ?></strong></div><div><span><?=$pagado?'Importe pagado':'Importe estimado del pedido'?></span><strong class="client-order-money"><?=$sinPrecio?'No registrado':'$'.number_format($monto,2)?></strong></div></div>
 <p class="client-order-history-note"><?= $pagado ? 'Cobro registrado por el negocio.' : ($pedido['total_cotizado']!==null ? 'Cotización guardada al enviar el pedido. Ahorro aplicado: $'.number_format((float)$pedido['descuento_cotizado'],2).'. Se respetará este importe cuando el negocio cobre exactamente los artículos solicitados.' : 'Todavía no hay cobro registrado; los pedidos antiguos pueden no tener una cotización con oferta guardada.') ?></p>
</article>
<?php endforeach; ?></div><?php endif; ?>
</section>
<?php elseif($page==='cliente-pedidos'): require __DIR__ . '/cliente_pedidos.php'; ?>
<?php elseif(in_array($page,['superadmin','superadmin-usuarios','superadmin-pagos'],true)): adminOnly(); require dirname(__DIR__,2) . '/admin/panel.php'; ?>
<?php elseif($page==='mi-cuenta'): require __DIR__ . '/mi_cuenta.php'; ?>
<?php elseif($page==='notificaciones'): require __DIR__ . '/notificaciones.php'; ?>
<?php elseif($page==='agenda'): require __DIR__ . '/agenda.php'; ?>
<?php elseif($page==='ayuda'): require __DIR__ . '/ayuda.php'; ?>
<?php elseif($page==='suscripcion'): ownerOnly(); require dirname(__DIR__,2) . '/dueno/suscripcion.php'; ?>
<?php elseif($page==='home'):
$today=me()['role']==='dueño'
  ? countQuery($pdo,"SELECT COUNT(*) FROM sales WHERE store_id=? AND DATE(created_at)=CURDATE() AND status='completada'",[$storeId])
  : countQuery($pdo,"SELECT COUNT(*) FROM sales WHERE store_id=? AND user_id=? AND DATE(created_at)=CURDATE() AND status='completada'",[$storeId,me()['id']]);
$income=me()['role']==='dueño' ? countQuery($pdo,"SELECT COALESCE(SUM(total),0) FROM sales WHERE store_id=? AND DATE(created_at)=CURDATE() AND status='completada'",[$storeId]) : 0;
$orders=countQuery($pdo,"SELECT COUNT(*) FROM orders WHERE store_id=? AND status IN('pendiente','preparacion','listo')",[$storeId]);
$low=countQuery($pdo,'SELECT COUNT(*) FROM products WHERE store_id=? AND active=1 AND stock<=min_stock',[$storeId]);
$mySales=countQuery($pdo,"SELECT COUNT(*) FROM sales WHERE store_id=? AND user_id=? AND DATE(created_at)=CURDATE() AND status='completada'",[$storeId,me()['id']]);
?>
<div class="page-heading"><div><p class="eyebrow">RESUMEN GENERAL</p><h1>¡Hola, <?=h(me()['first_name'])?>! 👋</h1><p class="muted"><?=h(me()['store_name'])?> · Aquí tienes el resumen de tu negocio.</p></div><span class="date-chip"><?=date('d/m/Y')?></span></div>
<?php if(me()['role']==='empleado') require dirname(__DIR__,2).'/empleado/aviso.php'; ?>
<div class="stats-grid">
<div class="stat-card"><span><?=me()['role']==='dueño'?'Ventas de hoy':'Tus ventas de hoy'?></span><strong><?=$today?></strong><small>Operaciones completadas</small></div>
<div class="stat-card"><span><?=me()['role']==='dueño'?'Ingresos de hoy':'Mi área de trabajo'?></span><strong><?=me()['role']==='dueño'?'$'.number_format((float)$income,2):'Caja'?></strong><small><?=me()['role']==='dueño'?'Total del negocio':'Vende y atiende pedidos'?></small></div>
<div class="stat-card"><span>Pedidos activos</span><strong><?=$orders?></strong><small>Pendientes, preparación o listos</small></div>
<div class="stat-card"><span>Productos por reabastecer</span><strong><?=$low?></strong><small>En existencia mínima</small></div>
</div>
<div class="quick-actions">
  <a class="quick-action" href="index.php?p=orders#cobrar-pedido"><span class="quick-icon" aria-hidden="true">🛒</span><span><b>Cobrar una venta</b><small>Agregar productos y registrar pago</small></span></a>
  <a class="quick-action" href="index.php?p=products"><span class="quick-icon" aria-hidden="true">📦</span><span><b>Consultar productos</b><small>Precios, fotos y existencias</small></span></a>
  <?php if(me()['role']==='dueño'): ?>
  <a class="quick-action" href="index.php?p=orders#historial-pedidos"><span class="quick-icon" aria-hidden="true">📋</span><span><b>Revisar pedidos</b><small>Ver pendientes y actualizar estado</small></span></a>
  <?php else: ?>
  <a class="quick-action" href="index.php?p=orders#registrar-pedido"><span class="quick-icon" aria-hidden="true">📋</span><span><b>Registrar pedido</b><small>Capturar una nueva solicitud</small></span></a>
  <?php endif; ?>
</div>
<?php if(me()['role']==='empleado'): ?><div class="notice">Has registrado <b><?=$mySales?></b> venta(s) hoy. Tu panel no muestra costos ni utilidades del negocio.</div><?php endif; ?>
<div class="content-grid"><section class="panel"><div class="panel-head"><div><h2><?=me()['role']==='dueño'?'Ventas recientes':'Tus ventas recientes'?></h2><p class="muted">Últimas operaciones registradas</p></div><?php if(me()['role']==='dueño'): ?><a href="index.php?p=reports">Ver reportes →</a><?php endif; ?></div>
<?php
if(me()['role']==='dueño') {
 $q=$pdo->prepare("SELECT s.id,s.total,s.payment_method,s.created_at,u.first_name FROM sales s JOIN users u ON u.id=s.user_id WHERE s.store_id=? ORDER BY s.id DESC LIMIT 6");
 $q->execute([$storeId]);
} else {
 $q=$pdo->prepare("SELECT s.id,s.total,s.payment_method,s.created_at,u.first_name FROM sales s JOIN users u ON u.id=s.user_id WHERE s.store_id=? AND s.user_id=? ORDER BY s.id DESC LIMIT 6");
 $q->execute([$storeId,me()['id']]);
}
$recent=$q->fetchAll();
?>
<div class="table-scroll"><table><thead><tr><th>Venta</th><th>Empleado</th><th>Total</th><th>Pago</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td>#<?=$r['id']?></td><td><?=h($r['first_name'])?></td><td>$<?=number_format($r['total'],2)?></td><td><?=h(ucfirst($r['payment_method']))?></td></tr><?php endforeach; ?><?php if(!$recent): ?><tr><td colspan="4" class="muted">Todavía no hay ventas registradas.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="panel"><h2>Productos con poca existencia</h2><?php $q=$pdo->prepare('SELECT name,stock,min_stock FROM products WHERE store_id=? AND active=1 AND stock<=min_stock ORDER BY stock LIMIT 6');$q->execute([$storeId]);$lowItems=$q->fetchAll();foreach($lowItems as $p): ?><div class="low-item"><span><?=h($p['name'])?></span><b class="warning-text"><?=$p['stock']?> / <?=$p['min_stock']?></b></div><?php endforeach; ?><?php if(!$lowItems): ?><p class="muted">Todo bien: no hay alertas de inventario.</p><?php endif; ?></section></div>
<?php
$chartLabels=[]; $chartValues=[];
for($i=6;$i>=0;$i--){$day=date('Y-m-d',strtotime("-$i days"));$chartLabels[]=date('d/m',strtotime($day));
  if(me()['role']==='dueño'){$cq=$pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE store_id=? AND DATE(created_at)=? AND status='completada'");$cq->execute([$storeId,$day]);$chartValues[]=(float)$cq->fetchColumn();}
  else{$cq=$pdo->prepare("SELECT COUNT(*) FROM sales WHERE store_id=? AND user_id=? AND DATE(created_at)=? AND status='completada'");$cq->execute([$storeId,me()['id'],$day]);$chartValues[]=(int)$cq->fetchColumn();}
}
?>
<section class="panel"><div class="panel-head"><div><h2><?=me()['role']==='dueño'?'Ingresos de los últimos 7 días':'Tus ventas de los últimos 7 días'?></h2><p class="muted"><?=me()['role']==='dueño'?'Ingresos diarios en pesos (MXN)':'Número de operaciones que registraste'?></p></div></div>
<div class="chart-wrap"><div class="native-chart" role="img" aria-label="Gráfica de ventas de los últimos siete días">
<?php $maxChart=max(1,max($chartValues)); foreach($chartLabels as $i=>$lbl): $val=$chartValues[$i]; $pct=max(3,($val/$maxChart)*86); ?>
<div class="chart-col"><span class="chart-value"><?=me()['role']==='dueño'?'$'.number_format((float)$val,0):h($val)?></span><span class="chart-bar" style="height:<?=round($pct,1)?>%" title="<?=h($lbl)?>: <?=h($val)?>"></span><span class="chart-label"><?=h($lbl)?></span></div>
<?php endforeach; ?>
</div></div></section>
<?php elseif($page==='products'): ?>
<?php employeeOrOwner(); $q=$pdo->prepare('SELECT * FROM products WHERE store_id=? ORDER BY name');$q->execute([$storeId]);$products=$q->fetchAll(); ?>
<div class="page-heading"><div><p class="eyebrow">CATÁLOGO</p><h1><?=me()['role']==='dueño'?'Productos e inventario':'Consultar productos'?></h1><p class="muted">Encuentra artículos por nombre o categoría, revisa sus precios y existencias.</p></div></div>
<?php if(me()['role']==='dueño'): ?>
<details class="panel product-editor" id="productEditor"><summary><span id="editorTitle">＋ Agregar un producto</span><small class="muted" style="font-size:14px;font-weight:550">Toca aquí para abrir</small></summary>
<form method="post" enctype="multipart/form-data" id="productForm"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" id="editingProductId" value="0">
<div class="form-grid compact">
<div><label for="prodName">Nombre del producto *</label><input id="prodName" name="name" placeholder="Ej. Agua natural 1 litro" required></div>
<div><label for="prodCategory">Categoría *</label><input id="prodCategory" name="category" placeholder="Ej. Bebidas" required></div>
<div><label for="prodPrice">Precio de venta (MXN) *</label><input id="prodPrice" type="number" name="price" min="0" step=".01" required></div>
<div><label for="prodCost">Costo (solo dueño) *</label><input id="prodCost" type="number" name="cost" min="0" step=".01" required></div>
<div><label for="prodStock">Existencias *</label><input id="prodStock" type="number" name="stock" min="0" required></div>
<div><label for="prodMin">Avisar cuando queden</label><input id="prodMin" type="number" name="min_stock" min="0" value="5"></div>
<div class="span-2"><label for="prodDesc">Descripción</label><textarea id="prodDesc" name="description" placeholder="Presentación, tamaño o detalles del artículo"></textarea></div>
<div class="span-2"><label for="prodPhoto">Fotografía del producto (JPG, PNG o WEBP)</label><input id="prodPhoto" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><img id="productPreview" class="image-preview" alt="Vista previa del producto"></div>
</div><div class="editor-actions"><button class="btn primary" id="saveProductBtn" type="submit">Guardar producto</button><button class="btn" type="button" id="cancelProductEdit">Cancelar edición</button></div>
</form></details><?php endif; ?>
<section class="panel"><div class="panel-head"><div><h2>Catálogo del local</h2><p class="muted" id="productResults">Busca por nombre o categoría. Hay <?=count($products)?> producto(s).</p></div><input class="search-input" id="productSearch" type="search" aria-label="Buscar productos" placeholder="🔎 Buscar producto o categoría..."></div>
<div class="table-scroll"><table id="productsTable"><thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><?php if(me()['role']==='dueño'): ?><th>Costo</th><?php endif; ?><th>Existencias</th><th>Estado</th><?php if(me()['role']==='dueño'): ?><th>Acciones</th><?php endif; ?></tr></thead><tbody>
<?php foreach($products as $p): ?><tr>
<td><span class="product-name"><?php if($p['photo']): ?><img class="product-thumb" src="<?=h($p['photo'])?>" alt="Imagen de <?=h($p['name'])?>" loading="lazy"><?php else: ?><span class="product-placeholder" aria-hidden="true">📦</span><?php endif; ?><span><?=h($p['name'])?><span class="product-meta">#<?=$p['id']?></span></span></span></td>
<td><?=h($p['category'])?></td><td>$<?=number_format($p['price'],2)?></td><?php if(me()['role']==='dueño'): ?><td>$<?=number_format($p['cost'],2)?></td><?php endif; ?>
<td><b class="<?=$p['stock']<=$p['min_stock']?'warning-text':''?>"><?=$p['stock']?></b> <?=($p['stock']<=$p['min_stock']?'<span class="pill-status low">Reabastecer</span>':'')?></td>
<td><span class="pill-status <?=$p['active']?'':'off'?>"><?=$p['active']?'Activo':'Desactivado'?></span></td>
<?php if(me()['role']==='dueño'): ?><td><div class="inline-form">
<button class="btn tiny edit-product" type="button" data-product="<?=h(json_encode(['id'=>$p['id'],'name'=>$p['name'],'category'=>$p['category'],'description'=>$p['description'],'price'=>$p['price'],'cost'=>$p['cost'],'stock'=>$p['stock'],'min_stock'=>$p['min_stock'],'photo'=>$p['photo']], JSON_UNESCAPED_UNICODE))?>">✎ Editar</button>
<form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?=$p['id']?>"><button class="btn tiny" type="submit"><?= $p['active']?'Desactivar':'Activar' ?></button></form>
</div></td><?php endif; ?></tr><?php endforeach; ?>
<?php if(!$products): ?><tr><td colspan="7" class="muted">Aún no hay productos en este negocio. Agrega el primero arriba.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php elseif($page==='orders'): ?>

<div class="page-heading pedidos-heading">
  <div><p class="eyebrow">VENTAS · PEDIDOS · COBROS</p><h1>Pedidos</h1>
    <p class="muted"><?=me()['role'] === 'dueño' ? 'Captura solicitudes, cobra productos y consulta el historial en un solo lugar.' : 'Registra nuevos pedidos y cobra productos. El historial está disponible únicamente para el dueño.'?></p>
  </div>
</div>
<nav class="orders-shortcuts" aria-label="Secciones de pedidos">
 <a href="#registrar-pedido"><span class="orders-shortcut-icon">＋</span><span><strong>Registrar pedido</strong><small>Cliente, canal y lugar</small></span><span aria-hidden="true">↗</span></a>
 <a href="#cobrar-pedido"><span class="orders-shortcut-icon">$</span><span><strong>Cobrar productos</strong><small>Caja, promociones y pago</small></span><span aria-hidden="true">↗</span></a>
 <?php if (me()['role'] === 'dueño'): ?><a href="#historial-pedidos"><span class="orders-shortcut-icon">▤</span><span><strong>Historial</strong><small>Estado, responsable y cobro</small></span><span aria-hidden="true">↗</span></a><?php endif; ?>
</nav>
<section class="panel orders-panel" id="registrar-pedido">
  <div class="orders-panel-title"><span class="orders-step">01</span><div><h2>Registrar pedido</h2><p class="muted">Se guarda sin cobrar. Puedes cobrarlo más abajo cuando el cliente pague.</p></div></div>
  <form method="post" enctype="multipart/form-data" action="index.php?p=orders#registrar-pedido" class="form-grid compact">
    <input type="hidden" name="csrf" value="<?=h(csrf())?>">
    <input type="hidden" name="action" value="new_order">
    <div>
      <label for="orderCustomer">Cliente</label>
      <input id="orderCustomer" name="customer_name" maxlength="120" value="<?=h(($_POST['action']??'')==='new_order'?($_POST['customer_name']??''):'')?>" required placeholder="Nombre del cliente">
    </div>
    <div>
      <label for="orderSource">Canal del pedido</label>
      <select id="orderSource" name="source" required>
        <?php foreach (['mostrador'=>'Mostrador','llamada'=>'Llamada','whatsapp'=>'WhatsApp'] as $val=>$label): ?>
          <option value="<?=h($val)?>" <?=($_POST['source']??'mostrador')===$val?'selected':''?>><?=h($label)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="span-2"><label for="orderRegisteredClient">Cuenta de cliente (opcional)</label><select id="orderRegisteredClient" name="id_cliente">
      <option value="0">Pedido sin cuenta vinculada</option>
      <?php $clientesDelLocal=$pdo->prepare("SELECT id,nombre,apellido_paterno,usuario FROM usuarios WHERE id_negocio=? AND rol='cliente' AND activo=1 ORDER BY nombre LIMIT 200");$clientesDelLocal->execute([$storeId]); foreach($clientesDelLocal as $cliente): ?>
      <option value="<?=$cliente['id']?>" <?=((int)($_POST['id_cliente']??0)===(int)$cliente['id']?'selected':'')?> ><?=h($cliente['nombre'].' '.$cliente['apellido_paterno'].' (@'.$cliente['usuario'].')')?></option>
      <?php endforeach; ?>
    </select><p class="hint">Al vincular la cuenta, el cliente podrá ver su pedido en Mi agenda. Solo aparecen cuentas registradas en este negocio.</p></div>
    <div class="span-2">
      <label for="orderLocation">Lugar de compra <span aria-hidden="true">*</span></label>
      <input id="orderLocation" name="purchase_location" maxlength="180" required
        value="<?=h(($_POST['action']??'')==='new_order'?($_POST['purchase_location']??''):'')?>"
        placeholder="Ej. Local principal, sucursal Centro o Feria de la colonia">
      <p class="hint">Escribe dónde se realizó la compra. No es lo mismo que el canal (mostrador, llamada o WhatsApp).</p>
    </div>
    <div><label for="orderDelivery">Entrega programada (opcional)</label><input id="orderDelivery" type="datetime-local" name="fecha_entrega_prevista"></div>
    <div class="span-2">
      <label for="orderNotes">Descripción del pedido</label>
      <textarea id="orderNotes" name="notes" rows="3" placeholder="Productos solicitados, indicaciones o comentarios"><?=h(($_POST['action']??'')==='new_order'?($_POST['notes']??''):'')?></textarea>
    </div>
    <div class="span-2"><label for="orderPhoto">Subir foto del producto solicitado (opcional)</label><input type="file" id="orderPhoto" name="foto_pedido" accept="image/jpeg,image/png,image/webp"><p class="hint">Si el cliente tiene una imagen del producto que necesita, adjúntala aquí. JPG, PNG o WEBP, máximo 3 MB.</p></div>
    <div><button class="btn primary" type="submit">Guardar pedido</button></div>
  </form>
</section>
<?php if(me()['role']==='empleado'):
  $q=$pdo->prepare("SELECT p.id,p.nombre_cliente,p.fecha_entrega_prevista,p.estado,p.foto_referencia,
     (SELECT GROUP_CONCAT(CONCAT(dp.nombre_producto,' ×',dp.cantidad) SEPARATOR ', ') FROM detalle_pedidos dp WHERE dp.id_pedido=p.id) AS detalle
     FROM pedidos p WHERE p.id_negocio=? AND p.origen='web' AND p.estado IN ('pendiente','preparacion','listo') ORDER BY p.id DESC LIMIT 80");
  $q->execute([$storeId]);$ordenesWeb=$q->fetchAll();
?>
<section class="panel" id="pedidos-operativos"><h2>Pedidos de clientes por preparar</h2><p class="muted">Solo se muestran pedidos web abiertos de tu negocio; este apartado NO es el historial completo. Al marcar «Listo», el cliente recibe una notificación.</p>
<div class="table-scroll"><table><thead><tr><th>Folio</th><th>Cliente</th><th>Productos solicitados</th><th>Entrega</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
<?php foreach($ordenesWeb as $o):?><tr><td>#<?=(int)$o['id']?></td><td><?=h($o['nombre_cliente'])?></td><td><?=h($o['detalle']?:'Consultar descripción')?><?php if(!empty($o['foto_referencia']) && preg_match('#^uploads/pedido_[a-f0-9]{32}\.(jpg|png|webp)$#',$o['foto_referencia'])):?><div><a target="_blank" rel="noopener" href="<?=h($o['foto_referencia'])?>"><img class="order-photo-thumb" src="<?=h($o['foto_referencia'])?>" alt="Foto de referencia" loading="lazy"></a></div><?php endif;?></td><td><?=h($o['fecha_entrega_prevista']?:'Sin agendar')?></td><td><?=h(ucfirst($o['estado']))?></td>
<td><form method="post" class="vendia-mini-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="order_status"><input type="hidden" name="id" value="<?=(int)$o['id']?>"><select name="status" aria-label="Estado del pedido #<?=(int)$o['id']?>"><option value="pendiente" <?=$o['estado']==='pendiente'?'selected':''?>>Pendiente</option><option value="preparacion" <?=$o['estado']==='preparacion'?'selected':''?>>En preparación</option><option value="listo" <?=$o['estado']==='listo'?'selected':''?>>Listo</option></select><button class="btn tiny">Actualizar</button></form></td></tr><?php endforeach;?>
<?php if(!$ordenesWeb):?><tr><td colspan="6">No hay nuevos pedidos web pendientes.</td></tr><?php endif; ?></tbody></table></div>
</section>
<?php endif; ?>
<?php
$q=$pdo->prepare('SELECT id,name,price,stock FROM products WHERE store_id=? AND active=1 AND stock>0 ORDER BY name');$q->execute([$storeId]);$available=$q->fetchAll();
$pq=$pdo->prepare('SELECT id,name,discount_percent,tipo_beneficio,unidades_llevas,unidades_pagas,start_date,end_date FROM promotions pr WHERE pr.store_id=? AND pr.active=1 AND pr.authorized=1 AND pr.start_date<=CURDATE() AND pr.end_date>=CURDATE() AND EXISTS (SELECT 1 FROM promocion_productos pp JOIN productos p ON p.id=pp.id_producto WHERE pp.id_promocion=pr.id AND p.id_negocio=pr.store_id AND p.activo=1) ORDER BY name');
$pq->execute([$storeId]);$cashPromotions=$pq->fetchAll();
$cashPromotionProducts=[];$cashPromotionNames=[];
$q=$pdo->prepare('SELECT pp.id_promocion,pp.id_producto,p.nombre AS nombre_producto FROM promocion_productos pp JOIN promociones pr ON pr.id=pp.id_promocion JOIN productos p ON p.id=pp.id_producto WHERE pr.id_negocio=? AND p.id_negocio=? AND p.activo=1');
$q->execute([$storeId,$storeId]);
foreach($q as $link) {$cashPromotionProducts[(int)$link['id_promocion']][]=(int)$link['id_producto'];$cashPromotionNames[(int)$link['id_promocion']][]=$link['nombre_producto'];}
$q=$pdo->prepare("SELECT o.id,o.customer_name,o.purchase_location,o.status, p.total_cotizado,p.descuento_cotizado FROM orders o JOIN pedidos p ON p.id=o.id WHERE o.store_id=? AND o.status IN ('pendiente','preparacion','listo') AND NOT EXISTS (SELECT 1 FROM sales s WHERE s.order_id=o.id AND s.store_id=o.store_id) ORDER BY o.id DESC LIMIT 200");
$q->execute([$storeId]); $ordersToCharge=$q->fetchAll();
$orderQuoteItems=[];
if($ordersToCharge){
  $q=$pdo->prepare('SELECT id_producto,cantidad FROM detalle_pedidos WHERE id_pedido=?');
  foreach($ordersToCharge as $oc){$q->execute([(int)$oc['id']]);$orderQuoteItems[(int)$oc['id']]=[];
    foreach($q as $dp)$orderQuoteItems[(int)$oc['id']][(int)$dp['id_producto']]=(int)$dp['cantidad'];
  }
}
?>

<section class="panel orders-panel" id="cobrar-pedido">
 <div class="orders-panel-title"><span class="orders-step">02</span><div><h2>Cobrar productos</h2><p class="muted">Cobra un pedido registrado o una venta directa. El inventario se descuenta únicamente al confirmar el pago.</p></div></div>
 <div class="checkout-promos" aria-label="Promociones vigentes del negocio">
  <h3><?=icono('etiqueta')?> Promociones disponibles para caja</h3>
  <p class="muted">El dueño autoriza las promociones. Se aplican solo a los productos indicados y dejan de poder cobrarse después de su fecha de caducidad.</p>
  <?php if($cashPromotions): ?><div class="checkout-promos-grid">
  <?php foreach($cashPromotions as $cp): ?>
    <div class="checkout-promo-card">
      <div><strong><?=h($cp['name'])?></strong><span class="checkout-promo-pct"><?=h(etiquetaOferta($cp))?></span></div>
      <small><b>Caduca:</b> <?=h(date('d/m/Y',strtotime($cp['end_date'])))?></small>
      <small><b>Solo:</b> <?=h(implode(', ',array_slice($cashPromotionNames[(int)$cp['id']]??[],0,7)))?><?=count($cashPromotionNames[(int)$cp['id']]??[])>7?' y más':''?></small>
    </div>
  <?php endforeach; ?></div>
  <?php else: ?><div class="notice checkout-promos-empty">No hay promociones autorizadas y vigentes para este negocio.</div><?php endif; ?>
 </div>
 <form method="post" action="index.php?p=orders#cobrar-pedido" id="saleForm"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="sale">
 <div class="order-link-row"><div><label for="saleOrder">Vincular a pedido (opcional)</label>
 <select id="saleOrder" name="order_id"><option value="0">Venta directa — sin pedido registrado</option>
 <?php foreach($ordersToCharge as $oc): ?><option value="<?= (int)$oc['id']?>" data-quote="<?=h((string)($oc['total_cotizado']??''))?>" data-savings="<?=h((string)($oc['descuento_cotizado']??''))?>" data-items="<?=h(json_encode($orderQuoteItems[(int)$oc['id']]??[]))?>" <?=((int)($_POST['order_id']??$_GET['cobrar']??0)===(int)$oc['id'])?'selected':''?>>Pedido #<?= (int)$oc['id']?> · <?=h($oc['customer_name'])?> (<?=h($oc['purchase_location']?:'Lugar no registrado')?>)</option><?php endforeach; ?>
 </select></div><p>Al cobrar un pedido, se registrará la venta y su estado cambiará a <strong>Entregado</strong>. En pedidos web cotizados, los artículos se cargan automáticamente y se respeta el importe confirmado al cliente. En otros pedidos, selecciona los artículos en la tabla.</p></div>
<div class="panel-head"><div><h3>1. Elige los productos</h3><p class="muted">Usa + y − para agregar unidades. Solo aparecen productos disponibles.</p></div><input class="search-input" id="posSearch" type="search" placeholder="Buscar producto por nombre..." aria-label="Buscar producto para cobrar"></div>
<div class="table-scroll"><table id="posTable"><thead><tr><th>Producto</th><th>Precio</th><th>Disponible</th><th>Cantidad</th></tr></thead><tbody><?php foreach($available as $p): ?><tr class="sale-row"><td><?=h($p['name'])?></td><td>$<?=number_format($p['price'],2)?></td><td><?=$p['stock']?></td><td><div class="qty-control"><button type="button" class="qty-step" data-step="-1" aria-label="Quitar una unidad de <?=h($p['name'])?>">−</button><input class="qty-input" data-product-id="<?= (int)$p['id']?>" aria-label="Cantidad de <?=h($p['name'])?>" type="number" min="0" max="<?=$p['stock']?>" value="0" name="qty[<?=$p['id']?>]" data-price="<?=$p['price']?>"><button type="button" class="qty-step" data-step="1" aria-label="Agregar una unidad de <?=h($p['name'])?>">+</button></div></td></tr><?php endforeach; ?>
<?php if(!$available): ?><tr><td colspan="4" class="muted">No hay productos activos con existencia. El dueño debe dar de alta artículos.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="checkout-row"><div class="promo-checkout"><label for="salePromotion">2. Promociones o descuentos (opcional)</label><select id="salePromotion" name="promotion_id"><option value="0" data-discount="0">Sin promoción</option><?php foreach($cashPromotions as $cp): ?><option value="<?= (int)$cp['id']?>" data-discount="<?=h($cp['discount_percent'])?>" data-tipo="<?=h($cp['tipo_beneficio'])?>" data-llevas="<?=(int)$cp['unidades_llevas']?>" data-pagas="<?=(int)$cp['unidades_pagas']?>" data-expires="<?=h($cp['end_date'])?>" data-products="<?=h(json_encode($cashPromotionProducts[(int)$cp['id']]??[]))?>"><?=h($cp['name'])?> (<?=h(etiquetaOferta($cp))?> · caduca <?=h(date('d/m/Y',strtotime($cp['end_date'])))?>)</option><?php endforeach; ?></select><p id="salePromotionHint" class="hint" aria-live="polite">El descuento se aplica solo a los productos de la promoción vigente.</p><p id="saleQuotedHint" class="hint" hidden>Pedido web: se respetará la oferta y el precio cotizados al enviarlo. No se modifica en caja.</p></div><div><label for="paymentMethod">3. Método de pago</label><select id="paymentMethod" name="payment_method"><option value="efectivo">💵 Efectivo</option><option value="transferencia">🏦 Transferencia</option></select></div><div class="total-box"><span id="cartCounter" class="cart-counter">0 artículos seleccionados</span><span id="saleOriginalTotal" class="sale-discount-label" hidden>Precio normal: $0.00</span><span id="saleDiscountText" class="sale-discount-label" hidden>Ahorro: $0.00</span><span style="display:block">Total a cobrar</span><strong id="saleTotal" aria-live="polite">$0.00</strong></div><button class="btn primary" id="checkoutButton" type="submit" disabled>Confirmar cobro</button></div>
</form>
</section>
<?php if (me()['role'] === 'dueño'): ?>
<section class="panel orders-panel" id="historial-pedidos">
  <div class="orders-panel-title"><span class="orders-step">03</span><div><h2>Historial de pedidos</h2><p class="muted">Los pedidos anteriores se mantienen y podrás comprobar qué venta corresponde a cada uno.</p></div></div>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Folio</th>
          <th>Cliente</th>
          <th>Canal</th>
          <th>Lugar de compra</th>
          <th>Detalle</th>
          <th>Responsable</th>
          <th>Fecha</th>
          <th>Cobro</th>
          <th>Estado / Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $q=$pdo->prepare("SELECT o.*, p.foto_referencia, (SELECT GROUP_CONCAT(CONCAT(dp.nombre_producto,' ×',dp.cantidad) SEPARATOR ', ') FROM detalle_pedidos dp WHERE dp.id_pedido=o.id) AS productos_pedidos, u.first_name AS responsible_name, s.id AS sale_id, s.total AS sale_total, s.payment_method AS sale_payment FROM orders o JOIN pedidos p ON p.id=o.id LEFT JOIN users u ON u.id=o.user_id LEFT JOIN sales s ON s.order_id=o.id AND s.store_id=o.store_id WHERE o.store_id=? ORDER BY o.id DESC LIMIT 100");
          $q->execute([$storeId]);
          $orderRows=$q->fetchAll();
          foreach($orderRows as $o):
        ?>
          <tr>
            <td><strong>#<?= (int)$o['id'] ?></strong></td>
            <td><?=h($o['customer_name'])?></td>
            <td><?=h(ucfirst($o['source']))?></td>
            <td><span class="order-location"><?=h(trim((string)($o['purchase_location']??'')) ?: 'No registrado')?></span></td>
            <td><?php if($o['productos_pedidos']):?><strong><?=h($o['productos_pedidos'])?></strong><br><?php endif;?><?=h($o['notes'] ?: 'Sin descripción')?><?php if(!empty($o['foto_referencia']) && preg_match('#^uploads/pedido_[a-f0-9]{32}\.(jpg|png|webp)$#',$o['foto_referencia'])):?><div><a target="_blank" rel="noopener" href="<?=h($o['foto_referencia'])?>"><img class="order-photo-thumb" src="<?=h($o['foto_referencia'])?>" alt="Foto del producto solicitado" loading="lazy"></a></div><?php endif;?></td>
            <td><?=h($o['responsible_name'] ?: 'Sin asignar')?></td>
            <td><?=h($o['created_at'])?></td>
            <td><?php if($o['sale_id']): ?><span class="paid-pill">Cobrado</span><div class="payment-detail">Venta #<?= (int)$o['sale_id'] ?> · $<?=number_format((float)$o['sale_total'],2)?><br><?=h(ucfirst((string)$o['sale_payment']))?></div><?php else: ?><span class="unpaid-pill">Sin cobrar</span><?php endif; ?></td>
            <td>
              <?php if (!$o['sale_id'] && $o['status'] !== 'entregado'): ?>
                <a class="btn tiny primary order-pay-link" href="index.php?p=orders&amp;cobrar=<?= (int)$o['id']?>#cobrar-pedido">Cobrar</a>
              <?php endif; ?>
              <form method="post" class="inline-form" action="index.php?p=orders#historial-pedidos">
                <input type="hidden" name="csrf" value="<?=h(csrf())?>">
                <input type="hidden" name="action" value="order_status">
                <input type="hidden" name="id" value="<?= (int)$o['id']?>">
                <label class="visually-hidden" for="orderStatus<?= (int)$o['id'] ?>">Estado del pedido #<?= (int)$o['id']?></label>
                <select name="status" id="orderStatus<?= (int)$o['id'] ?>" <?= $o['sale_id']?'disabled':'' ?>>
                  <?php foreach(['pendiente'=>'Pendiente','preparacion'=>'En preparación','listo'=>'Listo','entregado'=>'Entregado'] as $st=>$label): ?>
                    <option value="<?=h($st)?>" <?=$o['status']===$st?'selected':''?>><?=h($label)?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (!$o['sale_id']): ?><button class="btn tiny" type="submit">Actualizar</button><?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$orderRows): ?>
          <tr><td colspan="9" class="muted">Todavía no hay pedidos registrados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; // Historial privado del dueño. ?>
<?php elseif($page==='employees'): ownerOnly(); ?>
<div class="page-heading"><div><p class="eyebrow">EQUIPO</p><h1>Empleados y permisos</h1><p class="muted">Autoriza el primer acceso de cada empleado y desactiva cuentas cuando sea necesario.</p></div></div>
<?php $pendientes=(int)countQuery($pdo, "SELECT COUNT(*) FROM users WHERE store_id=? AND role='empleado' AND active=0", [$storeId]); ?>
<?php if($pendientes): ?><div class="notice"><b>Hay <?=$pendientes?> empleado(s) sin acceso.</b> Busca su usuario en la tabla y pulsa <b>Activar</b>. Podrá iniciar sesión con la contraseña que registró, sin crear otra cuenta.</div><?php endif; ?>
<section class="panel"><h2>Cuentas de empleados</h2><div class="table-scroll"><table><thead><tr><th>Nombre</th><th>Usuario</th><th>Teléfono</th><th>Edad</th><th>Antecedentes médicos</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php $q=$pdo->prepare("SELECT * FROM users WHERE store_id=? AND role='empleado' ORDER BY id DESC");$q->execute([$storeId]);foreach($q as $e): ?><tr><td><?=h($e['first_name'].' '.$e['last_name1'].' '.$e['last_name2'])?></td><td><?=h($e['username'])?></td><td><?=h($e['phone'])?></td><td><?=h($e['age'])?></td><td><?=h($e['medical_notes'])?></td><td><?=$e['active']?'Activo':'Pendiente / inactivo'?></td><td><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="employee_active"><input type="hidden" name="id" value="<?=$e['id']?>"><input type="hidden" name="active" value="<?=$e['active']?0:1?>"><button class="btn tiny"><?=$e['active']?'Desactivar':'Activar'?></button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php elseif($page==='envio-promociones'): ownerOnly(); require __DIR__.'/envio_promociones.php'; ?>
<?php elseif($page==='promotions'): ownerOnly(); ?>
<?php
  $q=$pdo->prepare('SELECT id,name,discount_percent,tipo_beneficio,unidades_llevas,unidades_pagas,start_date,end_date,authorized,active FROM promotions WHERE store_id=? ORDER BY id DESC');
  $q->execute([$storeId]); $promotions=$q->fetchAll();
  $q=$pdo->prepare('SELECT id,name,category,price,stock,photo FROM products WHERE store_id=? AND active=1 ORDER BY name');
  $q->execute([$storeId]); $promoCatalog=$q->fetchAll();
  $q=$pdo->prepare('SELECT pp.id_promocion,pp.id_producto,p.nombre FROM promocion_productos pp JOIN promociones pr ON pr.id=pp.id_promocion JOIN productos p ON p.id=pp.id_producto WHERE pr.id_negocio=? AND p.id_negocio=? ORDER BY p.nombre');
  $q->execute([$storeId,$storeId]); $promoProductsById=[];
  foreach($q as $link){$promoProductsById[(int)$link['id_promocion']][]=['id'=>(int)$link['id_producto'],'name'=>$link['nombre']];}
  $postedProductIds=(($_POST['action']??'')==='save_promotion' && is_array($_POST['product_ids']??null))?array_map('intval',$_POST['product_ids']):[];
  $today=date('Y-m-d');
?>
<div class="page-heading promotions-heading">
  <div><h1>Promociones o descuentos</h1><p class="muted">Configura descuentos porcentuales o promociones 2x1, 3x2, 4x3 y 3x1.</p></div>
</div>
<section class="panel promo-card" id="promoEditor">
  <div class="promo-card-heading"><div class="promo-heading-icon"><?=icono('etiqueta')?></div><h2 id="promoFormTitle">Crear promoción o descuento</h2></div>
  <form method="post" id="promotionForm" action="index.php?p=promotions">
    <input type="hidden" name="csrf" value="<?=h(csrf())?>">
    <input type="hidden" name="action" value="save_promotion">
    <input type="hidden" id="promotionId" name="id" value="0">
    <div class="form-grid promo-grid">
      <div><label for="promotionName">Nombre de promoción o descuento <span class="required">*</span></label><input id="promotionName" name="name" maxlength="150" required placeholder="Ej. Descuento de temporada" value="<?=h(($_POST['action']??'')==='save_promotion'?$name:'')?>"></div>
      <div class="offer-modes" aria-label="Promociones o descuentos">
        <h3>Promociones o descuentos <span class="required">*</span></h3>
        <input type="hidden" id="offerType" name="tipo_beneficio" value="<?=h(($_POST['action']??'')==='save_promotion'?$tipoOferta:'descuento')?>">
        <div class="offer-modes-grid">
          <div class="offer-option" id="discountModeCard">
            <label class="offer-mode-label"><input type="radio" name="offerModeUi" value="descuento" checked> Descuento (%)</label>
            <label for="promotionDiscount">Porcentaje que se rebajará</label>
            <input id="promotionDiscount" name="discount" type="number" step="0.01" min="0.01" max="100" placeholder="Ej. 15" value="<?=h(($_POST['action']??'')==='save_promotion'?$discountRaw:'')?>">
            <small>Ejemplo: $100 → $85 con 15 %.</small>
          </div>
          <div class="offer-option" id="bundleModeCard">
            <label class="offer-mode-label"><input type="radio" name="offerModeUi" value="promocion"> Promoción por cantidad</label>
            <label for="promotionBundle">Elige la promoción</label>
            <select id="promotionBundle" name="paquete" disabled>
              <option value="">Elige una opción</option>
              <?php foreach(['2x1','3x2','4x3','3x1'] as $oferta): ?><option value="<?=h($oferta)?>" <?=(($_POST['paquete']??'')===$oferta?'selected':'')?>><?=h($oferta)?></option><?php endforeach; ?>
            </select>
            <small>En cada grupo completo se cobran las unidades más caras; las restantes van gratis.</small>
          </div>
        </div>
      </div>
      <div><label for="promotionFrom">Válida desde <span class="required">*</span></label><input id="promotionFrom" name="start_date" type="date" required value="<?=h(($_POST['action']??'')==='save_promotion'?$startDate:'')?>"></div>
      <div><label for="promotionUntil">Válida hasta <span class="required">*</span></label><input id="promotionUntil" name="end_date" type="date" required value="<?=h(($_POST['action']??'')==='save_promotion'?$endDate:'')?>"></div>
    </div>
    <div class="promo-final-row">
      <div class="promo-form-actions"><button class="btn primary promo-submit" type="button" id="promotionSubmit">Seleccionar productos</button><button class="btn promo-cancel" type="button" id="cancelPromotionEdit" hidden>Cancelar edición</button><button type="submit" id="promotionRealSubmit" class="btn primary promo-real-save" hidden>Crear promoción o descuento</button><p class="promo-action-hint">Primero selecciona los productos; después podrás crear o guardar la promoción.</p></div>
      <div class="promo-selected-panel" aria-live="polite">
        <div class="promo-selected-title"><strong>Productos seleccionados</strong><span id="promoSelectedCount" class="promo-selected-count">0</span></div>
        <div id="promoSelectedPreview" class="promo-selected-list"><span class="promo-no-selection">Aún no seleccionaste productos. Presiona Seleccionar productos para elegirlos.</span></div><div class="promo-pricing-summary" id="promoPricingSummary" aria-live="polite"></div>
      </div>
    </div>
  </form>
  <dialog id="promoProductDialog" class="promo-product-dialog" aria-labelledby="promoDialogTitle">
    <div class="promo-dialog-top"><div><p class="eyebrow">CATÁLOGO DE TU NEGOCIO</p><h2 id="promoDialogTitle">Selecciona los productos</h2><p class="muted">Marca todos los productos que participarán en esta promoción.</p></div><button type="button" id="promoDialogClose" class="promo-close" aria-label="Cerrar selección">×</button></div>
    <div class="promo-dialog-toolbar"><input type="search" id="promoProductSearch" placeholder="Buscar productos por nombre o categoría" aria-label="Buscar productos"><span id="promoDialogCount" class="promo-dialog-count">0 seleccionados</span></div>
    <div class="promo-product-scroll" id="promoProductScroll">
    <?php foreach($promoCatalog as $prod): ?>
      <label class="promo-product-option" data-search="<?=h((function_exists('mb_strtolower') ? mb_strtolower($prod['name'].' '.$prod['category'],'UTF-8') : strtolower($prod['name'].' '.$prod['category'])))?>">
        <input type="checkbox" class="promo-product-checkbox" name="product_ids[]" value="<?= (int)$prod['id']?>" form="promotionForm" data-name="<?=h($prod['name'])?>" data-price="<?=h($prod['price'])?>" <?=in_array((int)$prod['id'],$postedProductIds,true)?'checked':''?>>
        <?php if($prod['photo'] && preg_match('#^uploads/[a-zA-Z0-9_.-]+$#',$prod['photo'])): ?><img src="<?=h($prod['photo'])?>" alt="" loading="lazy"><?php else: ?><span class="promo-option-placeholder"><?=icono('etiqueta')?></span><?php endif; ?>
        <span class="promo-product-info"><strong><?=h($prod['name'])?></strong><small><?=h($prod['category'])?> · <?= (int)$prod['stock']?> disponibles</small></span><span class="promo-option-price">$<?=number_format((float)$prod['price'],2)?></span>
      </label>
    <?php endforeach; ?>
    <?php if(!$promoCatalog): ?><div class="promo-empty-catalog">No hay productos activos en tu local. Primero registra productos desde <a href="index.php?p=products">Productos e inventario</a>.</div><?php endif; ?>
    </div>
    <div class="promo-dialog-footer"><span id="promoDialogHint">Elige al menos un producto.</span><div><button type="button" class="btn" id="promoDialogCancel">Cancelar</button><button type="button" class="btn primary" id="promoDialogConfirm" <?=!$promoCatalog?'disabled':''?>>Terminar selección</button></div></div>
  </dialog>
</section>
<section class="panel promo-card promo-list">
  <div class="promo-list-head"><h2>Promociones guardadas</h2><span class="promo-count"><?=count($promotions)?> registrada(s)</span></div>
  <div class="table-scroll"><table id="promotionsTable">
    <thead><tr><th>Promoción</th><th>Promoción o descuento</th><th>Vigencia</th><th>Autorizada</th><th>Estado</th><th>Acción</th></tr></thead>
    <tbody>
    <?php foreach($promotions as $promo):
      $active=(int)$promo['active']===1;
      $authorized=(int)$promo['authorized']===1;
      if(!$active) {$status='Desactivada';$statusClass='inactive';}
      elseif(!$promo['start_date']||!$promo['end_date']) {$status='Sin vigencia';$statusClass='inactive';}
      elseif($today<$promo['start_date']) {$status='Programada';$statusClass='scheduled';}
      elseif($today>$promo['end_date']) {$status='Vencida';$statusClass='expired';}
      else {$status='Vigente';$statusClass='current';}
      $promoData=['id'=>(int)$promo['id'],'name'=>$promo['name'],'discount'=>$promo['discount_percent'],'tipo_beneficio'=>$promo['tipo_beneficio'],'unidades_llevas'=>$promo['unidades_llevas'],'unidades_pagas'=>$promo['unidades_pagas'],'start_date'=>$promo['start_date'],'end_date'=>$promo['end_date'],'product_ids'=>array_column($promoProductsById[(int)$promo['id']]??[],'id')];
    ?>
    <tr>
      <td><strong><?=h($promo['name'])?></strong><small class="promo-id">#<?= (int)$promo['id']?> · <?=count($promoProductsById[(int)$promo['id']]??[])?> producto(s)</small><?php if(!empty($promoProductsById[(int)$promo['id']])): ?><small class="promo-included-names"><?=h(implode(', ',array_column($promoProductsById[(int)$promo['id']],'name')))?></small><?php endif; ?></td>
      <td><strong class="promo-discount"><?=h(etiquetaOferta($promo))?></strong></td>
      <td><span class="promo-dates"><?= $promo['start_date']&&$promo['end_date']?h(date('d/m/Y',strtotime($promo['start_date'])).' — '.date('d/m/Y',strtotime($promo['end_date']))):'Sin fechas definidas' ?></span></td>
      <td><span class="promo-badge <?=$authorized?'approved':'pending'?>"><?=$authorized?'Sí':'No'?></span></td>
      <td><span class="promo-badge <?=$statusClass?>"><?=h($status)?></span></td>
      <td><div class="promo-row-actions">
        <button type="button" class="btn tiny edit-promotion" data-promotion="<?=h(json_encode($promoData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>">Editar</button>
        <form method="post" class="inline-form" action="index.php?p=promotions">
          <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="promotion_authorize"><input type="hidden" name="id" value="<?= (int)$promo['id']?>"><input type="hidden" name="authorize" value="<?=$authorized?'0':'1'?>">
          <button type="submit" class="btn tiny <?=$authorized?'':'promo-authorize'?>" <?=(!$authorized && (!$active || !$promo['start_date'] || !$promo['end_date']))?'disabled title="Primero edita las fechas y activa la promoción"':''?>><?=$authorized?'Retirar autorización':'Autorizar'?></button>
        </form>
        <form method="post" class="inline-form" action="index.php?p=promotions">
          <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="promotion_active"><input type="hidden" name="id" value="<?= (int)$promo['id']?>"><input type="hidden" name="active" value="<?=$active?'0':'1'?>">
          <button type="submit" class="btn tiny"><?=$active?'Desactivar':'Activar'?></button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$promotions): ?><tr><td colspan="6" class="promo-empty">No hay registros todavía.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <p class="promo-help">Solo las promociones vigentes, activas y autorizadas se pueden utilizar en el punto de venta. Editar una promoción exige autorizarla de nuevo.</p>
</section>
<?php elseif($page==='reports'): ownerOnly(); ?>
<?php
// Periodos fijos: hoy incluido, sin permitir fechas arbitrarias ni consultar otros negocios.
$reportDays = (isset($_GET['period']) && (string)$_GET['period'] === '14') ? 14 : 7;
$reportStart = (new DateTimeImmutable('today'))->modify('-'.($reportDays-1).' days');
$reportEnd = (new DateTimeImmutable('tomorrow'));
$previousStart = $reportStart->modify('-'.$reportDays.' days');
$reportDates = [$storeId, $reportStart->format('Y-m-d H:i:s'), $reportEnd->format('Y-m-d H:i:s')];
$dailySql = "SELECT DATE(created_at) AS fecha, COUNT(*) AS operaciones, COALESCE(SUM(total),0) AS ingresos FROM sales WHERE store_id=? AND created_at>=? AND created_at<? AND status='completada' GROUP BY DATE(created_at) ORDER BY fecha";
$rq=$pdo->prepare($dailySql); $rq->execute($reportDates); $dailyMap=[];
foreach($rq->fetchAll(PDO::FETCH_ASSOC) as $d){$dailyMap[$d['fecha']]=$d;}
$reportSeries=[]; $reportTotal=0; $reportCount=0; $reportMax=0;
for($i=0;$i<$reportDays;$i++){
  $dt=$reportStart->modify('+'.$i.' days'); $key=$dt->format('Y-m-d');
  $income=(float)($dailyMap[$key]['ingresos']??0); $ops=(int)($dailyMap[$key]['operaciones']??0);
  $reportSeries[]=['day'=>$dt->format('d/m'),'income'=>$income,'operations'=>$ops,'date'=>$key];
  $reportTotal+=$income; $reportCount+=$ops; $reportMax=max($reportMax,$income);
}
$previousTotal=(float)countQuery($pdo,"SELECT COALESCE(SUM(total),0) FROM sales WHERE store_id=? AND created_at>=? AND created_at<? AND status='completada'",[$storeId,$previousStart->format('Y-m-d H:i:s'),$reportStart->format('Y-m-d H:i:s')]);
$reportDiff=$reportTotal-$previousTotal;
$reportDiffPct=$previousTotal>0 ? (($reportDiff/$previousTotal)*100) : null;
$marginSql="SELECT COALESCE(SUM((si.unit_price-p.cost)*si.quantity),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id WHERE s.store_id=? AND s.created_at>=? AND s.created_at<? AND s.status='completada'";
$reportMargin=(float)countQuery($pdo,$marginSql,$reportDates);
$reportDiscount=(float)countQuery($pdo,"SELECT COALESCE(SUM(discount_amount),0) FROM sales WHERE store_id=? AND created_at>=? AND created_at<? AND status='completada'",$reportDates);
$reportProfit=$reportMargin-$reportDiscount;
$reportTicket=$reportCount>0?$reportTotal/$reportCount:0;
$pq=$pdo->prepare("SELECT payment_method,COUNT(*) AS operaciones,COALESCE(SUM(total),0) AS ingresos FROM sales WHERE store_id=? AND created_at>=? AND created_at<? AND status='completada' GROUP BY payment_method ORDER BY ingresos DESC");
$pq->execute($reportDates); $paymentRows=$pq->fetchAll(PDO::FETCH_ASSOC);
$paymentColors=['efectivo'=>'#12a99d','transferencia'=>'#326ce0'];
$paymentParts=[]; $paymentOffset=0;
foreach($paymentRows as $r){$share=$reportTotal>0?(float)$r['ingresos']/$reportTotal*100:0;$color=$paymentColors[$r['payment_method']]??'#8294a7';$paymentParts[]=$color.' '.round($paymentOffset,3).'% '.round($paymentOffset+$share,3).'%';$paymentOffset+=$share;}
$paymentGradient=$paymentParts?'conic-gradient('.implode(',',$paymentParts).')':'conic-gradient(#dce9ee 0% 100%)';
$topq=$pdo->prepare("SELECT si.product_name AS producto,SUM(si.quantity) AS unidades,COALESCE(SUM(si.unit_price*si.quantity),0) AS subtotal FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.store_id=? AND s.created_at>=? AND s.created_at<? AND s.status='completada' GROUP BY si.product_id,si.product_name ORDER BY unidades DESC,subtotal DESC LIMIT 6");
$topq->execute($reportDates);$topProducts=$topq->fetchAll(PDO::FETCH_ASSOC);
$maxUnits=$topProducts?(int)$topProducts[0]['unidades']:1;
$recentq=$pdo->prepare("SELECT s.id,s.created_at,s.total,s.payment_method,u.first_name FROM sales s JOIN users u ON u.id=s.user_id WHERE s.store_id=? AND s.created_at>=? AND s.created_at<? AND s.status='completada' ORDER BY s.created_at DESC,s.id DESC LIMIT 15");
$recentq->execute($reportDates);$reportRecent=$recentq->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="page-heading reports-heading"><div><p class="eyebrow">INTELIGENCIA DEL NEGOCIO</p><h1>Reportes y análisis</h1><p class="muted">Resultados reales de las ventas completadas de tu negocio. Se actualizan al registrar nuevas operaciones.</p></div><a class="btn primary" href="index.php?p=reports&amp;period=<?=$reportDays?>&amp;csv=1">↓ Exportar ventas CSV</a></div>
<?php require __DIR__.'/reporte_semanal.php'; ?>
<?php
$stockBajo=$pdo->prepare('SELECT nombre,existencias,existencia_minima FROM productos WHERE id_negocio=? AND activo=1 AND existencias<=existencia_minima ORDER BY existencias ASC,nombre ASC LIMIT 3');
$stockBajo->execute([$storeId]);$faltantes=$stockBajo->fetchAll();
$pendientesActuales=(int)countQuery($pdo,"SELECT COUNT(*) FROM pedidos WHERE id_negocio=? AND estado IN('pendiente','preparacion','listo')",[$storeId]);
if($faltantes){$recomendacionActual='Reabastece primero '.implode(', ',array_map(static fn($p)=>$p['nombre'].' (quedan '.$p['existencias'].')',$faltantes)).'. Están en el mínimo o por debajo de él.';}
elseif($pendientesActuales>0){$recomendacionActual='Prioriza preparar y entregar los '.$pendientesActuales.' pedidos abiertos para evitar retrasos.';}
elseif($reportCount===0){$recomendacionActual='No hubo ventas completadas en este periodo. Revisa la disponibilidad del catálogo y considera activar una oferta para productos de alta demanda.';}
elseif($previousTotal>0 && $reportTotal<$previousTotal){$recomendacionActual='Los ingresos del periodo bajaron respecto al anterior. Revisa qué artículos dejaron de venderse y considera una promoción sobre existencias suficientes.';}
else{$recomendacionActual='Mantén disponibles los artículos con mayor rotación, revisa existencias al cierre del día y da seguimiento a nuevos pedidos.';}
?>
<section class="panel vendia-recommendation" id="recomendacion-negocio">
  <p class="eyebrow">RECOMENDACIÓN DEL SISTEMA · DATOS DEL NEGOCIO</p>
  <h2><?=icono('estadisticas')?> Qué conviene hacer ahora</h2>
  <p><?=h($recomendacionActual)?></p>
  <small>Generada con ventas, pedidos e inventario registrados en Vendia; no supone datos externos.</small>
</section>
<section class="reports-toolbar" aria-label="Periodo del reporte"><div><strong>Resumen de rendimiento</strong><p class="muted">Del <?=$reportStart->format('d/m/Y')?> al <?=$reportEnd->modify('-1 day')->format('d/m/Y')?> · Comparación con los <?=$reportDays?> días anteriores</p></div><div class="reports-period-switch" role="group" aria-label="Elegir periodo"><a class="<?= $reportDays===7?'selected':'' ?>" href="index.php?p=reports&amp;period=7" <?= $reportDays===7?'aria-current="page"':'' ?>>7 días · 1 semana</a><a class="<?= $reportDays===14?'selected':'' ?>" href="index.php?p=reports&amp;period=14" <?= $reportDays===14?'aria-current="page"':'' ?>>14 días · 2 semanas</a></div></section>
<div class="reports-metrics">
  <article class="reports-metric"><span>Ingresos del periodo</span><strong>$<?=number_format($reportTotal,2)?></strong><small class="<?= $reportDiff>=0?'positive':'negative' ?>"><?= $previousTotal>0?($reportDiff>=0?'▲ ':'▼ ').number_format(abs($reportDiffPct),1).'% frente al periodo anterior':($reportTotal>0?'Sin ventas en el periodo anterior':'Sin movimientos aún') ?></small></article>
  <article class="reports-metric"><span>Ventas realizadas</span><strong><?=number_format($reportCount)?></strong><small>Operaciones completadas</small></article>
  <article class="reports-metric"><span>Ticket promedio</span><strong>$<?=number_format($reportTicket,2)?></strong><small>Ingreso promedio por venta</small></article>
  <article class="reports-metric"><span>Utilidad bruta estimada</span><strong>$<?=number_format($reportProfit,2)?></strong><small>Según costos actuales y descuentos</small></article>
</div>
<div class="reports-grid">
<section class="panel reports-main-chart"><div class="reports-section-head"><div><h2>Evolución de ingresos</h2><p class="muted">Ingresos diarios en pesos mexicanos (MXN)</p></div><span class="reports-chart-tag">Últimos <?=$reportDays?> días</span></div>
<div class="reports-bar-chart" role="img" aria-label="Ingresos diarios de los últimos <?=$reportDays?> días: <?php foreach($reportSeries as $point) echo h($point['day'].' $'.number_format($point['income'],2).'; '); ?>">
<?php foreach($reportSeries as $point): $percent=$reportMax>0?($point['income']/$reportMax)*100:0; ?>
<div class="reports-bar-column" title="<?=h($point['date'])?> · $<?=number_format($point['income'],2)?> · <?=$point['operations']?> operaciones"><span class="reports-bar-value">$<?=number_format($point['income'],0)?></span><div class="reports-bar-track"><div class="reports-bar-fill" style="height:<?=round($percent,2)?>%"></div></div><small><?=h($point['day'])?></small></div>
<?php endforeach; ?>
</div><p class="reports-footnote">Cada barra representa las ventas completadas del día. Los días sin ventas aparecen en cero.</p></section>
<section class="panel reports-payment-panel"><div class="reports-section-head"><div><h2>Métodos de pago</h2><p class="muted">Distribución de ingresos cobrados</p></div></div><div class="reports-donut" style="background:<?=h($paymentGradient)?>"><div><strong>$<?=number_format($reportTotal,0)?></strong><small>Total cobrado</small></div></div><div class="reports-payment-legend">
<?php foreach($paymentRows as $r): $share=$reportTotal>0?(float)$r['ingresos']/$reportTotal*100:0; ?><div class="reports-payment-line"><span><i style="background:<?=h($paymentColors[$r['payment_method']]??'#8294a7')?>"></i><?=h(ucfirst($r['payment_method']))?></span><b><?=number_format($share,1)?>%</b><small>$<?=number_format((float)$r['ingresos'],2)?></small></div><?php endforeach; ?>
<?php if(!$paymentRows): ?><p class="muted">Aún no hay pagos en este periodo.</p><?php endif; ?></div></section>
<section class="panel reports-products-panel"><div class="reports-section-head"><div><h2>Productos más vendidos</h2><p class="muted">Clasificación por unidades vendidas</p></div></div>
<?php foreach($topProducts as $i=>$p): $pct=$maxUnits>0?((int)$p['unidades']/$maxUnits)*100:0; ?><div class="reports-product-row"><div class="reports-product-head"><span><b class="reports-rank"><?=($i+1)?></b> <?=h($p['producto'])?></span><strong><?=number_format((int)$p['unidades'])?> uds.</strong></div><div class="reports-progress"><span style="width:<?=round($pct,2)?>%"></span></div></div><?php endforeach; ?>
<?php if(!$topProducts): ?><p class="muted reports-empty">No se registraron productos vendidos en estos días.</p><?php endif; ?></section>
<section class="panel reports-compare-panel"><div class="reports-section-head"><div><h2>Comparativa de periodos</h2><p class="muted">Mismo número de días, periodos consecutivos</p></div></div><div class="reports-comparison"><div><span>Periodo actual</span><strong>$<?=number_format($reportTotal,2)?></strong><div class="reports-progress"><span style="width:<?=max(0,round($reportTotal/max(1,$reportTotal,$previousTotal)*100,2))?>%"></span></div></div><div><span>Periodo anterior</span><strong>$<?=number_format($previousTotal,2)?></strong><div class="reports-progress previous"><span style="width:<?=max(0,round($previousTotal/max(1,$reportTotal,$previousTotal)*100,2))?>%"></span></div></div></div><p class="reports-compare-caption"><?= $previousTotal>0 ? ($reportDiff>=0?'Los ingresos aumentaron ':'Los ingresos disminuyeron ').number_format(abs($reportDiffPct),1).'% respecto al periodo anterior.' : 'No se registraron ingresos en el periodo anterior para calcular una variación porcentual.' ?></p></section>
</div>
<section class="panel reports-recent"><div class="reports-section-head"><div><h2>Detalle de ventas</h2><p class="muted">Las últimas 15 ventas completadas dentro del periodo seleccionado</p></div></div><div class="table-scroll"><table><thead><tr><th>Venta</th><th>Fecha y hora</th><th>Registró</th><th>Método de pago</th><th>Total</th></tr></thead><tbody><?php foreach($reportRecent as $s): ?><tr><td>#<?= (int)$s['id']?></td><td><?=h($s['created_at'])?></td><td><?=h($s['first_name'])?></td><td><?=h(ucfirst($s['payment_method']))?></td><td><strong>$<?=number_format((float)$s['total'],2)?></strong></td></tr><?php endforeach; ?><?php if(!$reportRecent): ?><tr><td colspan="5" class="muted">Todavía no se registran ventas en este periodo.</td></tr><?php endif; ?></tbody></table></div></section>
<?php elseif($page==='audit'): ownerOnly(); ?>
<div class="page-heading"><div><p class="eyebrow">CONTROL</p><h1>Seguridad y auditoría</h1><p class="muted">Historial de acciones registradas en el sistema.</p></div></div><section class="panel"><div class="table-scroll"><table><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th></tr></thead><tbody><?php $q=$pdo->prepare('SELECT a.*,u.username FROM audit_logs a JOIN users u ON u.id=a.user_id WHERE a.store_id=? ORDER BY a.id DESC LIMIT 100');$q->execute([$storeId]);foreach($q as $a): ?><tr><td><?=h($a['created_at'])?></td><td><?=h($a['username'])?></td><td><?=h($a['action'])?></td><td><?=h($a['details'])?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php elseif($page==='settings'): ownerOnly(); ?>
<?php
$settingsError = '';
try {
    $q = $pdo->prepare('SELECT u.first_name,u.last_name1,u.last_name2,u.phone,u.email,s.name AS store_name,s.category,s.business_hours,s.photo FROM users u JOIN stores s ON s.id=u.store_id WHERE u.id=? AND u.store_id=? AND u.role=\'dueño\' AND s.owner_id=u.id LIMIT 1');
    $q->execute([(int)me()['id'], $storeId]);
    $settings = $q->fetch();
    if (!$settings) $settingsError = 'No se encontró el registro del dueño o del negocio.';
} catch (PDOException $e) {
    $settingsError = 'Falta actualizar la base de datos. Importa ACTUALIZAR_CONFIGURACION_SIN_PERDER_DATOS.sql desde phpMyAdmin.';
    $settings = null;
}
if ($settings && $error && ($_POST['action'] ?? '') === 'save_settings') {
    foreach (['first_name','last_name1','last_name2','store_name','category','phone','email','business_hours'] as $field) {
        $settings[$field] = trim($_POST[$field] ?? '');
    }
}
$businessCategories = ['Fonda','Tienda','Taller','Consultorio','Puesto en mercado','Central de abasto','Otro'];
if ($settings && !in_array($settings['category'], $businessCategories, true)) {
    array_unshift($businessCategories, $settings['category']);
}
$storedPhoto = $settings['photo'] ?? '';
$showPhoto = $storedPhoto && preg_match('#^uploads/[a-zA-Z0-9_.-]+$#', $storedPhoto) && is_file(__DIR__.'/'.$storedPhoto);
?>
<div class="page-heading settings-heading">
  <div><h1>Configuración del negocio</h1><p class="muted">Actualiza tu información y la imagen del local.</p></div>
</div>
<?php if ($settingsError): ?>
<div class="alert error" role="alert"><?=h($settingsError)?></div>
<?php elseif ($settings): ?>
<div class="settings-layout">
  <section class="panel settings-card" aria-labelledby="settingsGeneralTitle">
    <div class="settings-card-title"><span class="settings-card-icon" aria-hidden="true"><?=icono('tienda')?></span><div><h2 id="settingsGeneralTitle">Datos generales</h2><p>Información del responsable y del establecimiento</p></div></div>
    <form action="index.php?p=settings" method="post" enctype="multipart/form-data" class="settings-form" id="businessSettingsForm">
      <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="save_settings">
      <div class="form-grid settings-fields">
        <div><label for="settingFirstName">Nombre <span class="required">*</span></label><input id="settingFirstName" name="first_name" value="<?=h($settings['first_name'])?>" maxlength="100" required autocomplete="given-name"></div>
        <div><label for="settingLast1">Apellido paterno <span class="required">*</span></label><input id="settingLast1" name="last_name1" value="<?=h($settings['last_name1'])?>" maxlength="100" required autocomplete="family-name"></div>
        <div><label for="settingLast2">Apellido materno</label><input id="settingLast2" name="last_name2" data-solo-letras pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" maxlength="100" value="<?=h($settings['last_name2'])?>" maxlength="100"></div>
        <div><label for="settingStoreName">Nombre del local <span class="required">*</span></label><input id="settingStoreName" name="store_name" value="<?=h($settings['store_name'])?>" maxlength="150" required></div>
        <div><label for="settingCategory">Categoría <span class="required">*</span></label><select id="settingCategory" name="category" required><?php foreach ($businessCategories as $category): ?><option value="<?=h($category)?>" <?=$settings['category']===$category?'selected':''?>><?=h($category)?></option><?php endforeach; ?></select></div>
        <div><label for="settingPhone">Teléfono <span class="required">*</span></label><input id="settingPhone" type="tel" name="phone" value="<?=h($settings['phone'])?>" maxlength="24" minlength="10" inputmode="tel" autocomplete="tel" required placeholder="Ej. 2291234567"></div>
        <div><label for="settingEmail">Correo <span class="required">*</span></label><input id="settingEmail" name="email" type="email" value="<?=h($settings['email'])?>" maxlength="150" autocomplete="email" required></div>
        <div><label for="settingHours">Horario comercial</label><input id="settingHours" name="business_hours" value="<?=h($settings['business_hours']??'')?>" maxlength="160" placeholder="Ej. Lun–Sáb de 8:00 a 20:00"></div>
        <div class="span-2 settings-photo-field"><label for="settingPhoto">Imagen del local</label>
          <div class="settings-upload">
            <div class="store-photo-preview" id="storePhotoPreviewBox" aria-label="Vista previa de la imagen del local">
              <?php if ($showPhoto): ?><img src="<?=h($storedPhoto)?>" alt="Fotografía actual del local" id="storePhotoPreview"><?php else: ?><img id="storePhotoPreview" alt="Vista previa de la nueva imagen" hidden><span id="storePhotoPlaceholder" aria-hidden="true"><?=icono('tienda')?></span><?php endif; ?>
            </div>
            <div class="upload-info"><input id="settingPhoto" name="store_photo" type="file" accept="image/jpeg,image/png,image/webp"><label class="upload-photo-btn" for="settingPhoto"><?=icono('camara')?> Seleccionar fotografía</label><span class="store-photo-file-name" id="storePhotoFileName">Ninguna imagen nueva seleccionada</span><p class="hint">JPG, PNG o WEBP, máximo 3 MB. Si no seleccionas otra foto, se conserva la actual.</p></div>
          </div>
          <?php if ($showPhoto): ?><label class="settings-remove-photo"><input type="checkbox" name="remove_photo" value="1"> Eliminar imagen actual</label><?php endif; ?>
        </div>
      </div>
      <div class="settings-actions"><button type="submit" class="btn primary settings-submit"><?=icono('guardar')?> Guardar cambios</button><p class="muted">Los datos se actualizan para este negocio.</p></div>
    </form>
  </section>
  <section class="panel settings-card" aria-labelledby="settingsPasswordTitle">
    <div class="settings-card-title"><span class="settings-card-icon lock" aria-hidden="true"><?=icono('candado')?></span><div><h2 id="settingsPasswordTitle">Cambiar contraseña</h2><p>Protege tu cuenta de dueño</p></div></div>
    <form action="index.php?p=settings" method="post" id="changeOwnerPasswordForm" class="settings-password-form">
      <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="change_password">
      <div class="settings-password-field"><label for="currentOwnerPassword">Contraseña actual <span class="required">*</span></label><div class="password-wrap"><input type="password" id="currentOwnerPassword" name="current_password" required autocomplete="current-password"><button type="button" class="showpass" onclick="togglePass('currentOwnerPassword',this)">Ver</button></div></div>
      <div class="settings-password-field"><label for="newOwnerPassword">Nueva contraseña <span class="required">*</span></label><div class="password-wrap"><input type="password" id="newOwnerPassword" name="new_password" required minlength="9" autocomplete="new-password"><button type="button" class="showpass" onclick="togglePass('newOwnerPassword',this)">Ver</button></div></div>
      <div class="settings-password-field"><label for="confirmOwnerPassword">Confirmar nueva contraseña <span class="required">*</span></label><div class="password-wrap"><input type="password" id="confirmOwnerPassword" name="confirm_password" required minlength="9" autocomplete="new-password"><button type="button" class="showpass" onclick="togglePass('confirmOwnerPassword',this)">Ver</button></div></div>
      <p class="settings-password-hint"><span aria-hidden="true"><?=icono('informacion')?></span> Usa más de 8 caracteres, al menos una mayúscula, un número y un símbolo.</p>
      <button type="submit" class="btn primary settings-submit"><?=icono('candado')?> Cambiar contraseña</button>
      <div class="password-safety-note"><strong>Tu seguridad importa</strong><span>Para guardar la nueva contraseña, primero debes confirmar la actual.</span></div>
    </form>
  </section>
</div>
<?php endif; ?>
<?php else: ?><div class="panel"><h2>Página no encontrada</h2><a href="index.php?p=home">Volver al inicio</a></div><?php endif; ?>
<?php endif; ?>

<?php if(logged()): ?></main><?php require __DIR__.'/agente_flotante.php'; ?></div><?php else: ?></main><?php endif; ?>
<script src="assets/js/app.js?v=21.0"></script>
</body></html>
