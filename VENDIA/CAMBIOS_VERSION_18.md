# Vendia V18 — Resumen general descargable en Reportes y análisis

## Cambios

- El informe general semanal dejó de mostrarse en **Inicio** del dueño.
- Se encuentra exclusivamente en **Dueño → Reportes y análisis → Resumen general semanal**.
- Muestra explícitamente **Lunes DD/MM/AAAA**, y el rango analizado: el lunes al domingo anterior.
- El reporte incluye ventas, ingresos, pedidos pendientes, recomendación concreta y el mensaje completo basado en los registros del negocio.
- Descarga **PDF** directamente con botón **Descargar reporte PDF**, sin librerías externas, además de permitir recuperar PDF de lunes anteriores.
- La tarea programada sigue ejecutándose cada lunes. Los resúmenes guardados no se duplican gracias a la restricción única en `resumenes_semanales`.
- Cuando se genera, el aviso en la campana ahora conduce a `Reportes y análisis`. Las notificaciones anteriores con enlace al inicio se redirigen automáticamente.

## Cómo instalar (sin perder cuentas)

1. Respalda `Vendia` desde phpMyAdmin y la carpeta `uploads/`.
2. Sustituye los archivos de tu instalación XAMPP por los de este ZIP, conservando fotografías existentes.
3. **NO es necesario volver a importar SQL**; la estructura de `database/database.sql` no cambió.
4. Abre `http://localhost/VENDIA/` y actualiza con Ctrl + F5.
5. Entra como **Dueño → Reportes y análisis**. Si la notificación de este lunes no se ha generado, pulsa **Generar reporte para la demo**. Descarga el PDF.

## Programar cada lunes

- Programa `C:\xampp\php\php.exe` con el argumento `C:\xampp\htdocs\VENDIA\scripts\resumen_cada_lunes.php`.
- En el **Programador de tareas de Windows**, selecciona **Semanal → Lunes → 08:00**, zona horaria local `America/Mexico_City`.
- Para que se ejecute en XAMPP, MySQL debe estar iniciado. El script solo actúa los lunes y no duplica informes.
- Si el dueño abre el apartado Reportes un lunes, también se puede generar el informe faltante como respaldo.

**Nota:** La descarga es un informe general del negocio, no solo un archivo con ventas aisladas. Las cifras provienen de la base existente, no se inventan; los pendientes corresponden a la fecha en que se generó el informe. Para envío automático a WhatsApp aún se necesitaría su API oficial.
