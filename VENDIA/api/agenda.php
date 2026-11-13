<?php
/** API de solo lectura. Nunca expone pedidos ajenos a la sesión. */
require dirname(__DIR__).'/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(!logged()){http_response_code(401);echo json_encode(['error'=>'No autorizado']);exit;}
$mes=(string)($_GET['mes']??date('Y-m'));
if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$mes)) {http_response_code(400);echo json_encode(['error'=>'Mes inválido']);exit;}
$primero=DateTimeImmutable::createFromFormat('!Y-m-d',$mes.'-01');
$fin=$primero->modify('+1 month');
$sql='SELECT p.id,p.nombre_cliente,p.estado,p.fecha_entrega_prevista FROM pedidos p WHERE p.fecha_entrega_prevista>=? AND p.fecha_entrega_prevista<?';
$args=[$primero->format('Y-m-d'),$fin->format('Y-m-d')];
if(!in_array(me()['role'],['superadministrador','cliente'],true)){$sql.=' AND p.id_negocio=?';$args[]=(int)me()['store_id'];}
if(me()['role']==='empleado'){$sql.=" AND (p.id_usuario=? OR p.origen='web') AND p.estado<>'entregado'";$args[]=(int)me()['id'];}
if(me()['role']==='cliente'){$sql.=' AND p.id_cliente=?';$args[]=(int)me()['id'];}
$sql.=' ORDER BY p.fecha_entrega_prevista LIMIT 500';
$q=$pdo->prepare($sql);$q->execute($args);
echo json_encode(['mes'=>$mes,'pedidos'=>$q->fetchAll()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
