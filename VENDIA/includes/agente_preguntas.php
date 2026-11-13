<?php
/** Preguntas admitidas por Venti. La API siempre verifica el rol. */
function preguntasVenti(string $rol): array {
    if ($rol === 'dueño') return [
        'ventas_hoy'=>'¿Cuánto vendimos hoy?',
        'inventario'=>'¿Cuáles son los productos que tenemos en existencia?',
        'reabastecer'=>'¿Qué productos necesito surtir?',
        'bajas'=>'¿Cuáles tienen existencias bajas?',
        'horario'=>'¿Cuál es el horario del negocio?',
        'ventas_semana'=>'¿Cuánto vendimos esta semana?',
        'ventas_mes'=>'¿Cuánto vendimos este mes?',
        'producto_top'=>'¿Cuál es el producto más vendido?',
        'sin_ventas'=>'¿Qué productos no se han vendido?',
        'pedidos_pendientes'=>'¿Cuántos pedidos tenemos pendientes?',
        'entregados_hoy'=>'¿Cuántos pedidos se entregaron hoy?',
        'empleado_top'=>'¿Qué empleado realizó más ventas?',
        'utilidad'=>'¿Cuánto ganamos después de descontar los costos?',
        'promociones'=>'¿Cuáles son las promociones activas?',
        'prioridad_surtido'=>'¿Qué productos me recomiendas reabastecer primero?',
    ];
    if ($rol === 'cliente') return [
        'promos_hoy'=>'¿Cuáles son las promociones de hoy?',
        'recomendar'=>'¿Qué productos me recomiendas comprar?',
        'nuevos'=>'¿Qué productos nuevos tienen?',
        'mas_vendidos'=>'¿Cuáles son los productos más vendidos?',
        'descuentos'=>'¿Qué descuentos tienen disponibles?',
        'presupuesto'=>'¿Qué productos puedo comprar con $100?',
        'semana'=>'¿Qué productos tienen para esta semana?',
        'economicas'=>'¿Cuáles son las ofertas más económicas?',
        'paquetes'=>'¿Tienen paquetes promocionales?',
        'catalogo'=>'¿Dónde puedo ver todos los productos?',
        'disponibles'=>'¿Qué productos están disponibles?',
        'por_terminar'=>'¿Qué promociones están por terminar?',
        'recomendar_hoy'=>'¿Qué me recomiendas comprar hoy?',
        'ver_ofertas'=>'¿Dónde encuentro las ofertas del negocio?',
        'descuentos_especiales'=>'¿Qué productos tienen descuento especial?',
        'para_negocio'=>'¿Qué productos puedo comprar para mi negocio?',
        'novedades'=>'¿Cuáles son las novedades del día?',
        'compartir'=>'¿Qué promociones puedo compartir con mis clientes?',
        'fotos'=>'¿Dónde puedo ver las imágenes de las ofertas?',
    ];
    return [];
}
