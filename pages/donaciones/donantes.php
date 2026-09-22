<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../db/config.php';

// Parámetros de paginación y búsqueda
$registrosPorPagina = 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $registrosPorPagina;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $whereSQL = "";
    $params = [];

    if ($search !== '') {
        $whereSQL = " WHERE (Nombre LIKE :s OR ApellidoPaterno LIKE :s OR ApellidoMaterno LIKE :s OR Email LIKE :s OR Telefono LIKE :s)";
        $params[':s'] = "%$search%";
    }

    // Conteo total
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM donantes" . $whereSQL);
    foreach ($params as $key => $val) {
        $stmtCount->bindValue($key, $val);
    }
    $stmtCount->execute();
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

    // Consulta con total aportado acumulado
    $sql = "
        SELECT d.ID_Donante, d.Nombre, d.ApellidoPaterno, d.ApellidoMaterno, d.Email, d.Telefono,
               COALESCE(SUM(dn.MontoDonacion), 0) AS TotalAportado,
               COUNT(dn.ID_Donativo) AS NumeroDonaciones
        FROM donantes d
        LEFT JOIN donativos dn ON d.ID_Donante = dn.ID_Donante
        $whereSQL
        GROUP BY d.ID_Donante
        ORDER BY d.ID_Donante DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $donantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $donantes = [];
    $totalRegistros = 0;
    $totalPaginas = 1;
    $errorMsg = $e->getMessage();
}
?>
<!doctype html>
<html lang="es" data-bs-theme="auto">
  <head>
    <script src="../../assets/js/color-modes.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Directorio de Donantes | GesMujer</title>

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
    </style>
  </head>
  <body>

    <!-- Menú superior -->
    <?php require_once __DIR__ . '/../header.php'; ?>

    <!-- Menú lateral -->
    <?php require_once __DIR__ . '/../footer.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

      <!-- Encabezado Salesforce Header -->
      <div class="sf-header shadow-sm">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <h2 class="h3 fw-bold mb-0 text-dark">Directorio de Donantes</h2>
            <p class="text-muted small mb-0">Gestión y consulta de personas e instituciones donantes</p>
          </div>
          
          <div class="d-flex flex-wrap gap-2">
            <a href="donante-nuevo.php" class="btn btn-purple btn-sm px-3 py-2 fw-semibold shadow-sm">
              <i class="bi bi-person-plus-fill me-1"></i> + Nuevo Donante
            </a>
            <a href="donativo-nuevo.php" class="btn btn-outline-purple btn-sm px-3 py-2 fw-semibold">
              <i class="bi bi-cash-stack me-1"></i> Registrar Donación
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
          <a class="nav-link active" href="donantes.php"><i class="bi bi-people-fill me-1"></i> Directorio de Donantes</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="donativos.php"><i class="bi bi-coin me-1"></i> Historial de Donaciones</a>
        </li>
      </ul>

      <!-- Filtro y buscador -->
      <div class="card-sf p-3 mb-4">
        <form method="GET" action="donantes.php" class="row g-2 align-items-center">
          <div class="col-md-9 col-sm-8">
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
              <input type="text" class="form-control" name="search" placeholder="Buscar por nombre, email o teléfono..." value="<?= htmlspecialchars($search) ?>">
            </div>
          </div>
          <div class="col-md-3 col-sm-4 d-flex gap-2">
            <button type="submit" class="btn btn-purple w-100 fw-semibold">Buscar</button>
            <?php if ($search !== ''): ?>
              <a href="donantes.php" class="btn btn-outline-secondary" title="Limpiar"><i class="bi bi-arrow-repeat"></i></a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Tabla de Donantes -->
      <div class="card-sf">
        <div class="card-sf-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-person-lines-fill me-2 text-primary"></i> Donantes Registrados (<?= $totalRegistros ?>)</span>
          <span class="badge bg-light text-dark border">Página <?= $pagina ?> de <?= max(1, $totalPaginas) ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small text-uppercase">
                <tr>
                  <th style="width: 60px;">ID</th>
                  <th>Nombre Completo</th>
                  <th>Correo Electrónico</th>
                  <th>Teléfono</th>
                  <th class="text-center">Donaciones</th>
                  <th class="text-end">Total Aportado</th>
                  <th class="text-center" style="width: 160px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($donantes)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                      <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                      No se encontraron donantes registrados.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($donantes as $d): ?>
                    <tr>
                      <td class="text-muted fw-semibold">#<?= $d['ID_Donante'] ?></td>
                      <td>
                        <div class="fw-semibold text-dark">
                          <?= htmlspecialchars($d['Nombre'] . ' ' . $d['ApellidoPaterno'] . ' ' . $d['ApellidoMaterno']) ?>
                        </div>
                      </td>
                      <td>
                        <?php if ($d['Email']): ?>
                          <a href="mailto:<?= htmlspecialchars($d['Email']) ?>" class="text-decoration-none text-muted">
                            <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($d['Email']) ?>
                          </a>
                        <?php else: ?>
                          <span class="text-muted small">Sin correo</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($d['Telefono']): ?>
                          <span class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($d['Telefono']) ?></span>
                        <?php else: ?>
                          <span class="text-muted small">Sin teléfono</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1">
                          <?= (int)$d['NumeroDonaciones'] ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <span class="fw-bold text-success">
                          $<?= number_format((float)$d['TotalAportado'], 2) ?>
                        </span>
                      </td>
                      <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="donativo-nuevo.php?id_donante=<?= $d['ID_Donante'] ?>" class="btn btn-outline-success" title="Registrar Aportación">
                            <i class="bi bi-plus-circle"></i>
                          </a>
                          <a href="donante-editar.php?id=<?= $d['ID_Donante'] ?>" class="btn btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil-square"></i>
                          </a>
                          <button type="button" class="btn btn-outline-danger" title="Eliminar" onclick="confirmarEliminar(<?= $d['ID_Donante'] ?>, '<?= htmlspecialchars(addslashes($d['Nombre'])) ?>')">
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
            <small class="text-muted">Mostrando registros <?= $offset + 1 ?> a <?= min($offset + $registrosPorPagina, $totalRegistros) ?> de <?= $totalRegistros ?></small>
            <nav>
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&search=<?= urlencode($search) ?>">Anterior</a>
                </li>
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                  <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>">
                  <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&search=<?= urlencode($search) ?>">Siguiente</a>
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
      function confirmarEliminar(id, nombre) {
        Swal.fire({
          title: '¿Eliminar donante?',
          text: `Se eliminará el donante "${nombre}". Esta acción no se puede deshacer.`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = `acciones/eliminar-donante.php?id=${id}`;
          }
        });
      }

      // Mensajes SweetAlert2 de URL
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('status') === 'deleted') {
        Swal.fire({ icon: 'success', title: 'Eliminado', text: 'El donante fue eliminado correctamente.', timer: 2500, showConfirmButton: false });
      } else if (urlParams.get('status') === 'saved') {
        Swal.fire({ icon: 'success', title: 'Guardado', text: 'Los datos del donante se guardaron con éxito.', timer: 2500, showConfirmButton: false });
      } else if (urlParams.get('status') === 'error') {
        Swal.fire({ icon: 'error', title: 'Error', text: urlParams.get('msg') || 'Ocurrió un error en la operación.' });
      }
    </script>
  </body>
</html>

