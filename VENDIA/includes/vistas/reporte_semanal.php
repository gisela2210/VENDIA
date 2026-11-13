<?php
// Este módulo se muestra SOLO en Reportes y análisis (rol dueño).
$lunesInforme = lunesVendia();
$informeSemanal = null;
$historialSemanal = [];
$mensajeResumenError = '';
try {
    // Respaldo: los lunes se genera una sola vez aunque la tarea programada no corra.
    if ($lunesInforme->format('N') === '1') generarResumenVendia($pdo, (int)$storeId);
    $informeSemanal = resumenSemanalExistente($pdo, (int)$storeId, $lunesInforme);
    $rqSemanal = $pdo->prepare('SELECT fecha_lunes,periodo_inicio,periodo_fin,ventas_cantidad,ingresos
                               FROM resumenes_semanales WHERE id_negocio=? AND fecha_lunes<?
                               ORDER BY fecha_lunes DESC LIMIT 8');
    $rqSemanal->execute([(int)$storeId, $lunesInforme->format('Y-m-d')]);
    $historialSemanal = $rqSemanal->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Vendia · resumen en reportes: '.$e->getMessage());
    $mensajeResumenError = 'No se pudo cargar el informe semanal. Comprueba el archivo database.sql de Vendia.';
}
?>
<section class="panel weekly-summary-panel" id="resumen-semanal" aria-label="Reporte general de cada lunes">
  <div class="panel-head weekly-summary-heading">
    <div>
      <p class="eyebrow">CADA LUNES · REPORTE GENERAL DESCARGABLE</p>
      <h2>Resumen general semanal</h2>
      <p class="muted">Se genera cada <strong>lunes</strong>, con los resultados de la semana anterior (lunes a domingo) y una recomendación concreta.</p>
    </div>
    <span class="weekly-week-label">Lunes <?=h($lunesInforme->format('d/m/Y'))?></span>
  </div>
  <?php if ($informeSemanal): ?>
    <div class="weekly-metrics">
      <div><small>Ventas completadas</small><strong><?=number_format((int)$informeSemanal['ventas_cantidad'])?></strong></div>
      <div><small>Ingresos de la semana</small><strong>$<?=number_format((float)$informeSemanal['ingresos'],2)?></strong></div>
      <div><small>Pedidos pendientes al generar</small><strong><?=number_format((int)$informeSemanal['pedidos_pendientes'])?></strong></div>
    </div>
    <p class="weekly-period">Semana analizada: del <?=h(date('d/m/Y',strtotime($informeSemanal['periodo_inicio'])))?> al <?=h(date('d/m/Y',strtotime($informeSemanal['periodo_fin'])))?> · Informe generado el <strong>lunes <?=h($lunesInforme->format('d/m/Y'))?></strong>.</p>
    <div class="weekly-summary-message" id="textoResumenSemanal"><?=h($informeSemanal['mensaje'])?></div>
    <div class="weekly-recommendation"><strong>Recomendación de la semana</strong><p><?=h($informeSemanal['recomendacion'])?></p></div>
    <div class="weekly-summary-actions">
      <a class="btn primary" href="index.php?p=reports&amp;descargar_resumen=pdf&amp;lunes=<?=h($lunesInforme->format('Y-m-d'))?>">↓ Descargar reporte PDF</a>
      <button class="btn" type="button" id="copiarResumenSemanal" data-summary="<?=h($informeSemanal['mensaje'])?>">Copiar mensaje</button>
      <a class="btn" href="index.php?p=notificaciones">Ver notificación</a>
    </div>
  <?php else: ?>
    <p class="muted"><?=h($mensajeResumenError ?: 'Todavía no se ha generado el informe del lunes '. $lunesInforme->format('d/m/Y') .'. La tarea automática lo genera los lunes; también puedes prepararlo manualmente para la demostración.')?></p>
    <?php if (!$mensajeResumenError): ?>
    <form method="post" class="weekly-summary-actions">
      <input type="hidden" name="csrf" value="<?=h(csrf())?>">
      <input type="hidden" name="action" value="resumen_semanal_demo">
      <button type="submit" class="btn primary">Generar reporte para la demo</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($historialSemanal): ?>
  <div class="weekly-history">
    <h3>Reportes de lunes anteriores</h3>
    <div class="table-scroll"><table>
      <thead><tr><th>Lunes de emisión</th><th>Semana analizada</th><th>Ventas</th><th>Ingresos</th><th>Archivo</th></tr></thead>
      <tbody>
      <?php foreach($historialSemanal as $r): ?>
        <tr>
          <td>Lunes <?=h(date('d/m/Y',strtotime($r['fecha_lunes'])))?></td>
          <td><?=h(date('d/m/Y',strtotime($r['periodo_inicio'])))?> – <?=h(date('d/m/Y',strtotime($r['periodo_fin'])))?></td>
          <td><?=number_format((int)$r['ventas_cantidad'])?></td>
          <td>$<?=number_format((float)$r['ingresos'],2)?></td>
          <td><a class="btn small" href="index.php?p=reports&amp;descargar_resumen=pdf&amp;lunes=<?=h($r['fecha_lunes'])?>">↓ PDF</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>
</section>
