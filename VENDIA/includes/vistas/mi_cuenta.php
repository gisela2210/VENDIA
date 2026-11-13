<?php
// Solo el titular puede cambiar sus propios datos. El dueño usa Configuración del negocio.
if (!logged()) { http_response_code(403); exit('Acceso restringido.'); }
if (me()['role']==='dueño') { http_response_code(403); exit('Usa Configuración del negocio.'); }
$perfil=$pdo->prepare('SELECT nombre,apellido_paterno,apellido_materno,usuario,correo,telefono,rol FROM usuarios WHERE id=? LIMIT 1');
$perfil->execute([(int)me()['id']]);
$datosCuenta=$perfil->fetch();
if (!$datosCuenta) {http_response_code(404);exit('No se encontró tu cuenta.');}
?>
<div class="page-heading"><div><p class="eyebrow">MI CUENTA · <?=h(ucfirst($datosCuenta['rol']))?></p><h1>Configuración de mi cuenta</h1><p class="muted">Actualiza tus datos personales o cambia tu contraseña.</p></div></div>
<div class="settings-layout">
<section class="panel settings-card" aria-labelledby="perfilTit"><div class="settings-card-title"><span class="settings-card-icon"><?=icono('usuarios')?></span><h2 id="perfilTit">Información personal</h2></div>
<form method="post" action="index.php?p=mi-cuenta" class="settings-form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="actualizar_mi_cuenta">
<div class="form-grid settings-fields">
<div><label for="myFirstName">Nombre</label><input id="myFirstName" name="nombre" data-solo-letras pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" maxlength="100" required value="<?=h($datosCuenta['nombre'])?>"></div>
<div><label for="myLastName1">Apellido paterno</label><input id="myLastName1" name="apellido_paterno" data-solo-letras pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" maxlength="100" required value="<?=h($datosCuenta['apellido_paterno'])?>"></div>
<div><label for="myLastName2">Apellido materno</label><input id="myLastName2" name="apellido_materno" data-solo-letras pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" maxlength="100" value="<?=h($datosCuenta['apellido_materno'])?>"></div>
<div><label for="myUsername">Nombre de usuario (no editable)</label><input id="myUsername" value="<?=h($datosCuenta['usuario'])?>" disabled></div>
<div><label for="myEmail">Correo</label><input id="myEmail" type="email" name="correo" maxlength="150" required value="<?=h($datosCuenta['correo'])?>"><p class="hint">Se admiten Gmail, Hotmail y Outlook.</p></div>
<div><label for="myPhone">Teléfono</label><input id="myPhone" name="telefono" type="tel" inputmode="numeric" maxlength="10" minlength="10" pattern="[0-9]{10}" required value="<?=h($datosCuenta['telefono'])?>"></div>
</div><button type="submit" class="btn primary settings-submit"><?=icono('guardar')?> Guardar mis datos</button>
</form>
</section>
<section class="panel settings-card"><div class="settings-card-title"><span class="settings-card-icon lock"><?=icono('candado')?></span><h2>Cambiar contraseña</h2></div>
<form action="index.php?p=mi-cuenta" method="post" class="settings-password-form">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="actualizar_mi_clave">
<div class="settings-password-field"><label for="myPassOld">Contraseña actual</label><div class="password-wrap"><input type="password" name="actual" id="myPassOld" required autocomplete="current-password"><button class="showpass" type="button" onclick="togglePass('myPassOld',this)">Ver</button></div></div>
<div class="settings-password-field"><label for="myPassNew">Nueva contraseña</label><div class="password-wrap"><input type="password" name="nueva" id="myPassNew" required minlength="9" autocomplete="new-password"><button class="showpass" type="button" onclick="togglePass('myPassNew',this)">Ver</button></div></div>
<div class="settings-password-field"><label for="myPassConfirm">Confirmar nueva contraseña</label><div class="password-wrap"><input type="password" name="confirmar" id="myPassConfirm" required minlength="9" autocomplete="new-password"><button class="showpass" type="button" onclick="togglePass('myPassConfirm',this)">Ver</button></div></div>
<p class="settings-password-hint">Mínimo 9 caracteres, una mayúscula, un número y un carácter especial.</p>
<button type="submit" class="btn primary settings-submit"><?=icono('candado')?> Actualizar contraseña</button>
</form>
</section>
</div>

<?php if(me()['role']==='cliente'):
    $q=$pdo->prepare("SELECT n.id,n.nombre,COALESCE(c.autorizado,0) AS autorizado FROM negocios n LEFT JOIN consentimientos_whatsapp c ON c.id_cliente=? AND c.id_negocio=n.id WHERE n.activo=1 AND n.estado_suscripcion<>'suspendida' ORDER BY n.nombre");
    $q->execute([(int)me()['id']]);
    $negociosConsentimiento=$q->fetchAll();
?>
<section class="panel whatsapp-consent-panel" id="consentimientos-whatsapp">
 <h2>Publicidad por WhatsApp</h2>
 <p class="muted">Elige de qué negocios deseas recibir ofertas por WhatsApp. Este permiso es opcional y no afecta tus pedidos. Puedes retirarlo cuando quieras.</p>
 <?php foreach($negociosConsentimiento as $negocioConsentimiento): ?>
 <form method="post" action="index.php?p=mi-cuenta#consentimientos-whatsapp" class="consent-business-row">
   <input type="hidden" name="csrf" value="<?=h(csrf())?>">
   <input type="hidden" name="action" value="cliente_whatsapp_optin">
   <input type="hidden" name="id_negocio" value="<?=(int)$negocioConsentimiento['id']?>">
   <input type="hidden" name="autorizado" value="<?=$negocioConsentimiento['autorizado']?'0':'1'?>">
   <span><strong><?=h($negocioConsentimiento['nombre'])?></strong><small><?= $negocioConsentimiento['autorizado']?'Publicidad autorizada':'Publicidad no autorizada' ?></small></span>
   <button type="submit" class="btn <?= $negocioConsentimiento['autorizado']?'':'primary' ?> small"><?= $negocioConsentimiento['autorizado']?'Dejar de recibir':'Permitir ofertas' ?></button>
 </form>
 <?php endforeach; ?>
 <?php if(!$negociosConsentimiento): ?><p class="muted">Cuando compres en un negocio podrás configurar sus avisos desde aquí.</p><?php endif; ?>
</section>
<?php endif; ?>
