<?php
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';

// Filtros y búsqueda inicial
$search = trim($_GET['search'] ?? '');
$filtroEntidad = trim($_GET['entidad'] ?? '');
$filtroAccion = trim($_GET['accion'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        h.ID_Historial LIKE :search
        OR h.ID_Registro LIKE :search
        OR h.Campo LIKE :search 
        OR h.ValorAnterior LIKE :search 
        OR h.ValorNuevo LIKE :search 
        OR h.NombreUsuario LIKE :search 
        OR p.Nombre LIKE :search
        OR h.Detalles LIKE :search
        OR h.Entidad LIKE :search
    )";
    $params[':search'] = "%$search%";
}

if ($filtroEntidad !== '') {
    $where[] = "h.Entidad = :entidad";
    $params[':entidad'] = $filtroEntidad;
}

if ($filtroAccion !== '') {
    $where[] = "h.Accion = :accion";
    $params[':accion'] = $filtroAccion;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Métricas de Actividad
try {
    $totalEventos = (int)$conn->query("SELECT COUNT(*) FROM donaciones_historial_cambios")->fetchColumn();
    $totalDonantesCambios = (int)$conn->query("SELECT COUNT(*) FROM donaciones_historial_cambios WHERE Entidad = 'Donante'")->fetchColumn();
    $totalDonativosCambios = (int)$conn->query("SELECT COUNT(*) FROM donaciones_historial_cambios WHERE Entidad = 'Donativo'")->fetchColumn();
    $totalMensajesCambios = (int)$conn->query("SELECT COUNT(*) FROM donaciones_historial_cambios WHERE Entidad = 'Mensaje'")->fetchColumn();
} catch (PDOException $e) {
    $totalEventos = $totalDonantesCambios = $totalDonativosCambios = $totalMensajesCambios = 0;
}

// Paginación
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$registrosPorPagina = 10;
$offset = ($pagina - 1) * $registrosPorPagina;

// Conteo filtrado
try {
    $stmtCount = $conn->prepare("
        SELECT COUNT(*) 
        FROM donaciones_historial_cambios h
        LEFT JOIN personal p ON h.ID_Usuario = p.ID_Personal
        $whereSql
    ");
    $stmtCount->execute($params);
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = max(1, (int)ceil($totalRegistros / $registrosPorPagina));
} catch (PDOException $e) {
    $totalRegistros = 0;
    $totalPaginas = 1;
}

// Lista de movimientos
try {
    $sqlList = "
        SELECT h.ID_Historial, h.FechaHora, h.ID_Usuario, 
               COALESCE(NULLIF(p.Nombre, ''), NULLIF(h.NombreUsuario, ''), 'SOLEDAD') AS NombreUsuario,
               h.Entidad, h.ID_Registro, h.Accion, h.Campo, h.ValorAnterior, h.ValorNuevo, 
               h.Detalles
        FROM donaciones_historial_cambios h
        LEFT JOIN personal p ON h.ID_Usuario = p.ID_Personal
        $whereSql
        ORDER BY h.ID_Historial DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmtList = $conn->prepare($sqlList);
    foreach ($params as $k => $v) {
        $stmtList->bindValue($k, $v);
    }
    $stmtList->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtList->execute();
    $historialList = $stmtList->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $historialList = [];
}

/**
 * Genera la descripción amigable del movimiento
 */
function renderDescripcionMovimiento($h) {
    $entidad = $h['Entidad'] ?? '';
    $accion  = $h['Accion'] ?? '';
    $campo   = $h['Campo'] ?? '';
    $valNue  = trim($h['ValorNuevo'] ?? '');
    $det     = trim($h['Detalles'] ?? '');

    if ($entidad === 'Mensaje' || strpos($accion, 'Correo') !== false) {
        $dest = '';
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $valNue, $matches)) {
            $dest = $matches[0];
        }
        $titulo = !empty($dest) ? "Agradecimiento enviado a: <strong class='text-dark'>$dest</strong>" : htmlspecialchars($valNue);
        $sub = !empty($det) ? htmlspecialchars($det) : "Comunicación por correo electrónico";
        return "<div class='text-dark'>$titulo</div><small class='text-muted d-block'>$sub</small>";
    }

    if ($entidad === 'Donativo') {
        if ($accion === 'Generación' || strpos($campo, 'Carta') !== false) {
            $sub = !empty($det) ? htmlspecialchars($det) : "Documento oficial generado";
            return "<div class='fw-semibold text-dark'><i class='bi bi-file-earmark-pdf text-danger me-1'></i> Carta de agradecimiento en PDF generada</div><small class='text-muted d-block'>$sub</small>";
        }
        $sub = !empty($det) ? htmlspecialchars($det) : "Aportación registrada";
        return "<div class='fw-semibold text-dark'>" . htmlspecialchars($valNue) . "</div><small class='text-muted d-block'>$sub</small>";
    }

    if ($entidad === 'Donante') {
        if ($accion === 'Registro' || strpos($campo, 'Alta') !== false) {
            $sub = !empty($det) ? htmlspecialchars($det) : "Nuevo registro de donante en el padrón";
            return "<div class='fw-semibold text-dark'><i class='bi bi-person-check text-success me-1'></i> " . htmlspecialchars($valNue) . "</div><small class='text-muted d-block'>$sub</small>";
        }
        if ($campo === 'Estatus de Cuenta' || $accion === 'Cambio de Estatus') {
            $sub = !empty($det) ? htmlspecialchars($det) : "Cambio de estatus del donante";
            return "<div class='fw-semibold text-dark'>Estatus actualizado a: <span class='badge bg-success-subtle text-success border border-success-subtle'>" . htmlspecialchars($valNue) . "</span></div><small class='text-muted d-block'>$sub</small>";
        }
        $sub = !empty($det) ? htmlspecialchars($det) : "Modificación de datos";
        return "<div class='fw-semibold text-dark'>Actualización en expediente de " . htmlspecialchars($valNue) . "</div><small class='text-muted d-block'>$sub</small>";
    }

    $sub = !empty($det) ? htmlspecialchars($det) : htmlspecialchars($campo);
    return "<div class='fw-semibold text-dark'>" . htmlspecialchars($valNue ?: $campo) . "</div><small class='text-muted d-block'>$sub</small>";
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Actividades - Donaciones GesMujer</title>
    <link href="../../assets/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="../dashboard.css" rel="stylesheet">
    
    <style>
      body {
        background-color: #f4f6f9;
        color: #181818;
      }
      .sf-header {
        background: #ffffff;
        border: 1px solid #d8dde6;
        border-radius: 0.5rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
      }
      .sf-header-title {
        font-size: 1.35rem;
        font-weight: 700;
        color: #1a1a1a;
      }
      .sf-metric-strip {
        border-top: 1px solid #eef1f6;
        margin-top: 1rem;
        padding-top: 0.85rem;
      }
      .sf-metric-item {
        display: inline-block;
        margin-right: 2.5rem;
        vertical-align: top;
      }
      .sf-metric-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        color: #6c757d;
        font-weight: 600;
        letter-spacing: 0.03em;
        margin-bottom: 2px;
      }
      .sf-metric-val {
        font-size: 1.15rem;
        font-weight: 700;
        color: #1a1a1a;
      }
      .sf-nav-tabs {
        border-bottom: 2px solid #d8dde6;
        margin-bottom: 1.5rem;
      }
      .sf-nav-tabs .nav-link {
        color: #514f4d;
        font-weight: 600;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 0.75rem 1.25rem;
        font-size: 0.92rem;
      }
      .sf-nav-tabs .nav-link:hover {
        color: #721896;
      }
      .sf-nav-tabs .nav-link.active {
        color: #721896;
        border-bottom: 3px solid #721896;
        background: transparent;
        font-weight: 700;
      }
      .card-sf {
        border: 1px solid #d8dde6;
        border-radius: 0.5rem;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        margin-bottom: 1.5rem;
      }
      .card-sf-header {
        background: #fafbfc;
        border-bottom: 1px solid #d8dde6;
        padding: 0.85rem 1.25rem;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
      }
      .btn-purple {
        background-color: #721896;
        color: #ffffff;
        border: none;
      }
      .btn-purple:hover {
        background-color: #5a1277;
        color: #ffffff;
      }
      .table-sf th {
        background-color: #f8fafc;
        color: #495057;
        font-size: 0.76rem;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.03em;
        border-bottom: 1px solid #d8dde6;
        padding: 11px 14px;
      }
      .table-sf td {
        padding: 12px 14px;
        vertical-align: middle;
        font-size: 0.88rem;
        border-bottom: 1px solid #f0f2f5;
      }
      .sf-user-tag {
        color: #1a1a1a;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.85rem;
        background: #f1f5f9;
        padding: 3px 8px;
        border-radius: 12px;
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
          
          <!-- Encabezado Limpio y Profesional -->
          <div class="sf-header shadow-xs">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-3 p-2 d-flex align-items-center justify-content-center text-white shadow-sm" style="background:#721896; width:44px; height:44px;">
                <i class="bi bi-clock-history fs-4"></i>
              </div>
              <div>
                <h1 class="sf-header-title mb-0">Historial de Actividades</h1>
                <p class="text-muted small mb-0">Registro cronológico de movimientos, aportaciones y comunicaciones realizadas en el módulo de donaciones</p>
              </div>
            </div>

            <!-- Fila de métricas clave reales -->
            <div class="sf-metric-strip d-flex flex-wrap">
              <div class="sf-metric-item">
                <div class="sf-metric-label">Total de Movimientos</div>
                <div class="sf-metric-val"><?= number_format($totalEventos) ?></div>
              </div>
              <div class="sf-metric-item">
                <div class="sf-metric-label">Donantes (Altas / Cambios)</div>
                <div class="sf-metric-val text-primary"><?= number_format($totalDonantesCambios) ?></div>
              </div>
              <div class="sf-metric-item">
                <div class="sf-metric-label">Donativos Registrados</div>
                <div class="sf-metric-val text-success"><?= number_format($totalDonativosCambios) ?></div>
              </div>
              <div class="sf-metric-item">
                <div class="sf-metric-label">Correos de Agradecimiento</div>
                <div class="sf-metric-val" style="color:#721896;"><?= number_format($totalMensajesCambios) ?></div>
              </div>
            </div>
          </div>

          <!-- Barra de pestañas estándar del módulo de Donaciones -->
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
              <a class="nav-link" href="mensajes.php"><i class="bi bi-envelope-paper-fill me-1"></i> Historial de Mensajes</a>
            </li>
            <li class="nav-item">
              <a class="nav-link active" href="historial-cambios.php"><i class="bi bi-clock-history me-1"></i> Historial de Cambios</a>
            </li>
          </ul>

          <!-- Buscador y Filtros en tiempo real -->
          <div class="card-sf mb-4">
            <div class="card-body p-3">
              <div class="row g-2 align-items-center">
                <div class="col-md-6 col-sm-6">
                  <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="liveSearch"
                           placeholder="Buscar por descripción, donante, correo o usuario..."
                           value="<?= htmlspecialchars($search) ?>"
                           autocomplete="off">
                    <span class="input-group-text bg-white border-start-0" id="searchSpinner" style="display:none!important;">
                      <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                    </span>
                    <button class="btn btn-outline-secondary" id="btnLimpiar" title="Limpiar" style="display:none;" onclick="limpiarBusqueda()">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </div>
                </div>
                <div class="col-md-3 col-sm-3">
                  <select id="filtroEntidad" class="form-select" onchange="buscarHistorial()">
                    <option value="">Todas las Secciones / Módulos</option>
                    <option value="Donante" <?= $filtroEntidad === 'Donante' ? 'selected' : '' ?>>Donantes</option>
                    <option value="Donativo" <?= $filtroEntidad === 'Donativo' ? 'selected' : '' ?>>Donativos</option>
                    <option value="Mensaje" <?= $filtroEntidad === 'Mensaje' ? 'selected' : '' ?>>Correos / Comunicaciones</option>
                  </select>
                </div>
                <div class="col-md-3 col-sm-3">
                  <select id="filtroAccion" class="form-select" onchange="buscarHistorial()">
                    <option value="">Todos los Tipos de Acción</option>
                    <option value="Registro" <?= $filtroAccion === 'Registro' ? 'selected' : '' ?>>Nuevo Registro / Alta</option>
                    <option value="Modificación" <?= $filtroAccion === 'Modificación' ? 'selected' : '' ?>>Modificación de Datos</option>
                    <option value="Envío de Correo" <?= $filtroAccion === 'Envío de Correo' ? 'selected' : '' ?>>Envío de Correo</option>
                    <option value="Generación" <?= $filtroAccion === 'Generación' ? 'selected' : '' ?>>Generación de Carta PDF</option>
                    <option value="Cambio de Estatus" <?= $filtroAccion === 'Cambio de Estatus' ? 'selected' : '' ?>>Cambio de Estatus</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta de Tabla de Movimientos -->
          <div class="card-sf">
            <div class="card-sf-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
              <div class="d-flex align-items-center gap-2">
                <span class="rounded p-1 text-white d-inline-flex align-items-center justify-content-center" style="background:#721896; width:26px; height:26px;">
                  <i class="bi bi-list-check" style="font-size:0.85rem;"></i>
                </span>
                <strong class="text-dark" style="font-size:0.95rem;">Registro de Movimientos (<span id="contadorTotal"><?= number_format($totalRegistros) ?></span>)</strong>
              </div>
              <div class="d-flex align-items-center gap-3">
                <span class="badge bg-light text-dark border" id="paginaLabel">Página <?= $pagina ?> de <?= max(1, $totalPaginas) ?></span>
              </div>
            </div>

            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover table-sf align-middle mb-0">
                  <thead class="table-light small text-uppercase">
                    <tr>
                      <th style="width: 60px;">ID</th>
                      <th style="width: 155px;">Fecha / Hora</th>
                      <th style="width: 130px;">Usuario</th>
                      <th style="width: 135px;">Sección</th>
                      <th style="width: 180px;">Acción Realizada</th>
                      <th>Descripción del Movimiento</th>
                      <th class="text-center" style="width: 60px;">Detalle</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyHistorial">
                    <?php if (empty($historialList)): ?>
                      <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                          <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                          No se encontraron movimientos registrados con los filtros aplicados.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($historialList as $h): ?>
                        <tr>
                          <td class="text-muted fw-semibold">#<?= $h['ID_Historial'] ?></td>
                          <td>
                            <span class="fw-semibold text-dark d-block" style="font-size:0.84rem;">
                              <?= date('d/m/Y, h:i A', strtotime($h['FechaHora'])) ?>
                            </span>
                          </td>
                          <td>
                            <span class="sf-user-tag">
                              <i class="bi bi-person-fill text-purple"></i> <?= htmlspecialchars($h['NombreUsuario']) ?>
                            </span>
                          </td>
                          <td>
                            <?php if ($h['Entidad'] === 'Donante'): ?>
                              <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                <i class="bi bi-people-fill me-1"></i> Donante <?= $h['ID_Registro'] ? '#' . $h['ID_Registro'] : '' ?>
                              </span>
                            <?php elseif ($h['Entidad'] === 'Donativo'): ?>
                              <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                <i class="bi bi-coin me-1"></i> Donativo <?= $h['ID_Registro'] ? '#' . $h['ID_Registro'] : '' ?>
                              </span>
                            <?php elseif ($h['Entidad'] === 'Mensaje'): ?>
                              <span class="badge px-2 py-1" style="background:#f3e8ff; color:#721896; border:1px solid #d1a8e7;">
                                <i class="bi bi-envelope-fill me-1"></i> Correo <?= $h['ID_Registro'] ? '#' . $h['ID_Registro'] : '' ?>
                              </span>
                            <?php else: ?>
                              <span class="badge bg-secondary-subtle text-secondary px-2 py-1">
                                <?= htmlspecialchars($h['Entidad']) ?> <?= $h['ID_Registro'] ? '#' . $h['ID_Registro'] : '' ?>
                              </span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <strong class="text-dark d-block" style="font-size:0.87rem;"><?= htmlspecialchars($h['Campo']) ?></strong>
                            <small class="text-muted" style="font-size:0.75rem;"><i class="bi bi-arrow-right-short"></i> <?= htmlspecialchars($h['Accion']) ?></small>
                          </td>
                          <td>
                            <?= renderDescripcionMovimiento($h) ?>
                          </td>
                          <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-secondary p-1" style="line-height:1;" title="Ver Detalle Completo" onclick='verDetalleAuditoria(<?= htmlspecialchars(json_encode($h), ENT_QUOTES, 'UTF-8') ?>)'>
                              <i class="bi bi-eye"></i>
                            </button>
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
                  <small class="text-muted">Mostrando registros <?= $offset + 1 ?> a <?= min($offset + $registrosPorPagina, $totalRegistros) ?> de <?= $totalRegistros ?></small>
                  <nav>
                    <ul class="pagination pagination-sm mb-0">
                      <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="#" onclick="irPagina(<?= $pagina - 1 ?>); return false;">Anterior</a>
                      </li>
                      <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                          <a class="page-link" href="#" onclick="irPagina(<?= $i ?>); return false;"><?= $i ?></a>
                        </li>
                      <?php endfor; ?>
                      <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="#" onclick="irPagina(<?= $pagina + 1 ?>); return false;">Siguiente</a>
                      </li>
                    </ul>
                  </nav>
                </div>
              <?php endif; ?>
            </div>
          </div>

        </main>
      </div>
    </div>

    <!-- Modal: Detalle del Movimiento (Estilo Formal y Corporativo SYSGES) -->
    <div class="modal fade" id="modalDetalleAuditoria" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
          
          <!-- Encabezado Institucional Morado GesMujer -->
          <div class="modal-header text-white" style="background:#721896;">
            <h5 class="modal-title fs-6 fw-bold mb-0">
              <i class="bi bi-clock-history me-2"></i> Detalle del Movimiento
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>

          <div class="modal-body p-4">
            
            <!-- Resumen superior del movimiento -->
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center pb-3 mb-3 border-bottom gap-2">
              <div>
                <span class="badge px-2 py-1 mb-1" id="mdl_badge_seccion" style="background:#f3e8ff; color:#721896; border:1px solid #d1a8e7;">
                  Sección
                </span>
                <h5 class="fw-bold text-dark mb-0 fs-5">
                  <span id="mdl_titulo_movimiento">Acción Realizada</span>
                  <span class="badge bg-light text-secondary border font-monospace fw-normal fs-6 ms-1">#<span id="mdl_id_header"></span></span>
                </h5>
              </div>
              <div class="text-sm-end">
                <small class="text-muted d-block" style="font-size: 0.76rem;">Fecha y hora de registro</small>
                <span class="fw-semibold text-dark" id="mdl_fecha_hora"></span>
              </div>
            </div>

            <!-- Tabla Formal con Bordes (Estándar SYSGES) -->
            <div class="table-responsive mb-3">
              <table class="table table-bordered align-middle mb-0" style="font-size: 0.9rem;">
                <tbody>
                  <tr>
                    <th class="bg-light text-muted" style="width: 30%; font-weight: 600;">ID del movimiento</th>
                    <td>
                      <span class="badge bg-light text-dark border font-monospace fs-6">#<span id="mdl_id_historial"></span></span>
                      <span class="text-muted ms-2 small" id="mdl_id_registro_asociado"></span>
                    </td>
                  </tr>
                  <tr>
                    <th class="bg-light text-muted" style="font-weight: 600;">Usuario responsable</th>
                    <td>
                      <span class="sf-user-tag"><i class="bi bi-person-fill text-purple"></i> <span id="mdl_usuario"></span></span>
                    </td>
                  </tr>
                  <tr>
                    <th class="bg-light text-muted" style="font-weight: 600;">Módulo / Sección</th>
                    <td id="mdl_seccion_nombre" class="fw-semibold text-dark"></td>
                  </tr>
                  <tr>
                    <th class="bg-light text-muted" style="font-weight: 600;">Tipo de acción</th>
                    <td id="mdl_tipo_accion" class="text-dark"></td>
                  </tr>
                  <tr>
                    <th class="bg-light text-muted" style="font-weight: 600;">Descripción del cambio</th>
                    <td id="mdl_descripcion_principal" class="fw-bold text-dark"></td>
                  </tr>
                  <tr id="mdl_fila_observaciones">
                    <th class="bg-light text-muted" style="font-weight: 600;">Detalles / Observaciones</th>
                    <td id="mdl_observaciones" class="text-muted"></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Panel de Comparación (Solo si hubo modificación de un valor previo a uno nuevo) -->
            <div id="mdl_panel_cambio" class="card border mb-2" style="display:none;">
              <div class="card-header bg-light py-2 px-3 fw-semibold small text-muted">
                <i class="bi bi-arrow-left-right text-primary me-1"></i> Modificación de datos registrada
              </div>
              <div class="card-body p-3">
                <div class="row g-2">
                  <div class="col-6">
                    <div class="p-2 rounded bg-light border small">
                      <span class="text-muted d-block small mb-1 fw-bold">VALOR ANTERIOR</span>
                      <span id="mdl_val_ant" class="text-danger fw-semibold"></span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="p-2 rounded bg-light border small">
                      <span class="text-muted d-block small mb-1 fw-bold">VALOR ACTUALIZADO</span>
                      <span id="mdl_val_nue" class="text-success fw-bold"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
          
          <div class="modal-footer bg-light py-2">
            <button type="button" class="btn btn-secondary px-4 btn-sm" data-bs-dismiss="modal">
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>

    <script src="../../assets/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      let searchTimeout = null;
      let paginaActual = <?= $pagina ?>;

      const liveSearch    = document.getElementById('liveSearch');
      const filtroEntidad = document.getElementById('filtroEntidad');
      const filtroAccion  = document.getElementById('filtroAccion');
      const btnLimpiar    = document.getElementById('btnLimpiar');
      const searchSpinner = document.getElementById('searchSpinner');

      liveSearch.addEventListener('input', function() {
        btnLimpiar.style.display = this.value ? 'inline-block' : 'none';
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          paginaActual = 1;
          buscarHistorial();
        }, 300);
      });

      function limpiarBusqueda() {
        liveSearch.value = '';
        btnLimpiar.style.display = 'none';
        paginaActual = 1;
        buscarHistorial();
      }

      function irPagina(num) {
        paginaActual = num;
        buscarHistorial();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }

      function formatDescripcionJS(h) {
        const entidad = h.Entidad || '';
        const accion  = h.Accion || '';
        const campo   = h.Campo || '';
        const valNue  = h.ValorNuevo || '';
        const det     = h.Detalles || '';

        if (entidad === 'Mensaje' || accion.includes('Correo')) {
          const emailMatch = valNue.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
          const dest = emailMatch ? emailMatch[0] : '';
          const titulo = dest ? `Agradecimiento enviado a: <strong class="text-dark">${escHtml(dest)}</strong>` : escHtml(valNue);
          const sub = det ? escHtml(det) : "Comunicación por correo electrónico";
          return `<div class="text-dark">${titulo}</div><small class="text-muted d-block">${sub}</small>`;
        }

        if (entidad === 'Donativo') {
          if (accion === 'Generación' || campo.includes('Carta')) {
            const sub = det ? escHtml(det) : "Documento oficial generado";
            return `<div class="fw-semibold text-dark"><i class="bi bi-file-earmark-pdf text-danger me-1"></i> Carta de agradecimiento en PDF generada</div><small class="text-muted d-block">${sub}</small>`;
          }
          const sub = det ? escHtml(det) : "Aportación registrada";
          return `<div class="fw-semibold text-dark">${escHtml(valNue)}</div><small class="text-muted d-block">${sub}</small>`;
        }

        if (entidad === 'Donante') {
          if (accion === 'Registro' || campo.includes('Alta')) {
            const sub = det ? escHtml(det) : "Nuevo registro de donante en el padrón";
            return `<div class="fw-semibold text-dark"><i class="bi bi-person-check text-success me-1"></i> ${escHtml(valNue)}</div><small class="text-muted d-block">${sub}</small>`;
          }
          if (campo === 'Estatus de Cuenta' || accion === 'Cambio de Estatus') {
            const sub = det ? escHtml(det) : "Cambio de estatus del donante";
            return `<div class="fw-semibold text-dark">Estatus actualizado a: <span class="badge bg-success-subtle text-success border border-success-subtle">${escHtml(valNue)}</span></div><small class="text-muted d-block">${sub}</small>`;
          }
          const sub = det ? escHtml(det) : "Modificación de datos";
          return `<div class="fw-semibold text-dark">Actualización en expediente de ${escHtml(valNue)}</div><small class="text-muted d-block">${sub}</small>`;
        }

        const sub = det ? escHtml(det) : escHtml(campo);
        return `<div class="fw-semibold text-dark">${escHtml(valNue || campo)}</div><small class="text-muted d-block">${sub}</small>`;
      }

      function badgeEntidad(entidad, id) {
        const idBadge = id ? ` <span class="fw-normal text-muted" style="font-size:0.75rem;">#${id}</span>` : '';
        if (entidad === 'Donante') {
          return `<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-people-fill me-1"></i> Donante${idBadge}</span>`;
        } else if (entidad === 'Donativo') {
          return `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-coin me-1"></i> Donativo${idBadge}</span>`;
        } else if (entidad === 'Mensaje') {
          return `<span class="badge px-2 py-1" style="background:#f3e8ff; color:#721896; border:1px solid #d1a8e7;"><i class="bi bi-envelope-fill me-1"></i> Correo${idBadge}</span>`;
        }
        return `<span class="badge bg-secondary-subtle text-secondary px-2 py-1">${escHtml(entidad)}${idBadge}</span>`;
      }

      function buscarHistorial() {
        searchSpinner.style.setProperty('display', 'inline-flex', 'important');

        const params = new URLSearchParams({
          search:  liveSearch.value.trim(),
          entidad: filtroEntidad.value,
          accion:  filtroAccion.value,
          pagina:  paginaActual
        });

        fetch(`ajax/buscar_historial.php?${params.toString()}`)
          .then(res => res.json())
          .then(data => {
            searchSpinner.style.setProperty('display', 'none', 'important');
            if (data.success) {
              renderizarResultados(data);
            }
          })
          .catch(err => {
            searchSpinner.style.setProperty('display', 'none', 'important');
            console.error('Error al consultar historial:', err);
          });
      }

      function renderizarResultados(data) {
        document.getElementById('contadorTotal').textContent = data.totalRegistros.toLocaleString();
        document.getElementById('paginaLabel').textContent  = `Página ${data.pagina} de ${Math.max(1, data.totalPaginas)}`;

        const tbody = document.getElementById('tbodyHistorial');
        if (!data.historial || data.historial.length === 0) {
          tbody.innerHTML = `
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                No se encontraron movimientos registrados con los filtros aplicados.
              </td>
            </tr>`;
          renderizarPaginacion(data);
          return;
        }

        tbody.innerHTML = data.historial.map(h => {
          let fechaStr = h.FechaHora;
          try {
            const d = new Date(h.FechaHora.replace(/-/g, '/'));
            fechaStr = d.toLocaleString('es-MX', {
              day: '2-digit', month: '2-digit', year: 'numeric',
              hour: '2-digit', minute: '2-digit', hour12: true
            });
          } catch(e) {}

          const hJsonSafe = JSON.stringify(h).replace(/'/g, "&#39;");

          return `<tr>
            <td class="text-center">
              <span class="badge bg-light text-secondary border font-monospace">#${h.ID_Historial}</span>
            </td>
            <td><span class="fw-semibold text-dark d-block" style="font-size:0.84rem;">${fechaStr}</span></td>
            <td>
              <span class="sf-user-tag"><i class="bi bi-person-fill text-purple"></i> ${escHtml(h.NombreUsuario)}</span>
            </td>
            <td>${badgeEntidad(h.Entidad, h.ID_Registro)}</td>
            <td>
              <strong class="text-dark d-block" style="font-size:0.87rem;">${escHtml(h.Campo)}</strong>
              <small class="text-muted" style="font-size:0.75rem;"><i class="bi bi-arrow-right-short"></i> ${escHtml(h.Accion)}</small>
            </td>
            <td>${formatDescripcionJS(h)}</td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-outline-secondary p-1" style="line-height:1;" title="Ver Detalle Completo" onclick='verDetalleAuditoria(${hJsonSafe})'>
                <i class="bi bi-eye"></i>
              </button>
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
            <small class="text-muted">Mostrando registros ${offset + 1} a ${hasta} de ${data.totalRegistros}</small>
            <nav><ul class="pagination pagination-sm mb-0">
              <li class="page-item ${data.pagina <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina - 1});return false;">Anterior</a></li>
              ${links}
              <li class="page-item ${data.pagina >= data.totalPaginas ? 'disabled' : ''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina + 1});return false;">Siguiente</a></li>
            </ul></nav>
          </div>`;
      }

      function verDetalleAuditoria(h) {
        const entidad = h.Entidad || '';
        const accion  = h.Accion || '';
        const campo   = h.Campo || '';
        const valNue  = h.ValorNuevo || '';
        const valAnt  = h.ValorAnterior || '';
        const det     = h.Detalles || '';

        // 1. Módulo / Sección formal (SIN IDs artificiales como "#8")
        let seccionNombre = '';
        let badgeSeccionHtml = '';
        let tituloMovimiento = '';
        let descripcionPrincipal = '';
        let observacionesTexto = '';

        if (entidad === 'Mensaje' || accion.includes('Correo')) {
          seccionNombre = 'Historial de Mensajes (Agradecimientos y Notificaciones)';
          badgeSeccionHtml = '<i class="bi bi-envelope-heart-fill me-1"></i> Correos de Agradecimiento';
          tituloMovimiento = 'Envío de Correo Electrónico';

          const emailMatch = valNue.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
          const dest = emailMatch ? emailMatch[0] : '';
          descripcionPrincipal = dest ? `Correo enviado a: ${dest}` : valNue;
          observacionesTexto = det || 'Mensaje institucional enviado a la donante';
        } 
        else if (entidad === 'Donativo') {
          seccionNombre = 'Historial de Donativos y Aportaciones';
          badgeSeccionHtml = '<i class="bi bi-coin me-1"></i> Donativos y Aportaciones';
          
          if (accion === 'Generación' || campo.includes('Carta')) {
            tituloMovimiento = 'Generación de Carta en PDF';
            descripcionPrincipal = 'Carta Oficial de Agradecimiento emitida en PDF';
            observacionesTexto = det || 'Se generó la carta membretada para el donante';
          } else {
            tituloMovimiento = 'Registro de Donativo';
            descripcionPrincipal = `Aportación recibida: ${valNue}`;
            observacionesTexto = det || 'Aportación económica o en especie registrada';
          }
        } 
        else if (entidad === 'Donante') {
          seccionNombre = 'Directorio de Donantes';
          badgeSeccionHtml = '<i class="bi bi-people-fill me-1"></i> Directorio de Donantes';

          if (accion === 'Registro' || campo.includes('Alta')) {
            tituloMovimiento = 'Alta de Donante';
            descripcionPrincipal = `Nuevo donante registrado: ${valNue}`;
            observacionesTexto = det || 'Registro en el padrón de donantes';
          } else if (campo === 'Estatus de Cuenta' || accion === 'Cambio de Estatus') {
            tituloMovimiento = 'Cambio de Estatus de Donante';
            descripcionPrincipal = `Estatus actualizado a: ${valNue}`;
            observacionesTexto = det || 'Modificación del estado del expediente';
          } else {
            tituloMovimiento = 'Modificación de Expediente';
            descripcionPrincipal = `Actualización de datos: ${valNue}`;
            observacionesTexto = det || 'Modificación de información del donante';
          }
        } 
        else {
          seccionNombre = entidad || 'Módulo de Donaciones';
          badgeSeccionHtml = `<i class="bi bi-layers-fill me-1"></i> ${escHtml(entidad)}`;
          tituloMovimiento = campo || accion || 'Movimiento en el Sistema';
          descripcionPrincipal = valNue || campo;
          observacionesTexto = det || 'Sin observaciones adicionales';
        }

        // Formato formal de fecha y hora
        let fechaLegible = h.FechaHora;
        try {
          const d = new Date(h.FechaHora.replace(/-/g, '/'));
          const opciones = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit', 
            hour12: true 
          };
          const fStr = d.toLocaleDateString('es-MX', opciones);
          fechaLegible = fStr.charAt(0).toUpperCase() + fStr.slice(1);
        } catch(e) {}

        // Inyectar en el modal formal
        document.getElementById('mdl_id_historial').textContent         = h.ID_Historial || '';
        document.getElementById('mdl_id_header').textContent            = h.ID_Historial || '';
        if (h.ID_Registro) {
          document.getElementById('mdl_id_registro_asociado').innerHTML = `(Registro asociado: <strong>#${h.ID_Registro}</strong> en ${escHtml(h.Entidad)})`;
        } else {
          document.getElementById('mdl_id_registro_asociado').innerHTML = '';
        }

        document.getElementById('mdl_badge_seccion').innerHTML          = badgeSeccionHtml;
        document.getElementById('mdl_titulo_movimiento').textContent     = tituloMovimiento;
        document.getElementById('mdl_fecha_hora').textContent            = fechaLegible;
        document.getElementById('mdl_usuario').textContent               = h.NombreUsuario;
        document.getElementById('mdl_seccion_nombre').textContent        = seccionNombre;
        document.getElementById('mdl_tipo_accion').textContent           = `${h.Campo} (${h.Accion})`;
        document.getElementById('mdl_descripcion_principal').textContent = descripcionPrincipal;

        // Fila de observaciones: solo mostrar si aporta información no repetida
        const filaObs = document.getElementById('mdl_fila_observaciones');
        if (observacionesTexto && observacionesTexto.trim() !== '' && observacionesTexto !== descripcionPrincipal) {
          document.getElementById('mdl_observaciones').textContent = observacionesTexto;
          filaObs.style.display = '';
        } else {
          filaObs.style.display = 'none';
        }

        // Panel de comparación (solo si hubo un cambio real de datos y no son iguales)
        const panelCambio = document.getElementById('mdl_panel_cambio');
        if (valAnt && valAnt.trim() !== '' && valAnt !== valNue) {
          document.getElementById('mdl_val_ant').textContent = valAnt;
          document.getElementById('mdl_val_nue').textContent = valNue;
          panelCambio.style.display = 'block';
        } else {
          panelCambio.style.display = 'none';
        }

        new bootstrap.Modal(document.getElementById('modalDetalleAuditoria')).show();
      }

      function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }
    </script>
  </body>
</html>
