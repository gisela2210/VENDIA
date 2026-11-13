# Vendia — Versión 14: promociones o descuentos

Esta entrega parte de `VENDIA_COMPLETO_VENTI_AGENDA_PROMOCIONES_SESIONES.zip`.

## Mejoras

- **Venti**: contorno circular sin reducir ni ampliar los 118 px de la mascota en escritorio y 91 px en móvil definidos en la versión anterior.
- **Promociones**: la pantalla recupera la posición de desplazamiento después de activar, desactivar, autorizar o guardar una oferta (se guarda únicamente en la pestaña actual).
- **Dos modalidades**: descuento porcentual (1 a 100 %) y promoción por cantidad (2x1, 3x2, …, 10x9); el campo adecuado se habilita según la modalidad.
- **Fechas**: toda la superficie de los campos de fecha abre el calendario nativo en los navegadores compatibles. En otros navegadores se usa el selector habitual.
- **Vista previa**: por cada producto seleccionado se ve el precio normal y el precio con descuento, o un ejemplo del costo de N unidades iguales en una promoción NxM.
- **Caja y agenda**: muestran el tipo real de oferta; PHP calcula el ahorro en servidor y JavaScript muestra la misma cifra al cobrar.
- **Un solo SQL**: `database/database.sql` continúa trabajando sobre la base `Vendia`. Agrega tres campos a `promociones` sin eliminar registros y reconstruye la vista `promotions` para que los módulos nuevos puedan acceder a ellos.

## Regla de promociones NxM

Se cobran M unidades por cada grupo completo de N unidades elegibles. Cuando participan artículos de distinto precio, se cobran los M más caros del grupo y la diferencia se descuenta. Los grupos incompletos se cobran al precio normal. Una venta utiliza **una sola oferta**; no se acumulan descuento porcentual y promoción por cantidad. La validación en el servidor impide aplicar ofertas vencidas, sin autorización o pertenecientes a otro negocio.

Ejemplos:

- Descuento 15 % en producto de $100: **$100 → $85** por pieza.
- 2x1 en producto de $40: **2 piezas, $80 → $40**.
- 3x2 en producto de $40: **3 piezas, $120 → $80**.
- 2x1 con productos de $100 y $30: **$130 → $100**, la unidad de $30 se descuenta.
- 2x1 con solo una unidad elegible: se cobra a su precio normal.

## Actualización de XAMPP

1. **Respalda** la base `Vendia` en phpMyAdmin y la carpeta `VENDIA/uploads`.
2. Copia el proyecto completo en `C:\xampp\htdocs\VENDIA` y recupera tus fotografías.
3. Importa en phpMyAdmin **el único archivo** `database/database.sql` incluido en esta entrega. Contiene cambios de estructura no destructivos; no elimines tu base ni importes SQL de versiones anteriores.
4. Accede a `http://localhost/VENDIA/` y fuerza la recarga con `Ctrl+F5`.
5. Entra como dueño, crea una promoción 2x1, selecciona productos y autorízala; después comprueba el precio desde el perfil Empleado en Pedidos.

## Verificación disponible

- Revisión de sintaxis de 34 archivos PHP y de JavaScript.
- Pruebas de las dos modalidades, incluidos 1080 casos aleatorios de paquetes en PHP y comparación de casos representativos con JavaScript.
- **Pendiente en tu equipo**: comprobar importación, permisos y cobros con tu MariaDB real de XAMPP (no se tuvo acceso a esa instancia durante la creación).
