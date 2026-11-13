<?php
// Este archivo se incluye dentro del manejador POST, después de verificar CSRF.
if ($action === 'agenda_fecha') {
    employeeOrOwner();
    $id=(int)($_POST['id_pedido']??0);
    $raw=trim((string)($_POST['fecha_entrega_prevista']??''));
    $fecha=validarFechaEntrega($raw);
    // Reprogramar a una fecha que ya venció mantendría la alerta roja.
    if ($id<1 || !$fecha || strtotime($fecha)<=time()) {
        $error='Selecciona un pedido y una fecha de entrega FUTURA.'; $page='agenda';
    } else {
        $sql="SELECT id,id_usuario,origen,estado FROM pedidos WHERE id=? AND id_negocio=? AND estado<>'entregado'";
        $q=$pdo->prepare($sql);$q->execute([$id,(int)me()['store_id']]);$pedido=$q->fetch();
        if(!$pedido || (me()['role']==='empleado' && (int)$pedido['id_usuario']!==(int)me()['id'] && $pedido['origen']!=='web')) {
            http_response_code(403);exit('Pedido inexistente, entregado o fuera de tus permisos.');
        }
        $pdo->prepare('UPDATE pedidos SET fecha_entrega_prevista=?,fecha_reprogramada_en=NOW() WHERE id=? AND id_negocio=? AND estado<>\'entregado\'')
            ->execute([$fecha,$id,(int)me()['store_id']]);
        avisarClienteDePedido($pdo,$id,'agenda','Entrega reprogramada','Tu pedido #'.$id.' ahora tiene entrega prevista para '.$fecha.'.');
        avisarNegocio($pdo,(int)me()['store_id'],'agenda','Pedido reprogramado','El pedido #'.$id.' fue reprogramado para '.$fecha.'.','index.php?p=agenda',(int)me()['id']);
        audit($pdo,'Fecha de pedido reprogramada','Pedido #'.$id.' / '.$fecha);
        $_SESSION['flash_success']='Pedido reprogramado. Ahora aparece en naranja con su nueva fecha de entrega.';
        redirectPage('agenda&mes='.substr($fecha,0,7));
    }
}

// El cliente puede comprar en cualquier negocio ACTIVO, sin acceso a información interna.
if ($action === 'cliente_pedido') {
    if(!logged() || me()['role']!=='cliente') {http_response_code(403);exit('Acceso exclusivo del cliente.');}
    $page='cliente-pedidos';
    $negocioId=(int)($_POST['id_negocio']??0);
    $cantidades=$_POST['cantidades']??[];
    $notas=trim((string)($_POST['notas']??''));
    $rawFecha=trim((string)($_POST['fecha_entrega_prevista']??''));
    $fecha=$rawFecha!==''?validarFechaEntrega($rawFecha):null;
    if(!is_array($cantidades) || count($cantidades)>300 || (function_exists('mb_strlen')?mb_strlen($notas,'UTF-8'):strlen($notas))>1500 ||
        ($rawFecha!=='' && (!$fecha || strtotime($fecha)<=time()))) {
        $error='Revisa las cantidades, las notas (máximo 1500 caracteres) y la fecha futura.';
    } else {
        try {
            $q=$pdo->prepare("SELECT id,nombre FROM negocios WHERE id=? AND activo=1 AND estado_suscripcion<>'suspendida'");
            $q->execute([$negocioId]);$negocio=$q->fetch();
            if(!$negocio) throw new RuntimeException('El negocio no está disponible para recibir pedidos.');
            $seleccion=[];
            foreach($cantidades as $id=>$cantidad) {
                if(!ctype_digit((string)$id) || !preg_match('/^\d{1,3}$/D',(string)$cantidad)) throw new RuntimeException('La cantidad indicada no es válida.');
                $qty=(int)$cantidad;
                if($qty===0) continue;
                if($qty>30 || count($seleccion)>=30) throw new RuntimeException('Máximo 30 unidades por producto y 30 productos diferentes.');
                $seleccion[(int)$id]=$qty;
            }
            if(!$seleccion) throw new RuntimeException('Selecciona al menos un producto y su cantidad.');
            $ids=array_keys($seleccion);
            $sql='SELECT id,nombre,precio,existencias FROM productos WHERE id_negocio=? AND activo=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')';
            $q=$pdo->prepare($sql);$q->execute(array_merge([$negocioId],$ids));$productos=$q->fetchAll();
            if(count($productos)!==count($seleccion)) throw new RuntimeException('Alguno de los productos ya no está disponible en este negocio.');
            foreach($productos as $p) if((int)$p['existencias'] < $seleccion[(int)$p['id']]) throw new RuntimeException('Existencias insuficientes para '.$p['nombre'].'.');
            $lineasCotizadas=[];
            foreach($productos as $p)$lineasCotizadas[]=['id'=>(int)$p['id'],'cantidad'=>$seleccion[(int)$p['id']],'precio'=>(float)$p['precio']];
            $cotizacion=cotizarPedido($pdo,$negocioId,$lineasCotizadas);
            $pdo->beginTransaction();
            $q=$pdo->prepare('SELECT nombre,apellido_paterno,apellido_materno FROM usuarios WHERE id=? AND rol=\'cliente\' AND activo=1');
            $q->execute([(int)me()['id']]);$cliente=$q->fetch();
            if(!$cliente) throw new RuntimeException('Tu cuenta de cliente no está activa.');
            $nombre=trim($cliente['nombre'].' '.$cliente['apellido_paterno'].' '.$cliente['apellido_materno']);
            $pdo->prepare("INSERT INTO pedidos(id_negocio,id_usuario,id_cliente,nombre_cliente,origen,lugar_compra,notas,fecha_entrega_prevista,id_oferta_cotizada,subtotal_cotizado,descuento_cotizado,total_cotizado) VALUES(?,?,?,?,'web',?,?,?,?,?,?,?)")
                ->execute([$negocioId,(int)me()['id'],(int)me()['id'],$nombre,'Tienda online · '.$negocio['nombre'],$notas,$fecha,
                  $cotizacion['id_oferta'],$cotizacion['subtotal_centavos']/100,$cotizacion['descuento_centavos']/100,$cotizacion['total_centavos']/100]);
            $pedidoId=(int)$pdo->lastInsertId();
            $q=$pdo->prepare('INSERT INTO detalle_pedidos(id_pedido,id_producto,nombre_producto,cantidad,precio_unitario) VALUES(?,?,?,?,?)');
            foreach($productos as $p) $q->execute([$pedidoId,(int)$p['id'],$p['nombre'],$seleccion[(int)$p['id']],$p['precio']]);
            // La autorización de WhatsApp es opcional, específica para este negocio y solo se guarda al aceptarla.
            if(!empty($_POST['aceptar_whatsapp_promos'])) {
                $pdo->prepare('INSERT INTO consentimientos_whatsapp(id_cliente,id_negocio,autorizado,fecha_autorizacion,fecha_revocacion) VALUES(?,?,1,NOW(),NULL) ON DUPLICATE KEY UPDATE autorizado=1,fecha_autorizacion=NOW(),fecha_revocacion=NULL')
                  ->execute([(int)me()['id'],$negocioId]);
            }
            $pdo->commit();
            avisarNegocio($pdo,$negocioId,'pedido','Nuevo pedido del cliente','Pedido web #'.$pedidoId.' registrado por '.$nombre.'. Revisa y prepara la solicitud.','index.php?p=orders');
            crearNotificacion($pdo,(int)me()['id'],$negocioId,'pedido','Tu pedido fue enviado','Tu pedido #'.$pedidoId.' fue enviado a '.$negocio['nombre'].'.','index.php?p=cliente-inicio');
            $_SESSION['flash_success']='Pedido #'.$pedidoId.' registrado en '.$negocio['nombre'].' por $'.number_format($cotizacion['total_centavos']/100,2).'. Oferta aplicada: '.$cotizacion['nombre_oferta'].'. El negocio confirmará entrega.';
            redirectPage('cliente-inicio');
        } catch(Throwable $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            error_log('Vendia pedido cliente: '.$e->getMessage());
            $error=$e instanceof RuntimeException?$e->getMessage():'No se pudo registrar el pedido; revisa tu base de datos.';
        }
    }
}

if ($action === 'notificacion_leida' || $action === 'notificaciones_todas') {
    if(!logged()) {http_response_code(401);exit('Inicia sesión.');}
    if($action==='notificacion_leida') {
        $pdo->prepare('UPDATE notificaciones SET leida=1 WHERE id=? AND id_usuario=?')
            ->execute([(int)($_POST['id_notificacion']??0),(int)me()['id']]);
    } else {
        $pdo->prepare('UPDATE notificaciones SET leida=1 WHERE id_usuario=? AND leida=0')->execute([(int)me()['id']]);
    }
    redirectPage('notificaciones');
}

if ($action === 'dueno_responder_ayuda') {
    ownerOnly();
    $ticket=(int)($_POST['id_solicitud']??0);
    $respuesta=trim((string)($_POST['respuesta']??''));
    $estado=(string)($_POST['estado']??'atendida');
    if($ticket<1 || (function_exists('mb_strlen')?mb_strlen($respuesta,'UTF-8'):strlen($respuesta))<3 || (function_exists('mb_strlen')?mb_strlen($respuesta,'UTF-8'):strlen($respuesta))>4000 || !in_array($estado,['atendida','cerrada'],true)) {
        $error='Escribe una respuesta de entre 3 y 4000 caracteres.';$page='ayuda';
    } else {
        $q=$pdo->prepare('SELECT id_usuario FROM solicitudes_ayuda WHERE id=? AND id_negocio=? AND id_usuario<>?');
        $q->execute([$ticket,(int)me()['store_id'],(int)me()['id']]);$remitente=$q->fetchColumn();
        if(!$remitente) {http_response_code(403);exit('No tienes acceso a esta solicitud.');}
        $pdo->prepare('UPDATE solicitudes_ayuda SET respuesta=?,estado=?,fecha_respuesta=NOW() WHERE id=? AND id_negocio=?')
            ->execute([$respuesta,$estado,$ticket,(int)me()['store_id']]);
        crearNotificacion($pdo,(int)$remitente,(int)me()['store_id'],'ayuda','Respondieron tu mensaje','El dueño respondió tu solicitud de ayuda #'.$ticket.'.','index.php?p=ayuda');
        $_SESSION['flash_success']='Respuesta enviada al usuario.';
        redirectPage('ayuda');
    }
}
if ($action === 'enviar_ayuda') {
    if(!logged() || !in_array(me()['role'],['cliente','dueño','empleado'],true)) {http_response_code(403);exit('Acceso no permitido.');}
    $asunto=trim((string)($_POST['asunto']??''));
    $mensaje=trim((string)($_POST['mensaje']??''));
    $destino=(int)(me()['role']==='cliente'?($_POST['id_negocio']??0):me()['store_id']);
    if ($destino<1 || strlen($asunto)<5 || strlen($asunto)>140 || strlen($mensaje)<10 || strlen($mensaje)>4000) {
        $error='El asunto requiere de 5 a 140 caracteres y el mensaje de 10 a 4000.';
        $page='ayuda';
    } else {
        $q=$pdo->prepare("SELECT id FROM negocios WHERE id=? AND activo=1");$q->execute([$destino]);
        if(!$q->fetchColumn()) { $error='Selecciona un negocio activo.'; $page='ayuda'; }
        else {
            $pdo->prepare('INSERT INTO solicitudes_ayuda(id_negocio,id_usuario,asunto,mensaje) VALUES(?,?,?,?)')
                ->execute([$destino,(int)me()['id'],$asunto,$mensaje]);
            $sid=(int)$pdo->lastInsertId();
            avisarNegocio($pdo,$destino,'ayuda','Nueva solicitud de ayuda','Se recibió la consulta #'.$sid.': '.$asunto,'index.php?p=ayuda',(int)me()['id']);
            avisarAdministradores($pdo,'ayuda','Solicitud de soporte','Solicitud #'.$sid.' en el negocio #'.$destino.': '.$asunto,'index.php?p=ayuda');
        }
        if(!$error) { $_SESSION['flash_success']='Tu mensaje fue enviado.'; redirectPage('ayuda'); }
    }
}
if ($action === 'enviar_pago') {
    ownerOnly();
    $importe=trim((string)($_POST['importe']??''));
    $referencia=trim((string)($_POST['referencia']??''));
    $fecha=trim((string)($_POST['fecha_pago']??''));
    $metodo=(string)($_POST['metodo']??'transferencia');
    $fechaValida=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
    if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$importe) || (float)$importe<=0 ||
        !preg_match('/^[a-zA-Z0-9._\-\/ ]{5,80}$/D',$referencia) ||
        !$fechaValida || $fechaValida->format('Y-m-d')!==$fecha ||
        !in_array($metodo,['efectivo','transferencia','otro'],true)) {
        $error='Revisa importe, referencia, fecha y método de pago.'; $page='suscripcion';
    } else {
        $pdo->prepare('INSERT INTO pagos(id_negocio,id_usuario,importe,referencia,metodo,fecha_pago) VALUES(?,?,?,?,?,?)')
            ->execute([(int)me()['store_id'],(int)me()['id'],(float)$importe,$referencia,$metodo,$fecha]);
        audit($pdo,'Pago reportado','Referencia '.substr($referencia,0,80));
        avisarAdministradores($pdo,'pago','Pago pendiente de revisión','El negocio #'.(int)me()['store_id'].' reportó el pago '.$referencia.'.','index.php?p=superadmin-pagos');
        $_SESSION['flash_success']='Pago enviado a revisión; aún NO se ha verificado.';
        redirectPage('suscripcion');
    }
}
if (str_starts_with($action,'admin_')) {
    adminOnly();
    $sid=(int)($_POST['id_negocio']??0);
    $uid=(int)($_POST['id_usuario']??0);
    $adminId=(int)me()['id'];
    $filtroOrden=(string)($_POST['orden_usuarios']??'fecha_desc');
    $filtroTipo=(string)($_POST['tipo_usuario']??'todos');
    if(!in_array($filtroOrden,['fecha_desc','fecha_asc','nombre_asc','nombre_desc'],true))$filtroOrden='fecha_desc';
    if(!in_array($filtroTipo,['todos','cliente','dueño','empleado'],true))$filtroTipo='todos';
    $regresarUsuarios='superadmin-usuarios&orden_usuarios='.rawurlencode($filtroOrden).'&tipo_usuario='.rawurlencode($filtroTipo);
    $filtroPagosOrden=(string)($_POST['orden_pagos']??'pendientes');
    $filtroPagosEstado=(string)($_POST['estado_pagos']??'todos');
    if(!in_array($filtroPagosOrden,['pendientes','importe_desc','importe_asc','fecha_desc','fecha_asc'],true))$filtroPagosOrden='pendientes';
    if(!in_array($filtroPagosEstado,['todos','pendiente','verificado','rechazado'],true))$filtroPagosEstado='todos';
    $regresarPagos='superadmin-pagos&orden_pagos='.rawurlencode($filtroPagosOrden).'&estado_pagos='.rawurlencode($filtroPagosEstado);
    if ($action==='admin_negocio_estado' || $action==='admin_suscripcion_estado') {
        if ($sid<1) { http_response_code(400); exit('Negocio no válido.'); }
        $motivo=trim((string)($_POST['motivo']??''));
        if (strlen($motivo)>255) { $error='El motivo supera 255 caracteres.'; $page='superadmin'; }
        else {
            $pdo->beginTransaction();
            try {
                $check=$pdo->prepare('SELECT id,activo,estado_suscripcion FROM negocios WHERE id=? FOR UPDATE');
                $check->execute([$sid]); $negocio=$check->fetch();
                if(!$negocio) throw new RuntimeException('El negocio no existe.');
                if($action==='admin_negocio_estado') {
                    $activo=(int)($_POST['activo']??0)===1?1:0;
                    $estado=$activo?'al_corriente':'suspendida';
                    $pdo->prepare('UPDATE negocios SET activo=?,estado_suscripcion=?,fecha_baja=?,motivo_baja=? WHERE id=?')
                        ->execute([$activo,$estado,$activo?null:date('Y-m-d H:i:s'),$activo?null:($motivo?:'Suspensión administrativa'),$sid]);
                    $evento=$activo?'activacion':'baja';
                    $descripcion=($activo?'Negocio reactivado. ':'Negocio dado de baja (reversible). ').$motivo;
                } else {
                    $estado=(string)($_POST['estado_suscripcion']??'');
                    if(!in_array($estado,['al_corriente','pendiente','vencida','suspendida'],true)) throw new RuntimeException('Estado de suscripción no válido.');
                    $pdo->prepare('UPDATE negocios SET estado_suscripcion=?,activo=?,fecha_baja=IF(?=0,NOW(),NULL),motivo_baja=IF(?=0,?,NULL) WHERE id=?')
                        ->execute([$estado,$estado==='suspendida'?0:1,$estado==='suspendida'?0:1,$estado==='suspendida'?0:1,$motivo?:'Suscripción suspendida',$sid]);
                    $evento=$estado==='suspendida'?'suspension':'activacion';
                    $descripcion='Suscripción: '.$estado.'. '.$motivo;
                }
                $pdo->prepare('INSERT INTO eventos_negocio(id_negocio,id_admin,accion,descripcion) VALUES(?,?,?,?)')
                    ->execute([$sid,$adminId,$evento,$descripcion]);
                $pdo->commit();
                $_SESSION['flash_success']='Estado del negocio actualizado. Se conservan sus registros.';
                redirectPage('superadmin');
            } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); $error=$e->getMessage(); $page='superadmin'; }
        }
    }
    if ($action==='admin_usuario_estado') {
        $activo=(int)($_POST['activo']??0)===1?1:0;
        if ($uid<1) { $error='Usuario inválido.'; $page='superadmin'; }
        else {
            $q=$pdo->prepare("UPDATE usuarios SET activo=? WHERE id=? AND rol IN ('dueño','empleado','cliente')");
            $q->execute([$activo,$uid]);
            $_SESSION['flash_success']='Acceso del usuario actualizado (no se elimina su información).';
            redirectPage($regresarUsuarios);
        }
    }
    if ($action==='admin_usuario_editar') {
        $first=trim((string)($_POST['nombre']??''));
        $paterno=trim((string)($_POST['apellido_paterno']??''));
        $materno=trim((string)($_POST['apellido_materno']??''));
        $correo=trim((string)($_POST['correo']??''));
        $telefono=trim((string)($_POST['telefono']??''));
        if($uid<1 || !nombreValido($first) || !nombreValido($paterno) || ($materno!=='' && !nombreValido($materno)) ||
            !correoPermitido($correo) || strlen($correo)>150 || !telefonoValido($telefono)) {
            $error='Los datos del usuario no cumplen con las restricciones.';$page='superadmin';
        } else {
            $pdo->prepare("UPDATE usuarios SET nombre=?,apellido_paterno=?,apellido_materno=?,correo=?,telefono=? WHERE id=? AND rol IN ('dueño','empleado','cliente')")
                ->execute([$first,$paterno,$materno,$correo,$telefono,$uid]);
            $_SESSION['flash_success']='Datos del usuario actualizados.';
            redirectPage($regresarUsuarios);
        }
    }
    if ($action==='admin_pago_verificar') {
        $id=(int)($_POST['id_pago']??0);
        $estado=(string)($_POST['estado']??'');
        if($id<1 || !in_array($estado,['verificado','rechazado'],true)) { $error='Solicitud de verificación inválida.'; $page='superadmin'; }
        else {
            $pdo->beginTransaction();
            try {
                $q=$pdo->prepare("SELECT id,id_negocio,estado FROM pagos WHERE id=? FOR UPDATE"); $q->execute([$id]);$p=$q->fetch();
                if(!$p || $p['estado']!=='pendiente') throw new RuntimeException('Este pago ya fue revisado o no existe.');
                $pdo->prepare('UPDATE pagos SET estado=?,id_verificador=?,fecha_verificacion=NOW() WHERE id=?')
                    ->execute([$estado,$adminId,$id]);
                if ($estado==='verificado') {
                    $pdo->prepare("UPDATE negocios SET activo=1,estado_suscripcion='al_corriente',fecha_baja=NULL,motivo_baja=NULL WHERE id=?")
                        ->execute([$p['id_negocio']]);
                    $pdo->prepare("INSERT INTO eventos_negocio(id_negocio,id_admin,accion,descripcion) VALUES(?,?,'pago',?)")
                        ->execute([$p['id_negocio'],$adminId,'Pago #'.$id.' verificado por el administrador']);
                }
                $pdo->commit();
                $o=$pdo->prepare('SELECT id_dueno FROM negocios WHERE id=?');$o->execute([$p['id_negocio']]);
                $d=$o->fetchColumn();if($d)crearNotificacion($pdo,(int)$d,(int)$p['id_negocio'],'pago','Pago '.$estado,'Tu pago #'.$id.' fue marcado como '.$estado.' por administración.','index.php?p=suscripcion');
                $_SESSION['flash_success']='Pago marcado como '.$estado.'. Verificación manual: comprueba el depósito antes de aprobar.';
                redirectPage($regresarPagos);
            } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();$page='superadmin';}
        }
    }
    if ($action==='admin_mantenimiento') {
        $fecha=trim((string)($_POST['fecha']??''));
        $valida=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
        if($sid<1 || !$valida || $valida->format('Y-m-d')!==$fecha) { $error='Fecha de mantenimiento inválida.'; $page='superadmin'; }
        else {
            $pdo->prepare('UPDATE negocios SET proximo_mantenimiento=? WHERE id=?')->execute([$fecha,$sid]);
            $pdo->prepare("INSERT INTO eventos_negocio(id_negocio,id_admin,accion,descripcion) VALUES(?,?,'mantenimiento',?)")
                ->execute([$sid,$adminId,'Próximo mantenimiento: '.$fecha]);
            $_SESSION['flash_success']='Mantenimiento programado.';
            redirectPage('superadmin');
        }
    }
    if ($action==='admin_reset') {
        if ($uid<1 || $uid===$adminId) { http_response_code(403); exit('No se permite esta acción.'); }
        $q=$pdo->prepare("SELECT id,rol FROM usuarios WHERE id=? AND rol IN ('dueño','empleado','cliente')"); $q->execute([$uid]);
        if(!$q->fetch()) { $error='Solo se pueden recuperar cuentas de dueños, empleados y clientes.'; $page='superadmin'; }
        else {
            $token=bin2hex(random_bytes(32));
            $pdo->prepare('INSERT INTO recuperaciones_contrasena(id_usuario,token_hash,fecha_expiracion) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')
                ->execute([$uid,password_hash($token,PASSWORD_DEFAULT)]);
            $_SESSION['enlace_admin_reset']='reset.php?uid='.$uid.'&token='.$token;
            $_SESSION['flash_success']='Enlace temporal generado. Compártelo por un canal seguro; no se puede consultar la contraseña existente.';
            redirectPage($regresarUsuarios);
        }
    }
    if ($action==='admin_responder_ayuda') {
        $ticket=(int)($_POST['id_solicitud']??0);
        $respuesta=trim((string)($_POST['respuesta']??''));
        $estado=(string)($_POST['estado']??'atendida');
        if($ticket<1 || strlen($respuesta)<3 || strlen($respuesta)>4000 || !in_array($estado,['atendida','cerrada'],true)) {
            $error='Revisa la respuesta y el estado del mensaje.'; $page='ayuda';
        } else {
            $pdo->prepare('UPDATE solicitudes_ayuda SET respuesta=?,estado=?,fecha_respuesta=NOW() WHERE id=?')
                ->execute([$respuesta,$estado,$ticket]);
            $q=$pdo->prepare('SELECT id_usuario,id_negocio FROM solicitudes_ayuda WHERE id=?');$q->execute([$ticket]);$t=$q->fetch();
            if($t)crearNotificacion($pdo,(int)$t['id_usuario'],(int)$t['id_negocio'],'ayuda','Soporte respondió','Hay una respuesta a tu solicitud de ayuda #'.$ticket.'.','index.php?p=ayuda');
            $_SESSION['flash_success']='Respuesta enviada al negocio.';
            redirectPage('ayuda');
        }
    }
}
