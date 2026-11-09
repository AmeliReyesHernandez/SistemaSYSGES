<?php
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Validar y recibir filtros
$fechaInicio = isset($_GET['fecha_inicio']) && !empty($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : null;
$fechaFin = isset($_GET['fecha_fin']) && !empty($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : null;
$metodo = isset($_GET['metodo']) && !empty($_GET['metodo']) ? trim($_GET['metodo']) : null;
$idDonante = isset($_GET['id_donante']) && is_numeric($_GET['id_donante']) ? (int)$_GET['id_donante'] : null;

// Construir condiciones SQL
$where = [];
$params = [];

if ($fechaInicio) {
    $where[] = "DATE(d.FechaDonacion) >= :fecha_inicio";
    $params[':fecha_inicio'] = $fechaInicio;
}
if ($fechaFin) {
    $where[] = "DATE(d.FechaDonacion) <= :fecha_fin";
    $params[':fecha_fin'] = $fechaFin;
}
if ($metodo) {
    $where[] = "d.TipoDonacion = :metodo";
    $params[':metodo'] = $metodo;
}
if ($idDonante) {
    $where[] = "d.ID_Donante = :id_donante";
    $params[':id_donante'] = $idDonante;
}

$whereSQL = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

try {
    // 1. Consulta de donativos detallados (agrupados por persona en primer orden)
    $sql = "
        SELECT d.ID_Donativo, d.MontoDonacion, d.TipoDonacion, d.DescripcionEspecie, d.FechaDonacion,
               CONCAT(dn.Nombre, ' ', dn.ApellidoPaterno, ' ', COALESCE(dn.ApellidoMaterno, '')) AS NombreDonante,
               dn.Email, dn.Telefono, dn.RFC
        FROM donativos d
        LEFT JOIN donantes dn ON d.ID_Donante = dn.ID_Donante
        $whereSQL
        ORDER BY 
            CASE WHEN dn.Nombre IS NULL OR TRIM(dn.Nombre) = '' THEN 1 ELSE 0 END,
            TRIM(dn.Nombre) ASC,
            TRIM(dn.ApellidoPaterno) ASC,
            TRIM(COALESCE(dn.ApellidoMaterno, '')) ASC,
            d.FechaDonacion DESC,
            d.ID_Donativo DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $donativos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Métricas y estadísticas del periodo con agrupación por categorías
    $totalRecaudado = 0;
    $totalDonativos = count($donativos);
    $totalEspecie = 0;
    $desgloseMetodos = [];
    $donativosAgrupados = [];

    // Prioridad visual de categorías de pago conocidas
    $prioridadCategorias = [
        'Efectivo' => 1,
        'Transferencia Bancaria' => 2,
        'Transferencia' => 2,
        'PayPal' => 3,
        'Tarjeta de Crédito / Débito' => 4,
        'Tarjeta' => 4,
        'Depósito en Ventanilla' => 5,
        'Depósito' => 5,
        'Cheques' => 6,
        'Cheque' => 6,
        'Autogestivo' => 7,
        'Otro' => 8,
        'En Especie' => 9,
        'Bienes / Mobiliario' => 10,
    ];

    foreach ($donativos as $item) {
        $monto = (float)$item['MontoDonacion'];
        $tipo = trim($item['TipoDonacion'] ?? '');
        if ($tipo === '') {
            $tipo = 'No especificado';
        }
        $esEspecieItem = ($tipo === 'En Especie' || $tipo === 'Bienes / Mobiliario' || $monto <= 0 || !empty($item['DescripcionEspecie']));

        if ($esEspecieItem) {
            $totalEspecie++;
            $montoSuma = 0;
        } else {
            $totalRecaudado += $monto;
            $montoSuma = $monto;
        }

        if (!isset($desgloseMetodos[$tipo])) {
            $desgloseMetodos[$tipo] = ['cantidad' => 0, 'monto' => 0, 'es_especie' => $esEspecieItem];
        }
        $desgloseMetodos[$tipo]['cantidad']++;
        $desgloseMetodos[$tipo]['monto'] += $montoSuma;

        if (!isset($donativosAgrupados[$tipo])) {
            $donativosAgrupados[$tipo] = [
                'nombre' => $tipo,
                'es_especie' => $esEspecieItem,
                'items' => [],
                'subtotal' => 0,
                'cantidad' => 0
            ];
        }
        $donativosAgrupados[$tipo]['items'][] = $item;
        $donativosAgrupados[$tipo]['subtotal'] += $montoSuma;
        $donativosAgrupados[$tipo]['cantidad']++;
    }

    // Ordenar las categorías para presentación institucional
    uksort($donativosAgrupados, function($a, $b) use ($prioridadCategorias) {
        $pA = $prioridadCategorias[$a] ?? 50;
        $pB = $prioridadCategorias[$b] ?? 50;
        if ($pA === $pB) {
            return strcmp($a, $b);
        }
        return $pA <=> $pB;
    });

    $promedioDonacion = ($totalDonativos > 0 && ($totalDonativos - $totalEspecie) > 0) 
        ? ($totalRecaudado / ($totalDonativos - $totalEspecie)) 
        : 0;

} catch (PDOException $e) {
    die("Error al consultar datos para el reporte: " . $e->getMessage());
}

// Configuración de PDF con TCPDF
class ReporteDonacionesPDF extends TCPDF {
    public function Header() {
        $headerImg = realpath(__DIR__ . '/../../assets/img/membrete_header_carta.jpg');
        if (!$headerImg || !file_exists($headerImg)) {
            $headerImg = realpath(__DIR__ . '/../../assets/img/membrete_header.jpg');
        }
        if ($headerImg && file_exists($headerImg)) {
            // Encabezado institucional en formato Carta con márgenes normales (20mm a cada lado, ancho 176mm)
            // Se coloca a 7mm del borde superior. Con su proporción delgada (~22.3mm de alto), termina en ~29.3mm
            $this->Image($headerImg, 20, 7, 176, 0, 'JPG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        }
    }

    public function Footer() {
        $this->SetY(-14);
        $this->SetFont('helvetica', '', 7.5);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 10, 'SYSGES GesMujer  •  Grupo de Estudios sobre la Mujer "Rosario Castellanos" A.C.  •  Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Inicialización formal en Tamaño Carta ('LETTER')
$pdf = new ReporteDonacionesPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);

// Información del documento
$pdf->SetCreator('SYSGES - GesMujer');
$pdf->SetAuthor('GesMujer Oaxaca');
$pdf->SetTitle('Reporte de Donativos');
$pdf->SetSubject('Reporte de Donativos y Aportaciones');

// Margen normal de 20mm a los lados y 34mm superior para que JAMÁS se encime en el encabezado en ninguna hoja
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetHeaderMargin(7);
$pdf->SetMargins(20, 34, 20);
$pdf->SetAutoPageBreak(TRUE, 18);
$pdf->SetFont('helvetica', '', 8);
$pdf->AddPage();

// Formateo del periodo
$textoPeriodo = "Historial General Acumulado";
if ($fechaInicio && $fechaFin) {
    $textoPeriodo = "Del " . date('d/m/Y', strtotime($fechaInicio)) . " al " . date('d/m/Y', strtotime($fechaFin));
} elseif ($fechaInicio) {
    $textoPeriodo = "A partir del " . date('d/m/Y', strtotime($fechaInicio));
} elseif ($fechaFin) {
    $textoPeriodo = "Hasta el " . date('d/m/Y', strtotime($fechaFin));
}

$filtroMetodoText = $metodo ? " | Método: " . htmlspecialchars($metodo) : "";

// Espaciadores controlados para TCPDF (evitan que las tablas se peguen entre sí)
$spacer20 = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="18" style="font-size: 15pt; line-height: 18px;">&nbsp;</td></tr></table>';
$spacer14 = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="14" style="font-size: 11pt; line-height: 14px;">&nbsp;</td></tr></table>';
$spacer10 = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="10" style="font-size: 9pt; line-height: 10px;">&nbsp;</td></tr></table>';
$spacer8  = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="8" style="font-size: 7pt; line-height: 8px;">&nbsp;</td></tr></table>';

$html = '
<style>
    .titulo-rep { color: #721896; font-size: 12.5pt; font-weight: bold; text-align: center; }
    .subtitulo-rep { color: #555555; font-size: 8.5pt; text-align: center; }
    .fecha-rep { color: #888888; font-size: 7.5pt; text-align: center; }
    .sec-header-1 { color: #2b2d42; font-size: 9pt; font-weight: bold; }
    .sec-header-2 { color: #721896; font-size: 9.5pt; font-weight: bold; }
</style>

<!-- Título y Periodo (debajo del encabezado institucional de la hoja) -->
<table width="100%" cellpadding="1" cellspacing="0" border="0">
    <tr>
        <td align="center">
            <span class="titulo-rep">REPORTE EJECUTIVO DE DONATIVOS Y APORTACIONES</span><br>
            <span class="subtitulo-rep"><strong>Periodo:</strong> ' . $textoPeriodo . $filtroMetodoText . '</span><br>
            <span class="fecha-rep">Fecha de emisión: ' . date('d/m/Y h:i A') . '</span>
        </td>
    </tr>
</table>' . $spacer10;

// Tarjetas de Resumen Métrico (KPI)
$html .= '
<table cellpadding="6" cellspacing="0" border="0" width="100%">
    <tr>
        <td width="32%" bgcolor="#f3f9f4" style="border: 1px solid #28a745; text-align: center;">
            <span style="font-size: 7.2pt; color: #155724; font-weight: bold;">TOTAL RECAUDADO</span><br>
            <span style="font-size: 13pt; color: #198754; font-weight: bold;">$' . number_format($totalRecaudado, 2) . '</span>
        </td>
        <td width="2%">&nbsp;</td>
        <td width="32%" bgcolor="#f0f4ff" style="border: 1px solid #007bff; text-align: center;">
            <span style="font-size: 7.2pt; color: #004085; font-weight: bold;">APORTACIONES</span><br>
            <span style="font-size: 13pt; color: #0d6efd; font-weight: bold;">' . $totalDonativos . '</span>
        </td>
        <td width="2%">&nbsp;</td>
        <td width="32%" bgcolor="#fff9e6" style="border: 1px solid #ffc107; text-align: center;">
            <span style="font-size: 7.2pt; color: #856404; font-weight: bold;">EN ESPECIE / BIENES</span><br>
            <span style="font-size: 13pt; color: #d39e00; font-weight: bold;">' . $totalEspecie . '</span>
        </td>
    </tr>
</table>' . $spacer14;

// 1. Resumen Consolidado por Método de Pago
if (!empty($desgloseMetodos)) {
    $html .= '
    <table width="100%" cellpadding="3" cellspacing="0" border="0">
        <tr>
            <td class="sec-header-1">1. RESUMEN POR MÉTODO DE PAGO</td>
        </tr>
    </table>
    
    <table cellpadding="5" cellspacing="0" border="0" width="100%" style="border: 1.5px solid #2b2d42;" nobr="true">
        <thead>
            <tr bgcolor="#2b2d42" style="color: #ffffff; font-weight: bold;">
                <th width="46%" align="left" style="font-size: 8pt; padding: 6px 8px;">Método / Tipo</th>
                <th width="24%" align="center" style="font-size: 8pt; padding: 6px 8px;">Cantidad de Aportaciones</th>
                <th width="30%" align="right" style="font-size: 8pt; padding: 6px 8px;">Subtotal Acumulado ($ MXN)</th>
            </tr>
        </thead>
        <tbody>';
    
    $bg = false;
    foreach ($desgloseMetodos as $metodoName => $data) {
        $rowBg = $bg ? '#f8f9fa' : '#ffffff';
        $bg = !$bg;
        $subtotalTexto = (!empty($data['es_especie']))
            ? '<span style="color: #856404; font-style: italic;">En especie (Bienes)</span>'
            : '$' . number_format($data['monto'], 2);
        $html .= '
            <tr bgcolor="' . $rowBg . '">
                <td width="46%" align="left" style="font-size: 8pt; border-bottom: 1px solid #d1d5db; padding: 5px 8px;">' . htmlspecialchars($metodoName) . '</td>
                <td width="24%" align="center" style="font-size: 8pt; border-bottom: 1px solid #d1d5db; padding: 5px 8px;">' . $data['cantidad'] . '</td>
                <td width="30%" align="right" style="font-size: 8pt; border-bottom: 1px solid #d1d5db; font-weight: bold; padding: 5px 8px;">' . $subtotalTexto . '</td>
            </tr>';
    }
    
    $html .= '
        </tbody>
    </table>' . $spacer14;
}

// 2. Detalle Individual por Categoría
$html .= '
<table width="100%" cellpadding="3" cellspacing="0" border="0">
    <tr>
        <td>
            <span class="sec-header-2">2. DETALLE INDIVIDUAL DE APORTACIONES POR CATEGORÍA</span><br>
        </td>
    </tr>
</table>' . $spacer8;

if (empty($donativos)) {
    $html .= '
    <table cellpadding="10" cellspacing="0" border="0" width="100%" style="border: 1px dashed #999999; text-align: center;">
        <tr>
            <td style="color: #777777; font-size: 8.5pt;">
                No se encontraron registros de donativos en el periodo seleccionado.
            </td>
        </tr>
    </table>';
} else {
    foreach ($donativosAgrupados as $categoriaNombre => $catData) {
        $subtotalHeader = $catData['es_especie']
            ? '<span style="color: #856404; font-weight: bold;">En especie / Bienes</span>'
            : '<span style="color: #198754; font-weight: bold;">Subtotal: $' . number_format($catData['subtotal'], 2) . ' MXN</span>';

        $html .= '
        <table cellpadding="5" cellspacing="0" border="0" width="100%" style="border: 1px solid #d8b4e2;" nobr="true">
            <!-- Banner integrado de Categoría (sin borde negro) -->
            <tr bgcolor="#f8f4fa">
                <td colspan="2" width="72%" style="border-left: 4px solid #721896; border-bottom: 1px solid #d8b4e2; padding: 6px 8px;">
                    <strong style="color: #721896; font-size: 8.8pt; text-transform: uppercase;">CATEGORÍA: ' . htmlspecialchars($categoriaNombre) . '</strong>
                    <span style="color: #555555; font-size: 7.8pt;"> (' . $catData['cantidad'] . ($catData['cantidad'] === 1 ? ' aportación' : ' aportaciones') . ')</span>
                </td>
                <td width="28%" align="right" style="border-bottom: 1px solid #d8b4e2; padding: 6px 8px; font-size: 8.5pt;">
                    ' . $subtotalHeader . '
                </td>
            </tr>
            <!-- Encabezados de Columna (morado institucional, sin borde negro) -->
            <tr bgcolor="#721896" style="color: #ffffff; font-weight: bold;">
                <th width="26%" align="center" style="font-size: 8pt; padding: 5px;">Fecha y Hora</th>
                <th width="48%" align="left" style="font-size: 8pt; padding: 5px 8px;">Donante / Benefactor</th>
                <th width="26%" align="right" style="font-size: 8pt; padding: 5px 8px;">' . ($catData['es_especie'] ? 'Descripción del Bien' : 'Monto Aportado') . '</th>
            </tr>';

        $ultimoDonante = null;
        $alt = false;
        foreach ($catData['items'] as $d) {
            $donanteActual = trim($d['NombreDonante'] ?: 'Anónimo / No asignado');
            $esNuevoDonante = ($ultimoDonante !== null && $ultimoDonante !== $donanteActual);
            $ultimoDonante = $donanteActual;

            $rowBg = $alt ? '#fcfbfe' : '#ffffff';
            $alt = !$alt;

            // Separador suave entre donantes en lugar de raya negra
            $bordeTop = $esNuevoDonante ? 'border-top: 1.5px solid #c084fc;' : 'border-top: 1px solid #f0edf2;';
            $fechaFormateada = !empty($d['FechaDonacion']) ? date('d/m/Y h:i A', strtotime($d['FechaDonacion'])) : 'Sin fecha';
            $esEspecieFila = ($d['TipoDonacion'] === 'En Especie' || $d['TipoDonacion'] === 'Bienes / Mobiliario' || (float)$d['MontoDonacion'] <= 0);

            $montoHtml = (!$esEspecieFila && (float)$d['MontoDonacion'] > 0)
                ? '<strong style="color: #198754; font-size: 8.2pt;">$' . number_format((float)$d['MontoDonacion'], 2) . '</strong>'
                : '<span style="color: #856404; font-style: italic; font-size: 7.8pt;">' . htmlspecialchars($d['DescripcionEspecie'] ?: 'En especie') . '</span>';

            $rfcHtml = !empty($d['RFC']) ? '<br><span style="color: #777777; font-size: 7pt;">RFC: ' . htmlspecialchars($d['RFC']) . '</span>' : '';

            $html .= '
            <tr bgcolor="' . $rowBg . '">
                <td width="26%" align="center" style="' . $bordeTop . ' font-size: 7.8pt; padding: 5px 4px;">' . $fechaFormateada . '</td>
                <td width="48%" align="left" style="' . $bordeTop . ' font-size: 7.8pt; padding: 5px 8px;">
                    <strong>' . htmlspecialchars($donanteActual) . '</strong>' . $rfcHtml . '
                </td>
                <td width="26%" align="right" style="' . $bordeTop . ' padding: 5px 8px;">' . $montoHtml . '</td>
            </tr>';
        }

        $subtotalTextoFila = $catData['es_especie']
            ? $catData['cantidad'] . ' aportación(es) en especie'
            : '$' . number_format($catData['subtotal'], 2) . ' MXN';

        $html .= '
            <tr bgcolor="#f3e8ff" style="font-weight: bold; font-size: 8pt;">
                <td width="74%" colspan="2" align="right" style="border-top: 1.5px solid #d8b4e2; padding: 5px 8px; color: #4a154b;">
                    SUBTOTAL EN ' . htmlspecialchars($categoriaNombre) . ':
                </td>
                <td width="26%" align="right" style="border-top: 1.5px solid #d8b4e2; padding: 5px 8px; color: ' . ($catData['es_especie'] ? '#856404' : '#198754') . ';">
                    ' . $subtotalTextoFila . '
                </td>
            </tr>
        </table>' . $spacer14;
    }

    // Gran Total del Periodo (sin borde negro)
    $html .= '
    <table cellpadding="6" cellspacing="0" border="0" width="100%" bgcolor="#721896" nobr="true">
        <tr style="color: #ffffff; font-weight: bold;">
            <td width="70%" align="left" style="font-size: 8.8pt; padding: 7px 10px;">
                GRAN TOTAL GENERAL ACUMULADO EN EL PERIODO (' . $totalDonativos . ' APORTACIONES):
            </td>
            <td width="30%" align="right" style="font-size: 11pt; padding: 7px 10px;">
                $' . number_format($totalRecaudado, 2) . ' MXN
            </td>
        </tr>
    </table>';
}

$pdf->writeHTML($html, true, false, true, false, '');

// Limpiar cualquier búfer previo para evitar "Some data has already been output"
if (ob_get_length()) {
    ob_end_clean();
}

// Salida del PDF: 'D' para descargar, 'I' para visualización en navegador o iframe
$descargar = (isset($_GET['descargar']) && $_GET['descargar'] == '1');
$modo = $descargar ? 'D' : 'I';
$nombreArchivo = 'Reporte_Donativos_' . date('Ymd_His') . '.pdf';
$pdf->Output($nombreArchivo, $modo);
exit();
