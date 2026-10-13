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
    // 1. Consulta de donativos detallados
    $sql = "
        SELECT d.ID_Donativo, d.MontoDonacion, d.TipoDonacion, d.DescripcionEspecie, d.FechaDonacion,
               CONCAT(dn.Nombre, ' ', dn.ApellidoPaterno, ' ', COALESCE(dn.ApellidoMaterno, '')) AS NombreDonante,
               dn.Email, dn.Telefono, dn.RFC
        FROM donativos d
        LEFT JOIN donantes dn ON d.ID_Donante = dn.ID_Donante
        $whereSQL
        ORDER BY d.FechaDonacion ASC, d.ID_Donativo ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $donativos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Métricas y estadísticas del periodo
    $totalRecaudado = 0;
    $totalDonativos = count($donativos);
    $totalEspecie = 0;
    $desgloseMetodos = [];

    foreach ($donativos as $item) {
        $monto = (float)$item['MontoDonacion'];
        $tipo = $item['TipoDonacion'] ?: 'No especificado';
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
    }

    $promedioDonacion = ($totalDonativos > 0 && ($totalDonativos - $totalEspecie) > 0) 
        ? ($totalRecaudado / ($totalDonativos - $totalEspecie)) 
        : 0;

} catch (PDOException $e) {
    die("Error al consultar datos para el reporte: " . $e->getMessage());
}

// Configuración de PDF con TCPDF
class ReporteDonacionesPDF extends TCPDF {
    public function Header() {
        $headerImg = realpath(__DIR__ . '/../../assets/img/membrete_header.jpg');
        if ($headerImg && file_exists($headerImg)) {
            $this->Image($headerImg, 10, 8, 190, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            $this->SetY(32);
        } else {
            $this->SetFont('helvetica', 'B', 16);
            $this->SetTextColor(114, 24, 150); // #721896
            $this->Cell(0, 10, 'GesMujer Oaxaca', 0, 1, 'C');
            $this->SetFont('helvetica', '', 10);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 5, 'Grupo de Estudios sobre la Mujer Rosario Castellanos A.C.', 0, 1, 'C');
            $this->SetY(26);
        }
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'SYSGES GesMujer Oaxaca  |  Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new ReporteDonacionesPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Información del documento
$pdf->SetCreator('SYSGES - GesMujer');
$pdf->SetAuthor('GesMujer Oaxaca');
$pdf->SetTitle('Reporte de Donativos');
$pdf->SetSubject('Reporte de Donativos y Aportaciones');

$pdf->SetMargins(12, 34, 12);
$pdf->SetHeaderMargin(8);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 18);
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

// Encabezado del reporte
$htmlHeader = '
<div style="text-align: center; margin-bottom: 12px;">
    <h1 style="color: #721896; font-size: 16pt; margin: 0; font-weight: bold; text-transform: uppercase;">Reporte Ejecutivo de Donativos y Aportaciones</h1>
    <p style="color: #555; font-size: 10pt; margin: 3px 0 0 0;"><strong>Periodo:</strong> ' . $textoPeriodo . $filtroMetodoText . '</p>
    <p style="color: #888; font-size: 8pt; margin: 2px 0 0 0;">Fecha de emisión: ' . date('d/m/Y h:i A') . '</p>
</div>
';
$pdf->writeHTML($htmlHeader, true, false, true, false, '');

// Tarjetas de resumen métrico (HTML Table)
$htmlKpis = '
<table cellpadding="6" cellspacing="3" style="width: 100%; margin-bottom: 15px;">
    <tr>
        <td width="33%" style="background-color: #f3f9f4; border: 1px solid #c3e6cb; border-radius: 5px; text-align: center; width: 33%;">
            <span style="font-size: 8.5pt; color: #155724; font-weight: bold; text-transform: uppercase;">Total Recaudado</span><br>
            <span style="font-size: 13pt; color: #198754; font-weight: bold;">$' . number_format($totalRecaudado, 2) . '</span>
        </td>
        <td width="34%" style="background-color: #f0f4ff; border: 1px solid #b8daff; border-radius: 5px; text-align: center; width: 34%;">
            <span style="font-size: 8.5pt; color: #004085; font-weight: bold; text-transform: uppercase;">Aportaciones</span><br>
            <span style="font-size: 13pt; color: #0d6efd; font-weight: bold;">' . $totalDonativos . '</span>
        </td>
        <td width="33%" style="background-color: #fff9e6; border: 1px solid #ffeeba; border-radius: 5px; text-align: center; width: 33%;">
            <span style="font-size: 8.5pt; color: #856404; font-weight: bold; text-transform: uppercase;">En Especie / Bienes</span><br>
            <span style="font-size: 13pt; color: #d39e00; font-weight: bold;">' . $totalEspecie . '</span>
        </td>
    </tr>
</table>
';
$pdf->writeHTML($htmlKpis, true, false, true, false, '');

// Desglose por método de pago si hay registros
if (!empty($desgloseMetodos)) {
    $htmlDesglose = '
    <h3 style="color: #721896; font-size: 10pt; font-weight: bold; margin-bottom: 4px;">Resumen por Método / Tipo de Aportación</h3>
    <table cellpadding="5" cellspacing="0" border="0" style="width: 100%; border: 1px solid #ddd; font-size: 8.5pt;">
        <thead>
            <tr style="background-color: #721896; color: #ffffff; font-weight: bold;">
                <th width="46%" align="left" style="width: 46%; text-align: left; padding: 6px;">Método / Tipo</th>
                <th width="24%" align="center" style="width: 24%; text-align: center; padding: 6px;">Cantidad</th>
                <th width="30%" align="right" style="width: 30%; text-align: right; padding: 6px;">Subtotal ($ MXN)</th>
            </tr>
        </thead>
        <tbody>';
    
    $bg = false;
    foreach ($desgloseMetodos as $metodoName => $data) {
        $rowBg = $bg ? 'background-color: #f9f9f9;' : 'background-color: #ffffff;';
        $bg = !$bg;
        $subtotalTexto = (!empty($data['es_especie'])) 
            ? '<span style="color: #856404; font-style: italic;">En especie (Bienes)</span>' 
            : '$' . number_format($data['monto'], 2);
        $htmlDesglose .= '
            <tr style="' . $rowBg . '">
                <td width="46%" align="left" style="width: 46%; border-bottom: 1px solid #eee; text-align: left; padding: 5px;">' . htmlspecialchars($metodoName) . '</td>
                <td width="24%" align="center" style="width: 24%; border-bottom: 1px solid #eee; text-align: center; padding: 5px;">' . $data['cantidad'] . '</td>
                <td width="30%" align="right" style="width: 30%; border-bottom: 1px solid #eee; text-align: right; font-weight: bold; padding: 5px;">' . $subtotalTexto . '</td>
            </tr>';
    }

    $htmlDesglose .= '
        </tbody>
    </table>
    <br><br>';
    $pdf->writeHTML($htmlDesglose, true, false, true, false, '');
}

// Tabla Detallada de Donativos
$htmlDetalle = '
<h3 style="color: #721896; font-size: 11pt; font-weight: bold; margin-bottom: 6px;">Detalle de Aportaciones Registradas</h3>
<table cellpadding="5" cellspacing="0" border="0" style="width: 100%; border: 1px solid #ccc; font-size: 8pt;">
    <thead>
        <tr style="background-color: #4a154b; color: #ffffff; font-weight: bold;">
            <th width="9%" align="center" style="width: 9%; text-align: center; padding: 6px;">Folio</th>
            <th width="23%" align="center" style="width: 23%; text-align: center; padding: 6px;">Fecha</th>
            <th width="32%" align="left" style="width: 32%; text-align: left; padding: 6px;">Donante</th>
            <th width="18%" align="center" style="width: 18%; text-align: center; padding: 6px;">Método / Tipo</th>
            <th width="18%" align="right" style="width: 18%; text-align: right; padding: 6px;">Monto / Detalle</th>
        </tr>
    </thead>
    <tbody>';

if (empty($donativos)) {
    $htmlDetalle .= '
        <tr>
            <td width="100%" colspan="5" align="center" style="width: 100%; text-align: center; padding: 20px; color: #888;">
                No se encontraron registros de donativos en el periodo o filtros seleccionados.
            </td>
        </tr>';
} else {
    $alt = false;
    foreach ($donativos as $d) {
        $rowStyle = $alt ? 'background-color: #fbfbfb;' : 'background-color: #ffffff;';
        $alt = !$alt;

        $fechaFormateada = !empty($d['FechaDonacion']) ? date('d/m/Y h:i A', strtotime($d['FechaDonacion'])) : 'Sin fecha';
        $esEspecieFila = ($d['TipoDonacion'] === 'En Especie' || $d['TipoDonacion'] === 'Bienes / Mobiliario' || (float)$d['MontoDonacion'] <= 0);
        $montoHtml = (!$esEspecieFila && (float)$d['MontoDonacion'] > 0) 
            ? '<strong style="color: #198754;">$' . number_format((float)$d['MontoDonacion'], 2) . '</strong>'
            : '<span style="color: #856404; font-style: italic;">' . htmlspecialchars($d['DescripcionEspecie'] ?: 'En especie') . '</span>';

        $htmlDetalle .= '
        <tr style="' . $rowStyle . '">
            <td width="9%" align="center" style="width: 9%; border-bottom: 1px solid #e9ecef; text-align: center; font-weight: bold; color: #666; padding: 5px;">#' . $d['ID_Donativo'] . '</td>
            <td width="23%" align="center" style="width: 23%; border-bottom: 1px solid #e9ecef; text-align: center; padding: 5px;">' . $fechaFormateada . '</td>
            <td width="32%" align="left" style="width: 32%; border-bottom: 1px solid #e9ecef; text-align: left; padding: 5px;"><strong>' . htmlspecialchars($d['NombreDonante'] ?: 'Anónimo / No asignado') . '</strong></td>
            <td width="18%" align="center" style="width: 18%; border-bottom: 1px solid #e9ecef; text-align: center; padding: 5px;">' . htmlspecialchars($d['TipoDonacion'] ?: 'No especificado') . '</td>
            <td width="18%" align="right" style="width: 18%; border-bottom: 1px solid #e9ecef; text-align: right; padding: 5px;">' . $montoHtml . '</td>
        </tr>';
    }

    // Fila de Total
    $htmlDetalle .= '
        <tr style="background-color: #721896; color: #ffffff; font-weight: bold; font-size: 8.5pt;">
            <td width="82%" colspan="4" align="center" style="width: 82%; text-align: center; padding: 6px;">GRAN TOTAL RECAUDADO EN EL PERIODO:</td>
            <td width="18%" align="right" style="width: 18%; text-align: right; padding: 6px;">$' . number_format($totalRecaudado, 2) . ' MXN</td>
        </tr>';
}

$htmlDetalle .= '
    </tbody>
</table>';

$pdf->writeHTML($htmlDetalle, true, false, true, false, '');

// Salida del PDF
$nombreArchivo = 'Reporte_Donativos_' . date('Ymd_His') . '.pdf';
$pdf->Output($nombreArchivo, 'I');
exit();
