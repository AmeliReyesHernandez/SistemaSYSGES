<?php
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';

// Paginación y filtros
$registrosPorPagina = 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $registrosPorPagina;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$metodoFiltro = isset($_GET['metodo']) ? trim($_GET['metodo']) : '';

try {
    // Métricas rápidas
    $stmtMetricas = $conn->query("
        SELECT COALESCE(SUM(MontoDonacion), 0) AS TotalRecaudado,
               COUNT(*) AS TotalDonativos,
               COALESCE(AVG(MontoDonacion), 0) AS PromedioDonacion
        FROM donativos
    ");
    $metricas = $stmtMetricas->fetch(PDO::FETCH_ASSOC);

    // Condiciones de búsqueda y filtro
    $whereConditions = [];
    $params = [];

    if ($search !== '') {  
        $whereConditions[] = "(dn.Nombre LIKE :s OR dn.ApellidoPaterno LIKE :s OR dn.ApellidoMaterno LIKE :s OR dn.Email LIKE :s)";
        $params[':s'] = "%$search%";
    }

    if ($metodoFiltro !== '') {
        $whereConditions[] = "d.TipoDonacion = :metodo";
        $params[':metodo'] = $metodoFiltro;
    }

    $whereSQL = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    // Conteo total para paginación
    $countSql = "
        SELECT COUNT(*)
        FROM donativos d
        LEFT JOIN donantes dn ON d.ID_Donante = dn.ID_Donante
        $whereSQL
    ";
    $stmtCount = $conn->prepare($countSql);
    foreach ($params as $k => $v) {
        $stmtCount->bindValue($k, $v);
    }
    $stmtCount->execute();
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

    // Listado paginado
    $sql = "
        SELECT d.ID_Donativo, d.MontoDonacion, d.TipoDonacion, d.DescripcionEspecie, d.FechaDonacion, d.ID_Donante,
               CONCAT(dn.Nombre, ' ', dn.ApellidoPaterno, ' ', dn.ApellidoMaterno) AS Donante,
               dn.Email, dn.Telefono
        FROM donativos d
        LEFT JOIN donantes dn ON d.ID_Donante = dn.ID_Donante
        $whereSQL
        ORDER BY d.ID_Donativo DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $donativos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $donativos = [];
    $metricas = ['TotalRecaudado' => 0, 'TotalDonativos' => 0, 'PromedioDonacion' => 0];
    $totalRegistros = 0;
    $totalPaginas = 1;
}
?>
<!doctype html>
<html lang="es" data-bs-theme="auto">
  <head>
    <script src="../../assets/js/color-modes.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Historial de Donaciones | GesMujer</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@docsearch/css@3">
    <link href="../../assets/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../dashboard.css" rel="stylesheet">

    <style>
      .bi {
        vertical-align: -.125em;
        fill: currentColor;
      }
      main .card .bi, main .sf-header .bi, main .btn .bi, .modal .bi {
        width: auto;
        height: auto;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }
      .sf-header {
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        padding: 16px 24px;
        border-radius: 12px;
        margin-bottom: 20px;
      }
      .sf-nav-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 20px;
      }
      .sf-nav-tabs .nav-link {
        color: #4a5568;
        font-weight: 600;
        border: none;
        padding: 10px 20px;
        border-bottom: 3px solid transparent;
      }
      .sf-nav-tabs .nav-link.active {
        color: #721896;
        border-bottom: 3px solid #721896;
        background: transparent;
      }
      .card-sf {
        border-radius: 12px;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        background: #fff;
        margin-bottom: 20px;
      }
      .card-sf-header {
        background: #fafbfc;
        border-bottom: 1px solid #edf2f7;
        font-weight: 600;
        padding: 14px 20px;
        border-radius: 12px 12px 0 0;
      }
      .btn-purple {
        background: #721896;
        color: white;
        border: none;
      }
      .btn-purple:hover {
        background: #5b1378;
        color: white;
      }
      .btn-outline-purple {
        color: #721896;
        border-color: #721896;
      }
      .btn-outline-purple:hover {
        background: #721896;
        color: white;
      }
      .kpi-mini {
        background: #fafbfc;
        border-radius: 10px;
        padding: 12px 16px;
        border: 1px solid #edf2f7;
      }
      .text-purple {
        color: #721896 !important;
      }
      .bg-purple-subtle {
        background-color: #f3e8ff !important;
        color: #721896 !important;
      }
      .border-purple {
        border-color: #d1a8e7 !important;
      }
      .badge.bg-purple-subtle {
        background-color: #f3e8ff !important;
        color: #721896 !important;
        border: 1px solid #d1a8e7 !important;
      }
    </style>
  </head>
  <body>

    <!-- Menú superior -->
    <?php require_once __DIR__ . '/header.php'; ?>

    <!-- Menú lateral -->
    <?php require_once __DIR__ . '/footer.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

      <!-- Encabezado Salesforce Header -->
      <div class="sf-header shadow-sm">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <h2 class="h3 fw-bold mb-0 text-dark">Historial de Donativos</h2>
            <p class="text-muted small mb-0">Control y registro cronológico de aportaciones económicas recibidas</p>
          </div>
          
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-danger btn-sm px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalReportePDF" onclick="recargarPreviewPDF()">
              <i class="bi bi-file-earmark-pdf-fill me-1"></i> Generar Reporte PDF
            </button>
            <a href="donativo-nuevo.php" class="btn btn-purple btn-sm px-3 py-2 fw-semibold shadow-sm">
              <i class="bi bi-cash-stack me-1"></i> + Nuevo Donativo
            </a>
            <a href="donante-nuevo.php" class="btn btn-outline-purple btn-sm px-3 py-2 fw-semibold">
              <i class="bi bi-person-plus-fill me-1"></i> Nuevo Donante
            </a>
          </div>
        </div>
      </div>

      <!-- Barra de pestañas -->
      <ul class="nav sf-nav-tabs">
        <li class="nav-item">
          <a class="nav-link" href="index.php"><i class="bi bi-grid-1x2-fill me-1"></i> Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="donantes.php"><i class="bi bi-people-fill me-1"></i> Directorio de Donantes</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="donativos.php"><i class="bi bi-coin me-1"></i> Historial de Donativos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="mensajes.php"><i class="bi bi-envelope-paper-fill me-1"></i> Historial de Mensajes</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="historial-cambios.php"><i class="bi bi-clock-history me-1"></i> Historial de Cambios</a>
        </li>
      </ul>

      <!-- Resumen Métrico Rápido -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="kpi-mini d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-semibold text-uppercase">Total Recaudado</span>
              <h4 class="fw-bold text-success mb-0">$<?= number_format((float)$metricas['TotalRecaudado'], 2) ?></h4>
            </div>
            <div class="rounded-circle bg-success-subtle text-success p-2 fs-5">
              <i class="bi bi-cash-coin"></i>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="kpi-mini d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-semibold text-uppercase">Aportaciones Registradas</span>
              <h4 class="fw-bold text-dark mb-0"><?= (int)$metricas['TotalDonativos'] ?></h4>
            </div>
            <div class="rounded-circle bg-primary-subtle text-primary p-2 fs-5">
              <i class="bi bi-receipt"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Filtros y Buscador (búsqueda en tiempo real) -->
      <div class="card-sf p-3 mb-4">
        <div class="row g-2 align-items-center">
          <div class="col-md-7 col-sm-7">
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-search text-purple"></i></span>
              <input type="text" class="form-control" id="liveSearch"
                     placeholder="Buscar por donante o correo..."
                     value="<?= htmlspecialchars($search) ?>"
                     autocomplete="off">
              <span class="input-group-text bg-white border-start-0" id="searchSpinner" style="display:none!important;">
                <span class="spinner-border spinner-border-sm text-purple" role="status"></span>
              </span>
              <button class="btn btn-outline-secondary" id="btnLimpiar" title="Limpiar" style="display:none;" onclick="limpiarBusqueda()">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>
          <div class="col-md-5 col-sm-5">
            <select class="form-select" id="filtroMetodo" onchange="buscarDonativos()">
              <option value="">Todos los métodos de pago</option>
              <?php
              $metodos = [
                  'Transferencia Bancaria','Efectivo','PayPal','Cheques',
                  'Tarjeta de Crédito / Débito','Depósito en Ventanilla',
                  'Autogestivo','En Especie','Bienes / Mobiliario','Otro'
              ];
              foreach ($metodos as $m) {
                  $sel = ($metodoFiltro === $m) ? 'selected' : '';
                  echo "<option value=\"$m\" $sel>$m</option>";
              }
              ?>
            </select>
          </div>
        </div>
        <div class="mt-2" id="searchHint" style="display:none;">
          <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Resultados para: <strong id="searchHintText"></strong></small>
        </div>
      </div>

      <!-- Tabla de Donativos -->
      <div class="card-sf">
        <div class="card-sf-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-receipt-cutoff me-2 text-purple"></i> Registro de Aportaciones (<span id="contadorTotal"><?= $totalRegistros ?></span>)</span>
          <span class="badge bg-light text-dark border" id="paginaLabel">Página <?= $pagina ?> de <?= max(1, $totalPaginas) ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small text-uppercase">
                <tr>
                  <th style="width: 60px;">ID</th>
                  <th>Donante</th>
                  <th>Correo</th>
                  <th class="text-center">Método / Tipo</th>
                  <th class="text-center">Fecha</th>
                  <th class="text-end">Monto</th>
                  <th class="text-center" style="width: 120px;">Carta PDF</th>
                </tr>
              </thead>
              <tbody id="tbodyDonativos">
                <?php if (empty($donativos)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                      <i class="bi bi-cash-stack fs-1 d-block mb-2 text-secondary opacity-50"></i>
                      No se encontraron aportaciones registradas.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($donativos as $don): ?>
                    <tr>
                      <td class="text-muted fw-semibold">#<?= $don['ID_Donativo'] ?></td>
                      <td>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($don['Donante'] ?: 'Sin asignar') ?></div>
                      </td>
                      <td>
                        <span class="text-muted small">
                          <?= $don['Email'] ? htmlspecialchars($don['Email']) : 'Sin correo' ?>
                        </span>
                      </td>
                      <td class="text-center">
                        <?php
                          $tipoLimpio = trim($don['TipoDonacion'] ?? '');
                          if ($tipoLimpio === 'En Especie' || $tipoLimpio === 'Bienes / Mobiliario'):
                        ?>
                          <span class="badge px-2 py-1" style="background-color: #f3e8ff; color: #721896; border: 1px solid #d1a8e7; font-weight: 600; font-size: 0.8rem;">
                            <i class="bi bi-box-seam me-1" style="color: #721896;"></i><?= htmlspecialchars($tipoLimpio) ?>
                          </span>
                        <?php elseif ($tipoLimpio === 'Efectivo'): ?>
                          <span class="badge px-2 py-1" style="background-color: #eafaf1; color: #0f5132; border: 1px solid #c3e6cb; font-weight: 600; font-size: 0.8rem;">
                            <i class="bi bi-cash me-1" style="color: #198754;"></i>Efectivo
                          </span>
                        <?php elseif ($tipoLimpio === 'PayPal'): ?>
                          <span class="badge px-2 py-1" style="background-color: #e8f4fd; color: #003087; border: 1px solid #b8daff; font-weight: 600; font-size: 0.8rem;">
                            <i class="bi bi-paypal me-1" style="color: #0079C1;"></i>PayPal
                          </span>
                        <?php elseif ($tipoLimpio === 'Cheque' || $tipoLimpio === 'Cheques'): ?>
                          <span class="badge px-2 py-1" style="background-color: #f8f9fa; color: #495057; border: 1px solid #ced4da; font-weight: 600; font-size: 0.8rem;">
                            <?= htmlspecialchars($tipoLimpio) ?>
                          </span>
                        <?php elseif ($tipoLimpio === 'Autogestivo'): ?>
                          <span class="badge px-2 py-1" style="background-color: #fff9e6; color: #856404; border: 1px solid #ffeeba; font-weight: 600; font-size: 0.8rem;">Autogestivo</span>
                        <?php elseif ($tipoLimpio !== ''): ?>
                          <span class="badge px-2 py-1" style="background-color: #f8f9fa; color: #212529; border: 1px solid #dee2e6; font-weight: 600; font-size: 0.8rem;"><?= htmlspecialchars($tipoLimpio) ?></span>
                        <?php else: ?>
                          <span class="badge px-2 py-1" style="background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; font-weight: 500; font-size: 0.8rem;">No especificado</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <span class="small text-secondary">
                          <i class="bi bi-clock me-1"></i><?= !empty($don['FechaDonacion']) ? date('d/m/Y h:i A', strtotime($don['FechaDonacion'])) : 'Automática' ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <?php $esEspecie = ($don['TipoDonacion'] === 'En Especie' || $don['TipoDonacion'] === 'Bienes / Mobiliario' || (float)$don['MontoDonacion'] <= 0); ?>
                        <?php if (!$esEspecie && (float)$don['MontoDonacion'] > 0): ?>
                          <span class="badge bg-success-subtle text-success fs-6 fw-bold px-2 py-1">$<?= number_format((float)$don['MontoDonacion'], 2) ?></span>
                        <?php else: ?>
                          <span class="text-dark small fw-semibold" title="<?= htmlspecialchars($don['DescripcionEspecie'] ?? '') ?>">
                            <i class="bi bi-box-seam me-1 text-purple"></i><?= htmlspecialchars($don['DescripcionEspecie'] ? (mb_strlen($don['DescripcionEspecie']) > 30 ? mb_substr($don['DescripcionEspecie'], 0, 27) . '...' : $don['DescripcionEspecie']) : 'En especie') ?>
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <a href="carta-agradecimiento.php?id=<?= $don['ID_Donativo'] ?>" target="_blank"
                           class="btn btn-sm btn-outline-purple d-inline-flex align-items-center gap-1 px-2 py-1"
                           title="Ver / Descargar Carta de Agradecimiento PDF" style="font-size: 0.78rem; white-space: nowrap;">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2zm5.5 1.5v2a1 1 0 0 0 1 1h2l-3-3zM8 7.993c1.664-1.711 5.825 1.283 0 5.132-5.825-3.85-1.664-6.843 0-5.132z"/></svg>
                          <span>Carta</span>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <!-- Paginación dinámica -->
        <div id="paginacionContainer">
        <?php if ($totalPaginas > 1): ?>
          <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Mostrando <?= $offset + 1 ?> a <?= min($offset + $registrosPorPagina, $totalRegistros) ?> de <?= $totalRegistros ?></small>
            <nav><ul class="pagination pagination-sm mb-0">
              <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="#" onclick="irPagina(<?= $pagina - 1 ?>);return false;">Anterior</a>
              </li>
              <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                  <a class="page-link" href="#" onclick="irPagina(<?= $i ?>);return false;"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>">
                <a class="page-link" href="#" onclick="irPagina(<?= $pagina + 1 ?>);return false;">Siguiente</a>
              </li>
            </ul></nav>
          </div>
        <?php endif; ?>
        </div>
      </div>

    </main>
  </div>
</div>


    <!-- Modal: Generar Reporte PDF con Vista Previa al Lado (Agrupado por Categorías) -->
    <div class="modal fade" id="modalReportePDF" tabindex="-1" aria-labelledby="modalReportePDFLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
          <div class="modal-header text-white" style="background:#721896;">
            <div class="d-flex align-items-center gap-2">
              <h5 class="modal-title mb-0" id="modalReportePDFLabel">
                <i class="bi bi-file-earmark-pdf-fill me-2 text-danger"></i> Generar Reporte PDF de Aportaciones
              </h5>
              <span class="badge bg-white text-purple fw-semibold d-none d-sm-inline-block">Agrupado por Categorías</span>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          
          <div class="modal-body p-0">
            <div class="row g-0">
              
              <!-- Columna Izquierda: Parámetros y Acciones -->
              <div class="col-lg-4 col-md-5 p-3 p-md-4 border-end bg-light d-flex flex-column justify-content-between">
                <div>
                  <h6 class="fw-bold text-dark mb-3">
                    <i class="bi bi-sliders2 text-purple me-1"></i> Parámetros del Reporte
                  </h6>

                  <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted text-uppercase">Periodo Rápido</label>
                    <select class="form-select form-select-sm" id="rangoRapido" onchange="cambiarRangoFechas(this.value)">
                      <option value="mes_actual">Mes Actual (<?= date('F Y') ?>)</option>
                      <option value="mes_anterior">Mes Anterior</option>
                      <option value="anio_actual">Todo el Año Actual (<?= date('Y') ?>)</option>
                      <option value="ultimos_3_meses">Últimos 3 Meses</option>
                      <option value="todos">Todo el Historial Histórico</option>
                      <option value="personalizado" selected>Rango Personalizado de Fechas</option>
                    </select>
                  </div>

                  <div class="row g-2 mb-3">
                    <div class="col-6">
                      <label class="form-label fw-semibold small text-muted text-uppercase">Fecha Inicio</label>
                      <input type="date" class="form-control form-control-sm" name="fecha_inicio" id="rep_fecha_inicio" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-6">
                      <label class="form-label fw-semibold small text-muted text-uppercase">Fecha Fin</label>
                      <input type="date" class="form-control form-control-sm" name="fecha_fin" id="rep_fecha_fin" value="<?= date('Y-m-d') ?>">
                    </div>
                  </div>

                  <div class="card border-0 bg-white p-3 rounded-3 shadow-sm mb-3">
                    <div class="d-flex align-items-start gap-2">
                      <i class="bi bi-layers-fill text-purple fs-5 mt-1"></i>
                      <div>
                        <strong class="d-block text-dark small">Agrupación Automática por Categorías</strong>
                        <small class="text-muted d-block" style="font-size:0.77rem; line-height: 1.35;">
                          El reporte clasifica automáticamente los donativos en secciones independientes (Efectivo, Transferencia, PayPal, Cheques, En Especie, etc.) calculando subtotales por categoría y gran total.
                        </small>
                      </div>
                    </div>
                  </div>

                  <button type="button" class="btn btn-outline-purple btn-sm w-100 mb-2" onclick="recargarPreviewPDF()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Actualizar Vista Previa
                  </button>
                </div>

                <div class="pt-3 border-top mt-2">
                  <small class="text-muted d-block mb-2" style="font-size: 0.76rem;">
                    <i class="bi bi-shield-check text-success me-1"></i> Verifica la información al ladito antes de descargar.
                  </small>
                  <div class="d-grid">
                    <a href="reporte-pdf.php?fecha_inicio=<?= date('Y-m-01') ?>&fecha_fin=<?= date('Y-m-d') ?>&descargar=1" id="btnDescargarPDF" class="btn btn-danger fw-semibold shadow-sm py-2">
                      <i class="bi bi-download me-1"></i> Descargar Reporte PDF
                    </a>
                  </div>
                </div>
              </div>

              <!-- Columna Derecha: Visualización del Reporte al Ladito -->
              <div class="col-lg-8 col-md-7 p-3 bg-white d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom flex-wrap gap-2">
                  <div>
                    <span class="fw-semibold text-dark small">
                      <i class="bi bi-eye-fill text-purple me-1"></i> Vista Previa del Documento
                    </span>
                    <span id="previewEstadoBadge" class="badge bg-success-subtle text-success border border-success-subtle ms-1">
                      <i class="bi bi-check-circle-fill me-1"></i> Listo para verificar
                    </span>
                  </div>
                  <div>
                    <a href="reporte-pdf.php?fecha_inicio=<?= date('Y-m-01') ?>&fecha_fin=<?= date('Y-m-d') ?>&descargar=1" id="btnDescargarTop" class="btn btn-sm btn-danger fw-semibold" title="Descargar archivo PDF">
                      <i class="bi bi-download me-1"></i> Descargar Reporte PDF
                    </a>
                  </div>
                </div>

                <div class="position-relative flex-grow-1 border rounded-3 bg-light overflow-hidden" style="min-height: 520px; height: 560px;">
                  <div id="previewSpinner" class="position-absolute top-50 start-50 translate-middle text-center" style="display:none; z-index:10;">
                    <div class="spinner-border text-purple" role="status"></div>
                    <small class="text-muted d-block mt-2 fw-semibold">Generando vista previa del reporte...</small>
                  </div>
                  <iframe id="iframePreviewReporte" src="reporte-preview.php?fecha_inicio=<?= date('Y-m-01') ?>&fecha_fin=<?= date('Y-m-d') ?>" style="width: 100%; height: 100%; border: 0;" title="Vista previa del reporte PDF"></iframe>
                </div>
              </div>

            </div>
          </div>

          <div class="modal-footer bg-light py-2 px-3 justify-content-between">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <a href="reporte-pdf.php?fecha_inicio=<?= date('Y-m-01') ?>&fecha_fin=<?= date('Y-m-d') ?>&descargar=1" id="btnDescargarFooter" class="btn btn-sm btn-danger fw-semibold">
              <i class="bi bi-download me-1"></i> Descargar Reporte PDF
            </a>
          </div>
        </div>
      </div>
    </div>

    <script src="../../assets/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      function cambiarRangoFechas(tipo) {
        const hoy = new Date();
        const y = hoy.getFullYear();
        const m = hoy.getMonth();
        const fi = document.getElementById('rep_fecha_inicio');
        const ff = document.getElementById('rep_fecha_fin');
        const format = (d) => {
          const year = d.getFullYear();
          const month = String(d.getMonth() + 1).padStart(2, '0');
          const day = String(d.getDate()).padStart(2, '0');
          return `${year}-${month}-${day}`;
        };
        if (tipo === 'mes_actual') { 
          fi.value = format(new Date(y, m, 1)); 
          ff.value = format(new Date(y, m + 1, 0)); 
        } else if (tipo === 'mes_anterior') { 
          fi.value = format(new Date(y, m - 1, 1)); 
          ff.value = format(new Date(y, m, 0)); 
        } else if (tipo === 'anio_actual') { 
          fi.value = `${y}-01-01`; 
          ff.value = `${y}-12-31`; 
        } else if (tipo === 'ultimos_3_meses') { 
          fi.value = format(new Date(y, m - 2, 1)); 
          ff.value = format(new Date(y, m + 1, 0)); 
        } else if (tipo === 'todos') { 
          fi.value = ''; 
          ff.value = ''; 
        }
        recargarPreviewPDF();
      }

      function obtenerURLPreview() {
        const fi = document.getElementById('rep_fecha_inicio')?.value || '';
        const ff = document.getElementById('rep_fecha_fin')?.value || '';
        const params = new URLSearchParams();
        if (fi) params.set('fecha_inicio', fi);
        if (ff) params.set('fecha_fin', ff);
        return 'reporte-preview.php?' + params.toString();
      }

      function obtenerURLPDF(esDescarga = false) {
        const fi = document.getElementById('rep_fecha_inicio')?.value || '';
        const ff = document.getElementById('rep_fecha_fin')?.value || '';
        const params = new URLSearchParams();
        if (fi) params.set('fecha_inicio', fi);
        if (ff) params.set('fecha_fin', ff);
        if (esDescarga) params.set('descargar', '1');
        return 'reporte-pdf.php?' + params.toString();
      }

      function recargarPreviewPDF() {
        const iframe = document.getElementById('iframePreviewReporte');
        const spinner = document.getElementById('previewSpinner');
        const badge = document.getElementById('previewEstadoBadge');
        if (!iframe) return;

        const urlPreview = obtenerURLPreview();
        const urlVerPDF = obtenerURLPDF(false);
        const urlDescarga = obtenerURLPDF(true);

        const btnDesc = document.getElementById('btnDescargarPDF');
        const btnDescTop = document.getElementById('btnDescargarTop');
        const btnDescFooter = document.getElementById('btnDescargarFooter');
        if (btnDesc) btnDesc.href = urlDescarga;
        if (btnDescTop) btnDescTop.href = urlDescarga;
        if (btnDescFooter) btnDescFooter.href = urlDescarga;

        if (spinner) spinner.style.display = 'block';
        if (badge) {
          badge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle ms-1';
          badge.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Actualizando...';
        }

        iframe.src = urlPreview;
        iframe.onload = function() {
          if (spinner) spinner.style.display = 'none';
          if (badge) {
            badge.className = 'badge bg-success-subtle text-success border border-success-subtle ms-1';
            badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Listo para verificar';
          }
        };
      }

      document.addEventListener('DOMContentLoaded', () => {
        const modalReporte = document.getElementById('modalReportePDF');
        if (modalReporte) {
          modalReporte.addEventListener('shown.bs.modal', () => {
            recargarPreviewPDF();
          });
        }
        document.getElementById('rep_fecha_inicio')?.addEventListener('change', () => {
          const sel = document.getElementById('rangoRapido');
          if (sel) sel.value = 'personalizado';
          recargarPreviewPDF();
        });
        document.getElementById('rep_fecha_fin')?.addEventListener('change', () => {
          const sel = document.getElementById('rangoRapido');
          if (sel) sel.value = 'personalizado';
          recargarPreviewPDF();
        });
      });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      // ─── BÚSQUEDA EN TIEMPO REAL ─────────────────────────────────────────
      let searchTimeout = null;
      let paginaActual = <?= $pagina ?>;

      const inputSearch  = document.getElementById('liveSearch');
      const selMetodo    = document.getElementById('filtroMetodo');
      const btnLimpiar   = document.getElementById('btnLimpiar');
      const searchHint   = document.getElementById('searchHint');
      const searchHintTxt= document.getElementById('searchHintText');
      const spinner      = document.getElementById('searchSpinner');

      // SVG inline del icóno carta (reutilizable)
      const cartaSVG = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2zm5.5 1.5v2a1 1 0 0 0 1 1h2l-3-3zM8 7.993c1.664-1.711 5.825 1.283 0 5.132-5.825-3.85-1.664-6.843 0-5.132z"/></svg>`;

      inputSearch.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { paginaActual = 1; buscarDonativos(); }, 320);
        btnLimpiar.style.display = inputSearch.value.trim() !== '' ? '' : 'none';
      });

      function limpiarBusqueda() {
        inputSearch.value = '';
        btnLimpiar.style.display = 'none';
        searchHint.style.display = 'none';
        paginaActual = 1;
        buscarDonativos();
      }

      function irPagina(p) { paginaActual = p; buscarDonativos(); }

      function buscarDonativos() {
        const q      = inputSearch.value.trim();
        const metodo = selMetodo.value;
        const url    = `ajax/buscar_donativos.php?search=${encodeURIComponent(q)}&metodo=${encodeURIComponent(metodo)}&pagina=${paginaActual}`;

        spinner.style.removeProperty('display');
        fetch(url)
          .then(r => r.json())
          .then(data => {
            spinner.style.display = 'none';
            if (!data.success) return;
            renderizarTabla(data);
            if (q) { searchHintTxt.textContent = q; searchHint.style.display = ''; }
            else { searchHint.style.display = 'none'; }
          })
          .catch(() => { spinner.style.display = 'none'; });
      }

      function badgeMetodo(tipo) {
        const t = (tipo || '').trim();
        if (t === 'En Especie' || t === 'Bienes / Mobiliario')
          return `<span class="badge px-2 py-1" style="background-color:#f3e8ff;color:#721896;border:1px solid #d1a8e7;font-weight:600;font-size:0.8rem;">${escHtml(t)}</span>`;
        if (t === 'Efectivo')
          return `<span class="badge px-2 py-1" style="background-color:#eafaf1;color:#0f5132;border:1px solid #c3e6cb;font-weight:600;font-size:0.8rem;">Efectivo</span>`;
        if (t === 'PayPal')
          return `<span class="badge px-2 py-1" style="background-color:#e8f4fd;color:#003087;border:1px solid #b8daff;font-weight:600;font-size:0.8rem;">PayPal</span>`;
        if (t === 'Cheque' || t === 'Cheques')
          return `<span class="badge px-2 py-1" style="background-color:#f8f9fa;color:#495057;border:1px solid #ced4da;font-weight:600;font-size:0.8rem;">${escHtml(t)}</span>`;
        if (t === 'Autogestivo')
          return `<span class="badge px-2 py-1" style="background-color:#fff9e6;color:#856404;border:1px solid #ffeeba;font-weight:600;font-size:0.8rem;">Autogestivo</span>`;
        if (t !== '')
          return `<span class="badge px-2 py-1" style="background-color:#f8f9fa;color:#212529;border:1px solid #dee2e6;font-weight:600;font-size:0.8rem;">${escHtml(t)}</span>`;
        return `<span class="badge px-2 py-1" style="background-color:#f8f9fa;color:#6c757d;border:1px solid #dee2e6;font-weight:500;font-size:0.8rem;">No especificado</span>`;
      }

      function renderizarTabla(data) {
        const tbody = document.getElementById('tbodyDonativos');
        document.getElementById('contadorTotal').textContent = data.totalRegistros;
        document.getElementById('paginaLabel').textContent  = `Página ${data.pagina} de ${data.totalPaginas}`;

        if (!data.donativos || data.donativos.length === 0) {
          tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron aportaciones con los filtros seleccionados.</td></tr>`;
          document.getElementById('paginacionContainer').innerHTML = '';
          return;
        }

        tbody.innerHTML = data.donativos.map(d => {
          const monto    = parseFloat(d.MontoDonacion || 0);
          const esEspecie = (d.TipoDonacion === 'En Especie' || d.TipoDonacion === 'Bienes / Mobiliario' || monto <= 0);
          const montoHtml = !esEspecie && monto > 0
            ? `<span class="badge bg-success-subtle text-success fs-6 fw-bold px-2 py-1">$${monto.toLocaleString('es-MX',{minimumFractionDigits:2})}</span>`
            : `<span class="text-dark small fw-semibold" title="${escHtml(d.DescripcionEspecie||'')}"><i class="bi bi-box-seam me-1 text-purple"></i>${escHtml(d.DescripcionEspecie ? (d.DescripcionEspecie.length>30?d.DescripcionEspecie.slice(0,27)+'...':d.DescripcionEspecie) : 'En especie')}</span>`;

          const fecha = d.FechaDonacion
            ? new Date(d.FechaDonacion).toLocaleString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true})
            : 'Automática';

          return `<tr>
            <td class="text-muted fw-semibold">#${d.ID_Donativo}</td>
            <td><div class="fw-semibold text-dark">${escHtml(d.Donante||'Sin asignar')}</div></td>
            <td><span class="text-muted small">${escHtml(d.Email||'Sin correo')}</span></td>
            <td class="text-center">${badgeMetodo(d.TipoDonacion)}</td>
            <td class="text-center"><span class="small text-secondary"><i class="bi bi-clock me-1"></i>${escHtml(fecha)}</span></td>
            <td class="text-end">${montoHtml}</td>
            <td class="text-center">
              <a href="carta-agradecimiento.php?id=${d.ID_Donativo}" target="_blank"
                 class="btn btn-sm btn-outline-purple d-inline-flex align-items-center gap-1 px-2 py-1"
                 title="Ver / Descargar Carta de Agradecimiento PDF" style="font-size: 0.78rem; white-space: nowrap;">
                ${cartaSVG} <span>Carta</span>
              </a>
            </td>
          </tr>`;
        }).join('');

        renderizarPaginacion(data);
      }

      function renderizarPaginacion(data) {
        const cont = document.getElementById('paginacionContainer');
        if (data.totalPaginas <= 1) { cont.innerHTML = ''; return; }
        const offset = (data.pagina - 1) * 10;
        const hasta  = Math.min(offset + 10, data.totalRegistros);
        let links = '';
        for (let i = 1; i <= data.totalPaginas; i++) {
          links += `<li class="page-item ${i === data.pagina ? 'active' : ''}"><a class="page-link" href="#" onclick="irPagina(${i});return false;">${i}</a></li>`;
        }
        cont.innerHTML = `
          <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Mostrando ${offset + 1} a ${hasta} de ${data.totalRegistros}</small>
            <nav><ul class="pagination pagination-sm mb-0">
              <li class="page-item ${data.pagina <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina-1});return false;">Anterior</a></li>
              ${links}
              <li class="page-item ${data.pagina >= data.totalPaginas ? 'disabled' : ''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina+1});return false;">Siguiente</a></li>
            </ul></nav>
          </div>`;
      }

      function escHtml(str) {
        return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
      }
      // ─────────────────────────────────────────

      // Mensajes SweetAlert2 de URL
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('status') === 'saved') {
        Swal.fire({ icon: 'success', title: 'Guardado', text: 'El donativo se registró con éxito.', timer: 2500, showConfirmButton: false });
      } else if (urlParams.get('status') === 'error') {
        Swal.fire({ icon: 'error', title: 'Error', text: urlParams.get('msg') || 'Ocurrió un error en la operación.' });
      }
    </script>
  </body>
</html>

