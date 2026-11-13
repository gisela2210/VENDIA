# Vendia 15 — Mejoras para Cliente y Superadministrador

- Inicio Cliente: productos solicitados, fecha/hora, fecha prevista, importe estimado y cobro registrado si existe. El importe sin venta confirmada se identifica como estimación, no deuda definitiva.
- Hacer pedido: secciones naranja de descuentos y promociones vigentes y autorizadas, filtradas por negocio; el importe online es orientativo y las promociones se aplican al cobrar.
- Se elimina la foto de referencia del formulario exclusivo de Cliente; sigue disponible en el pedido capturado por dueño o empleado.
- Se ocultan Configuración en menú lateral del dueño, y Ayuda/Notificaciones en menú lateral de empleado y cliente; siguen accesibles desde perfil y campana.
- Superadministrador: filtros de usuarios por tipo y nombre o fecha de creación, pagos por importe/fecha/estado, y restauración de desplazamiento después de acciones POST.

## Instalación
1. Respaldar `Vendia` desde phpMyAdmin y la carpeta `uploads` del proyecto actual.
2. Reemplazar los archivos en `C:\xampp\htdocs\VENDIA` con el contenido de este ZIP.
3. Conservar las imágenes propias de `uploads`.
4. **No reimportar `database.sql`**: esta actualización no cambia el esquema.
5. Recargar con Ctrl+F5 e iniciar sesión normalmente.

La aplicación siempre consulta la base `Vendia`; las consultas se prueban completamente al conectar MariaDB en XAMPP.
