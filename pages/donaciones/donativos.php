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
        SELECT d.ID_Donativo, d.MontoDonacion, d.TipoDonacion, d.ID_Donante,
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
            <h2 class="h3 fw-bold mb-0 text-dark">Historial de Donaciones</h2>
            <p class="text-muted small mb-0">Control y registro cronológico de aportaciones económicas recibidas</p>
          </div>
          
          <div class="d-flex flex-wrap gap-2">
            <a href="donativo-nuevo.php" class="btn btn-purple btn-sm px-3 py-2 fw-semibold shadow-sm">
              <i class="bi bi-cash-stack me-1"></i> + Nueva Donación
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
          <a class="nav-link active" href="donativos.php"><i class="bi bi-coin me-1"></i> Historial de Donaciones</a>
        </li>
      </ul>

      <!-- Resumen Métrico Rápido -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
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
        <div class="col-md-4">
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
        <div class="col-md-4">
          <div class="kpi-mini d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-semibold text-uppercase">Donación Promedio</span>
              <h4 class="fw-bold text-purple mb-0" style="color:#721896;">$<?= number_format((float)$metricas['PromedioDonacion'], 2) ?></h4>
            </div>
            <div class="rounded-circle p-2 fs-5" style="background:#f3e8ff; color:#721896;">
              <i class="bi bi-pie-chart-fill"></i>
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
              $metodos = ['Transferencia Bancaria', 'Efectivo', 'Depósito en Ventanilla', 'Tarjeta de Crédito / Débito', 'Cheque', 'En Especie', 'Otro'];
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
                  <th class="text-center">Método de Aportación</th>
                  <th class="text-end">Monto Recibido</th>
                  <th class="text-center" style="width: 120px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($donativos)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
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
                        <span class="badge bg-light text-secondary border px-2 py-1">
                          <i class="bi bi-credit-card-2-front me-1"></i><?= htmlspecialchars($don['TipoDonacion'] ?: 'No especificado') ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <span class="badge bg-success-subtle text-success fs-6 fw-bold px-2 py-1">
                          $<?= number_format((float)$don['MontoDonacion'], 2) ?>
                        </span>
                      </td>
                      <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
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

    <script src="../../assets/dist/js/bootstrap.bundle.min.js"></script>
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

