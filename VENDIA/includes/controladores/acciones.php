<?php
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    require __DIR__.'/autenticacion.php';

    // Un solo resumen por lunes. El dueño puede dispararlo manualmente para
    // la demostración; la tarea programada usa la misma operación idempotente.
    if ($action === 'resumen_semanal_demo') {
        ownerOnly();
        try {
            $respuestaResumen = generarResumenVendia($pdo,(int)me()['store_id']);
            $_SESSION['flash_success'] = $respuestaResumen['nuevo']
                ? 'Resumen semanal generado: consulta el mensaje y su recomendación.'
                : 'El resumen de esta semana ya existía; no se duplicó el mensaje.';
        } catch (Throwable $e) {
            error_log('Vendia: error al generar resumen semanal: '.$e->getMessage());
            $_SESSION['flash_success'] = 'No se pudo generar el resumen. Revisa si importaste el database.sql actualizado.';
        }
        redirectPage('reports#resumen-semanal');
    }


    // Permiso voluntario para publicidad por WhatsApp, individual por negocio.
    if ($action === 'cliente_whatsapp_optin') {
        if (!logged() || me()['role'] !== 'cliente') {http_response_code(403);exit('Acceso solo para clientes.');}
        $clienteId=(int)me()['id'];
        $negocioId=(int)($_POST['id_negocio']??0);
        $autorizar=(int)($_POST['autorizado']??-1);
        if ($negocioId<1 || !in_array($autorizar,[0,1],true)) {http_response_code(400);exit('Solicitud inválida.');}
        // El cliente elige expresamente a qué negocio autoriza; no debe haber pedido previo.
        $q=$pdo->prepare("SELECT id FROM negocios WHERE id=? AND activo=1 AND estado_suscripcion<>'suspendida' LIMIT 1");
        $q->execute([$negocioId]);
        if (!$q->fetchColumn()) {http_response_code(403);exit('El negocio no está activo o no existe.');}
        $pdo->prepare('INSERT INTO consentimientos_whatsapp (id_cliente,id_negocio,autorizado,fecha_autorizacion,fecha_revocacion) VALUES(?,?,?,IF(?=1,NOW(),NULL),IF(?=0,NOW(),NULL)) ON DUPLICATE KEY UPDATE autorizado=VALUES(autorizado),fecha_autorizacion=IF(VALUES(autorizado)=1,NOW(),fecha_autorizacion),fecha_revocacion=IF(VALUES(autorizado)=0,NOW(),NULL)')->execute([$clienteId,$negocioId,$autorizar,$autorizar,$autorizar]);
        $_SESSION['flash_success']=$autorizar?'Autorizaste recibir promociones por WhatsApp de este negocio.':'Retiraste la autorización para publicidad por WhatsApp de este negocio.';
        redirectPage('mi-cuenta#consentimientos-whatsapp');
    }

    // Perfil propio: aplica a empleado, cliente y superadministrador. Dueños conservan Configuración del negocio.
    if (in_array($action, ['actualizar_mi_cuenta','actualizar_mi_clave'], true)) {
        if (!logged()) {http_response_code(403);exit('Debes iniciar sesión.');}
        if (me()['role'] === 'dueño') redirectPage('settings');
        $page = 'mi-cuenta';
        $uid = (int)me()['id'];
        if ($action === 'actualizar_mi_cuenta') {
            $nombre=trim((string)($_POST['nombre']??''));
            $paterno=trim((string)($_POST['apellido_paterno']??''));
            $materno=trim((string)($_POST['apellido_materno']??''));
            $correo=trim((string)($_POST['correo']??''));
            $telefono=trim((string)($_POST['telefono']??''));
            if(!nombreValido($nombre)||!nombreValido($paterno)||($materno!==''&&!nombreValido($materno))) {
                $error='Nombre y apellidos deben contener únicamente letras y espacios.';
            } elseif(!correoPermitido($correo)) {
                $error='El correo debe ser Gmail, Hotmail u Outlook.';
            } elseif(!telefonoValido($telefono)) {
                $error='El teléfono debe contener exactamente 10 números.';
            } else {
                $dup=$pdo->prepare('SELECT id FROM usuarios WHERE LOWER(correo)=LOWER(?) AND id<>? LIMIT 1');
                $dup->execute([$correo,$uid]);
                if($dup->fetchColumn()) {
                    $error='Ese correo ya está registrado en otra cuenta.';
                } else {
                    $pdo->prepare('UPDATE usuarios SET nombre=?,apellido_paterno=?,apellido_materno=?,correo=?,telefono=? WHERE id=?')
                        ->execute([$nombre,$paterno,$materno,$correo,$telefono,$uid]);
                    $_SESSION['user']['first_name']=$nombre;
                    $_SESSION['flash_success']='Datos personales guardados correctamente.';
                    redirectPage('mi-cuenta');
                }
            }
        } else {
            $claveActual=(string)($_POST['actual']??'');
            $claveNueva=(string)($_POST['nueva']??'');
            $claveConfirmada=(string)($_POST['confirmar']??'');
            $q=$pdo->prepare('SELECT contrasena_hash FROM usuarios WHERE id=? LIMIT 1');
            $q->execute([$uid]);$hash=(string)$q->fetchColumn();
            if(!$hash||!password_verify($claveActual,$hash))$error='La contraseña actual es incorrecta.';
            elseif(!passwordValid($claveNueva))$error='La nueva contraseña requiere mínimo 9 caracteres, mayúscula, número y símbolo.';
            elseif($claveNueva!==$claveConfirmada)$error='Las nuevas contraseñas no coinciden.';
            elseif(password_verify($claveNueva,$hash))$error='Escribe una contraseña distinta de la anterior.';
            else {
                $pdo->prepare('UPDATE usuarios SET contrasena_hash=? WHERE id=?')->execute([password_hash($claveNueva,PASSWORD_DEFAULT),$uid]);
                session_regenerate_id(true);
                $_SESSION['flash_success']='Contraseña actualizada.';
                redirectPage('mi-cuenta');
            }
        }
    }


    // Configuración del dueño: se guardan datos personales y del negocio en la misma transacción.
    if ($action === 'save_settings') {
        ownerOnly();
        $page = 'settings';
        $first = trim($_POST['first_name'] ?? '');
        $last1 = trim($_POST['last_name1'] ?? '');
        $last2 = trim($_POST['last_name2'] ?? '');
        $storeName = trim($_POST['store_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $hours = trim($_POST['business_hours'] ?? '');
        $allowedCategories = ['Fonda', 'Tienda', 'Taller', 'Consultorio', 'Puesto en mercado', 'Central de abasto', 'Otro', 'Fondas', 'Tiendas', 'Talleres', 'Consultorios', 'Puestos en mercados', 'Centrales de abasto'];
        $removePhoto = !empty($_POST['remove_photo']);
        $photoFile = $_FILES['store_photo'] ?? null;
        $hasUpload = $photoFile && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $newPhoto = null;
        $previousPhoto = null;
        $photoPath = null;

        if (!$first || !$last1 || !$storeName || !$phone || !$email || !$category) {
            $error = 'Completa los datos obligatorios de tu perfil y tu local.';
        } elseif (!nombreValido($first) || !nombreValido($last1) || ($last2!=='' && !nombreValido($last2))) {
            $error = 'Nombre y apellidos deben contener únicamente letras y espacios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Escribe un correo electrónico válido.';
        } elseif (!preg_match('/^[0-9+()\s-]{10,24}$/', $phone)) {
            $error = 'El teléfono debe tener al menos 10 dígitos y usar solo números, espacios, + o guiones.';
        } elseif (!in_array($category, $allowedCategories, true)) {
            $error = 'Selecciona una categoría válida.';
        } elseif (strlen($first)>100 || strlen($last1)>100 || strlen($last2)>100 || strlen($storeName)>150 || strlen($category)>80 || strlen($email)>150 || strlen($hours)>160) {
            $error = 'Uno de los campos supera el máximo de caracteres permitido.';
        }

        if (!$error && $hasUpload) {
            if (($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
                !is_uploaded_file($photoFile['tmp_name']) ||
                $photoFile['size'] <= 0 || $photoFile['size'] > 3*1024*1024) {
                $error = 'La imagen no se pudo cargar. Usa una fotografía JPG, PNG o WEBP de hasta 3 MB.';
            } else {
                $imageInfo = @getimagesize($photoFile['tmp_name']);
                $mime = $imageInfo['mime'] ?? '';
                $types = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
                if (!isset($types[$mime]) || ($imageInfo[0] ?? 0) > 6000 || ($imageInfo[1] ?? 0) > 6000) {
                    $error = 'La fotografía no es válida. Selecciona JPG, PNG o WEBP de hasta 6000 píxeles por lado.';
                }
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();
                $q = $pdo->prepare('SELECT id,imagen_local AS photo FROM negocios WHERE id=? AND id_dueno=? LIMIT 1 FOR UPDATE');
                $q->execute([(int)me()['store_id'], (int)me()['id']]);
                $storeRecord = $q->fetch();
                if (!$storeRecord) throw new RuntimeException('No se encontró el negocio del dueño.');
                $previousPhoto = $storeRecord['photo'] ?? null;
                $photoPath = $removePhoto ? null : $previousPhoto;

                if ($hasUpload) {
                    $newPhoto = 'uploads/negocio_'.(int)me()['store_id'].'_'.bin2hex(random_bytes(12)).'.'.$types[$mime];
                    if (!move_uploaded_file($photoFile['tmp_name'], dirname(__DIR__,2).'/'.$newPhoto)) {
                        throw new RuntimeException('No se pudo guardar la fotografía en uploads.');
                    }
                    $photoPath = $newPhoto;
                }

                $pdo->prepare('UPDATE users SET first_name=?,last_name1=?,last_name2=?,email=?,phone=? WHERE id=? AND store_id=? AND role=\'dueño\'')
                    ->execute([$first,$last1,$last2,$email,$phone,(int)me()['id'],(int)me()['store_id']]);
                $pdo->prepare('UPDATE stores SET name=?,category=?,phone=?,email=?,business_hours=?,photo=? WHERE id=? AND owner_id=?')
                    ->execute([$storeName,$category,$phone,$email,$hours,$photoPath,(int)me()['store_id'],(int)me()['id']]);
                $pdo->commit();

                $_SESSION['user']['first_name'] = $first;
                $_SESSION['user']['store_name'] = $storeName;
                audit($pdo,'Configuración actualizada','El dueño actualizó los datos de su negocio y perfil.');
                if ($previousPhoto && $previousPhoto !== $photoPath && preg_match('#^uploads/negocio_[0-9]+_[a-f0-9]{24}\.(jpg|png|webp)$#', $previousPhoto)) {
                    @unlink(dirname(__DIR__,2).'/'.$previousPhoto);
                }
                $_SESSION['flash_success'] = 'Los datos del negocio se actualizaron correctamente.';
                redirectPage('settings');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($newPhoto && is_file(dirname(__DIR__,2).'/'.$newPhoto)) @unlink(dirname(__DIR__,2).'/'.$newPhoto);
                error_log('Vendia, configuracion: '.$e->getMessage());
                $error = 'No fue posible guardar los cambios. Verifica que hayas importado la actualización de la base de datos y que uploads tenga permisos de escritura.';
            }
        }
    }

    // Cambio de contraseña independiente: exige la contraseña actual y evita guardar contraseñas en texto plano.
    if ($action === 'change_password') {
        ownerOnly();
        $page = 'settings';
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $q = $pdo->prepare('SELECT password_hash FROM users WHERE id=? AND store_id=? AND role=\'dueño\' LIMIT 1');
        $q->execute([(int)me()['id'], (int)me()['store_id']]);
        $record = $q->fetch();
        if (!$record || !password_verify($current, $record['password_hash'])) {
            $error = 'La contraseña actual no es correcta.';
        } elseif (!passwordValid($new)) {
            $error = 'La nueva contraseña debe tener más de 8 caracteres, al menos una mayúscula, un número y un símbolo.';
        } elseif ($new !== $confirm) {
            $error = 'La confirmación de la contraseña no coincide.';
        } elseif (password_verify($new, $record['password_hash'])) {
            $error = 'La nueva contraseña debe ser distinta de la actual.';
        } else {
            try {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=? AND store_id=? AND role=\'dueño\'')
                    ->execute([password_hash($new,PASSWORD_DEFAULT),(int)me()['id'],(int)me()['store_id']]);
                session_regenerate_id(true);
                audit($pdo,'Cambio de contraseña','El dueño cambió la contraseña de su propia cuenta.');
                $_SESSION['flash_success'] = 'Contraseña cambiada. Utilízala la próxima vez que inicies sesión.';
                redirectPage('settings');
            } catch (Throwable $e) {
                error_log('Vendia, cambio de contraseña: '.$e->getMessage());
                $error = 'No se pudo cambiar la contraseña. Intenta de nuevo.';
            }
        }
    }

    if ($action === 'save_product') {
        ownerOnly();
        $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $cat=trim($_POST['category']??'');
        $desc=trim($_POST['description']??''); $price=(float)($_POST['price']??0); $cost=(float)($_POST['cost']??0);
        $stock=(int)($_POST['stock']??0); $min=(int)($_POST['min_stock']??5);
        $moneyOK=static fn($v)=>preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',(string)$v)===1;
        $countOK=static fn($v)=>preg_match('/^\d{1,9}$/D',(string)$v)===1;
        if (!$name || !$cat || !$moneyOK($_POST['price']??'') || !$moneyOK($_POST['cost']??'') ||
            !$countOK($_POST['stock']??'') || !$countOK($_POST['min_stock']??'') ||
            $price<0 || $cost<0 || $stock<0 || $min<0 || strlen($name)>150 || strlen($cat)>100)
            $error='Completa los campos y escribe cantidades y precios válidos (máximo 2 decimales).';
        else {
            $photo=null;
            if (isset($_FILES['photo']) && ($_FILES['photo']['error']??UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $file=$_FILES['photo'];
                if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3*1024*1024 || !is_uploaded_file($file['tmp_name'])) {
                    $error='No se pudo subir la imagen. Utiliza una fotografía de hasta 3 MB.';
                } else {
                    $mime=@getimagesize($file['tmp_name'])['mime']??'';
                    $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                    if (!isset($types[$mime])) $error='Formato no válido. Utiliza una imagen JPG, PNG o WEBP.';
                    else {
                        $photo='uploads/'.bin2hex(random_bytes(12)).'.'.$types[$mime];
                        if (!move_uploaded_file($file['tmp_name'],dirname(__DIR__,2).'/'.$photo)) {
                            $error='No fue posible guardar la foto. Verifica los permisos de la carpeta uploads.';
                        }
                    }
                }
            }
            if (!$error) {
                try {
                    if ($id) {
                        $sql='UPDATE products SET name=?,category=?,description=?,price=?,cost=?,stock=?,min_stock=?'.($photo?',photo=?':'').' WHERE id=? AND store_id=?';
                        $args=[$name,$cat,$desc,$price,$cost,$stock,$min]; if ($photo) $args[]=$photo;
                        $args[]=$id; $args[]=me()['store_id'];
                        $pdo->prepare($sql)->execute($args);
                    } else {
                        $pdo->prepare('INSERT INTO products(store_id,name,category,description,price,cost,stock,min_stock,photo) VALUES(?,?,?,?,?,?,?,?,?)')->execute([me()['store_id'],$name,$cat,$desc,$price,$cost,$stock,$min,$photo]);
                    }
                    audit($pdo,$id?'Edición de producto':'Alta de producto',$name);
                    $_SESSION['flash_success']=$id?'Producto actualizado correctamente.':'Producto guardado correctamente.';
                    redirectPage('products');
                } catch (Exception $e) {
                    $error='No se pudo guardar el producto. Revisa los datos e inténtalo de nuevo.';
                }
            }
        }
        $page='products';
    }

    if ($action === 'toggle_product') {
        ownerOnly(); $id=(int)$_POST['id'];
        $pdo->prepare('UPDATE products SET active=1-active WHERE id=? AND store_id=?')->execute([$id,me()['store_id']]);
        audit($pdo,'Cambio de estado de producto','Producto #'.$id); $_SESSION['flash_success']='Estado del producto actualizado.'; redirectPage('products');
    }

    if ($action === 'sale') {
        employeeOrOwner();
        $quantities=$_POST['qty']??[];
        if (!is_array($quantities)) $quantities=[];
        $method=$_POST['payment_method']??'efectivo';
        if(!in_array($method,['efectivo','transferencia'],true)) $method='efectivo';
        $promotionId=(int)($_POST['promotion_id']??0);
        $orderId=(int)($_POST['order_id']??0);
        $items=[]; $subtotal=0.0; $discountAmount=0.0; $total=0.0;
        try {
            $pdo->beginTransaction();
            // El pedido debe pertenecer al negocio, estar pendiente y no haberse cobrado.
            // FOR UPDATE evita que dos cajeros cobren simultáneamente el mismo pedido.
            if ($orderId > 0) {
                $oq=$pdo->prepare('SELECT o.id,o.customer_name,o.status,p.total_cotizado,p.subtotal_cotizado,p.descuento_cotizado,p.id_oferta_cotizada FROM orders o JOIN pedidos p ON p.id=o.id WHERE o.id=? AND o.store_id=? FOR UPDATE');
                $oq->execute([$orderId,(int)me()['store_id']]);
                $order=$oq->fetch();
                if (!$order) throw new RuntimeException('El pedido elegido no pertenece a este negocio.');
                if ($order['status']==='entregado') throw new RuntimeException('El pedido ya aparece entregado y no puede cobrarse otra vez.');
                $cq=$pdo->prepare('SELECT id FROM sales WHERE order_id=? AND store_id=? LIMIT 1');
                $cq->execute([$orderId,(int)me()['store_id']]);
                if ($cq->fetchColumn()) throw new RuntimeException('Ese pedido ya tiene un cobro registrado.');
                if($order['total_cotizado']!==null){
                    // El cliente recibió esta cotización: al cobrar respetamos cantidades y precios originales.
                    $qLineas=$pdo->prepare('SELECT id_producto,cantidad,precio_unitario FROM detalle_pedidos WHERE id_pedido=? ORDER BY id_producto');
                    $qLineas->execute([$orderId]);$lineasPedido=$qLineas->fetchAll();
                    if(!$lineasPedido)throw new RuntimeException('El pedido cotizado no tiene artículos para cobrar.');
                    $quantities=[];$preciosPedido=[];
                    foreach($lineasPedido as $linea){
                        $idP=(int)$linea['id_producto'];
                        $quantities[$idP]=(string)(int)$linea['cantidad'];
                        $preciosPedido[$idP]=(float)$linea['precio_unitario'];
                    }
                    $promotionId=0; // Ignorar promociones no pactadas por el cliente.
                }
            }
            foreach($quantities as $pid=>$rawQty) {
                // No permitir cantidades negativas, decimales o mayores que las existencias.
                if (!ctype_digit((string)$pid) || !ctype_digit((string)$rawQty)) throw new RuntimeException('La cantidad de productos no es válida.');
                $qty=(int)$rawQty;
                if($qty<=0) continue;
                $q=$pdo->prepare('SELECT id,name,price,stock FROM products WHERE id=? AND store_id=? AND active=1 FOR UPDATE');
                $q->execute([(int)$pid,(int)me()['store_id']]);
                $p=$q->fetch();
                if (!$p || $qty>(int)$p['stock']) throw new RuntimeException('Existencia insuficiente o producto no disponible: '.($p['name']??'producto').'.');
                if(isset($preciosPedido[(int)$pid]))$p['price']=$preciosPedido[(int)$pid];
                $items[]=[$p,$qty];
                $subtotal+=round((float)$p['price']*$qty,2);
            }
            if (!$items) throw new RuntimeException('Agrega al menos un producto.');
            $promo=null;
            if ($promotionId>0) {
                $q=$pdo->prepare('SELECT id,discount_percent,tipo_beneficio,unidades_llevas,unidades_pagas FROM promotions WHERE id=? AND store_id=? AND active=1 AND authorized=1 AND start_date<=CURDATE() AND end_date>=CURDATE() FOR UPDATE');
                $q->execute([$promotionId,(int)me()['store_id']]);
                $promo=$q->fetch();
                if (!$promo) throw new RuntimeException('La promoción seleccionada no está autorizada o ya no está vigente.');
                $membership=$pdo->prepare('SELECT pp.id_producto FROM promocion_productos pp JOIN productos p ON p.id=pp.id_producto WHERE pp.id_promocion=? AND p.id_negocio=? AND p.activo=1');
                $membership->execute([$promotionId,(int)me()['store_id']]);
                $eligible=array_fill_keys(array_map('intval',$membership->fetchAll(PDO::FETCH_COLUMN)),true);
                $eligibleItems=[];
                foreach ($items as [$item,$quantity]) {
                    if (isset($eligible[(int)$item['id']])) $eligibleItems[]=[(float)$item['price'],$quantity];
                }
                if (!$eligibleItems) throw new RuntimeException('La venta no contiene productos incluidos en esta promoción.');
                $discountAmount=descuentoOfertaCentavos($promo,$eligibleItems)/100;
            }
            $subtotal=round($subtotal,2);
            if($orderId>0 && $order['total_cotizado']!==null){
                // Comprobar la cotización almacenada antes de registrar una venta.
                if(abs($subtotal-(float)$order['subtotal_cotizado'])>0.011)throw new RuntimeException('Los precios del pedido no coinciden con su cotización. No se realizó el cobro.');
                $discountAmount=(float)$order['descuento_cotizado'];
                $promo=$order['id_oferta_cotizada']?['id'=>(int)$order['id_oferta_cotizada']]:null;
            }
            $total=round(max(0,$subtotal-$discountAmount),2);
            $pdo->prepare('INSERT INTO sales(store_id,user_id,subtotal,discount_amount,promotion_id,order_id,total,payment_method) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([(int)me()['store_id'],(int)me()['id'],$subtotal,$discountAmount,$promo['id']??null,$orderId>0?$orderId:null,$total,$method]);
            $saleId=(int)$pdo->lastInsertId();
            foreach($items as [$p,$qty]) {
                $pdo->prepare('INSERT INTO sale_items(sale_id,product_id,product_name,quantity,unit_price) VALUES(?,?,?,?,?)')
                    ->execute([$saleId,$p['id'],$p['name'],$qty,$p['price']]);
                $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=? AND store_id=?')
                    ->execute([$qty,$p['id'],(int)me()['store_id']]);
            }
            if ($orderId>0) {
                $pdo->prepare("UPDATE orders SET status='entregado' WHERE id=? AND store_id=?")
                    ->execute([$orderId,(int)me()['store_id']]);
            }
            $pdo->commit();
            if($orderId>0) avisarClienteDePedido($pdo,$orderId,'estado_pedido','Tu pedido fue entregado','El pedido #'.$orderId.' se cobró y se marcó como entregado.');
            audit($pdo,'Venta registrada','Venta #'.$saleId.($orderId>0?' del pedido #'.$orderId:' directa').' total $'.number_format($total,2).' descuento $'.number_format($discountAmount,2));
            $_SESSION['flash_success']='Venta #'.$saleId.($orderId>0?' del pedido #'.$orderId:'').' registrada por $'.number_format($total,2).'. El inventario se actualizó correctamente.';
            redirectPage(me()['role'] === 'dueño' ? 'orders#historial-pedidos' : 'orders#registrar-pedido');
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            error_log('Vendia venta: '.$e->getMessage());
            $error=$e instanceof RuntimeException?$e->getMessage():'No se pudo registrar la venta. Verifica que importaste la actualización SQL.';
        }
        $page='orders';
    }

    if ($action === 'new_order') {
        employeeOrOwner();
        $customer = trim($_POST['customer_name'] ?? '');
        $source = $_POST['source'] ?? 'mostrador';
        $purchaseLocation = trim($_POST['purchase_location'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $fechaEntrega = validarFechaEntrega(trim((string)($_POST['fecha_entrega_prevista']??'')));
        $clienteId=(int)($_POST['id_cliente']??0);
        $fotoPedido=null;
        if ($customer === '' || !nombreValido($customer) || !in_array($source, ['mostrador', 'llamada', 'whatsapp'], true)) {
            $error = 'Escribe el nombre del cliente usando solo letras y espacios, y selecciona el canal.';
        } elseif (trim((string)($_POST['fecha_entrega_prevista']??''))!=='' && !$fechaEntrega) {
            $error='La fecha programada debe ser válida.';
        } elseif ($purchaseLocation === '' || strlen($purchaseLocation) > 180) {
            $error = 'Especifica el lugar de compra (máximo 180 caracteres).';
        } else {
            try {
                if ($clienteId>0) {
                    $c=$pdo->prepare("SELECT id,nombre,apellido_paterno,apellido_materno FROM usuarios WHERE id=? AND id_negocio=? AND rol='cliente' AND activo=1");
                    $c->execute([$clienteId,(int)me()['store_id']]);
                    $cliente=$c->fetch();
                    if (!$cliente) throw new RuntimeException('El cliente seleccionado no pertenece a tu negocio.');
                    $customer=trim($cliente['nombre'].' '.$cliente['apellido_paterno'].' '.$cliente['apellido_materno']);
                }
                $fotoPedido=guardarFotoPedido($_FILES['foto_pedido']??null);
                $pdo->prepare('INSERT INTO pedidos(id_negocio,id_usuario,id_cliente,nombre_cliente,origen,lugar_compra,notas,fecha_entrega_prevista,foto_referencia) VALUES(?,?,?,?,?,?,?,?,?)')
                    ->execute([me()['store_id'], me()['id'], $clienteId>0?$clienteId:null,$customer, $source, $purchaseLocation, $notes, $fechaEntrega,$fotoPedido]);
                $pedidoId=(int)$pdo->lastInsertId();
                avisarNegocio($pdo,(int)me()['store_id'],'pedido','Pedido registrado','Se registró el pedido #'.$pedidoId.' de '.$customer.'.','index.php?p=orders',(int)me()['id']);
                if($clienteId>0)crearNotificacion($pdo,$clienteId,(int)me()['store_id'],'pedido','Nuevo pedido vinculado','El negocio registró tu pedido #'.$pedidoId.'.','index.php?p=cliente-inicio');
                audit($pdo, 'Pedido creado', $customer . ' | Lugar de compra: ' . $purchaseLocation);
                $_SESSION['flash_success'] = 'Pedido registrado correctamente.';
                redirectPage(me()['role'] === 'dueño' ? 'orders#historial-pedidos' : 'orders#registrar-pedido');
            } catch (Throwable $e) {
                if($fotoPedido && is_file(dirname(__DIR__,2).'/'.$fotoPedido))@unlink(dirname(__DIR__,2).'/'.$fotoPedido);
                error_log('Vendia pedidos: ' . $e->getMessage());
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo registrar el pedido. Comprueba la estructura de la base Vendia.';
            }
        }
        $page = 'orders';
    }

    if ($action === 'order_status') {
        employeeOrOwner();
        $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');
        if($id<1 || !in_array($status,['pendiente','preparacion','listo','entregado'],true)) { http_response_code(400);exit('Estado del pedido inválido.'); }
        $q=$pdo->prepare('SELECT id,estado,id_usuario,origen FROM pedidos WHERE id=? AND id_negocio=? LIMIT 1');
        $q->execute([$id,(int)me()['store_id']]);$pedido=$q->fetch();
        if(!$pedido) {http_response_code(403);exit('Pedido no encontrado en tu negocio.');}
        if(me()['role']==='empleado' && ((int)$pedido['id_usuario']!==(int)me()['id'] && $pedido['origen']!=='web' || $status==='entregado' || $pedido['estado']==='entregado')) {
            http_response_code(403);exit('Solo puedes preparar o marcar como listo un pedido abierto asignado o recibido por web.');
        }
        $cq=$pdo->prepare('SELECT id FROM sales WHERE order_id=? AND store_id=? LIMIT 1');
        $cq->execute([$id,(int)me()['store_id']]);
        if($cq->fetchColumn() && $status!=='entregado') {
            $_SESSION['flash_success']='El pedido ya fue cobrado y debe conservar el estado Entregado.';
        } else {
            $pdo->prepare('UPDATE pedidos SET estado=? WHERE id=? AND id_negocio=?')->execute([$status,$id,(int)me()['store_id']]);
            audit($pdo,'Cambio de estado de pedido','Pedido #'.$id.' -> '.$status);
            avisarClienteDePedido($pdo,$id,'estado_pedido','Actualización de tu pedido','El pedido #'.$id.' cambió al estado '.str_replace('_',' ',$status).'.');
            avisarNegocio($pdo,(int)me()['store_id'],'estado_pedido','Estado del pedido','Pedido #'.$id.' actualizado a '.$status.'.','index.php?p=orders',(int)me()['id']);
            $_SESSION['flash_success']='Estado del pedido actualizado.';
        }
        redirectPage(me()['role']==='dueño'?'orders#historial-pedidos':'orders#pedidos-operativos');
    }

    if ($action === 'employee_active') {
        ownerOnly(); $id=(int)$_POST['id']; $active=(int)$_POST['active'];
        $pdo->prepare("UPDATE users SET active=? WHERE id=? AND store_id=? AND role='empleado'")->execute([$active,$id,me()['store_id']]);
        audit($pdo,'Cambio de acceso de empleado','Empleado #'.$id.' activo='.$active); $_SESSION['flash_success']=$active?'Empleado activado. Ya puede iniciar sesión.':'Empleado desactivado.'; redirectPage('employees');
    }

    // Promociones: solo el dueño crea, edita, autoriza y cambia su estado.
    if ($action === 'save_promotion') {
        ownerOnly();
        $page='promotions';
        $id=(int)($_POST['id']??0);
        $name=trim((string)($_POST['name']??''));
        $discountRaw=(string)($_POST['discount']??'');
        $tipoOferta=(string)($_POST['tipo_beneficio']??'descuento');
        $ofertaElegida=(string)($_POST['paquete']??'');
        $llevas=null;$pagas=null;
        if ($tipoOferta==='promocion' && in_array($ofertaElegida,['2x1','3x2','4x3','3x1'],true)) {
            [$llevas,$pagas]=array_map('intval',explode('x',$ofertaElegida));
        }
        $startDate=trim((string)($_POST['start_date']??''));
        $endDate=trim((string)($_POST['end_date']??''));
        $validDate = static function($date) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return false;
            $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            return $d && $d->format('Y-m-d') === $date;
        };
        $discount=(float)$discountRaw;
        $promocionValida=$tipoOferta==='promocion' && $llevas!==null && $pagas!==null && $pagas<$llevas && in_array($llevas.'x'.$pagas,['2x1','3x2','4x3','3x1'],true);
        // Asociar exclusivamente productos activos que pertenecen al negocio autenticado.
        $rawProductIds=$_POST['product_ids']??[];
        $productIds=[];
        $invalidProducts=!is_array($rawProductIds);
        if (is_array($rawProductIds)) {
            foreach ($rawProductIds as $rawProductId) {
                if (!is_scalar($rawProductId) || !ctype_digit((string)$rawProductId) || (int)$rawProductId<1) {
                    $invalidProducts=true; break;
                }
                $productIds[(int)$rawProductId]=(int)$rawProductId;
            }
        }
        $productIds=array_values($productIds);
        if ($name==='' || (function_exists('mb_strlen') ? mb_strlen($name,'UTF-8') : strlen($name))>150) {
            $error='Escribe un nombre de promoción de hasta 150 caracteres.';
        } elseif (!in_array($tipoOferta,['descuento','promocion'],true)) {
            $error='Elige Descuento o Promoción.';
        } elseif ($tipoOferta==='descuento' && ($discountRaw==='' || !preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D',$discountRaw) || !is_finite($discount) || $discount<=0 || $discount>100)) {
            $error='El descuento debe ser mayor que 0 y no superar 100 %.';
        } elseif ($tipoOferta==='promocion' && !$promocionValida) {
            $error='Selecciona una promoción válida: 2x1, 3x2, 4x3 o 3x1.';
        } elseif (!$validDate($startDate) || !$validDate($endDate) || $endDate<$startDate) {
            $error='Selecciona fechas válidas; "Válida hasta" no puede ser anterior a "Válida desde".';
        } elseif ($invalidProducts || !$productIds) {
            $error='Selecciona por lo menos un producto del negocio para esta promoción.';
        } else {
            try {
                $pdo->beginTransaction();
                $marks=implode(',',array_fill(0,count($productIds),'?'));
                if ($tipoOferta==='promocion') $discount=0.0;
                $checkProducts=$pdo->prepare("SELECT id FROM products WHERE store_id=? AND active=1 AND id IN ($marks)");
                $checkProducts->execute(array_merge([(int)me()['store_id']],$productIds));
                if (count($checkProducts->fetchAll(PDO::FETCH_COLUMN))!==count($productIds)) {
                    throw new RuntimeException('Alguno de los productos seleccionados está inactivo o no pertenece a tu negocio.');
                }
                if ($id>0) {
                    // Revocar autorización al modificar: el dueño debe revisar y autorizar de nuevo.
                    $q=$pdo->prepare('UPDATE promotions SET name=?,discount_percent=?,tipo_beneficio=?,unidades_llevas=?,unidades_pagas=?,start_date=?,end_date=?,authorized=0 WHERE id=? AND store_id=?');
                    $q->execute([$name,$discount,$tipoOferta,$llevas,$pagas,$startDate,$endDate,$id,(int)me()['store_id']]);
                    if (!$q->rowCount()) {
                        $check=$pdo->prepare('SELECT id FROM promotions WHERE id=? AND store_id=?');
                        $check->execute([$id,(int)me()['store_id']]);
                        if (!$check->fetch()) throw new RuntimeException('No se encontró la promoción.');
                    }
                    $savedId=$id;
                    $_SESSION['flash_success']='Promoción actualizada. Autorízala nuevamente para que pueda utilizarse en caja.';
                } else {
                    $q=$pdo->prepare('INSERT INTO promotions(store_id,name,description,discount_percent,tipo_beneficio,unidades_llevas,unidades_pagas,start_date,end_date,authorized,active) VALUES(?,?,?,?,?,?,?,?,?,0,1)');
                    $q->execute([(int)me()['store_id'],$name,'',$discount,$tipoOferta,$llevas,$pagas,$startDate,$endDate]);
                    $savedId=(int)$pdo->lastInsertId();
                    $_SESSION['flash_success']='Promoción creada con '.count($productIds).' producto(s). Pulsa Autorizar para permitir su uso en caja.';
                }
                // Reemplazar la relación completa en la misma transacción; nunca mezclar locales.
                $pdo->prepare('DELETE FROM promocion_productos WHERE id_promocion=?')->execute([$savedId]);
                $attach=$pdo->prepare('INSERT INTO promocion_productos(id_promocion,id_producto) VALUES(?,?)');
                foreach ($productIds as $productId) $attach->execute([$savedId,$productId]);
                $pdo->commit();
                audit($pdo,$id>0?'Promoción editada':'Promoción creada',
                      'Promoción #'.$savedId.'; productos: '.implode(',',$productIds));
                redirectPage('promotions');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Vendia promociones, guardar: '.$e->getMessage());
                $error=$e instanceof RuntimeException?$e->getMessage():
                    'No fue posible guardar la promoción. Importa database/database.sql.';
            }
        }
    }

    if ($action === 'promotion_authorize') {
        ownerOnly();
        $id=(int)($_POST['id']??0);
        $authorize=(int)($_POST['authorize']??0)===1?1:0;
        $q=$pdo->prepare('SELECT id,name,start_date,end_date,active FROM promotions WHERE id=? AND store_id=?');
        $q->execute([$id,(int)me()['store_id']]);
        $promo=$q->fetch();
        if (!$promo) {
            $error='No se encontró la promoción de este negocio.';
        } elseif ($authorize && !$pdo->query('SELECT 1 FROM promocion_productos WHERE id_promocion='.(int)$id.' LIMIT 1')->fetchColumn()) {
            $error='Esta promoción no tiene productos. Pulsa Editar y selecciona al menos uno.';
        } else {
            $pdo->prepare('UPDATE promotions SET authorized=? WHERE id=? AND store_id=?')->execute([$authorize,$id,(int)me()['store_id']]);
            audit($pdo,$authorize?'Promoción autorizada':'Autorización retirada','Promoción #'.$id.' '.$promo['name']);
            $_SESSION['flash_success']=$authorize?'Promoción autorizada para su aplicación en caja.':'Autorización retirada.';
            redirectPage('promotions');
        }
        $page='promotions';
    }

    if ($action === 'promotion_active') {
        ownerOnly();
        $id=(int)($_POST['id']??0);
        $active=(int)($_POST['active']??0)===1?1:0;
        // Al desactivar, también se revoca la autorización.
        $q=$pdo->prepare('UPDATE promotions SET active=?,authorized=IF(?=0,0,authorized) WHERE id=? AND store_id=?');
        $q->execute([$active,$active,$id,(int)me()['store_id']]);
        audit($pdo,$active?'Promoción activada':'Promoción desactivada','Promoción #'.$id);
        $_SESSION['flash_success']=$active?'Promoción activada. Autorízala si deseas usarla en caja.':'Promoción desactivada y autorización retirada.';
        redirectPage('promotions');
    }

    if ($action === 'request_reset') {
        $value=trim($_POST['contact']??''); $method=$_POST['method']??'email';
        $q=$pdo->prepare('SELECT id,email,phone FROM users WHERE '.($method==='phone'?'phone=?':'email=?').' LIMIT 1');
        $q->execute([$value]); $u=$q->fetch();
        $success='Si los datos coinciden con una cuenta, se generará una solicitud de recuperación.';
        if($u) {
            $token=bin2hex(random_bytes(24)); $hash=password_hash($token,PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$u['id'],$hash]);
            // En desarrollo se muestra el enlace. En producción debe enviarse por SMTP o SMS y nunca mostrarse aquí.
            if (getenv('VENDIA_LOCAL_RESET_LINKS')==='1') {
                $_SESSION['dev_reset_link']='reset.php?token='.$token.'&uid='.$u['id'];
            } // En producción: conectar un proveedor SMTP/SMS verificado; no revelar token públicamente.
        }
        $page='forgot';
    }
    require __DIR__ . "/acciones_nuevas.php";
}

