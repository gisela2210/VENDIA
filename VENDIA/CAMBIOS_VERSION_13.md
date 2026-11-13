# Vendia v13 — Resumen de cambios

- **Venti** aparece como personaje transparente, ampliado y con globo de saludo. Solo se muestra a dueño y cliente.
- **Dueño**: se retiraron Notificaciones y Ayuda del menú izquierdo. Las notificaciones se abren desde la campana superior y la ayuda desde el menú de su nombre, opción «Ayuda y soporte».
- **Mi agenda / Empleado**: muestra promociones del negocio activas, autorizadas y vigentes dentro del calendario; al final aparecen como avisos azules con porcentaje, productos y fecha de caducidad. Reprogramado usa color naranja (si vuelve a vencer sin entrega pasa a rojo).
- **Promociones / Dueño**: «Seleccionar productos» abre la ventana; «Terminar selección» únicamente cierra la ventana; después aparece el botón «Crear promoción» o «Guardar cambios» para grabar en la base.
- **Sesiones**: se corrigió la mezcla de actores de diversas pestañas de un mismo navegador. Vendia aísla la autenticación y CSRF de cada pestaña mediante un identificador aleatorio, también contemplando la duplicación de pestañas cuando el navegador permite BroadcastChannel. Las solicitudes que no incluyan dicho identificador se rechazan.
- No se ha modificado la estructura de datos. La base sigue llamándose `Vendia`, con un solo `database/database.sql`. **No vuelvas a importarlo** al actualizar una instalación existente.

## Actualización segura
1. Respalda `C:\xampp\htdocs\VENDIA`, la carpeta `uploads` y la base de datos Vendia (desde phpMyAdmin).
2. Reemplaza los archivos por la carpeta `VENDIA` de este ZIP; conserva los archivos que subiste a `uploads`.
3. Abre `http://localhost/VENDIA/` y actualiza con Ctrl+F5. Inicia sesión nuevamente en cada pestaña.
4. Abre el dueño en una pestaña y el empleado o cliente en otra: deberían mantener su propio perfil.
5. Autoriza una promoción vigente desde el dueño y actualiza la agenda del empleado: debe aparecer en azul, además de poder usarse en caja.

## Límites de prueba
Se ha comprobado la sintaxis PHP/JavaScript y el aislamiento de contextos con una sesión de prueba en un servidor PHP local. El entorno de desarrollo no tiene el controlador `pdo_mysql`; la conexión real a MariaDB y las operaciones con productos y ventas deben verificarse en tu instalación de XAMPP.
