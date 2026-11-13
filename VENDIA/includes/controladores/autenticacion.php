<?php
/** Controlador de accesos Vendia: datos nativos de la base Vendia, respuesta JSON si AJAX. */
$authActions=['register_owner','register_employee','register_client','login'];
if (in_array($action,$authActions,true)) {
    $fieldErrors=[];
    $oldInput=$_POST; // used only in server-generated HTML; passwords are never echoed into HTML.
    $authAjax=($_POST['ajax_form']??'')==='1';

    $failField=function(string $name,string $message) use (&$fieldErrors,&$error): void {
        $fieldErrors[$name]=$message;
        if($error==='')$error=$message;
    };
    $authFinish=function(?string $redirect=null,?string $successMessage=null) use (&$fieldErrors,&$error,$authAjax): void {
        if ($authAjax) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            if ($fieldErrors || $error) http_response_code(422);
            echo json_encode(['ok'=>empty($fieldErrors)&&$error==='',
                'errors'=>$fieldErrors,'message'=>$error?:$successMessage,
                'redirect'=>$redirect],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if (!$fieldErrors && $error==='' && $redirect) {
            header('Location: '.$redirect);exit;
        }
    };
    $validateCommon=function(bool $ageRequired=false) use (&$failField): void {
        $first=trim((string)($_POST['first_name']??''));
        $last1=trim((string)($_POST['last_name1']??''));
        $last2=trim((string)($_POST['last_name2']??''));
        $phone=trim((string)($_POST['phone']??''));
        $email=trim((string)($_POST['email']??''));
        $username=trim((string)($_POST['username']??''));
        $pass=(string)($_POST['password']??'');
        $confirm=(string)($_POST['confirm']??'');
        foreach (['first_name'=>$first,'last_name1'=>$last1,'last_name2'=>$last2] as $name=>$value) {
            if($name==='last_name2'&&$value==='')continue;
            if(!nombreValido($value))$failField($name,'Solo se permiten letras y espacios.');
        }
        if(!telefonoValido($phone))$failField('phone','Escribe exactamente 10 dígitos.');
        if(!correoPermitido($email))$failField('email','Utiliza un correo @gmail.com, @hotmail.com o @outlook.com.');
        if(!usuarioValido($username))$failField('username','Escribe de 4 a 40 letras, números, puntos o guiones bajos.');
        if(!passwordValid($pass))$failField('password','La contraseña debe tener mínimo 9 caracteres, una mayúscula, un número y un carácter especial.');
        if($pass!==$confirm)$failField('confirm','Las contraseñas no coinciden.');
        if(empty($_POST['acepto_terminos']))$failField('acepto_terminos','Debes aceptar los Términos y condiciones.');
        if(empty($_POST['acepto_privacidad']))$failField('acepto_privacidad','Debes confirmar que leíste el Aviso de privacidad.');
        if($ageRequired && !edadClienteValida($_POST['age']??null))$failField('age','Debes tener al menos 18 años (máximo 120).');
    };
    $checkUnique=function() use ($pdo,&$failField): void {
        $usuario=trim((string)($_POST['username']??''));
        $correo=trim((string)($_POST['email']??''));
        $q=$pdo->prepare('SELECT usuario,correo FROM usuarios WHERE usuario=? OR LOWER(correo)=LOWER(?) LIMIT 2');
        $q->execute([$usuario,$correo]);
        foreach($q->fetchAll() as $row){
            if(strcasecmp($row['usuario'],$usuario)===0)$failField('username','Ese nombre de usuario ya está registrado.');
            if(strcasecmp($row['correo'],$correo)===0)$failField('email','Ese correo electrónico ya se encuentra registrado.');
        }
    };

    if ($action==='login') {
        $page='login';
        $username=trim((string)($_POST['username']??''));
        $pass=(string)($_POST['password']??'');
        if($username==='')$failField('username','Escribe tu nombre de usuario o correo.');
        if($pass==='')$failField('password','Escribe tu contraseña.');
        if(!$fieldErrors){
            try {
                $q=$pdo->prepare("SELECT u.id,u.id_negocio AS store_id,u.rol AS role,u.nombre AS first_name,
                          u.usuario AS username,u.contrasena_hash AS password_hash,u.activo AS active,
                          n.nombre AS store_name,n.activo AS negocio_activo,n.estado_suscripcion
                    FROM usuarios u LEFT JOIN negocios n ON n.id=u.id_negocio
                    WHERE u.usuario=? OR (u.correo=? AND u.rol='superadministrador') LIMIT 1");
                $q->execute([$username,$username]);$u=$q->fetch();
                if(!$u || $u['role']!==$loginRole || !password_verify($pass,$u['password_hash'])) {
                    $failField('username','Usuario, contraseña o tipo de cuenta incorrectos.');
                } elseif((int)$u['active']!==1) {
                    $failField('username',$u['role']==='empleado'?'Tu cuenta está pendiente de activación por el dueño.':'La cuenta está desactivada. Contacta con soporte.');
                } elseif(!in_array($u['role'],['superadministrador','cliente'],true) && (empty($u['store_id']) || (int)$u['negocio_activo']!==1 || $u['estado_suscripcion']==='suspendida')) {
                    $failField('username','Este negocio no tiene el acceso habilitado. Contacta al dueño o soporte.');
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user']=['id'=>(int)$u['id'],'store_id'=>(int)($u['store_id']??0),
                        'role'=>$u['role'],'first_name'=>$u['first_name'],'username'=>$u['username'],'store_name'=>$u['store_name']];
                    try {audit($pdo,'Inicio de sesión','Acceso a Vendia');}catch(Throwable $e){error_log('Vendia auditoría acceso: '.$e->getMessage());}
                    $redirect='index.php?p='.($u['role']==='superadministrador'?'superadmin':($u['role']==='cliente'?'cliente-inicio':'home'));
                    $authFinish($redirect);
                }
            } catch(Throwable $e) {
                error_log('Vendia error en login: '.$e->getMessage());
                $failField('username','No se pudo consultar Vendia. Verifica que importaste database/database.sql y que MySQL esté funcionando.');
            }
        }
        $authFinish();
    }
    if ($action==='register_owner') {
        $page='register-owner';
        $validateCommon();
        $store=trim((string)($_POST['store_name']??''));
        $category=trim((string)($_POST['category']??''));
        if((function_exists('mb_strlen')?mb_strlen($store,'UTF-8'):strlen($store))>150 || $store==='')$failField('store_name','Escribe el nombre del negocio (máximo 150 caracteres).');
        if(!in_array($category,['Fondas','Tiendas','Talleres','Consultorios','Puestos en mercados','Centrales de abasto','Otro'],true))$failField('category','Selecciona una categoría válida.');
        if(!$fieldErrors){
            try {
                $checkUnique();
                if(!$fieldErrors){
                    $pdo->beginTransaction();
                    $q=$pdo->prepare("INSERT INTO usuarios(rol,nombre,apellido_paterno,apellido_materno,usuario,correo,telefono,contrasena_hash,activo,acepto_terminos_en,aviso_privacidad_visto_en) VALUES('dueño',?,?,?,?,?,?,?,1,NOW(),NOW())");
                    $q->execute([trim($_POST['first_name']),trim($_POST['last_name1']),trim($_POST['last_name2']??''),trim($_POST['username']),trim($_POST['email']),trim($_POST['phone']),password_hash($_POST['password'],PASSWORD_DEFAULT)]);
                    $uid=(int)$pdo->lastInsertId();
                    $q=$pdo->prepare('INSERT INTO negocios(id_dueno,nombre,categoria,telefono,correo) VALUES(?,?,?,?,?)');
                    $q->execute([$uid,$store,$category,trim($_POST['phone']),trim($_POST['email'])]);
                    $sid=(int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE usuarios SET id_negocio=? WHERE id=?')->execute([$sid,$uid]);
                    $pdo->commit();
                    // Bitácora secundaria: si falta la tabla de eventos, no deshacer
                    // un negocio y una cuenta que ya se crearon correctamente.
                    try {
                        $pdo->prepare("INSERT INTO eventos_negocio(id_negocio,id_admin,accion,descripcion) VALUES(?,?,'alta',?)")->execute([$sid,$uid,'Registro inicial de negocio']);
                    } catch(Throwable $e){error_log('Vendia evento alta negocio: '.$e->getMessage());}
                    session_regenerate_id(true);
                    $_SESSION['user']=['id'=>$uid,'store_id'=>$sid,'role'=>'dueño','first_name'=>trim($_POST['first_name']), 'username'=>trim($_POST['username']),'store_name'=>$store];
                    $authFinish('index.php?p=home');
                }
            }catch(PDOException $e){
                if($pdo->inTransaction())$pdo->rollBack();
                error_log('Vendia alta dueño: '.$e->getMessage());
                if((int)($e->errorInfo[1]??0)===1062)$failField('username','Ese nombre de usuario ya existe. Elige otro.');
                else $error='No se pudo registrar el negocio. Comprueba la estructura de Vendia con scripts/reparar_registro.php.';
            }catch(Throwable $e){
                if($pdo->inTransaction())$pdo->rollBack();
                error_log('Vendia alta dueño: '.$e->getMessage());
                $error='No se pudo completar el registro. Revisa el diagnóstico de Vendia.';
            }
        }
        $authFinish();
    }
    if ($action==='register_employee' || $action==='register_client') {
        $isClient=$action==='register_client';
        $page=$isClient?'client-register':'employee-register';
        $validateCommon($isClient);
        // Un cliente NO está ligado a un negocio. Jamás enviar FALSE, 0 o '' a la FK.
        $sid=$isClient?null:filter_var($_POST['store_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $age=filter_var($_POST['age']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>$isClient?18:16,'max_range'=>$isClient?120:100]]);
        if($age===false)$failField('age',$isClient?'Debes tener entre 18 y 120 años.':'Escribe una edad válida entre 16 y 100 años.');
        if(!$isClient && $sid===false)$failField('store_id','Selecciona un negocio válido.');
        $medical=trim((string)($_POST['medical_notes']??''));
        if(!$isClient && (function_exists('mb_strlen')?mb_strlen($medical,'UTF-8'):strlen($medical))>1000)$failField('medical_notes','Máximo 1,000 caracteres.');
        if(!$isClient && $medical!=='' && empty($_POST['acepto_datos_salud']))$failField('acepto_datos_salud','Para guardar datos médicos debes autorizar expresamente su tratamiento. También puedes dejar vacío ese apartado.');
        if(!$fieldErrors){
            try {
                if(!$isClient) {
                    $q=$pdo->prepare("SELECT id FROM negocios WHERE id=? AND activo=1 AND estado_suscripcion<>'suspendida'");
                    $q->execute([(int)$sid]);
                    if(!$q->fetchColumn())$failField('store_id','Este negocio no existe o no está disponible.');
                }
                if(!$fieldErrors)$checkUnique();
                if(!$fieldErrors){
                    $nombre=trim((string)$_POST['first_name']);
                    $paterno=trim((string)$_POST['last_name1']);
                    $materno=trim((string)($_POST['last_name2']??''));
                    $usuario=trim((string)$_POST['username']);
                    $correo=trim((string)$_POST['email']);
                    $telefono=trim((string)$_POST['phone']);
                    $hash=password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
                    $pdo->beginTransaction();
                    if($isClient){
                        // No incluir id_negocio: la BD usa su valor NULL por defecto.
                        // Así no se viola fk_usuarios_negocio al crear clientes multinegocio.
                        $q=$pdo->prepare("INSERT INTO usuarios
                            (rol,nombre,apellido_paterno,apellido_materno,usuario,correo,telefono,contrasena_hash,edad,activo,acepto_terminos_en,aviso_privacidad_visto_en)
                            VALUES('cliente',:nombre,:paterno,:materno,:usuario,:correo,:telefono,:hash,:edad,1,NOW(),NOW())");
                    }else{
                        $q=$pdo->prepare("INSERT INTO usuarios
                            (id_negocio,rol,nombre,apellido_paterno,apellido_materno,usuario,correo,telefono,contrasena_hash,edad,antecedentes_medicos,activo,acepto_terminos_en,aviso_privacidad_visto_en,consentimiento_salud_en)
                            VALUES(:negocio,'empleado',:nombre,:paterno,:materno,:usuario,:correo,:telefono,:hash,:edad,:medicos,0,NOW(),NOW(),:consentimiento)");
                        $q->bindValue(':negocio',(int)$sid,PDO::PARAM_INT);
                        $q->bindValue(':medicos',$medical!==''?$medical:null,$medical!==''?PDO::PARAM_STR:PDO::PARAM_NULL);
                        $q->bindValue(':consentimiento',$medical!==''?date('Y-m-d H:i:s'):null,$medical!==''?PDO::PARAM_STR:PDO::PARAM_NULL);
                    }
                    foreach([':nombre'=>$nombre,':paterno'=>$paterno,':materno'=>$materno,
                             ':usuario'=>$usuario,':correo'=>$correo,':telefono'=>$telefono,':hash'=>$hash] as $param=>$value){
                        $q->bindValue($param,$value,PDO::PARAM_STR);
                    }
                    $q->bindValue(':edad',(int)$age,PDO::PARAM_INT);
                    $q->execute();
                    $nuevoUsuario=(int)$pdo->lastInsertId();
                    $pdo->commit();
                    // Los avisos son secundarios: un fallo de notificación NUNCA debe
                    // hacer creer al usuario que su cuenta no quedó registrada.
                    try {
                        if($isClient){
                            crearNotificacion($pdo,$nuevoUsuario,null,'general','Bienvenido a Vendia','Tu cuenta fue creada. Ya puedes comprar en cualquier negocio activo.','index.php?p=cliente-pedidos');
                            avisarAdministradores($pdo,'general','Nuevo cliente registrado','Un cliente nuevo se registró en Vendia: '.$nombre.'.','index.php?p=superadmin-usuarios');
                        } else {
                            avisarNegocio($pdo,(int)$sid,'general','Nuevo empleado por autorizar','La solicitud de '.$nombre.' '.$paterno.' está pendiente de activación.','index.php?p=employees');
                        }
                    } catch(Throwable $e){error_log('Vendia: notificación posterior al registro (cuenta creada): '.$e->getMessage());}
                    if($isClient){
                        $_SESSION['flash_success']='Cuenta creada. Ya puedes iniciar sesión.';
                        $authFinish('index.php?p=login&role=cliente','Cuenta creada correctamente.');
                    } else {
                        $success='Solicitud de registro guardada. El dueño debe activar tu cuenta antes de iniciar sesión.';
                        $authFinish(null,$success);
                    }
                }
            } catch(PDOException $e) {
                if($pdo->inTransaction())$pdo->rollBack();
                error_log('Vendia alta de '.($isClient?'cliente':'empleado').': SQLSTATE '.$e->getCode().' / '.$e->getMessage());
                $mysqlCode=(int)($e->errorInfo[1]??0);
                if($mysqlCode===1062){
                    $failField('username','El usuario o correo ya existe. Utiliza uno diferente.');
                }elseif(in_array($mysqlCode,[1054,1146,1364,1048],true)){
                    $error='La estructura de Vendia necesita actualizarse. Ejecuta scripts/reparar_registro.php y aplica la reparación indicada.';
                }elseif($mysqlCode===1452){
                    $error='La base rechazó la relación con el negocio. Ejecuta el diagnóstico de registros y comprueba la estructura de Vendia.';
                }else{
                    $error='No se pudo guardar la cuenta en Vendia. Revisa el diagnóstico de registros en XAMPP.';
                }
            } catch(Throwable $e) {
                if($pdo->inTransaction())$pdo->rollBack();
                error_log('Vendia alta de '.($isClient?'cliente':'empleado').': '.$e->getMessage());
                $error='No se pudo completar el registro. Comprueba la conexión de Vendia y el diagnóstico.';
            }
        }
        $authFinish();
    }
}
