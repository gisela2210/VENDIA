<?php if(logged() && me()['role']==='empleado'): ?>
<div class="notice">Tu acceso es operativo: puedes registrar pedidos, cobrar y revisar <a href="index.php?p=agenda">tu agenda</a>. El historial completo, los costos y las utilidades pertenecen al dueño.</div>
<?php endif; ?>
