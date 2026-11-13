# Vendia — Actualización 12: encabezado, caja y Venti

## Cambios

- Encabezado superior **fijo al desplazarse** en escritorio y móvil.
- Al pulsar sobre el nombre del usuario, se abre un menú con **Configuración de mi cuenta** y **Cerrar sesión**. Funciona para dueño, empleado, cliente y superadministrador. El dueño conserva su Configuración del negocio; los otros tres roles pueden actualizar sus propios datos y cambiar su contraseña desde Mi configuración.
- **Pedidos / cobro:** una sección enumera las promociones *autorizadas y vigentes* con porcentaje, productos participantes y día de vencimiento. El empleado puede elegirlas al cobrar.
- El servidor verifica nuevamente fecha de vigencia, autorización, negocio y productos elegibles al confirmar la venta. Las promociones vencidas dejan de mostrarse en caja y no se pueden cobrar, incluso si alguien modifica el formulario.
- **Venti dueño:** 15 consultas reales sobre ventas del día/semana/mes, inventario, faltantes, horarios, productos más vendidos, sin ventas, pedidos, empleados, utilidad bruta estimada y promociones.
- **Venti cliente:** 19 consultas basadas en el catálogo y las promociones vigentes del negocio seleccionado. El cliente elige el negocio desde el chat; no puede consultar sus datos financieros internos.
- Las respuestas se obtienen de tablas reales de MariaDB, sin conectar un modelo externo ni inventar cifras. Las sugerencias comerciales se basan en existencias, precios o ventas registradas.

## Instalación / actualización con XAMPP

1. Respalda `C:\xampp\htdocs\VENDIA`, incluyendo la carpeta `uploads`, y exporta tu base `Vendia` desde phpMyAdmin.
2. Descomprime el ZIP en `C:\xampp\htdocs\` y reemplaza la carpeta `VENDIA` con sus archivos, **conservando los archivos subidos que no estén en el ZIP**.
3. Inicia Apache y MySQL y entra a `http://localhost/VENDIA/`. Pulsa `Ctrl + F5` si observas CSS o JavaScript antiguo.
4. **No importes de nuevo `database/database.sql`** para actualizar: esta entrega no introduce tablas ni columnas. El SQL único se conserva dentro del ZIP para instalaciones nuevas.

## Límites importantes

- Una promoción vence al finalizar su último día indicado. El sistema la excluye automáticamente de caja y bloquea su aplicación en servidor, aunque el registro histórico conserve `activo=1` hasta que el dueño lo desactive o edite.
- «Pedidos entregados hoy» se puede verificar con precisión únicamente para pedidos cobrados y entregados mediante caja, porque el esquema actual no guarda una marca temporal específica de entrega manual.
- «Utilidad» es estimada con costos **actuales** registrados en productos, y no descuenta renta, salarios, impuestos ni gastos operativos.
- Venti no genera respuestas libres con inteligencia artificial externa; procesa 15 preguntas del dueño y 19 del cliente mediante consultas programadas y filtros de permisos.

## Pruebas hechas al generar esta entrega

- Revisión de sintaxis de archivos PHP y JavaScript.
- Cabecera fija y menú desplegable comprobados con navegador en 1440 px y 390 px.
- Cálculo de descuentos sobre artículos elegibles, promoción vencida y retiro de promoción comprobados en navegador.
- 15 respuestas de dueño y 19 de cliente ejecutadas con datos simulados, para revisar el flujo y evitar errores fatales.
- La ejecución real de consultas y ventas en **tu** MariaDB/XAMPP aún debe probarse allí.

Si cambias los costos de un producto, la utilidad histórica estimada también puede cambiar; para una contabilidad exacta se deberá almacenar el costo unitario en cada detalle de venta.
