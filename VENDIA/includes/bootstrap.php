<?php
session_start();
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
// Las pestañas del mismo navegador comparten cookie PHP. Aislar los actores por
// un contexto aleatorio de pestaña evita suplantaciones accidentales entre pestañas.
$vendiaContext = (string)($_POST['vendia_ctx'] ?? $_GET['vendia_ctx'] ?? $_SERVER['HTTP_X_VENDIA_CONTEXT'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/D', $vendiaContext)) $vendiaContext = '';
if ($vendiaContext !== '') {
    $vendiaFields = ['user','csrf','flash_success','dev_reset_link','enlace_admin_reset'];
    $vendiaSaved = $_SESSION['vendia_contextos'][$vendiaContext] ?? [];
    foreach ($vendiaFields as $f) {
        unset($_SESSION[$f]);
        if (array_key_exists($f, $vendiaSaved)) $_SESSION[$f] = $vendiaSaved[$f];
    }
    register_shutdown_function(static function () use ($vendiaContext, $vendiaFields): void {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        $data = [];
        foreach ($vendiaFields as $f) {
            if (array_key_exists($f, $_SESSION)) $data[$f] = $_SESSION[$f];
            unset($_SESSION[$f]);
        }
        $_SESSION['vendia_contextos'][$vendiaContext] = $data;
    });
    // Mantener el contexto a través de redirects hechos por PHP.
    header_register_callback(static function () use ($vendiaContext): void {
        foreach (headers_list() as $line) {
            if (!preg_match('~^Location:\s*(index\.php(?:[^\r\n]*)?)$~i', $line, $hit)) continue;
            $url = $hit[1];
            if (strpos($url, 'vendia_ctx=') !== false) continue;
            $parts = explode('#', $url, 2);
            $url = $parts[0] . (strpos($parts[0], '?')===false?'?':'&') . 'vendia_ctx=' . $vendiaContext;
            if (isset($parts[1])) $url .= '#'.$parts[1];
            header('Location: '.$url, true);
        }
    });
} else {
    // Nunca emplear la última cuenta autenticada de otra pestaña.
    foreach (['user','csrf','flash_success','dev_reset_link','enlace_admin_reset'] as $f) unset($_SESSION[$f]);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        http_response_code(409);
        exit('No se identificó la pestaña. Vuelve a cargar Vendia y repite la acción.');
    }
}
date_default_timezone_set('America/Mexico_City');
require dirname(__DIR__) . '/config/conexion.php';
require_once __DIR__.'/ofertas.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Iconografía vectorial local: sustituye pictogramas emoji en Configuración.
function icono($nombre, $clase='') {
    $formas = [
        'tienda' => '<path d="M3 10V6l2-3h14l2 3v4"/><path d="M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 13v8h14v-8"/><path d="M9 21v-6h6v6"/>',
        'candado' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 15v2"/>',
        'camara' => '<path d="M3 7h4l2-3h6l2 3h4v13H3z"/><circle cx="12" cy="13" r="4"/>',
        'guardar' => '<path d="M5 3h12l4 4v14H3V3z"/><path d="M7 3v7h10V3"/><path d="M7 21v-8h10v8"/>',
        'informacion' => '<circle cx="12" cy="12" r="10"/><path d="M12 11v6M12 7h.01"/>',
        'ajustes' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3" fill="currentColor" stroke="white"/><circle cx="16" cy="17" r="3" fill="currentColor" stroke="white"/>',
        'calendario' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'etiqueta' => '<path d="M20 13 13 20a2 2 0 0 1-2.83 0L3 12.83V4a1 1 0 0 1 1-1h8.83L20 10.17a2 2 0 0 1 0 2.83z"/><circle cx="8" cy="8" r="1"/>',
        'inicio' => '<path d="m3 10 9-7 9 7"/><path d="M5 9v12h14V9"/><path d="M9 21v-8h6v8"/>',
        'productos' => '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="M3 7v10l9 5 9-5V7M12 12v10"/>',
        'pedidos' => '<circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M2 3h2l2.4 12h13.4l2-9H5"/>',
        'campana' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'ayuda' => '<circle cx="12" cy="12" r="10"/><path d="M9.2 9a3 3 0 0 1 5.6 1.5c0 2-2.8 2.3-2.8 4.5M12 18h.01"/>',
        'usuarios' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3H3"/><path d="M17 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5v1"/>',
        'reporte' => '<path d="M4 21V11M10 21V3M16 21v-9M22 21V7"/>',
        'auditoria' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
        'tarjeta' => '<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20"/>',
        'porcentaje' => '<path d="m5 19 14-14"/><circle cx="6" cy="6" r="3"/><circle cx="18" cy="18" r="3"/>',
        'salir' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M13 3h7v18h-7"/>',
        'chat' => '<path d="M21 11a8 8 0 0 1-8 8H8l-5 3V11a8 8 0 1 1 18 0Z"/><path d="M8 11h8M8 14h5"/>',
        'verificar' => '<path d="M4 12l5 5L20 6"/><path d="M12 2a10 10 0 1 1-10 10"/>',
        'clientes' => '<circle cx="12" cy="7" r="4"/><path d="M4 21v-3a8 8 0 0 1 16 0v3"/>',
        'estadisticas' => '<path d="M3 3v18h18M7 16l4-4 3 2 5-7"/>',

    ];
    if (!isset($formas[$nombre])) return '';
    return '<svg class="ui-icon '.h($clase).'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$formas[$nombre].'</svg>';
}

function logged() { return !empty($_SESSION['user']); }
function me() { return $_SESSION['user'] ?? null; }
function ownerOnly() {
    if (!logged() || me()['role'] !== 'dueño') { http_response_code(403); exit('Acceso exclusivo del dueño.'); }
}
function employeeOrOwner() {
    if (!logged() || !in_array(me()['role'],['dueño','empleado'],true)) { http_response_code(403); exit('Acceso no autorizado.'); }
}
function adminOnly() {
    if (!logged() || me()['role'] !== 'superadministrador') { http_response_code(403); exit('Acceso exclusivo del superadministrador.'); }
}
function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verifyCsrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { exit('Solicitud no válida. Recarga la página.'); }
}
function audit($pdo, $action, $details='') {
    if (!logged() || empty(me()['store_id'])) return;
    $q=$pdo->prepare('INSERT INTO bitacora(id_negocio,id_usuario,accion,detalles) VALUES(?,?,?,?)');
    $q->execute([me()['store_id'],me()['id'],$action,$details]);
}
function passwordValid($p) {
    return strlen($p) >= 9 && preg_match('/[A-Z]/',$p) && preg_match('/[0-9]/',$p) && preg_match('/[^A-Za-z0-9]/',$p);
}
function redirectPage($p) { header('Location: index.php?p='.$p); exit; }
require __DIR__ . '/validaciones.php';
require __DIR__ . '/notificaciones.php';
require __DIR__ . '/resumen_semanal.php';
require __DIR__ . '/imagenes.php';

$error = ''; $success = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);
$page = $_GET['p'] ?? (logged() ? (me()['role']==='superadministrador'?'superadmin':(me()['role']==='cliente'?'cliente-inicio':'home')) : 'landing');
// Compatibilidad con accesos directos antiguos al punto de venta.
if ($page === 'pos') { header('Location: index.php?p=orders#cobrar-pedido'); exit; }
// El módulo de asistente antiguo se retiró; Venti solo se muestra como ventana flotante.
if (logged() && me()['role']==='dueño' && $page==='mi-cuenta') {header('Location: index.php?p=settings');exit;}
if ($page === 'assistant') {
    $destino = !logged() ? 'landing' : (me()['role'] === 'superadministrador' ? 'superadmin' : (me()['role'] === 'cliente' ? 'cliente-inicio' : 'home'));
    header('Location: index.php?p='.$destino, true, 302);
    exit;
}
$loginRole = $_POST['login_role'] ?? $_GET['role'] ?? 'dueño';
if (!in_array($loginRole, ['dueño', 'empleado', 'cliente', 'superadministrador'], true)) $loginRole = 'dueño';
if (isset($_GET['status']) && $_GET['status'] === 'desactivada') {
    $error = 'Tu cuenta fue desactivada. Contacta al dueño para recuperar el acceso.';
}


if (isset($_GET['logout'])) { unset($_SESSION['user'],$_SESSION['csrf']); header('Location: index.php?p=landing'); exit; }

// No conservar una sesión activa si el dueño desactivó la cuenta posteriormente.
if (logged()) {
    $checkAccount=$pdo->prepare('SELECT u.id,u.activo AS active,u.id_negocio AS store_id,u.rol AS role,n.activo AS negocio_activo,n.estado_suscripcion AS estado_suscripcion FROM usuarios u LEFT JOIN negocios n ON n.id=u.id_negocio WHERE u.id=? LIMIT 1');
    $checkAccount->execute([me()['id']]);
    $currentAccount=$checkAccount->fetch();
    if (!$currentAccount || (int)$currentAccount['active'] !== 1 ||
        (int)$currentAccount['store_id'] !== (int)me()['store_id'] ||
        $currentAccount['role'] !== me()['role'] ||
        (!in_array($currentAccount['role'],['superadministrador','cliente'],true) &&
        ((int)($currentAccount['negocio_activo']??0)!==1 || ($currentAccount['estado_suscripcion']??'')==='suspendida'))) {
        unset($_SESSION['user']);
        header('Location: index.php?p=login&status=desactivada');
        exit;
    }
}



// Un superadministrador nunca usa los módulos privados de un negocio por URL directa.
if (logged() && me()['role']==='superadministrador' && !in_array($page,['superadmin','superadmin-usuarios','superadmin-pagos','notificaciones','agenda','ayuda','mi-cuenta'],true)) {
    header('Location: index.php?p=superadmin'); exit;
}

// Cliente solo puede consultar sus pedidos, agenda, ayuda y su sesión.
if (logged() && me()['role']==='cliente' && !in_array($page,['cliente-inicio','cliente-pedidos','notificaciones','agenda','ayuda','mi-cuenta'],true)) {
    header('Location: index.php?p=cliente-inicio'); exit;
}
