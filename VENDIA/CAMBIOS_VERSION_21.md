# Vendia V21 — Envío a WhatsApp reparado

## ¿Por qué no funcionaba?

El botón **Preparar mensajes** quedaba deshabilitado cuando ningún descuento o promoción estaba seleccionado, incluso si había clientes autorizados. Por eso parecía que no permitía enviar.

## Cambios

- La primera promoción vigente queda seleccionada por defecto.
- Al marcar uno o varios destinatarios autorizados, se habilita **Abrir WhatsApp para enviar**. El clic abre el primer chat con el texto ya preparado.
- Si hay varios seleccionados, el sistema muestra enlaces **Abrir WhatsApp** y **WhatsApp Web** para cada uno.
- También se puede abrir un chat directamente desde cada fila del listado.
- Se indica claramente por qué está bloqueada la acción cuando falta una oferta o un cliente autorizado.
- Se mantienen la protección de datos y la autorización de cada cliente por negocio.

## Limitación importante

Este mecanismo utiliza `wa.me` y WhatsApp Web. **No envía automáticamente** ni marca mensajes como entregados. Después de abrir cada chat, el dueño debe pulsar **Enviar** desde WhatsApp. Para enviar automáticamente una campaña a varios clientes se requiere WhatsApp Business Platform (Cloud API), credenciales, plantillas de marketing aprobadas y consentimiento verificable.

## Instalación en XAMPP

1. Respalda tu carpeta `VENDIA`, las imágenes `uploads` y la base de datos.
2. Sustituye los archivos del proyecto por los de este ZIP.
3. Entra a `http://localhost/VENDIA/`, pulsa `Ctrl+F5` e inicia sesión como dueño.
4. Ve a **Envío de promociones**, verifica que exista una oferta activa y selecciona clientes autorizados.
5. Pulsa **Abrir WhatsApp para enviar** y confirma cada envío dentro de WhatsApp.

**No hay cambios de base de datos ni necesidad de reimportar `database.sql`.** Se conserva la base `Vendia` y todos sus registros.
