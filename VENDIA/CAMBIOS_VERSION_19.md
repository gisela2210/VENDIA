# VENDIA V19: Correcciones de precios, clientes y WhatsApp

## Cambios
- El registro Cliente ya no exige ni muestra el local: `usuarios.id_negocio` queda NULL.
- Las altas generan notificación de bienvenida al cliente y un aviso al superadministrador.
- El cliente elige negocio al hacer pedido, puede autorizar ahí la publicidad WhatsApp de ese negocio sin que sea requisito para comprar.
- **El precio del pedido incluye automáticamente la oferta vigente de mayor ahorro** entre las ofertas aplicables; solo se aplica una (no acumulables). Se guarda en `pedidos` subtotal, descuento, total e ID de oferta. El empleado carga y cobra el pedido con los precios de esa cotización, aunque el producto cambie de precio después o la oferta expire. El inventario siempre se valida antes de confirmar. Las ventas directas siguen recalculando y verificando la oferta vigente en el servidor.
- El historial del cliente muestra su importe cotizado y el ahorro real.
- En Envío de promociones aparecen los clientes que hicieron pedidos **aunque aún no hayan pagado**; una columna separa pedidos de compras cobradas. Solo clientes que expresamente autorizaron el WhatsApp de ese negocio pueden recibir mensajes.
- Hay enlaces de WhatsApp y WhatsApp Web, preparados para apertura individual: el dueño debe confirmar cada envío en la conversación. No existe envío masivo automático sin API oficial.
- Reportes y análisis incluye una recomendación del sistema basada en existencias bajas, pedidos abiertos o tendencias de ventas.

## Instalar sin borrar datos
1. Exporta un respaldo de la base `Vendia` desde phpMyAdmin y haz una copia de `uploads`.
2. Cierra las sesiones antiguas y reemplaza la carpeta del proyecto `C:\xampp\htdocs\VENDIA` con la del ZIP. Conserva tus archivos de `uploads`.
3. Importa **únicamente** `database/database.sql` sobre la base **Vendia** existente. El archivo incluye cambios de columnas condicionales; no elimina las cuentas o ventas. No importes archivos antiguos.
4. Actualiza `http://localhost/VENDIA/` con Ctrl+F5; inicia sesión en cada pestaña.
5. Prueba: crear cliente sin local, hacer pedido de un producto con 20% de descuento, comprobar cotización y posterior cobro con mismo total, autorizar WhatsApp para ese negocio y ver al cliente en Envío de promociones. Si el cliente no autoriza, aparece en la lista pero no puede ser destinatario.

## Notas
- Los **pedidos anteriores** sin cotización guardada siguen mostrando su cálculo histórico y se cobran mediante el flujo anterior.
- Los mensajes externos no se envían por sí solos. Requieren una cuenta activa de WhatsApp Web o una aplicación compatible.
- El sistema puede mostrar el importe esperado del pedido; no implica un pago recibido hasta que se registre la venta.
- Antes de producción, configurar HTTPS, privacidad de datos, control de envíos de WhatsApp y pruebas transaccionales en MariaDB.
