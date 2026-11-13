<?php
// Descarga del informe general semanal. Autorización y aislamiento por negocio,
// antes de cualquier HTML para evitar corrupción del PDF.
if (logged() && me()['role'] === 'dueño' && $page === 'reports' && ($_GET['descargar_resumen'] ?? '') === 'pdf') {
    $fecha = (string)($_GET['lunes'] ?? '');
    $lunesValido = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, new DateTimeZone('America/Mexico_City')) : false;
    if (!$lunesValido || $lunesValido->format('Y-m-d') !== $fecha || $lunesValido->format('N') !== '1') {
        http_response_code(400);
        exit('Fecha de lunes inválida.');
    }
    $idNegocio = (int)me()['store_id'];
    $resumen = resumenSemanalExistente($pdo, $idNegocio, $lunesValido);
    if (!$resumen) { http_response_code(404); exit('No se encontró un reporte para este lunes.'); }
    $nombreNegocio = $pdo->prepare('SELECT nombre FROM negocios WHERE id=? LIMIT 1');
    $nombreNegocio->execute([$idNegocio]);
    require __DIR__.'/reporte_semanal_pdf.php';
    $pdf = vendiaGenerarPdfResumen($resumen, (string)($nombreNegocio->fetchColumn() ?: 'Mi negocio'));
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Vendia_Reporte_Lunes_'.$fecha.'.pdf"');
    header('Content-Length: '.strlen($pdf));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}
// Descargar reportes antes de emitir HTML para que los encabezados CSV funcionen.
if (logged() && me()['role'] === 'dueño' && $page === 'reports' && isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vendia_ventas.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Venta','Empleado','Subtotal','Descuento','Total','Método de pago','Fecha'], ',', '"', '');
    $csvDays = isset($_GET['period']) && (string)$_GET['period'] === '14' ? 14 : 7;
    $csvStart = (new DateTimeImmutable('today'))->modify('-'.($csvDays-1).' days')->format('Y-m-d H:i:s');
    $csvEnd = (new DateTimeImmutable('tomorrow'))->format('Y-m-d H:i:s');
    $q = $pdo->prepare("SELECT s.id,u.first_name,COALESCE(s.subtotal,s.total) AS subtotal,s.discount_amount,s.total,s.payment_method,s.created_at FROM sales s JOIN users u ON u.id=s.user_id WHERE s.store_id=? AND s.status='completada' AND s.created_at>=? AND s.created_at<? ORDER BY s.created_at DESC");
    $q->execute([(int)me()['store_id'],$csvStart,$csvEnd]);
    foreach ($q as $r) fputcsv($out, array_values($r), ',', '"', '');
    fclose($out);
    exit;
}

$storeId = logged() ? (int)me()['store_id'] : 0;
$businessBadge = null;
if ($storeId) {
    try {
        $bq = $pdo->prepare('SELECT s.*,u.first_name,u.last_name1,u.last_name2 FROM stores s JOIN users u ON u.id=? WHERE s.id=? LIMIT 1');
        $bq->execute([(int)me()['id'], $storeId]);
        $businessBadge = $bq->fetch();
    } catch (PDOException $e) {
        error_log('Vendia, etiqueta de negocio: '.$e->getMessage());
    }
}
$businessLogo = $businessBadge['photo'] ?? '';
$businessHasLogo = $businessLogo && preg_match('#^uploads/[a-zA-Z0-9_.-]+$#', $businessLogo) && is_file(dirname(__DIR__).'/'.$businessLogo);
function countQuery($pdo,$sql,$args=[]) { $q=$pdo->prepare($sql);$q->execute($args);return $q->fetchColumn(); }
