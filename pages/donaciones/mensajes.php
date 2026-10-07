<?php
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';

// Filtros y búsqueda
$search = trim($_GET['search'] ?? '');
$filtroEstado = trim($_GET['estado'] ?? '');
$filtroTipo = trim($_GET['tipo'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(NombreDestino LIKE :search OR EmailDestino LIKE :search OR Asunto LIKE :search OR Mensaje LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filtroEstado !== '') {
    $where[] = "Estado = :estado";
    $params[':estado'] = $filtroEstado;
}

if ($filtroTipo !== '') {
    $where[] = "TipoMensaje = :tipo";
    $params[':tipo'] = $filtroTipo;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Métricas de Mensajes
try {
    $totalMensajes = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes")->fetchColumn();
    $totalEnviados = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes WHERE Estado = 'Enviado'")->fetchColumn();
    $totalBorradores = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes WHERE Estado = 'Borrador'")->fetchColumn();
    $totalCumpleanos = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes WHERE TipoMensaje = 'Cumpleaños'")->fetchColumn();
} catch (PDOException $e) {
    $totalMensajes = $totalEnviados = $totalBorradores = $totalCumpleanos = 0;
}

// Paginación
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$registrosPorPagina = 10;
$offset = ($pagina - 1) * $registrosPorPagina;

// Conteo filtrado
try {
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM donaciones_mensajes $whereSql");
    $stmtCount->execute($params);
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);
} catch (PDOException $e) {
    $totalRegistros = 0;
    $totalPaginas = 1;
}

// Lista de mensajes
try {
    $sqlList = "
        SELECT *
        FROM donaciones_mensajes
        $whereSql
        ORDER BY ID_Mensaje DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmtList = $conn->prepare($sqlList);
    foreach ($params as $k => $v) {
        $stmtList->bindValue($k, $v);
    }
    $stmtList->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtList->execute();
    $mensajesList = $stmtList->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensajesList = [];
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Mensajes y Correos - SYSGES</title>
    <link href="../../assets/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="../dashboard.css" rel="stylesheet">
    
    <style>
      body {
        background-color: #f8f9fa;
        color: #333;
      }
      .sf-header {
        background: #ffffff;
        border-bottom: 1px solid #e3e6f0;
        padding: 1.25rem 1.5rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
      }
      .sf-nav-tabs {
        border-bottom: 2px solid #e3e6f0;
        margin-bottom: 1.5rem;
      }
      .sf-nav-tabs .nav-link {
        color: #6c757d;
        font-weight: 600;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 0.75rem 1.25rem;
      }
      .sf-nav-tabs .nav-link:hover {
        color: #721896;
      }
      .sf-nav-tabs .nav-link.active {
        color: #721896;
        border-bottom: 3px solid #721896;
        background: transparent;
      }
      .card-sf {
        border: 1px solid #e3e6f0;
        border-radius: 0.75rem;
        background: #ffffff;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.04);
      }
      .card-sf-header {
        background: #fdf8ff;
        border-bottom: 1px solid #f0d8ff;
        padding: 1rem 1.25rem;
        font-weight: 700;
        color: #721896;
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
      }
      .btn-purple {
        background-color: #721896;
        color: #ffffff;
      }
      .btn-purple:hover {
        background-color: #5a1277;
        color: #ffffff;
      }
    </style>
  </head>
  <body>
    
    <!-- Cabecera principal -->
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <!-- Menú lateral -->
        <?php require_once __DIR__ . '/footer.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
          
          <!-- Encabezado Header -->
          <div class="sf-header shadow-sm">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
              <div>
                <h2 class="h3 fw-bold mb-0 text-dark">Historial de Mensajes y Correos</h2>
                <p class="text-muted small mb-0">Registro completo de comunicaciones enviadas, borradores y mensajes automáticos</p>
              </div>
              <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-purple btn-sm px-3 py-2 fw-semibold">
                  <i class="bi bi-plus-circle me-1"></i> Redactar Nuevo Correo
                </a>
              </div>
            </div>
          </div>

          <!-- Barra de pestañas estilo Salesforce -->
          <ul class="nav sf-nav-tabs">
            <li class="nav-item">
              <a class="nav-link" href="index.php"><i class="bi bi-grid-1x2-fill me-1"></i> Inicio</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="donantes.php"><i class="bi bi-people-fill me-1"></i> Directorio de Donantes</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="donativos.php"><i class="bi bi-coin me-1"></i> Historial de Donativos</a>
            </li>
            <li class="nav-item">
              <a class="nav-link active" href="mensajes.php"><i class="bi bi-envelope-paper-fill me-1"></i> Historial de Mensajes</a>
            </li>
          </ul>

          <!-- Resumen Métrico Rápido -->
          <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
              <div class="card-sf p-3">
                <span class="text-muted small fw-semibold text-uppercase">Total Registrados</span>
                <h4 class="fw-bold text-dark mb-0 mt-1"><?= number_format($totalMensajes) ?></h4>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card-sf p-3">
                <span class="text-muted small fw-semibold text-uppercase">Enviados con Éxito</span>
                <h4 class="fw-bold text-success mb-0 mt-1"><?= number_format($totalEnviados) ?></h4>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card-sf p-3">
                <span class="text-muted small fw-semibold text-uppercase">Borradores Guardados</span>
                <h4 class="fw-bold text-warning mb-0 mt-1"><?= number_format($totalBorradores) ?></h4>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card-sf p-3">
                <span class="text-muted small fw-semibold text-uppercase">Automáticos Cumpleaños</span>
                <h4 class="fw-bold text-purple mb-0 mt-1"><?= number_format($totalCumpleanos) ?></h4>
              </div>
            </div>
          </div>

          <!-- Filtro y buscador -->
          <div class="card-sf mb-4">
            <div class="card-body p-3">
              <form method="GET" action="mensajes.php" class="row g-2">
                <div class="col-md-5">
                  <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" name="search" placeholder="Buscar por destinatario, correo o asunto..." value="<?= htmlspecialchars($search) ?>">
                  </div>
                </div>
                <div class="col-md-3">
                  <select name="estado" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos los Estados</option>
                    <option value="Enviado" <?= $filtroEstado === 'Enviado' ? 'selected' : '' ?>>Enviados</option>
                    <option value="Borrador" <?= $filtroEstado === 'Borrador' ? 'selected' : '' ?>>Borradores</option>
                    <option value="Fallido" <?= $filtroEstado === 'Fallido' ? 'selected' : '' ?>>Fallidos</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <select name="tipo" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos los Tipos</option>
                    <option value="Manual" <?= $filtroTipo === 'Manual' ? 'selected' : '' ?>>Manuales</option>
                    <option value="Agradecimiento" <?= $filtroTipo === 'Agradecimiento' ? 'selected' : '' ?>>Agradecimientos</option>
                    <option value="Cumpleaños" <?= $filtroTipo === 'Cumpleaños' ? 'selected' : '' ?>>Cumpleaños</option>
                  </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                  <button type="submit" class="btn btn-purple w-100 fw-semibold">Filtrar</button>
                  <?php if ($search !== '' || $filtroEstado !== '' || $filtroTipo !== ''): ?>
                    <a href="mensajes.php" class="btn btn-light" title="Limpiar Filtros"><i class="bi bi-x-lg"></i></a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>

          <!-- Tabla de Mensajes -->
          <div class="card-sf">
            <div class="card-sf-header d-flex justify-content-between align-items-center">
              <span><i class="bi bi-envelope-paper-heart me-2"></i> Registro de Mensajes</span>
              <small class="text-muted fw-normal">Total: <?= number_format($totalRegistros) ?> registros</small>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Estado</th>
                      <th>Tipo</th>
                      <th>Destinatario</th>
                      <th>Correo Electrónico</th>
                      <th>Asunto</th>
                      <th class="text-center">Fecha de Registro</th>
                      <th class="text-center">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($mensajesList)): ?>
                      <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                          <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                          No se encontraron mensajes registrados con los filtros aplicados.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($mensajesList as $m): ?>
                        <tr>
                          <td>
                            <?php if ($m['Estado'] === 'Enviado'): ?>
                              <span class="badge bg-success-subtle text-success px-2 py-1"><i class="bi bi-check-circle me-1"></i> Enviado</span>
                            <?php elseif ($m['Estado'] === 'Borrador'): ?>
                              <span class="badge bg-warning-subtle text-warning px-2 py-1"><i class="bi bi-file-earmark-text me-1"></i> Borrador</span>
                            <?php else: ?>
                              <span class="badge bg-danger-subtle text-danger px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i> Fallido</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ($m['TipoMensaje'] === 'Cumpleaños'): ?>
                              <span class="badge bg-danger-subtle text-danger"><i class="bi bi-gift me-1"></i> Cumpleaños</span>
                            <?php elseif ($m['TipoMensaje'] === 'Agradecimiento'): ?>
                              <span class="badge px-2 py-1" style="background-color:#f3e8ff; color:#721896;"><i class="bi bi-heart-fill me-1"></i> Agradecimiento</span>
                            <?php else: ?>
                              <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-send me-1"></i> Manual</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <strong class="text-dark"><?= htmlspecialchars($m['NombreDestino']) ?></strong>
                          </td>
                          <td>
                            <span class="small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($m['EmailDestino']) ?></span>
                          </td>
                          <td>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($m['Asunto']) ?></span>
                          </td>
                          <td class="text-center">
                            <span class="small text-secondary">
                              <i class="bi bi-clock me-1"></i><?= date('d/m/Y h:i A', strtotime($m['FechaRegistro'])) ?>
                            </span>
                          </td>
                          <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-purple" onclick="verDetalleMensaje(<?= htmlspecialchars(json_encode($m)) ?>)" title="Ver Contenido">
                              <i class="bi bi-eye-fill"></i> Ver Mensaje
                            </button>
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
                      <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&search=<?= urlencode($search) ?>&estado=<?= urlencode($filtroEstado) ?>&tipo=<?= urlencode($filtroTipo) ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                      <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>&search=<?= urlencode($search) ?>&estado=<?= urlencode($filtroEstado) ?>&tipo=<?= urlencode($filtroTipo) ?>"><?= $i ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>">
                      <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&search=<?= urlencode($search) ?>&estado=<?= urlencode($filtroEstado) ?>&tipo=<?= urlencode($filtroTipo) ?>">Siguiente</a>
                    </li>
                  </ul>
                </nav>
              </div>
            <?php endif; ?>
          </div>

        </main>
      </div>
    </div>

    <!-- Modal: Ver Detalle del Mensaje -->
    <div class="modal fade" id="modalVerMensaje" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
          <div class="modal-header text-white" style="background:#721896;">
            <h5 class="modal-title" id="m_titulo"><i class="bi bi-envelope-paper me-2"></i> Detalle del Mensaje</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <strong class="d-block fs-5 text-dark" id="m_destino_nombre"></strong>
                <span class="text-muted small" id="m_destino_email"></span>
              </div>
              <div id="m_badges"></div>
            </div>
            <hr>
            <div class="mb-3">
              <label class="fw-bold text-muted small text-uppercase">Asunto del Correo</label>
              <p class="fs-6 fw-semibold text-dark mb-0" id="m_asunto"></p>
            </div>
            <div class="mb-3">
              <label class="fw-bold text-muted small text-uppercase">Contenido del Mensaje</label>
              <div class="p-3 rounded-3 border bg-light mt-1" style="white-space: pre-line; font-family: monospace; font-size: 14px;" id="m_contenido"></div>
            </div>
            <div class="text-end">
              <small class="text-muted" id="m_fecha"></small>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <script src="../../assets/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      function verDetalleMensaje(msg) {
        document.getElementById('m_destino_nombre').innerText = msg.NombreDestino;
        document.getElementById('m_destino_email').innerText = 'Correo: ' + msg.EmailDestino;
        document.getElementById('m_asunto').innerText = msg.Asunto;
        document.getElementById('m_contenido').innerText = msg.Mensaje;
        document.getElementById('m_fecha').innerText = 'Fecha de Registro: ' + msg.FechaRegistro;

        let badges = '';
        if (msg.Estado === 'Enviado') {
          badges += '<span class="badge bg-success me-1">Enviado</span>';
        } else if (msg.Estado === 'Borrador') {
          badges += '<span class="badge bg-warning me-1 text-dark">Borrador</span>';
        } else {
          badges += '<span class="badge bg-danger me-1">Fallido</span>';
        }

        badges += `<span class="badge bg-purple">${msg.TipoMensaje}</span>`;
        document.getElementById('m_badges').innerHTML = badges;

        new bootstrap.Modal(document.getElementById('modalVerMensaje')).show();
      }
    </script>
  </body>
</html>
