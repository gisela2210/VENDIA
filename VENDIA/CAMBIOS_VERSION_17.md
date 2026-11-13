# Vendia V17 — reto del resumen semanal + Venti

## Qué se implementó

La dueña recibe **una sola notificación semanal**, cada lunes, con datos del negocio de **la semana completa anterior (lunes a domingo)** y **una recomendación concreta**.

El mensaje agrupa: total de operaciones completadas, ingresos de ventas completadas, pedidos que siguen pendientes en el momento de generar el resumen, producto más vendido y **una recomendación priorizada**. Las recomendaciones se basan únicamente en registros de `Vendia` (existencias bajo mínimo, pendientes, producto más vendido o ausencia de ventas). La cifra de ingresos NO es utilidad.

El resumen se guarda en `resumenes_semanales` con índice único `(id_negocio, fecha_lunes)`. Por eso la tarea del lunes, una reejecución accidental y el botón de demo no producen mensajes duplicados. La notificación se dirige solo a los dueños activos de ese negocio y se muestra en la campana. El resumen también aparece en el **Inicio** del dueño, con un botón para copiarlo. Se añade un enlace de WhatsApp para **preparar** el mensaje al número registrado del dueño; **no envía automáticamente WhatsApp**, porque falta contratar y configurar la API oficial, permisos y plantillas.

**Venti:** el globo «¡Hola! Soy Venti. ¿Te ayudo?» se oculta por defecto. Solo aparece al poner el cursor encima del personaje (o al enfocarlo con teclado); en dispositivos táctiles no se mantiene flotando. El tamaño y el círculo de Venti permanecen iguales.

## Actualización segura en XAMPP

1. Exporta un respaldo de la base **`Vendia`** desde phpMyAdmin y copia `uploads/`.
2. Sustituye la carpeta `C:\xampp\htdocs\VENDIA` por la carpeta `VENDIA` del ZIP, **sin borrar fotos**.
3. Importa **solo `database/database.sql`** sobre la base existente `Vendia`. Es el único SQL consolidado, crea únicamente la tabla `resumenes_semanales` cuando falta y no borra usuarios, ventas ni otras tablas.
4. Entra a `http://localhost/VENDIA/` y pulsa `Ctrl + F5`.
5. Entra como **dueño → Inicio → Tu resumen semanal**. Para la demostración pulsa **Generar resumen para la demo**. El mensaje se calcula con los datos que ya estén registrados y queda en la campana.

## Automatizar cada lunes en Windows

La app no puede ejecutarse por sí misma cuando Apache y MySQL están apagados. Para la entrega del reto configura una tarea programada:

- Abre **Programador de tareas de Windows** → **Crear tarea básica**.
- Nombre: **Vendia — Resumen semanal**.
- Desencadenador: **Semanal**, **lunes**, **8:00 a. m.**, hora local del equipo (México).
- Acción: **Iniciar un programa**.
- Programa: `C:\xampp\php\php.exe`.
- Argumentos: `C:\xampp\htdocs\VENDIA\scripts\resumen_cada_lunes.php`.
- Iniciar en (si está disponible): `C:\xampp\htdocs\VENDIA`.
- Mantén **MySQL de XAMPP iniciado** y el equipo encendido a la hora programada. En una instalación en servidor, usa cron con PHP CLI y la zona `America/Mexico_City`.

El script solo admite ejecución desde CLI, valida que sea lunes y emite un aviso por negocio. En caso de reejecución no duplica registros. Como respaldo, cuando el dueño entra al inicio un lunes también genera el aviso faltante.

**Limitación:** si el equipo está apagado el lunes y no hay tarea que se recupere al encender, la entrega automática no ocurrirá. Windows puede configurarse para «Ejecutar la tarea lo antes posible si se omitió»; de lo contrario, el dueño puede usar el botón para generar el informe desde el panel.

## Cómo explicar el reto en la demo final

1. Genera ventas y registra existencias mínimas en un **negocio de prueba**. Para que aparezcan en el resumen, deben tener fecha de la **semana pasada**; no se fabrican cifras.
2. Desde el panel **Dueño → Inicio**, pulsa el botón de demo.
3. Presenta el **único mensaje** con resultados semanales y **una acción recomendada**, por ejemplo surtir un producto por debajo del mínimo.
4. Abre **Notificaciones** y enseña que ese resumen también llegó a la campana. Pulsa otra vez el botón o ejecuta el script el mismo lunes para comprobar que no se duplica.
5. Muestra la tarea semanal configurada en Windows. Para enviar sin intervención a un WhatsApp externo sería necesaria la integración de **WhatsApp Business Cloud API** y sus políticas comerciales; el sistema actual no afirma hacer ese envío.
6. Mueve y retira el cursor de Venti; el globo solo debe verse con el cursor encima y desaparece al alejarlo.

## Comprobaciones pendientes

La sintaxis PHP, JavaScript y la estructura del ZIP se pueden verificar sin XAMPP; las consultas y las inserciones en MariaDB deben probarse en la instalación real. El mensaje de prueba refleja únicamente datos de la base actual.
