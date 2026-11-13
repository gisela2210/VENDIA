**Corrección V20 del registro de clientes/empleados:** consulta `CAMBIOS_VERSION_20.md` antes de reinstalar. No es necesario borrar o recrear la base.

> **V17 — Reto final:** resumen cada lunes en una sola notificación con recomendación basada en las ventas reales; Venti solo muestra su saludo al pasar el cursor. **Esta versión sí añade una tabla:** importa `database/database.sql` con respaldo previo. Configura la tarea semanal con `scripts/resumen_cada_lunes.php`; consulta `CAMBIOS_VERSION_17.md`.

> **V16:** incluye nueva sección Envío de promociones para dueños, con selección de compradores, autorización por negocio, enlaces individuales de WhatsApp y las modalidades 2x1, 3x2, 4x3 y 3x1. Consulta `CAMBIOS_VERSION_16.md`. Antes de actualizar, respalda `Vendia` e importa el único `database/database.sql` para agregar `consentimientos_whatsapp` sin eliminar registros.

# VENDIA — paneles con iconos y Venti (sin asistente independiente)

**Stack:** PHP 8.1+, MariaDB/MySQL de XAMPP, HTML, CSS y JavaScript. Utiliza **una sola base de datos llamada `Vendia`** y **un solo SQL consolidado**, `database/database.sql`.

## Actualización de una instalación existente (sin perder cuentas)

1. **Primero respalda:** en phpMyAdmin exporta la base `Vendia` en SQL y conserva una copia de la carpeta `VENDIA/uploads` (contiene fotos de productos y negocios).
2. Descomprime el ZIP en `C:\xampp\htdocs\VENDIA`, sustituyendo los archivos de la versión anterior, **pero mantén tus fotos de `uploads`**. No copies el directorio de pruebas de tu computadora anterior.
3. Inicia Apache y MySQL desde el panel de XAMPP. Entra a `http://localhost/phpmyadmin` y comprueba que exista `Vendia`.
4. **Esta actualización es visual**: si tu Vendia ya funciona con la base actual, **no importes ningún SQL ni vuelvas a cargar las cuentas de prueba**. El archivo `database/database.sql` se conserva solo para instalaciones nuevas. Si vienes de una versión anterior a los módulos de agenda y notificaciones, revisa primero el respaldo y las instrucciones de esa migración.
5. Desde CMD comprueba la instalación (si tu XAMPP está en otra unidad, cambia la ruta):

   ```bat
   cd C:\xampp\htdocs\VENDIA
   C:\xampp\php\php.exe scripts\diagnosticar_modulos.php
   ```

6. Abre `http://localhost/VENDIA/` y actualiza con **Ctrl + F5**. Si el proyecto no encuentra MariaDB, revisa `config/conexion.php` y el puerto del servidor.

**No borres ni recrees tu base de datos.** El ZIP no puede acceder directamente a los usuarios que tienes guardados en el XAMPP de tu computadora, por lo que primero debes hacer el respaldo. El SQL no contiene instrucciones `DROP DATABASE` ni `DROP TABLE`.

## Funciones nuevas y permisos

| Función | Dueño | Empleado | Cliente | Superadministrador |
|---|---|---|---|---|
| Ver mi agenda | Pedidos del negocio | Pedidos propios y web del negocio | Sus pedidos en cualquier negocio | Pedidos globales |
| Reprogramar pedidos abiertos | Sí | Los propios y web del negocio | No | No |
| Hacer pedidos en diferentes negocios | No (captura en su local) | No (captura en su local) | Sí | No |
| Subir foto al registrar pedido | Sí | Sí | Sí | No |
| Historial completo del negocio | Sí | No | Solo sus pedidos | No |
| Preparar pedidos web | Sí | Sí, solo abiertos | No | No |
| Ver notificaciones privadas | Sí | Sí | Sí | Sí |
| Responder solicitudes de ayuda | De su negocio | No | No | Todas |
| Agente flotante | Sí | No | Sí | No |
| Usuarios y pagos globales | No | No | No | Sí |

### Mi agenda

El estado visual del calendario se calcula **con la fecha real de entrega**:
- **Rojo:** fecha ya vencida y pedido no entregado.
- **Amarillo:** pedido pendiente con fecha futura; tras reprogramarlo se muestra como *Reprogramado* en el nuevo día.
- **Verde:** pedido marcado como entregado.

No se permite reprogramar a una fecha pasada ni modificar pedidos ya entregados. Se guarda la fecha de modificación y se envía una notificación al cliente cuando está vinculado al pedido.

### Pedidos web

El Cliente abre **Hacer pedido**, elige entre los negocios activos, indica cantidades, anexa opcionalmente una foto JPG/PNG/WEBP de máximo 3 MB y envía la solicitud. El pedido queda registrado en ese negocio con detalle de artículos y **sin pago automático**. El empleado de ese negocio recibe una notificación y puede marcarlo como *En preparación* o *Listo*. El cliente recibe aviso por los cambios.

No se descuenta inventario cuando se solicita el pedido: se valida disponibilidad al crearlo, pero el stock solo baja al cobrar en caja. Los precios mostrados al cliente son estimados y se registra una copia del precio vigente en el detalle del pedido. La tienda debe confirmar su entrega y el importe final.

### Notificaciones

Cada aviso se vincula a un **ID de usuario**, no se comparte entre negocios. La campana de la parte superior muestra cuántas hay sin leer; el menú incluye **Notificaciones** para cada actor. Los avisos se generan al registrar pedidos, prepararlos, marcarlos como listos o entregados, reprogramar, enviar mensajes de ayuda y revisar pagos. Para ver avisos nuevos se debe recargar la pantalla: todavía no hay notificaciones push en tiempo real.

### Superadministrador

El menú tiene secciones **Negocios y estadísticas**, **Usuarios (dueños, empleados, clientes)**, **Verificación manual de pagos** y **Notificaciones**. La verificación de pagos es manual; no existe integración con banco o pasarela.

### Agente flotante

Aparece en la esquina inferior derecha **solo para dueño y cliente**, con el logotipo Vendia que compartiste. Responde consultas guiadas de pedidos pendientes, ayuda, tiendas y notificaciones con información autorizada del usuario. **No utiliza una IA generativa externa** ni ofrece pagos o verificaciones bancarias. Para atención humana, enlaza al módulo **Ayuda**.

## Pruebas y seguridad

Se comprobó sintaxis de los archivos PHP y JavaScript y renderizado aislado de las pantallas principales con datos simulados. **No es una prueba de integración con tu XAMPP:** después de instalar comprueba con dos negocios y una cuenta de cada rol que los pedidos, el calendario, las fotos y las notificaciones se guardan en MariaDB. Para producción se requieren HTTPS, respaldos automáticos, revisión legal del aviso de privacidad y pruebas adicionales de permisos y concurrencia de stock.


## Ajustes de interfaz — Vendia 11

- Eliminado el panel independiente **Asistente de apoyo** de Dueño y Empleado. Los demás perfiles tampoco muestran dicho panel.
- Se retiró la ruta de dicho panel; los enlaces antiguos a `?p=assistant` redirigen al inicio del perfil.
- Menús de Dueño, Empleado, Cliente y Superadministrador incluyen iconos SVG integrados en PHP; no dependen de internet.
- Se incorporó la mascota **Venti** proporcionada por el usuario en `assets/img/venti.png`; el agente flotante se muestra solo en Dueño y Cliente.
- La imagen de la tienda original no se altera ni se genera una nueva: una **V** se superpone mediante HTML/CSS a la antigua C de la bolsa en la pantalla de bienvenida.
- No se cambian las tablas ni las credenciales. **No es necesario volver a importar `database.sql`** al actualizar solamente estos archivos.

**Actualización recomendada:** respalda el proyecto y `uploads`, reemplaza los archivos y abre `http://localhost/VENDIA/` con Ctrl+F5. No borres ni reinicies la base `Vendia`.


## Cambios Vendia 13: seguridad entre pestañas y promociones

- Cada pestaña crea un identificador aleatorio en `sessionStorage` y envía ese identificador al servidor en enlaces, formularios y consultas de Venti. PHP guarda autenticación y CSRF por pestaña dentro de una misma sesión de navegador. Así, Cliente, Empleado y Dueño no se pisan al abrir varias pestañas. Se detectan también pestañas duplicadas con `BroadcastChannel` y se asigna otro contexto. Si el navegador no admite esta función, es preferible abrir otra pestaña nueva o usar perfiles distintos de navegador.
- Si el navegador bloquea JavaScript o `sessionStorage`, el sistema no aceptará operaciones POST sin contexto; es una medida intencional de seguridad.
- El enlace **Ayuda y soporte** del dueño se encuentra ahora en el menú de su nombre, arriba a la derecha. Notificaciones siguen en la campana superior.
- Mi agenda de empleado incluye promociones autorizadas y vigentes en azul, y reprogramaciones pendientes en naranja. No hay que cambiar tablas ni volver a importar la base.
- **Seleccionar productos** abre la ventana de selección, **Terminar selección** cierra esa ventana y **Crear promoción** aparece abajo para confirmar el guardado real.
- Venti es el personaje sin recuadro de color, con saludo en burbuja y accesos solo en Dueño y Cliente.
- **Al actualizar:** conserva las carpetas `uploads/` y la base `Vendia`. Sustituye los archivos del proyecto y presiona Ctrl+F5. Los usuarios necesitarán iniciar sesión de nuevo en cada pestaña porque ahora cada pestaña tiene un contexto propio.


## Vendia 14 — Promociones o descuentos

**Actualización de una instalación ya existente (conservar usuarios y ventas):**

1. Exporta un respaldo de `Vendia` en phpMyAdmin y conserva `uploads/`.
2. Sustituye los archivos PHP/CSS/JS por los del ZIP actualizado.
3. Selecciona la base `Vendia` en phpMyAdmin e importa **solamente** `database/database.sql` del ZIP. No cambies el nombre de la base ni la elimines. El SQL comprueba si las nuevas columnas existen antes de agregarlas, y no borra usuarios ni ventas. **No combines con SQL antiguos ni importes archivos de otras versiones.**
4. Haz `Ctrl+F5` y entra como dueño a *Promociones o descuentos*.
5. Una oferta anterior conserva su porcentaje. Las nuevas pueden ser descuento de 1–100 % o una modalidad 2x1, 3x2, …, 10x9. Al editar una oferta, debe volver a autorizarse.

**Regla de caja:** se aplica una oferta a los productos elegidos de un mismo negocio y con vigencia activa y autorización. En las promociones Nx(N−1) se toma cada grupo completo de N unidades elegibles, se cobran las N−1 unidades de mayor precio y la restante se descuenta. Las unidades sobrantes que no completan grupo se cobran a precio normal. No se combinan dos ofertas en una misma venta. El precio mostrado en el formulario es un ejemplo para N unidades del mismo producto; la caja recalcula el total de una mezcla real. El servidor valida nuevamente la vigencia, las cantidades, el stock y el descuento y no confía en el importe mostrado por el navegador.

**Navegación y accesibilidad:** al autorizar/activar se guarda el desplazamiento de la página en esa pestaña y se restaura tras la redirección de PHP. Las fechas pueden abrir su calendario con un toque en todo el campo en los navegadores que admiten `showPicker()`. Venti mantiene el tamaño de la versión 13 y añade un borde circular exterior, sin encoger la mascota.

> Validación de desarrollo: la sintaxis PHP y JavaScript y los cálculos unitarios se pueden comprobar localmente. La conexión y actualización SQL deben confirmarse en tu XAMPP, pues esta entrega no tiene acceso a tu MariaDB.


## V18: descarga del reporte general cada lunes
El resumen de lunes de la semana anterior se consulta en **Dueño → Reportes y análisis**. Allí se descarga como PDF y se consultan los lunes anteriores. Ya no aparece en Inicio. El trabajo automático de `scripts/resumen_cada_lunes.php` no cambia, la base `Vendia` sigue siendo una sola y no debes volver a importar el SQL por este cambio. Más detalles en `CAMBIOS_VERSION_18.md`.
