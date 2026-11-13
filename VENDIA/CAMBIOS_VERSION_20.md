# VENDIA V20 · Corrección del registro de clientes y empleados

## Qué se corrigió

- Cliente se registra **sin local**. Antes se enviaba `false`/vacío a `id_negocio`, causando un error SQL por relación foránea o por un campo obligatorio en bases antiguas.
- Cliente y Empleado ahora se insertan mediante consultas separadas con **parámetros tipados**, sin confundir los campos de cada rol.
- Los mensajes indican si existe un nombre de usuario duplicado, falta actualizar el esquema o el negocio elegido ya no está disponible.
- La notificación posterior al registro (Cliente: bienvenida y aviso al superadministrador; Empleado: aviso al dueño) no puede convertir una cuenta ya guardada en un falso error de registro.
- Dueño: la anotación secundaria de alta en auditoría no revierte una cuenta creada si falta la tabla de eventos en una instalación incompleta.

## Actualización segura de XAMPP (con usuarios y ventas existentes)

1. **Primero exporta `Vendia` como respaldo** desde phpMyAdmin y guarda tu carpeta `uploads`.
2. Sustituye el código PHP/JS/CSS de tu instalación por los archivos de este ZIP. No borres tu base de datos.
3. Abre una consola CMD o PowerShell, y ejecuta:

   ```bat
   cd C:\xampp\htdocs\VENDIA
   C:\xampp\php\php.exe scripts\reparar_registro.php
   ```

4. Si muestra operaciones pendientes, verifica que tienes respaldo y luego ejecuta:

   ```bat
   C:\xampp\php\php.exe scripts\reparar_registro.php --aplicar
   ```

5. Entra a `http://localhost/VENDIA/` y refresca con `Ctrl + F5`. Intenta registrar un cliente **con un usuario y correo nuevos**. La cuenta Empleado seguirá pendiente de activación por el dueño.

**No es necesario reimportar la base completa** para esta reparación. La actualización se hace en la misma base `Vendia` con un script PHP de cambios idempotentes. También se conserva `database/database.sql` como único SQL consolidado para nuevas instalaciones.

Si el error persiste, comprueba `C:\xampp\apache\logs\error.log` y el resultado de `scripts\reparar_registro.php`, sin compartir contraseñas ni datos sensibles.

## Limitaciones de las pruebas

Se validó el código PHP y la estructura del ZIP, pero no se conectó a tu instalación real de MariaDB. La reparación debe ejecutarse sobre tu XAMPP después del respaldo.
