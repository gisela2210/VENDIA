<?php
if(logged() && in_array(me()['role'],['dueño','cliente'],true)):
require_once dirname(__DIR__).'/agente_preguntas.php';
$agentQuestions=preguntasVenti(me()['role']);
$agentStores=[];
if(me()['role']==='cliente') {
    $agentStores=$pdo->query("SELECT id,nombre FROM negocios WHERE activo=1 AND estado_suscripcion<>'suspendida' ORDER BY nombre")->fetchAll();
}
?>
<div class="vendia-assistant-widget" id="vendiaAgent">
 <button type="button" class="vendia-agent-toggle" id="vendiaAgentToggle" aria-controls="vendiaAgentPanel" aria-expanded="false" aria-label="Abrir conversación con Venti"><img src="assets/img/venti.png" alt="Venti"></button><span class="venti-saludo" id="ventiSaludo" aria-hidden="true">¡Hola! Soy Venti. ¿Te ayudo?</span>
 <section class="vendia-agent-panel" id="vendiaAgentPanel" hidden aria-label="Agente Venti">
  <div class="vendia-agent-header"><img src="assets/img/venti.png" alt="Mascota Venti"><div><strong>Venti</strong><small><?=me()['role']==='dueño'?'Ventas, inventario y pedidos':'Productos y promociones del negocio'?></small></div><button type="button" id="vendiaAgentClose" aria-label="Cerrar Venti">×</button></div>
  <div class="vendia-agent-thread" id="vendiaAgentThread" aria-live="polite"><p class="agent-message"><?=me()['role']==='dueño'?'¡Hola! Soy Venti. Consulta tus ventas, productos, pedidos y promociones registrados.':'¡Hola! Soy Venti. Elige un negocio y consulta su catálogo y sus promociones disponibles.'?></p></div>
  <form id="vendiaAgentForm" data-csrf="<?=h(csrf())?>">
   <?php if(me()['role']==='cliente'): ?><label class="venti-field-label" for="vendiaAgentStore">Negocio que quieres consultar</label><select name="id_negocio" id="vendiaAgentStore" required><option value="">Selecciona un negocio</option><?php foreach($agentStores as $n): ?><option value="<?=(int)$n['id']?>"><?=h($n['nombre'])?></option><?php endforeach; ?></select><?php endif; ?>
   <label class="venti-field-label" for="vendiaAgentPreset">Preguntas frecuentes</label>
   <select id="vendiaAgentPreset"><option value="">Elige una pregunta para consultar</option><?php foreach($agentQuestions as $id=>$text): ?><option value="<?=h($id)?>"><?=h($text)?></option><?php endforeach; ?></select>
   <input type="hidden" id="vendiaAgentIntent" value="">
   <div class="venti-input-row"><input id="vendiaAgentQuestion" maxlength="300" required placeholder="Escribe o elige una pregunta" aria-label="Tu pregunta"><button class="btn primary" type="submit">Consultar</button></div>
  </form>
  <a class="vendia-agent-help" href="index.php?p=ayuda">Enviar mensaje a una persona de soporte</a>
 </section>
</div>
<?php endif; ?>
