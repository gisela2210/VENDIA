# Vendia V16 — Envío de promociones

## Nueva pantalla para dueño

En **Envío de promociones** puedes:

- Consultar compradores **identificados en ventas completadas** y vinculadas a pedidos con cuenta de cliente. Un cliente aparece una sola vez, sin importar cuántas compras haya realizado.
- Ver qué personas autorizaron recibir publicidad del negocio por WhatsApp.
- Seleccionar personas una por una o usar **Seleccionar todos los autorizados**.
- Elegir entre descuentos y promociones activos, vigentes y autorizados, con sus productos participantes.
- Preparar mensajes personalizados y abrir los chats individuales de WhatsApp. **No se envían mensajes automáticamente.**

Las ventas anónimas de mostrador no se pueden asociar a una cuenta y, por tanto, no permiten identificar destinatario. Las cuentas sin permiso expreso aparecen deshabilitadas para publicidad. No se inventan consentimientos de los clientes antiguos.

## Permisos y seguridad

- El permiso de publicidad es **por negocio**, no global. La persona puede concederlo o revocarlo en **Mi configuración → Publicidad por WhatsApp**.
- También puede concederlo voluntariamente en el registro de cliente para el negocio seleccionado.
- La consulta de compradores y la consulta de promociones siempre filtran por el negocio del dueño autenticado.
- WhatsApp `wa.me` abre un solo destinatario cada vez. Para envíos automáticos masivos se requiere WhatsApp Business Platform, plantillas aprobadas y consentimiento pertinente. El módulo nunca afirma que los mensajes se enviaron cuando solo se abrió el enlace.

## Promociones permitidas

2x1, 3x2, 4x3 y 3x1, además de descuentos porcentuales. El servidor valida esas cuatro opciones. Las ofertas antiguas conservan sus datos; al editarlas puede ser necesario cambiar a una de estas cuatro.

## Actualización de XAMPP

1. Exporta un respaldo completo de `Vendia` desde phpMyAdmin y conserva la carpeta `uploads`.
2. Sustituye los archivos del proyecto con los de este ZIP, sin borrar fotografías.
3. Importa el **único** archivo `database/database.sql` sobre tu base existente; crea **solo** la nueva tabla `consentimientos_whatsapp` si todavía no existe. No borra usuarios ni ventas. Nunca elimines tu base de datos para instalar esta versión.
4. Presiona `Ctrl + F5` y, desde una sesión de Cliente, autoriza publicidad para el negocio correspondiente. Compra de prueba: un pedido debe tener una venta completada asociada para aparecer en la lista de compradores.
5. Desde Dueño → Envío de promociones, selecciona una oferta activa y los compradores que autorizaron publicidad.

**Limitación de la consulta de compradores:** solo identifica compras que tengan una venta `completada` asociada a `pedidos.id_cliente`. Las ventas directas sin cuenta no identifican personas, y por privacidad no deben asociarse por conjeturas.
