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
            <button class="btn btn-outline-danger btn-sm px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalReportePDF">
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

      <!-- Filtros y Buscador -->
      <div class="card-sf p-3 mb-4">
        <form method="GET" action="donativos.php" class="row g-2 align-items-center">
          <div class="col-md-6 col-sm-12">
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
              <input type="text" class="form-control" name="search" placeholder="Buscar por donante o correo..." value="<?= htmlspecialchars($search) ?>">
            </div>
          </div>
          <div class="col-md-4 col-sm-8">
            <select class="form-select" name="metodo">
              <option value="">Todos los métodos de pago</option>
              <?php
              $metodos = [
                  'Transferencia Bancaria',
                  'Efectivo',
                  'PayPal',
                  'Cheques',
                  'Tarjeta de Crédito / Débito',
                  'Depósito en Ventanilla',
                  'Autogestivo',
                  'En Especie',
                  'Bienes / Mobiliario',
                  'Otro'
              ];
              foreach ($metodos as $m) {
                  $sel = ($metodoFiltro === $m) ? 'selected' : '';
                  echo "<option value=\"$m\" $sel>$m</option>";
              }
              ?>
            </select>
          </div>
          <div class="col-md-2 col-sm-4 d-flex gap-2">
            <button type="submit" class="btn btn-purple w-100 fw-semibold">Filtrar</button>
            <?php if ($search !== '' || $metodoFiltro !== ''): ?>
              <a href="donativos.php" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="bi bi-arrow-repeat"></i></a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Tabla de Donativos -->
      <div class="card-sf">
        <div class="card-sf-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-receipt-cutoff me-2 text-purple"></i> Registro de Aportaciones (<?= $totalRegistros ?>)</span>
          <span class="badge bg-light text-dark border">Página <?= $pagina ?> de <?= max(1, $totalPaginas) ?></span>
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
                  <th class="text-center">Fecha de Registro</th>
                  <th class="text-end">Monto / Detalle</th>
                  <th class="text-center" style="width: 120px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($donativos)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                      <i class="bi bi-cash-stack fs-1 d-block mb-2 text-secondary opacity-50"></i>
                      No se encontraron donaciones registradas con los filtros seleccionados.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($donativos as $don): ?>
                    <tr>
                      <td class="text-muted fw-semibold">#<?= $don['ID_Donativo'] ?></td>
                      <td>
                        <div class="fw-semibold text-dark">
                          <?= htmlspecialchars($don['Donante'] ?: 'Donante no asignado') ?>
                        </div>
                      </td>
                      <td>
                        <?php if ($don['Email']): ?>
                          <span class="text-muted small"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($don['Email']) ?></span>
                        <?php else: ?>
                          <span class="text-muted small">Sin correo</span>
                        <?php endif; ?>
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
                            <i class="bi bi-card-checklist me-1" style="color: #495057;"></i><?= htmlspecialchars($tipoLimpio) ?>
                          </span>
                        <?php elseif ($tipoLimpio === 'Autogestivo'): ?>
                          <span class="badge px-2 py-1" style="background-color: #fff9e6; color: #856404; border: 1px solid #ffeeba; font-weight: 600; font-size: 0.8rem;">
                            <i class="bi bi-award me-1" style="color: #d39e00;"></i>Autogestivo
                          </span>
                        <?php elseif ($tipoLimpio !== ''): ?>
                          <span class="badge px-2 py-1" style="background-color: #f8f9fa; color: #212529; border: 1px solid #dee2e6; font-weight: 600; font-size: 0.8rem;">
                            <i class="bi bi-credit-card-2-front me-1" style="color: #0d6efd;"></i><?= htmlspecialchars($tipoLimpio) ?>
                          </span>
                        <?php else: ?>
                          <span class="badge px-2 py-1" style="background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; font-weight: 500; font-size: 0.8rem;">
                            <i class="bi bi-question-circle me-1"></i>No especificado
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <span class="small text-secondary">
                          <i class="bi bi-clock me-1"></i><?= !empty($don['FechaDonacion']) ? date('d/m/Y h:i A', strtotime($don['FechaDonacion'])) : 'Automática' ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <?php 
                          $esEspecieItem = ($don['TipoDonacion'] === 'En Especie' || $don['TipoDonacion'] === 'Bienes / Mobiliario' || (float)$don['MontoDonacion'] <= 0);
                        ?>
                        <?php if (!$esEspecieItem && (float)$don['MontoDonacion'] > 0): ?>
                          <span class="badge bg-success-subtle text-success fs-6 fw-bold px-2 py-1">
                            $<?= number_format((float)$don['MontoDonacion'], 2) ?>
                          </span>
                        <?php else: ?>
                          <span class="text-dark small fw-semibold" title="<?= htmlspecialchars($don['DescripcionEspecie'] ?? '') ?>">
                            <i class="bi bi-box-seam me-1 text-purple"></i><?= htmlspecialchars($don['DescripcionEspecie'] ? (mb_strlen($don['DescripcionEspecie']) > 30 ? mb_substr($don['DescripcionEspecie'], 0, 27) . '...' : $don['DescripcionEspecie']) : 'En especie') ?>
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="carta-agradecimiento.php?id=<?= $don['ID_Donativo'] ?>" target="_blank" class="btn btn-outline-purple" title="Descargar / Ver Carta de Agradecimiento (PDF)">
                            <i class="bi bi-file-earmark-heart-fill"></i>
                          </a>
                          <a href="donativo-editar.php?id=<?= $don['ID_Donativo'] ?>" class="btn btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil-square"></i>
                          </a>
                          <button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="confirmarEliminarDonativo(<?= $don['ID_Donativo'] ?>, '<?= number_format((float)$don['MontoDonacion'], 2) ?>')">
                            <i class="bi bi-trash3"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
          <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">Mostrando aportaciones <?= $offset + 1 ?> a <?= min($offset + $registrosPorPagina, $totalRegistros) ?> de <?= $totalRegistros ?></small>
            <nav>
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&search=<?= urlencode($search) ?>&metodo=<?= urlencode($metodoFiltro) ?>">Anterior</a>
                </li>
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                  <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?>&search=<?= urlencode($search) ?>&metodo=<?= urlencode($metodoFiltro) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&search=<?= urlencode($search) ?>&metodo=<?= urlencode($metodoFiltro) ?>">Siguiente</a>
                </li>
              </ul>
            </nav>
          </div>
        <?php endif; ?>
      </div>

    </main>
  </div>
</div>

    <!-- Modal: Generar Reporte PDF por Rango de Fechas -->
    <div class="modal fade" id="modalReportePDF" tabindex="-1" aria-labelledby="modalReportePDFLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header text-white" style="background:#721896;">
            <h5 class="modal-title" id="modalReportePDFLabel"><i class="bi bi-file-earmark-pdf-fill me-2 text-danger"></i> Generar Reporte PDF de Aportaciones</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="reporte-pdf.php" method="GET" target="_blank">
            <div class="modal-body p-4">
              
              <div class="mb-3">
                <label class="form-label fw-semibold">Seleccionar Periodo Rápido</label>
                <select class="form-select" id="rangoRapido" onchange="cambiarRangoFechas(this.value)">
                  <option value="mes_actual">Mes Actual (<?= date('F Y') ?>)</option>
                  <option value="mes_anterior">Mes Anterior</option>
                  <option value="anio_actual">Todo el Año Actual (<?= date('Y') ?>)</option>
                  <option value="ultimos_3_meses">Últimos 3 Meses</option>
                  <option value="todos">Todo el Historial Histórico</option>
                  <option value="personalizado" selected>Rango Personalizado de Fechas</option>
                </select>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-6">
                  <label class="form-label fw-semibold">Fecha Inicio</label>
                  <input type="date" class="form-control" name="fecha_inicio" id="rep_fecha_inicio" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold">Fecha Fin</label>
                  <input type="date" class="form-control" name="fecha_fin" id="rep_fecha_fin" value="<?= date('Y-m-d') ?>">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Filtrar por Método / Tipo de Donativo</label>
                <select class="form-select" name="metodo" id="rep_metodo">
                  <option value="">Todos los Métodos y Tipos</option>
                  <option value="Transferencia Bancaria">Transferencia Bancaria (SPEI)</option>
                  <option value="Efectivo">Efectivo</option>
                  <option value="PayPal">PayPal</option>
                  <option value="Cheques">Cheques / Cheque</option>
                  <option value="Tarjeta de Crédito / Débito">Tarjeta de Crédito / Débito</option>
                  <option value="Depósito en Ventanilla">Depósito en Ventanilla</option>
                  <option value="Autogestivo">Autogestivo</option>
                  <option value="En Especie">En Especie</option>
                  <option value="Bienes / Mobiliario">Bienes / Mobiliario</option>
                </select>
              </div>

              <div class="alert alert-light border p-2 small text-muted mb-0 rounded-3">
                <i class="bi bi-info-circle text-purple me-1"></i> El reporte generado incluirá membrete institucional oficial de GesMujer, resumen ejecutivo, estadísticas métricas, desglose por método y tabla detallada.
              </div>

            </div>
            <div class="modal-footer bg-light justify-content-between">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-danger fw-semibold"><i class="bi bi-download me-1"></i> Descargar Reporte PDF</button>
            </div>
          </form>
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

        const format = (d) => d.toISOString().split('T')[0];

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
      }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      function confirmarEliminarDonativo(id, monto) {
        Swal.fire({
          title: '¿Eliminar donativo?',
          text: `Se eliminará el donativo por $${monto}. Esta acción no se puede deshacer.`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = `acciones/eliminar-donativo.php?id=${id}`;
          }
        });
      }

      // Mensajes SweetAlert2 de URL
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('status') === 'deleted') {
        Swal.fire({ icon: 'success', title: 'Eliminado', text: 'El donativo fue eliminado correctamente.', timer: 2500, showConfirmButton: false });
      } else if (urlParams.get('status') === 'saved') {
        Swal.fire({ icon: 'success', title: 'Guardado', text: 'El donativo se registró con éxito.', timer: 2500, showConfirmButton: false });
      } else if (urlParams.get('status') === 'error') {
        Swal.fire({ icon: 'error', title: 'Error', text: urlParams.get('msg') || 'Ocurrió un error en la operación.' });
      }
    </script>
  </body>
</html>

