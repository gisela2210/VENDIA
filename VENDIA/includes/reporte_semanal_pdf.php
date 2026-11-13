<?php
/**
 * Exportación PDF autónoma del resumen semanal de un negocio.
 * Solo se llama tras verificar rol, negocio y fecha desde contexto.php.
 * No depende de Composer ni de extensiones PDF de XAMPP.
 */
function vendiaTextoPdf(string $texto): string {
    $convertido = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto) : false;
    $cadena = $convertido === false ? preg_replace('/[^\x20-\x7e]/', '?', $texto) : $convertido;
    return str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", ' ', ' '], $cadena);
}
function vendiaPartirTextoPdf(string $texto, int $max = 87): array {
    $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    if ($texto === '') return [''];
    $palabras = preg_split('/\s+/u', $texto) ?: [];
    $lineas = []; $linea = '';
    foreach ($palabras as $palabra) {
        $candidata = $linea === '' ? $palabra : $linea.' '.$palabra;
        $longitud = function_exists('iconv') ? strlen(@iconv('UTF-8','Windows-1252//TRANSLIT',$candidata) ?: $candidata) : strlen($candidata);
        if ($linea !== '' && $longitud > $max) {
            $lineas[] = $linea;
            $linea = $palabra;
        } else {
            $linea = $candidata;
        }
    }
    if ($linea !== '') $lineas[] = $linea;
    return $lineas;
}
function vendiaGenerarPdfResumen(array $resumen, string $negocio): string {
    $comandos = [];
    $rectangulo = static function(float $x, float $y, float $w, float $h, string $color) use (&$comandos): void {
        $comandos[] = $color.' rg '.sprintf('%.2F %.2F %.2F %.2F re f', $x, 842-$y-$h, $w, $h);
    };
    $texto = static function(float $x, float $y, float $tamano, string $cadena, string $color = '0.09 0.18 0.28', bool $negrita = false) use (&$comandos): void {
        $comandos[] = $color.' rg BT /'.($negrita?'F2':'F1').' '.sprintf('%.2F', $tamano).' Tf '.sprintf('%.2F %.2F', $x, 842-$y).' Td ('.vendiaTextoPdf($cadena).') Tj ET';
    };
    $parrafo = static function(float $x, float $y, string $cadena, int $max = 88, float $tamano = 10.5, float $interlineado = 17) use ($texto): float {
        foreach (vendiaPartirTextoPdf($cadena, $max) as $linea) {
            $texto($x,$y,$tamano,$linea);
            $y += $interlineado;
        }
        return $y;
    };

    $lunes = new DateTimeImmutable($resumen['fecha_lunes']);
    $inicio = new DateTimeImmutable($resumen['periodo_inicio']);
    $fin = new DateTimeImmutable($resumen['periodo_fin']);
    $periodo = $inicio->format('d/m/Y').' al '.$fin->format('d/m/Y');
    $ventas = (int)$resumen['ventas_cantidad'];
    $ingresos = (float)$resumen['ingresos'];
    $ticket = $ventas > 0 ? $ingresos/$ventas : 0;
    $pendientes = (int)$resumen['pedidos_pendientes'];

    // Diseño A4, una página, colores Vendia.
    $rectangulo(0,0,595,150,'0.04 0.52 0.50');
    $texto(39,48,18,'VENDIA','1 1 1',true);
    $texto(39,89,23,'REPORTE GENERAL SEMANAL','1 1 1',true);
    $texto(39,120,11,'Cierre semanal emitido el lunes '.$lunes->format('d/m/Y'),'0.87 1 0.98');

    $texto(40,187,10,'NEGOCIO','0.08 0.55 0.53',true);
    $texto(40,210,16,(function_exists('mb_substr') ? mb_substr($negocio,0,53,'UTF-8') : substr($negocio,0,53)),'0.06 0.19 0.30',true);
    $texto(40,234,10,'Periodo informado: lunes a domingo, '.$periodo);
    $texto(40,253,10,'Fecha de emisión: lunes '.$lunes->format('d/m/Y'));

    $metricas = [
        ['VENTAS COMPLETADAS',number_format($ventas,0,'.',',')],
        ['INGRESOS POR VENTAS','$'.number_format($ingresos,2,'.',',').' MXN'],
        ['TICKET PROMEDIO','$'.number_format($ticket,2,'.',',').' MXN'],
        ['PEDIDOS PENDIENTES',(string)$pendientes],
    ];
    foreach ($metricas as $i => [$etiqueta, $valor]) {
        $x = 40 + ($i%2)*263; $y = 282 + intdiv($i,2)*91;
        $rectangulo($x,$y,250,78,'0.94 0.98 0.98');
        $texto($x+16,$y+27,9,$etiqueta,'0.27 0.43 0.49',true);
        $texto($x+16,$y+55,17,$valor,'0.04 0.38 0.39',true);
    }
    $y=484;
    $texto(40,$y,13,'RECOMENDACIÓN CONCRETA','0.02 0.48 0.47',true);
    $y=$parrafo(40,$y+27,(string)$resumen['recomendacion'],88,10.5,17);
    $y+=22;
    $texto(40,$y,13,'RESUMEN EN UN SOLO MENSAJE','0.02 0.48 0.47',true);
    $y=$parrafo(40,$y+24,(string)$resumen['mensaje'],88,10,16);

    $rectangulo(40,770,515,1,'0.84 0.89 0.91');
    $texto(40,793,8.5,'Fuente: registros del negocio en Vendia. Ingresos no representan utilidad neta.','0.39 0.48 0.53');
    $texto(40,810,8.5,'Pedidos pendientes: estado observado al generar el reporte del lunes.','0.39 0.48 0.53');

    $contenido=implode("\n",$comandos)."\n";
    $objetos = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        6 => '<< /Length '.strlen($contenido)." >>\nstream\n".$contenido.'endstream',
    ];
    $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets=[0];
    foreach ($objetos as $indice=>$objeto) {
        $offsets[$indice]=strlen($pdf);
        $pdf.=$indice." 0 obj\n".$objeto."\nendobj\n";
    }
    $xref=strlen($pdf);
    $pdf.="xref\n0 7\n0000000000 65535 f \n";
    for($i=1;$i<=6;$i++) $pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";
    $pdf.="trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    return $pdf;
}
