<?php
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';

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

    $totalRecaudado = 0;
    $totalDonativos = count($donativos);
    $totalEspecie = 0;
    $desgloseMetodos = [];
    $donativosAgrupados = [];

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

    uksort($donativosAgrupados, function($a, $b) use ($prioridadCategorias) {
        $pA = $prioridadCategorias[$a] ?? 50;
        $pB = $prioridadCategorias[$b] ?? 50;
        if ($pA === $pB) {
            return strcmp($a, $b);
        }
        return $pA <=> $pB;
    });

} catch (PDOException $e) {
    die("Error al consultar datos para la vista previa: " . $e->getMessage());
}

$textoPeriodo = "Historial General Acumulado";
if ($fechaInicio && $fechaFin) {
    $textoPeriodo = "Del " . date('d/m/Y', strtotime($fechaInicio)) . " al " . date('d/m/Y', strtotime($fechaFin));
} elseif ($fechaInicio) {
    $textoPeriodo = "A partir del " . date('d/m/Y', strtotime($fechaInicio));
} elseif ($fechaFin) {
    $textoPeriodo = "Hasta el " . date('d/m/Y', strtotime($fechaFin));
}

$queryPDF = http_build_query([
    'fecha_inicio' => $fechaInicio,
    'fecha_fin'    => $fechaFin,
]);

// Particionado físico oficial en Hojas Carta (idéntico a la lógica milimétrica de TCPDF)
$alturaUsablePagina = 227; // mm útiles en Carta (279.4mm - margen superior 34mm - margen inferior 18mm)
$alturaIntroHoja1 = 25 + 22 + (!empty($desgloseMetodos) ? (10 + count($desgloseMetodos) * 7) : 0) + 10;
$alturaDisponible = $alturaUsablePagina - $alturaIntroHoja1; // ~139mm disponibles en Hoja 1

$paginas = [];
$paginaActual = 1;
$paginas[$paginaActual] = [
    'es_primera' => true,
    'categorias' => []
];

if (!empty($donativosAgrupados)) {
    foreach ($donativosAgrupados as $catNombre => $catData) {
        $altoTabla = 32 + (count($catData['items']) * 8); // en mm
        
        // Si la tabla no cabe en la hoja actual y ya tenemos al menos una categoría en esta hoja
        if ($altoTabla > $alturaDisponible && !empty($paginas[$paginaActual]['categorias'])) {
            $paginaActual++;
            $alturaDisponible = $alturaUsablePagina;
            $paginas[$paginaActual] = [
                'es_primera' => false,
                'categorias' => []
            ];
        }
        
        $paginas[$paginaActual]['categorias'][$catNombre] = $catData;
        $alturaDisponible -= $altoTabla;
    }
}
$totalPaginas = count($paginas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Vista Previa del Reporte de Donativos</title>
  <link rel="stylesheet" href="../../assets/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body {
      background-color: #525659;
      margin: 0;
      padding: 15px 10px 40px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    .sheet-page {
      max-width: 816px;
      margin: 0 auto 35px auto;
    }
    .sheet-page-indicator {
      max-width: 816px;
      margin: 0 auto 6px auto;
    }
    .sheet-content-box {
      background: #ffffff;
      width: 100%;
      min-height: 1056px; /* Proporción estándar Carta a 96 DPI (8.5 x 11 in) */
      padding: 26px 9.26% 65px 9.26%; /* Margen oficial del PDF: 20mm = 9.26% a los lados */
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.35), 0 0 1px rgba(0, 0, 0, 0.2);
      border-radius: 2px;
      color: #212529;
      box-sizing: border-box;
      position: relative;
    }
    .sheet-body {
      width: 100%;
    }
    .sheet-header {
      margin-bottom: 18px;
    }
    .sheet-header-img {
      width: 100%;
      height: auto;
      aspect-ratio: 1500 / 190;
      object-fit: contain;
      display: block;
    }
    .kpi-box {
      border-radius: 4px;
      padding: 7px 6px;
      text-align: center;
    }
    .section-title-resumen {
      color: #2b2d42;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      margin-bottom: 6px;
    }
    .table-resumen {
      border: 1.5px solid #2b2d42 !important;
      font-size: 0.78rem;
      margin-bottom: 16px;
    }
    .table-resumen thead th {
      background-color: #2b2d42 !important;
      color: #ffffff !important;
      font-weight: 600;
      padding: 5px 8px;
      border: none;
      font-size: 0.78rem;
    }
    .table-resumen tbody td {
      padding: 5px 8px;
      vertical-align: middle;
      border-bottom: 1px solid #d1d5db;
      font-size: 0.78rem;
    }
    .section-title-detalle {
      color: #721896;
      font-size: 0.88rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      margin-top: 10px;
      margin-bottom: 6px;
    }
    .category-banner {
      background-color: #f8f4fa;
      border: 1px solid #d8b4e2;
      border-left: 4px solid #721896;
      padding: 6px 10px;
      margin-top: 12px;
      margin-bottom: 0px;
      border-top-left-radius: 3px;
      border-top-right-radius: 3px;
    }
    .table-categoria {
      border: 1px solid #d8b4e2 !important;
      font-size: 0.78rem;
      margin-bottom: 16px;
      border-top: none !important;
    }
    .table-categoria thead th {
      background-color: #721896 !important;
      color: #ffffff !important;
      font-weight: 600;
      padding: 5px 8px;
      border: none;
      font-size: 0.78rem;
    }
    .table-categoria tbody td {
      padding: 5px 8px;
      vertical-align: middle;
      font-size: 0.78rem;
    }
    .gran-total-banner {
      background-color: #721896;
      color: #ffffff;
      padding: 10px 14px;
      border-radius: 3px;
      margin-top: 16px;
      margin-bottom: 12px;
    }
    .sheet-footer {
      border-top: 1px solid #dee2e6;
      font-size: 0.74rem;
      color: #777777;
      text-align: center;
      padding-top: 8px;
      position: absolute;
      bottom: 22px;
      left: 9.26%;
      right: 9.26%;
    }
    @media print {
      body { background: transparent !important; padding: 0 !important; margin: 0 !important; }
      .sheet-page { margin: 0 !important; page-break-after: always !important; break-after: page !important; max-width: 100% !important; }
      .sheet-content-box { box-shadow: none !important; border-radius: 0 !important; padding: 20mm 20mm !important; min-height: 100vh !important; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>

  <!-- Barra de utilidades superior de la vista previa -->
  <div class="no-print d-flex justify-content-between align-items-center mb-3 mx-auto" style="max-width: 816px;">
    <div class="d-flex align-items-center gap-2">
    
      <span class="text-white-50 small d-none d-sm-inline">
        <?= count($donativos) ?> registro(s)
      </span>
    </div>
    
  </div>

  <?php 
    $imgMembrete = file_exists(__DIR__ . '/../../assets/img/membrete_header_carta.jpg') 
        ? '../../assets/img/membrete_header_carta.jpg' 
        : '../../assets/img/membrete_header.jpg';
  ?>

  <!-- Hojas Oficiales del Documento (Generadas con lógica milimétrica de TCPDF) -->
  <?php foreach ($paginas as $numPag => $infoPag): ?>
    <div class="sheet-page mb-4">
      <div class="sheet-page-indicator no-print d-flex justify-content-between align-items-center mb-2 px-1">
        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 shadow-sm">
          <i class="bi bi-file-earmark-text me-1"></i> Hoja <?= $numPag ?>
      </div>

      <div class="sheet-content-box">
        <!-- Encabezado Institucional (en todas las hojas) -->
        <div class="sheet-header mb-3">   
          <img src="<?= $imgMembrete ?>" alt="Membrete GesMujer" class="sheet-header-img">
        </div>

        <div class="sheet-body">
          <?php if ($infoPag['es_primera']): ?>
            <!-- Título y Periodo -->
            <div class="text-center mb-3">
              <h5 class="fw-bold text-uppercase mb-1" style="color: #721896; letter-spacing: 0.5px;">
                Reporte Ejecutivo de Donativos y Aportaciones
              </h5>
              <p class="text-muted small mb-0">
                <strong>Periodo:</strong> <?= htmlspecialchars($textoPeriodo) ?>
              </p>
              <p class="text-muted small mb-0" style="font-size:0.75rem;">
                Fecha de emisión: <?= date('d/m/Y h:i A') ?>
              </p>
            </div>

            <!-- Tarjetas Métricas KPI -->
            <div class="row g-2 mb-3">
              <div class="col-4">
                <div class="kpi-box" style="background-color: #f3f9f4; border: 1px solid #c3e6cb;">
                  <small class="text-success fw-bold text-uppercase d-block" style="font-size: 0.70rem;">Total Recaudado</small>
                  <span class="fs-5 fw-bold text-success">$<?= number_format($totalRecaudado, 2) ?></span>
                </div>
              </div>
              <div class="col-4">
                <div class="kpi-box" style="background-color: #f0f4ff; border: 1px solid #b8daff;">
                  <small class="text-primary fw-bold text-uppercase d-block" style="font-size: 0.70rem;">Aportaciones</small>
                  <span class="fs-5 fw-bold text-primary"><?= $totalDonativos ?></span>
                </div>
              </div>
              <div class="col-4">
                <div class="kpi-box" style="background-color: #fff9e6; border: 1px solid #ffeeba;">
                  <small class="text-warning-emphasis fw-bold text-uppercase d-block" style="font-size: 0.70rem;">En Especie / Bienes</small>
                  <span class="fs-5 fw-bold text-warning-emphasis"><?= $totalEspecie ?></span>
                </div>
              </div>
            </div>

            <!-- Resumen por método si hay datos -->
            <?php if (!empty($desgloseMetodos)): ?>
              <div class="mb-3">
                <h6 class="section-title-resumen">
                  <i class="bi bi-pie-chart-fill me-1"></i> 1. Resumen Consolidado por Método de Pago
                </h6>
                <div class="table-responsive">
                  <table class="table table-sm table-resumen align-middle mb-0">
                    <thead>
                      <tr>
                        <th style="width: 46%;">Método / Tipo</th>
                        <th class="text-center" style="width: 24%;">Cantidad de Aportaciones</th>
                        <th class="text-end" style="width: 30%;">Subtotal Acumulado ($ MXN)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($desgloseMetodos as $nombreMet => $dMet): ?>
                        <tr>
                          <td><?= htmlspecialchars($nombreMet) ?></td>
                          <td class="text-center"><?= $dMet['cantidad'] ?></td>
                          <td class="text-end fw-bold">
                            <?php if (!empty($dMet['es_especie'])): ?>
                              <span class="text-warning-emphasis fst-italic">En especie (Bienes)</span>
                            <?php else: ?>
                              $<?= number_format($dMet['monto'], 2) ?>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endif; ?>

            <!-- Título de Detalle -->
            <div class="mb-2">
              <h6 class="section-title-detalle">
                <i class="bi bi-layers-fill me-1"></i> 2. Detalle Individual de Aportaciones por Categoría
              </h6>
            </div>
          <?php endif; ?>

          <?php if (empty($donativos)): ?>
            <div class="text-center py-5 border border-dashed rounded-3 text-muted">
              <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
              No se encontraron registros de donativos en el periodo seleccionado.
            </div>
          <?php else: ?>
            <?php foreach ($infoPag['categorias'] as $catNombre => $catData): ?>
              <!-- Categoría Individual -->
              <div class="mb-3">
                <div class="category-banner d-flex justify-content-between align-items-center">
                  <div>
                    <strong class="text-uppercase" style="color: #721896; font-size: 0.88rem;">
                      <i class="bi bi-tag-fill me-1"></i> Categoría: <?= htmlspecialchars($catNombre) ?>
                    </strong>
                    <span class="text-muted small ms-1">(<?= $catData['cantidad'] ?> <?= $catData['cantidad'] === 1 ? 'aportación' : 'aportaciones' ?>)</span>
                  </div>
                  <div>
                    <?php if ($catData['es_especie']): ?>
                      <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold">
                        En Especie / Bienes
                      </span>
                    <?php else: ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold">
                        Subtotal: $<?= number_format($catData['subtotal'], 2) ?> MXN
                      </span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-hover table-categoria align-middle mb-0">
                    <thead>
                      <tr>
                        <th style="width: 26%;" class="text-center">Fecha y Hora</th>
                        <th style="width: 48%;">Donante / Benefactor</th>
                        <th style="width: 26%;" class="text-end"><?= $catData['es_especie'] ? 'Descripción del Bien' : 'Monto Aportado' ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                        $ultimoDonante = null;
                        foreach ($catData['items'] as $d): 
                          $donanteActual = trim($d['NombreDonante'] ?: 'Anónimo / No asignado');
                          $esNuevoDonante = ($ultimoDonante !== null && $ultimoDonante !== $donanteActual);
                          $ultimoDonante = $donanteActual;
                          $estiloFila = $esNuevoDonante ? 'border-top: 1.5px solid #c084fc;' : 'border-top: 1px solid #f0edf2;';

                          $fechaFormateada = !empty($d['FechaDonacion']) ? date('d/m/Y h:i A', strtotime($d['FechaDonacion'])) : 'Sin fecha';
                          $esEspecieFila = ($d['TipoDonacion'] === 'En Especie' || $d['TipoDonacion'] === 'Bienes / Mobiliario' || (float)$d['MontoDonacion'] <= 0);
                      ?>
                        <tr style="<?= $estiloFila ?>">
                          <td class="text-center small"><?= $fechaFormateada ?></td>
                          <td>
                            <strong class="text-dark d-block"><?= htmlspecialchars($donanteActual) ?></strong>
                            <?php if (!empty($d['RFC'])): ?>
                              <small class="text-muted" style="font-size:0.75rem;">RFC: <?= htmlspecialchars($d['RFC']) ?></small>
                            <?php endif; ?>
                          </td>
                          <td class="text-end">
                            <?php if (!$esEspecieFila && (float)$d['MontoDonacion'] > 0): ?>
                              <strong class="text-success">$<?= number_format((float)$d['MontoDonacion'], 2) ?></strong>
                            <?php else: ?>
                              <span class="text-warning-emphasis fst-italic small"><?= htmlspecialchars($d['DescripcionEspecie'] ?: 'En especie') ?></span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                      
                      <tr style="background-color: #f3e8ff; border-top: 1.5px solid #d8b4e2; font-weight: bold;">
                        <td colspan="2" class="text-end" style="color: #4a154b;">
                          SUBTOTAL EN <?= htmlspecialchars($catNombre) ?>:
                        </td>
                        <td class="text-end <?= $catData['es_especie'] ? 'text-warning-emphasis' : 'text-success' ?>">
                          <?= $catData['es_especie'] ? $catData['cantidad'] . ' aportación(es) en especie' : '$' . number_format($catData['subtotal'], 2) . ' MXN' ?>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if ($numPag === $totalPaginas): ?>
              <!-- Gran Total en la última página -->
              <div class="gran-total-banner d-flex justify-content-between align-items-center shadow-sm">
                <div>
                  <span class="text-uppercase fw-bold" style="font-size: 0.92rem;">
                    Gran Total General Acumulado en el Periodo (<?= $totalDonativos ?> aportaciones)
                  </span>
                  <?php if ($totalEspecie > 0): ?>
                    <small class="d-block text-white-50" style="font-size: 0.76rem;">
                      Incluye <?= $totalEspecie ?> aportación(es) en especie / bienes materiales.
                    </small>
                  <?php endif; ?>
                </div>
                <div class="fs-4 fw-bold text-end">
                  $<?= number_format($totalRecaudado, 2) ?> <small class="fs-6 fw-normal text-white-50">MXN</small>
                </div>
              </div>
            <?php endif; ?>

          <?php endif; ?>
        </div>

        <!-- Pie Institucional Oficial (Idéntico al PDF en cada hoja) -->
        <div class="sheet-footer">
          SYSGES GesMujer &bull; Grupo de Estudios sobre la Mujer &quot;Rosario Castellanos&quot; A.C. &bull; P&aacute;gina <?= $numPag ?> de <?= $totalPaginas ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

</body>
</html>
