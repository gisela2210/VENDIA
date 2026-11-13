<?php
/** Notificaciones privadas. Cada aviso tiene un destinatario concreto. */
function crearNotificacion(PDO $pdo,int $usuarioId,?int $negocioId,string $tipo,string $titulo,string $mensaje,string $enlace='index.php?p=notificaciones'): void {
    if ($usuarioId<=0) return;
    // No se permite registrar enlaces arbitrarios externos.
    if (!preg_match('/^index\.php\?p=[a-z0-9_-]+(?:[&#][a-zA-Z0-9_=&%-]+)*$/D',$enlace)) $enlace='index.php?p=notificaciones';
    try {
        $pdo->prepare('INSERT INTO notificaciones(id_usuario,id_negocio,tipo,titulo,mensaje,enlace) VALUES(?,?,?,?,?,?)')
            ->execute([$usuarioId,$negocioId,$tipo,(function_exists('mb_substr')?mb_substr($titulo,0,150,'UTF-8'):substr($titulo,0,150)),(function_exists('mb_substr')?mb_substr($mensaje,0,500,'UTF-8'):substr($mensaje,0,500)),$enlace]);
    } catch(Throwable $e) { error_log('Vendia, notificación: '.$e->getMessage()); }
}
function avisarNegocio(PDO $pdo,int $negocioId,string $tipo,string $titulo,string $mensaje,string $enlace='index.php?p=notificaciones',int $excluirId=0): void {
    $q=$pdo->prepare("SELECT id FROM usuarios WHERE id_negocio=? AND rol IN ('dueño','empleado') AND activo=1 AND id<>?");
    $q->execute([$negocioId,$excluirId]);
    foreach($q as $u) crearNotificacion($pdo,(int)$u['id'],$negocioId,$tipo,$titulo,$mensaje,$enlace);
}
function avisarAdministradores(PDO $pdo,string $tipo,string $titulo,string $mensaje,string $enlace='index.php?p=notificaciones'): void {
    $q=$pdo->query("SELECT id FROM usuarios WHERE rol='superadministrador' AND activo=1");
    foreach($q as $u) crearNotificacion($pdo,(int)$u['id'],null,$tipo,$titulo,$mensaje,$enlace);
}
function avisarClienteDePedido(PDO $pdo,int $pedidoId,string $tipo,string $titulo,string $mensaje): void {
    $q=$pdo->prepare('SELECT id_cliente,id_negocio FROM pedidos WHERE id=?');$q->execute([$pedidoId]);$p=$q->fetch();
    if($p && $p['id_cliente']) crearNotificacion($pdo,(int)$p['id_cliente'],(int)$p['id_negocio'],$tipo,$titulo,$mensaje,'index.php?p=cliente-inicio');
}
function contarNotificaciones(PDO $pdo,int $usuarioId): int {
    try{$q=$pdo->prepare('SELECT COUNT(*) FROM notificaciones WHERE id_usuario=? AND leida=0');$q->execute([$usuarioId]);return (int)$q->fetchColumn();}
    catch(Throwable $e){error_log('Vendia notificaciones: '.$e->getMessage());return 0;}
}
