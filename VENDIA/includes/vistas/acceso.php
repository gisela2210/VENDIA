<?php
/** Formularios del portal público. $oldInput y $fieldErrors proceden del POST fallido, sin recuperar contraseñas. */
$oldInput=$oldInput??[]; $fieldErrors=$fieldErrors??[];
function valorAnterior(string $name, array $old): string { return h($old[$name]??''); }
function errorCampo(string $name,array $errors): string {
    return isset($errors[$name]) ? '<span class="field-error" id="error-'.h($name).'" role="alert">'.h($errors[$name]).'</span>' : '<span class="field-error" id="error-'.h($name).'" aria-live="polite" hidden></span>';
}
function campoRegistro(string $name,string $label,array $old,array $errors,string $attributes=''): void {
    $invalid=isset($errors[$name]);
    $type=$name==='email'?'email':($name==='phone'?'tel':($name==='age'?'number':'text'));
    echo '<div class="auth-field"><label for="reg-'.h($name).'">'.h($label).'</label>';
    echo errorCampo($name,$errors);
    echo '<input id="reg-'.h($name).'" name="'.h($name).'" type="'.$type.'" value="'.valorAnterior($name,$old).'" '.$attributes.' class="'.($invalid?'is-invalid':'').'" '.($invalid?'aria-invalid="true" aria-describedby="error-'.h($name).'"':'').'></div>';
}
function campoClave(string $name,string $label,string $id,array $errors): void {
    $invalid=isset($errors[$name]);
    echo '<div class="auth-field"><label for="'.h($id).'">'.h($label).'</label>'.errorCampo($name,$errors);
    echo '<div class="password-wrap"><input type="password" name="'.h($name).'" id="'.h($id).'" required minlength="9" autocomplete="new-password" class="'.($invalid?'is-invalid':'').'" '.($invalid?'aria-invalid="true"':'').'><button type="button" class="showpass" onclick="togglePass(\''.h($id).'\',this)">Ver</button></div></div>';
}
function casillasLegales(array $old,array $errors,bool $medical=false): void { ?>
 <div class="legal-acceptance">
  <div class="auth-field check-field">
   <?=errorCampo('acepto_terminos',$errors)?>
   <label class="auth-check"><input type="checkbox" name="acepto_terminos" value="1" required <?=!empty($old['acepto_terminos'])?'checked':''?>> <span>Acepto los <a href="legal/terminos.php" target="_blank" rel="noopener">Términos y condiciones</a>.</span></label>
  </div>
  <div class="auth-field check-field">
   <?=errorCampo('acepto_privacidad',$errors)?>
   <label class="auth-check"><input type="checkbox" name="acepto_privacidad" value="1" required <?=!empty($old['acepto_privacidad'])?'checked':''?>> <span>He leído el <a href="legal/privacidad.php" target="_blank" rel="noopener">Aviso de privacidad</a>.</span></label>
  </div>
  <?php if($medical): ?>
   <div class="auth-field check-field">
    <?=errorCampo('acepto_datos_salud',$errors)?>
    <label class="auth-check"><input type="checkbox" name="acepto_datos_salud" value="1" <?=!empty($old['acepto_datos_salud'])?'checked':''?>> <span>Si escribí información médica opcional, autorizo expresamente su almacenamiento. Puedo dejar el apartado vacío.</span></label>
   </div>
  <?php endif; ?>
 </div>
<?php }
?>
<?php if($page==='landing'): ?>
<section class="landing-stage" aria-label="Bienvenida a Vendia">
 <div class="landing-topline"><img class="brand-logo landing-brand-logo" src="assets/img/logo_vendia.png" alt="Logotipo Vendia"><div><strong>VENDIA</strong><span>Todo tu negocio en un solo lugar</span></div></div>
 <div class="landing-body"><div class="landing-copy">
  <h1>¡Bienvenido a <em>Vendia!</em></h1><p>Gestiona tu negocio de forma simple y eficiente: revisa ventas, controla productos y mucho más.</p>
  <div class="landing-links"><a class="landing-button primary" href="index.php?p=welcome">Comenzar <span aria-hidden="true">→</span></a></div>
 </div><div class="landing-picture"><div class="landing-illustration"><img src="assets/img/tienda_bienvenida.png" alt="Ilustración de tienda Vendia"><span class="landing-v-mark" aria-hidden="true">V</span></div></div></div>
 <div class="landing-footer"><span>Tu negocio, en buenas manos</span><a href="legal/terminos.php">Términos y condiciones →</a></div>
</section>
<?php elseif($page==='welcome'): ?>
<section class="auth-card welcome-card welcome-stage"><div class="welcome-copy">
 <div class="auth-brand"><img class="brand-logo" src="assets/img/logo_vendia.png" alt="Logo Vendia"><div><b>VENDIA</b><small>Todo tu negocio en un solo lugar</small></div></div>
 <span class="welcome-tag">★ Fácil de usar · Hecho para negocios locales</span><h1>¡Bienvenido a <span>Vendia!</span></h1>
 <p class="muted">Administra tu negocio desde un solo lugar. Primero, elige cómo quieres entrar.</p>
 <div class="role-grid">
  <a class="role-card owner" href="index.php?p=login&amp;role=dueño"><span class="role-icon">🏪</span><b>Soy dueño</b><small>Revisa ventas, productos, empleados y reportes.</small><span class="role-arrow">→</span></a>
  <a class="role-card employee" href="index.php?p=login&amp;role=empleado"><span class="role-icon">🛒</span><b>Soy empleado</b><small>Registra ventas, consulta productos y atiende pedidos.</small><span class="role-arrow">→</span></a>
  <a class="role-card customer" href="index.php?p=login&amp;role=cliente"><span class="role-icon">♙</span><b>Soy cliente</b><small>Consulta pedidos y fechas de entrega.</small><span class="role-arrow">→</span></a>
 </div>
 <div class="auth-links admin-entry"><a href="index.php?p=login&amp;role=superadministrador">Acceso de superadministrador</a></div>
 <a class="welcome-return" href="index.php?p=landing">← Volver a bienvenida</a>
 </div><img class="welcome-shop-art" src="assets/img/tienda_acceso.png" alt="Pequeño negocio abierto"></section>
<?php elseif($page==='login'): ?>
<section class="auth-card narrow"><a class="back" href="index.php?p=welcome">← Elegir otro perfil</a>
 <h1><?= $loginRole==='empleado'?'Ingresar como empleado':($loginRole==='cliente'?'Ingresar como cliente':($loginRole==='superadministrador'?'Acceso de superadministrador':'Ingresar como dueño')) ?></h1>
 <p class="muted">Ingresa el nombre de usuario y la contraseña que registraste.</p>
 <form method="post" action="index.php?p=login&amp;role=<?=urlencode($loginRole)?>" data-vendia-auth novalidate>
 <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="login"><input type="hidden" name="login_role" value="<?=h($loginRole)?>">
 <div class="auth-field"><label for="loginUsername"><?=$loginRole==='superadministrador'?'Correo o usuario':'Nombre de usuario'?></label><?=errorCampo('username',$fieldErrors)?><input id="loginUsername" name="username" required autocomplete="username" value="<?=valorAnterior('username',$oldInput)?>" <?=isset($fieldErrors['username'])?'class="is-invalid" aria-invalid="true"':''?> autofocus></div>
 <div class="auth-field"><label for="loginPass">Contraseña</label><?=errorCampo('password',$fieldErrors)?><div class="password-wrap"><input id="loginPass" name="password" type="password" required autocomplete="current-password" <?=isset($fieldErrors['password'])?'class="is-invalid" aria-invalid="true"':''?>><button type="button" class="showpass" onclick="togglePass('loginPass',this)">Ver</button></div></div>
 <button class="btn primary full" type="submit">Entrar al sistema</button></form>
 <?php if($loginRole==='superadministrador'): ?><p class="hint">Acceso exclusivo del equipo administrador de Vendia.</p>
 <?php else: ?><div class="auth-links"><a href="index.php?p=<?=$loginRole==='dueño'?'register-owner':($loginRole==='empleado'?'employee-register':'client-register')?>">Crear cuenta de <?=$loginRole?></a><a href="index.php?p=forgot">¿Olvidaste tu contraseña?</a></div>
 <?php if($loginRole==='empleado'): ?><p class="hint">La cuenta nueva deberá ser activada por el dueño en el panel de empleados.</p><?php endif; ?>
 <?php endif; ?>
</section>
<?php elseif(in_array($page,['register-owner','employee-register','client-register'],true)): ?>
<?php $isOwner=$page==='register-owner';$isEmployee=$page==='employee-register';$titulo=$isOwner?'Crear cuenta de dueño':($isEmployee?'Registro de empleado':'Crear cuenta de cliente');$accion=$isOwner?'register_owner':($isEmployee?'register_employee':'register_client'); ?>
<section class="auth-card form-card"><a class="back" href="index.php?p=welcome">← Elegir perfil</a><h1><?=h($titulo)?></h1>
 <p class="muted"><?=$isOwner?'Registra los datos de tu negocio.':($isEmployee?'El dueño debe activar tu cuenta antes de ingresar.':'Solo mayores de edad. Consulta tus pedidos y agenda.')?></p>
 <form method="post" data-vendia-auth novalidate>
 <input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="<?=h($accion)?>">
 <div class="form-grid">
  <?php campoRegistro('first_name','Nombre',$oldInput,$fieldErrors,'required data-solo-letras maxlength="100" autocomplete="given-name"'); ?>
  <?php campoRegistro('last_name1','Apellido paterno',$oldInput,$fieldErrors,'required data-solo-letras maxlength="100" autocomplete="family-name"'); ?>
  <?php campoRegistro('last_name2','Apellido materno',$oldInput,$fieldErrors,'data-solo-letras maxlength="100"'); ?>
  <?php if($isOwner): ?>
   <?php campoRegistro('store_name','Nombre del negocio',$oldInput,$fieldErrors,'required maxlength="150"'); ?>
   <div class="auth-field"><label for="category">Categoría del negocio</label><?=errorCampo('category',$fieldErrors)?><select id="category" name="category" required><option value="">Selecciona una categoría</option><?php foreach(['Fondas','Tiendas','Talleres','Consultorios','Puestos en mercados','Centrales de abasto','Otro'] as $c): ?><option value="<?=h($c)?>" <?=($oldInput['category']??'')===$c?'selected':''?>><?=h($c)?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <?php if($isEmployee): ?>
   <div class="auth-field"><label for="reg-store_id">Local o negocio</label><?=errorCampo('store_id',$fieldErrors)?><select id="reg-store_id" name="store_id" required><option value="">Selecciona tu negocio</option><?php foreach($pdo->query("SELECT id,nombre,categoria FROM negocios WHERE activo=1 AND estado_suscripcion<>'suspendida' ORDER BY nombre") as $s): ?><option value="<?=$s['id']?>" <?=((string)($oldInput['store_id']??'')===(string)$s['id']?'selected':'')?>><?=h($s['nombre'].' — '.$s['categoria'])?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <?php campoRegistro('phone','Teléfono celular',$oldInput,$fieldErrors,'required data-solo-digitos inputmode="numeric" maxlength="10" minlength="10"'); ?>
  <?php campoRegistro('email','Correo electrónico',$oldInput,$fieldErrors,'required data-correo-permitido maxlength="150" autocomplete="email"'); ?>
  <?php if(!$isOwner): ?><?php campoRegistro('age',$isEmployee?'Edad (16 a 100 años)':'Edad (18 a 120 años)',$oldInput,$fieldErrors,'required min="'.($isEmployee?'16':'18').'" max="'.($isEmployee?'100':'120').'" inputmode="numeric"'); ?><?php endif; ?>
  <?php campoRegistro('username','Nombre de usuario',$oldInput,$fieldErrors,'required minlength="4" maxlength="40" autocomplete="username"'); ?>
  <?php if($isEmployee): ?><div class="auth-field span-2"><label for="medicalNotes">Antecedentes médicos (opcional)</label><?=errorCampo('medical_notes',$fieldErrors)?><textarea name="medical_notes" id="medicalNotes" maxlength="1000" placeholder="Puedes dejarlo vacío; solo si deseas compartir información médica."><?=valorAnterior('medical_notes',$oldInput)?></textarea></div><?php endif; ?>
  <?php campoClave('password','Contraseña','reg-password',$fieldErrors); ?>
  <?php campoClave('confirm','Confirmar contraseña','reg-confirm',$fieldErrors); ?>
 </div>
 <p class="hint">La contraseña debe tener mínimo 9 caracteres, una mayúscula, un número y un carácter especial.</p>
 <?php casillasLegales($oldInput,$fieldErrors,$isEmployee); ?>
 <?php if(!$isOwner && !$isEmployee): ?>
 <p class="hint">No tienes que elegir un local al crear tu cuenta. Podrás comprar en cualquiera de los negocios activos. Si deseas recibir ofertas por WhatsApp, podrás autorizar a cada negocio cuando hagas tu pedido.</p>
 <?php endif; ?>
 <button class="btn primary" type="submit"><?=$isOwner?'Crear mi negocio':($isEmployee?'Enviar solicitud de registro':'Crear cuenta de cliente')?></button>
 </form>
 <?php if($isEmployee): ?><p class="hint">¿Ya te activaron? <a href="index.php?p=login&amp;role=empleado">Entrar como empleado</a></p><?php endif; ?>
</section>
<?php elseif($page==='forgot'): ?>
<section class="auth-card narrow"><a class="back" href="index.php?p=login">← Regresar</a><h1>Recuperar contraseña</h1><p class="muted">Elige cómo quieres recuperar tu acceso.</p>
 <form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="request_reset"><label>Medio de recuperación</label><select name="method"><option value="email">Correo electrónico</option><option value="phone">Teléfono celular</option></select><label>Correo o número de teléfono</label><input name="contact" required><button class="btn primary full">Solicitar enlace</button></form>
 <?php if(!empty($_SESSION['dev_reset_link'])): ?><div class="dev-note">Solo pruebas locales: <a href="<?=h($_SESSION['dev_reset_link'])?>">Abrir enlace de recuperación</a></div><?php unset($_SESSION['dev_reset_link']); endif; ?>
 <p class="hint">El envío real de correos y SMS requiere configurar los proveedores correspondientes.</p>
</section>
<?php endif; ?>
