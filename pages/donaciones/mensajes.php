<?php
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';

// Filtros y búsqueda
$search       = trim($_GET['search'] ?? '');
$filtroEstado = trim($_GET['estado'] ?? '');
$filtroTipo   = trim($_GET['tipo']   ?? '');

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        m.ID_Mensaje LIKE :search
        OR m.NombreDestino LIKE :search 
        OR m.EmailDestino LIKE :search 
        OR m.Asunto LIKE :search 
        OR m.Mensaje LIKE :search
        OR dn.Nombre LIKE :search
        OR dn.ApellidoPaterno LIKE :search
        OR dn.ApellidoMaterno LIKE :search
    )";
    $params[':search'] = "%$search%";
}

if ($filtroEstado !== '') {
    $where[] = "m.Estado = :estado";
    $params[':estado'] = $filtroEstado;
}

if ($filtroTipo !== '') {
    $where[] = "m.TipoMensaje = :tipo";
    $params[':tipo'] = $filtroTipo;
}

$whereSql  = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$fromJoins = "
    FROM donaciones_mensajes m
    LEFT JOIN donantes dn ON m.ID_Donante = dn.ID_Donante
";

// Métricas
try {
    $totalMensajes   = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes")->fetchColumn();
    $totalEnviados   = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes WHERE Estado = 'Enviado'")->fetchColumn();
    $totalBorradores = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes WHERE Estado = 'Borrador'")->fetchColumn();
    $totalCumpleanos = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes WHERE TipoMensaje = 'Cumpleaños'")->fetchColumn();
    $todosDonantesLista = $conn->query("
        SELECT ID_Donante, 
               TRIM(CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno, ''))) AS NombreCompleto, 
               Email 
        FROM donantes 
        ORDER BY Nombre ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $totalMensajes = $totalEnviados = $totalBorradores = $totalCumpleanos = 0;
    $todosDonantesLista = [];
}

// Paginación
$pagina             = max(1, (int)($_GET['pagina'] ?? 1));
$registrosPorPagina = 10;
$offset             = ($pagina - 1) * $registrosPorPagina;

try {
    $stmtCount = $conn->prepare("SELECT COUNT(DISTINCT m.ID_Mensaje) $fromJoins $whereSql");
    $stmtCount->execute($params);
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas   = max(1, (int)ceil($totalRegistros / $registrosPorPagina));
} catch (PDOException $e) {
    $totalRegistros = 0; $totalPaginas = 1;
}

try {
    $sqlList = "
        SELECT m.ID_Mensaje, m.ID_Donante, m.NombreDestino, m.EmailDestino, m.Asunto, m.Mensaje,
               m.ArchivoAdjunto, m.TipoMensaje, m.Estado, m.FechaRegistro,
               COALESCE(
                   NULLIF(TRIM(CONCAT(dn.Nombre, ' ', dn.ApellidoPaterno, ' ', COALESCE(dn.ApellidoMaterno,''))), ''),
                   m.NombreDestino
               ) AS DestinatarioCompleto
        $fromJoins
        $whereSql
        GROUP BY m.ID_Mensaje
        ORDER BY m.ID_Mensaje DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmtList = $conn->prepare($sqlList);
    foreach ($params as $k => $v) $stmtList->bindValue($k, $v);
    $stmtList->bindValue(':limit',  $registrosPorPagina, PDO::PARAM_INT);
    $stmtList->bindValue(':offset', $offset,             PDO::PARAM_INT);
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
      .sf-nav-tabs .nav-link:hover { color: #721896; }
      .sf-nav-tabs .nav-link.active {
        color: #721896;
        border-bottom: 3px solid #721896;
        background: transparent;
      }
      .card-sf {
        border: 1px solid #e3e6f0;
        border-radius: 0.75rem;
        background: #ffffff;
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.04);
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

      /* ── Botones plantilla ── */
      .btn-plantilla {
        background: #fff;
        border: 1.5px solid #d1d5db;
        border-radius: 2rem;
        padding: 4px 14px;
        font-size: .8rem;
        font-weight: 600;
        cursor: pointer;
        color: #374151;
        transition: all .15s;
      }
      .btn-plantilla:hover { border-color: #721896; color: #721896; }
      .btn-plantilla.active { background: #721896; color: #fff; border-color: #721896; }

      /* ── Autocomplete dropdown ── */
      .ac-wrap { position: relative; }
      #ac_dropdown_msg {
        display: none;
        position: absolute;
        top: 100%;
        left: 0; right: 0;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: .6rem;
        box-shadow: 0 8px 28px rgba(0,0,0,.13);
        z-index: 9999;
        max-height: 240px;
        overflow-y: auto;
        margin-top: 3px;
      }
      .ac-item {
        padding: .55rem 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: .65rem;
        border-bottom: 1px solid #f3f4f6;
        transition: background .1s;
      }
      .ac-item:last-child { border-bottom: none; }
      .ac-item:hover, .ac-item.focused { background: #f3e8ff; }
      .ac-avatar {
        width: 34px; height: 34px;
        background: linear-gradient(135deg, #721896, #9b2ccc);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .75rem; font-weight: 800; color: #fff;
        flex-shrink: 0;
      }
      .ac-name  { font-weight: 600; font-size: .85rem; color: #111827; }
      .ac-email { font-size: .74rem; color: #6b7280; }
      .ac-badge { font-size: .65rem; padding: 1px 7px; border-radius: 999px; background: #f3e8ff; color: #721896; font-weight: 700; }

      /* ── Secciones del modal de redactar ── */
      .compose-section {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #f0d8ff;
      }
      .compose-section-label {
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: #721896;
        margin-bottom: .5rem;
        display: flex;
        align-items: center;
        gap: .3rem;
      }
      .compose-field-icon {
        width: 28px; height: 28px;
        border-radius: .4rem;
        display: flex; align-items: center; justify-content: center;
        font-size: .85rem; flex-shrink: 0;
      }

      /* Botones de acción en tabla */
      .btn-accion {
        padding: 3px 8px;
        font-size: .75rem;
        border-radius: .4rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all .12s;
      }
      .btn-ver    { background:#f3e8ff; color:#721896; border-color:#e0c4f8; }
      .btn-ver:hover { background:#721896; color:#fff; }
      .btn-edit   { background:#fef3c7; color:#d97706; border-color:#fde68a; }
      .btn-edit:hover { background:#f59e0b; color:#fff; }
      .btn-send   { background:#dcfce7; color:#16a34a; border-color:#bbf7d0; }
      .btn-send:hover { background:#16a34a; color:#fff; }
      .btn-del    { background:#fee2e2; color:#dc2626; border-color:#fecaca; }
      .btn-del:hover { background:#dc2626; color:#fff; }
      .btn-retry  { background:#dbeafe; color:#2563eb; border-color:#bfdbfe; }
      .btn-retry:hover { background:#2563eb; color:#fff; }
      .btn-resend { background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
      .btn-resend:hover { background:#15803d; color:#fff; }
    </style>
  </head>
  <body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php require_once __DIR__ . '/footer.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

          <!-- Encabezado -->
          <div class="sf-header shadow-sm">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
              <div>
                <h2 class="h3 fw-bold mb-0 text-dark">Historial de Mensajes y Correos</h2>
                <p class="text-muted small mb-0">Registro completo de comunicaciones enviadas, borradores y mensajes automáticos</p>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-purple btn-sm px-3 py-2 fw-semibold" onclick="abrirModalRedactar()">
                  <i class="bi bi-plus-circle me-1"></i> Redactar Nuevo Correo
                </button>
              </div>
            </div>
          </div>

          <!-- Nav tabs -->
          <ul class="nav sf-nav-tabs">
            <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-grid-1x2-fill me-1"></i> Inicio</a></li>
            <li class="nav-item"><a class="nav-link" href="donantes.php"><i class="bi bi-people-fill me-1"></i> Directorio de Donantes</a></li>
            <li class="nav-item"><a class="nav-link" href="donativos.php"><i class="bi bi-coin me-1"></i> Historial de Donativos</a></li>
            <li class="nav-item"><a class="nav-link active" href="mensajes.php"><i class="bi bi-envelope-paper-fill me-1"></i> Historial de Mensajes</a></li>
            <li class="nav-item"><a class="nav-link" href="historial-cambios.php"><i class="bi bi-clock-history me-1"></i> Historial de Cambios</a></li>
          </ul>

          <!-- Métricas -->
          <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
              <div class="card-sf p-3">
                <span class="text-muted small fw-semibold text-uppercase">Total Registrados</span>
                <h4 class="fw-bold text-dark mb-0 mt-1"><?= number_format($totalMensajes) ?></h4>
              </div>
            </div>
            <div class="col-6 col-md-3" style="cursor:pointer;" onclick="filtrarRapido('Enviado')">
              <div class="card-sf p-3">
                <span class="text-muted small fw-semibold text-uppercase">Enviados con Éxito</span>
                <h4 class="fw-bold text-success mb-0 mt-1"><?= number_format($totalEnviados) ?></h4>
              </div>
            </div>
            <div class="col-6 col-md-3" style="cursor:pointer;" onclick="filtrarRapido('Borrador')">
              <div class="card-sf p-3">
                <span class="text-muted small fw-semibold text-uppercase">Borradores Guardados</span>
                <h4 class="fw-bold text-warning mb-0 mt-1"><?= number_format($totalBorradores) ?></h4>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card-sf p-3">
                <span class="text-muted small fw-semibold text-uppercase">Automáticos Cumpleaños</span>
                <h4 class="fw-bold mb-0 mt-1" style="color:#721896;"><?= number_format($totalCumpleanos) ?></h4>
              </div>
            </div>
          </div>

          <!-- Filtros -->
          <div class="card-sf mb-4">
            <div class="card-body p-3">
              <div class="row g-2 align-items-center">
                <div class="col-md-6 col-sm-6">
                  <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-purple"></i></span>
                    <input type="text" class="form-control" id="liveSearch"
                           placeholder="Buscar por destinatario, correo o asunto..."
                           value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    <span class="input-group-text bg-white border-start-0" id="searchSpinner" style="display:none!important;">
                      <span class="spinner-border spinner-border-sm text-purple" role="status"></span>
                    </span>
                    <button class="btn btn-outline-secondary" id="btnLimpiar" title="Limpiar" style="display:none;" onclick="limpiarBusqueda()">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </div>
                </div>
                <div class="col-md-3 col-sm-3">
                  <select id="filtroEstado" class="form-select" onchange="paginaActual=1;buscarMensajes()">
                    <option value="">Todos los Estados</option>
                    <option value="Enviado"  <?= $filtroEstado === 'Enviado'  ? 'selected' : '' ?>>Enviados</option>
                    <option value="Borrador" <?= $filtroEstado === 'Borrador' ? 'selected' : '' ?>>Borradores</option>
                    <option value="Fallido"  <?= $filtroEstado === 'Fallido'  ? 'selected' : '' ?>>Fallidos</option>
                  </select>
                </div>
                <div class="col-md-3 col-sm-3">
                  <select id="filtroTipo" class="form-select" onchange="paginaActual=1;buscarMensajes()">
                    <option value="">Todos los Tipos</option>
                    <option value="Manual"         <?= $filtroTipo === 'Manual'         ? 'selected' : '' ?>>Manuales</option>
                    <option value="Agradecimiento" <?= $filtroTipo === 'Agradecimiento' ? 'selected' : '' ?>>Agradecimientos</option>
                    <option value="Cumpleaños"     <?= $filtroTipo === 'Cumpleaños'     ? 'selected' : '' ?>>Cumpleaños</option>
                  </select>
                </div>
              </div>
              <div class="mt-2" id="searchHint" style="display:none;">
                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Resultados para: <strong id="searchHintText"></strong></small>
              </div>
            </div>
          </div>

          <!-- Tabla de Mensajes -->
          <div class="card-sf">
            <div class="card-sf-header d-flex justify-content-between align-items-center">
              <span><i class="bi bi-envelope-paper-heart me-2 text-purple"></i> Registro de Mensajes (<span id="contadorTotal"><?= number_format($totalRegistros) ?></span>)</span>
              <span class="badge bg-light text-dark border" id="paginaLabel">Página <?= $pagina ?> de <?= max(1,$totalPaginas) ?></span>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light small text-uppercase">
                    <tr>
                      <th style="width:60px;">ID</th>
                      <th>Estado</th>
                      <th>Tipo</th>
                      <th>Destinatario</th>
                      <th>Correo Electrónico</th>
                      <th>Asunto / Adjunto</th>
                      <th class="text-center">Fecha de Registro</th>
                      <th class="text-center" style="width:120px;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyMensajes">
                    <?php if (empty($mensajesList)): ?>
                      <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                          <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                          No se encontraron mensajes registrados con los filtros aplicados.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($mensajesList as $m): ?>
                        <?php $jsonData = htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8'); ?>
                        <tr>
                          <td class="text-muted fw-semibold">#<?= $m['ID_Mensaje'] ?></td>
                          <td>
                            <?php if ($m['Estado'] === 'Enviado'): ?>
                              <span class="badge bg-success-subtle text-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>Enviado</span>
                            <?php elseif ($m['Estado'] === 'Borrador'): ?>
                              <span class="badge bg-warning-subtle text-warning px-2 py-1"><i class="bi bi-file-earmark-text me-1"></i>Borrador</span>
                            <?php else: ?>
                              <span class="badge bg-danger-subtle text-danger px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i>Fallido</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ($m['TipoMensaje'] === 'Cumpleaños'): ?>
                              <span class="badge bg-danger-subtle text-danger"><i class="bi bi-gift me-1"></i>Cumpleaños</span>
                            <?php elseif ($m['TipoMensaje'] === 'Agradecimiento'): ?>
                              <span class="badge px-2 py-1" style="background-color:#f3e8ff;color:#721896;"><i class="bi bi-heart-fill me-1"></i>Agradecimiento</span>
                            <?php else: ?>
                              <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-send me-1"></i>Manual</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <strong class="text-dark"><?= htmlspecialchars($m['DestinatarioCompleto'] ?: $m['NombreDestino']) ?></strong>
                          </td>
                          <td>
                            <span class="small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($m['EmailDestino']) ?></span>
                          </td>
                          <td>
                            <span class="fw-semibold text-dark d-block"><?= htmlspecialchars($m['Asunto']) ?></span>
                            <?php if (!empty($m['ArchivoAdjunto'])): ?>
                              <span class="badge bg-light text-primary border mt-1"><i class="bi bi-paperclip me-1"></i><?= htmlspecialchars($m['ArchivoAdjunto']) ?></span>
                            <?php endif; ?>
                          </td>
                          <td class="text-center">
                            <span class="small text-secondary"><i class="bi bi-clock me-1"></i><?= date('d/m/Y h:i A', strtotime($m['FechaRegistro'])) ?></span>
                          </td>
                          <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                              <?php if ($m['Estado'] === 'Borrador'): ?>
                                <button class="btn-accion btn-ver" onclick="verDetalleMensaje(<?= $jsonData ?>)" title="Ver"><i class="bi bi-eye-fill"></i></button>
                                <button class="btn-accion btn-edit" onclick="editarBorrador(<?= $jsonData ?>)" title="Editar"><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn-accion btn-send" onclick="enviarBorrador(<?= $jsonData ?>)" title="Enviar"><i class="bi bi-send-fill"></i></button>
                                <button class="btn-accion btn-del" onclick="eliminarMensaje(<?= $m['ID_Mensaje'] ?>,'Borrador')" title="Eliminar"><i class="bi bi-trash3-fill"></i></button>
                              <?php elseif ($m['Estado'] === 'Fallido'): ?>
                                <button class="btn-accion btn-ver" onclick="verDetalleMensaje(<?= $jsonData ?>)" title="Ver"><i class="bi bi-eye-fill"></i></button>
                                <button class="btn-accion btn-retry" onclick="reenviarMensaje(<?= $jsonData ?>)" title="Reintentar"><i class="bi bi-arrow-clockwise"></i></button>
                                <button class="btn-accion btn-del" onclick="eliminarMensaje(<?= $m['ID_Mensaje'] ?>,'Fallido')" title="Eliminar"><i class="bi bi-trash3-fill"></i></button>
                              <?php else: ?>
                                <button class="btn-accion btn-ver" onclick="verDetalleMensaje(<?= $jsonData ?>)" title="Ver"><i class="bi bi-eye-fill"></i></button>
                                <button class="btn-accion btn-resend" onclick="reenviarMensaje(<?= $jsonData ?>)" title="Reenviar"><i class="bi bi-reply-fill"></i></button>
                              <?php endif; ?>
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
            <div id="paginacionContainer">
              <?php if ($totalPaginas > 1): ?>
                <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
                  <small class="text-muted">Mostrando <?= $offset + 1 ?> a <?= min($offset + $registrosPorPagina, $totalRegistros) ?> de <?= $totalRegistros ?></small>
                  <nav>
                    <ul class="pagination pagination-sm mb-0">
                      <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="#" onclick="irPagina(<?= $pagina - 1 ?>);return false;">Anterior</a>
                      </li>
                      <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                          <a class="page-link" href="#" onclick="irPagina(<?= $i ?>);return false;"><?= $i ?></a>
                        </li>
                      <?php endfor; ?>
                      <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="#" onclick="irPagina(<?= $pagina + 1 ?>);return false;">Siguiente</a>
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

    <!-- ══ Modal: Ver Detalle del Mensaje ══════════════════════════════════════ -->
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
              <div class="p-3 rounded-3 border bg-light mt-1" style="white-space:pre-line;font-family:monospace;font-size:14px;max-height:300px;overflow-y:auto;" id="m_contenido"></div>
            </div>
            <div class="mb-3 d-none" id="m_container_adjunto">
              <label class="fw-bold text-muted small text-uppercase"><i class="bi bi-paperclip me-1 text-purple"></i> Archivo Adjunto</label>
              <div class="mt-1">
                <span class="badge bg-light text-dark border p-2 fs-6">
                  <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> <span id="m_adjunto_nombre"></span>
                </span>
              </div>
            </div>
            <div class="text-end">
              <small class="text-muted" id="m_fecha"></small>
            </div>
          </div>
          <div class="modal-footer bg-light justify-content-between flex-wrap gap-2">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            <div class="d-flex gap-2" id="m_footer_acciones"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ Modal: Redactar / Editar Borrador ══════════════════════════════════ -->
    <div class="modal fade" id="modalEnviarCorreo" tabindex="-1" aria-labelledby="modalEnviarCorreoLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow" style="border-radius:.75rem;overflow:hidden;">

          <!-- Cabecera con degradado -->
          <div class="modal-header text-white" style="background:linear-gradient(135deg,#721896 0%,#9b2ccc 100%);padding:1.1rem 1.5rem;">
            <div class="d-flex align-items-center gap-2">
              <div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:.5rem;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-envelope-paper-heart fs-5"></i>
              </div>
              <div>
                <h5 class="modal-title mb-0 fw-bold" id="modalEnviarCorreoLabel">Redactar Correo Institucional</h5>
                <small style="opacity:.8;font-size:.75rem;">GesMujer — Comunicación con donantes</small>
              </div>
            </div>
            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <form action="acciones/enviar-correo.php" method="POST" enctype="multipart/form-data" id="formCorreo">
            <input type="hidden" name="return_url"  value="../mensajes.php">
            <input type="hidden" name="id_donante"  id="correo_id_donante" value="">
            <input type="hidden" name="id_mensaje"  id="correo_id_mensaje"  value="">

            <div class="modal-body p-0" style="max-height:68vh;overflow-y:auto;">

              <!-- 1. Plantilla -->
              <div class="compose-section" style="background:#f8f0ff;">
                <div class="compose-section-label"><i class="bi bi-layout-text-window-reverse"></i> Plantilla de Mensaje</div>
                <div class="d-flex gap-2 flex-wrap" id="plantilla_btns">
                  <button type="button" class="btn-plantilla active" data-val="cumpleanos"     onclick="selPlantilla('cumpleanos')">🎂 Cumpleaños</button>
                  <button type="button" class="btn-plantilla"        data-val="agradecimiento" onclick="selPlantilla('agradecimiento')">💜 Agradecimiento</button>
                  <button type="button" class="btn-plantilla"        data-val="recibo"         onclick="selPlantilla('recibo')">📄 Recibo</button>
                  <button type="button" class="btn-plantilla"        data-val="informativo"    onclick="selPlantilla('informativo')">📊 Informativo</button>
                  <button type="button" class="btn-plantilla"        data-val="personalizado"  onclick="selPlantilla('personalizado')">✏️ Personalizado</button>
                </div>
                <input type="hidden" id="plantillaSelect" value="cumpleanos">
              </div>

              <!-- 2. Destinatario -->
              <div class="compose-section">
                <div class="compose-section-label"><i class="bi bi-person-fill"></i> Destinatario</div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark mb-1">Nombre del donante <span class="text-danger">*</span></label>
                    <div class="ac-wrap" id="autocomplete_wrapper_msg">
                      <div class="input-group">
                        <span class="input-group-text bg-white border-end-0" style="border-color:#dee2e6;">
                          <i class="bi bi-search" style="color:#9ca3af;font-size:.85rem;"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-1" name="nombre" id="correo_nombre"
                               placeholder="Buscar por nombre, apellido o correo..."
                               oninput="acMsgInput(this.value)"
                               onkeydown="acMsgKeyDown(event)"
                               autocomplete="off" required>
                      </div>
                      <div id="ac_dropdown_msg"></div>
                    </div>
                    <small class="text-muted" style="font-size:.71rem;">Escribe para buscar o ingresa un nombre nuevo</small>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark mb-1">Correo electrónico <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text bg-white border-end-0" style="border-color:#dee2e6;">
                        <i class="bi bi-envelope" style="color:#9ca3af;font-size:.85rem;"></i>
                      </span>
                      <input type="email" class="form-control border-start-0 ps-1" name="email" id="correo_email"
                             placeholder="correo@ejemplo.com" required>
                    </div>
                    <small class="text-muted" style="font-size:.71rem;">Se autocompleta al seleccionar un donante</small>
                  </div>
                </div>
              </div>

              <!-- 3. Asunto -->
              <div class="compose-section">
                <div class="compose-section-label"><i class="bi bi-chat-left-text-fill"></i> Asunto</div>
                <input type="text" class="form-control" name="asunto" id="correo_asunto" required
                       placeholder="Escribe el asunto del correo...">
              </div>

              <!-- 4. Mensaje -->
              <div class="compose-section">
                <div class="compose-section-label"><i class="bi bi-file-text-fill"></i> Contenido del Mensaje</div>
                <textarea class="form-control" name="mensaje" id="correo_mensaje" rows="4" required
                          placeholder="Escribe el contenido del mensaje..." style="resize:vertical;font-size:.88rem;min-height:90px;"></textarea>
              </div>

              <!-- 5. Adjunto -->
              <div class="compose-section" style="border-bottom:none;">
                <div class="compose-section-label"><i class="bi bi-paperclip"></i> Archivo Adjunto <span class="text-muted fw-normal text-lowercase" style="font-size:.65rem;">(opcional)</span></div>
                <div style="border:1.5px dashed #d1a8f0;border-radius:.5rem;padding:.75rem 1rem;background:#fdf8ff;">
                  <input type="file" name="adjunto" id="correo_adjunto" class="form-control form-control-sm"
                         accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xls,.xlsx">
                  <div class="form-text" style="font-size:.71rem;">PDF, Word, Imágenes o Excel — máx. 15 MB</div>
                </div>
              </div>

            </div>

            <div class="modal-footer justify-content-between flex-wrap gap-2" style="background:#f8f0ff;border-top:1px solid #e8d5f8;">
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
              <div class="d-flex gap-2">
                <button type="submit" name="accion" value="borrador" class="btn btn-warning btn-sm fw-semibold">
                  <i class="bi bi-file-earmark-text me-1"></i> Guardar Borrador
                </button>
                <button type="submit" name="accion" value="enviar" class="btn btn-sm fw-bold text-white" style="background:linear-gradient(135deg,#721896,#9b2ccc);border:none;">
                  <i class="bi bi-send-fill me-1"></i> Enviar Correo
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="../../assets/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      // ─── HELPERS ──────────────────────────────────────────────────────────────
      function escHtml(str) {
        return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
      }

      // ─── VER DETALLE ──────────────────────────────────────────────────────────
      let msgActual = null;
      function verDetalleMensaje(msg) {
        msgActual = msg;
        document.getElementById('m_titulo').innerHTML    = '<i class="bi bi-envelope-paper me-2"></i> Detalle del Mensaje #' + (msg.ID_Mensaje||'');
        document.getElementById('m_destino_nombre').textContent = msg.DestinatarioCompleto || msg.NombreDestino || 'Sin destinatario';
        document.getElementById('m_destino_email').textContent  = 'Correo: ' + (msg.EmailDestino||'Sin correo');
        document.getElementById('m_asunto').textContent         = msg.Asunto || '';
        document.getElementById('m_contenido').textContent      = msg.Mensaje || '';
        document.getElementById('m_fecha').textContent          = 'Fecha de Registro: ' + (msg.FechaRegistro||'');

        const contAdj = document.getElementById('m_container_adjunto');
        if (msg.ArchivoAdjunto) {
          document.getElementById('m_adjunto_nombre').textContent = msg.ArchivoAdjunto;
          contAdj.classList.remove('d-none');
        } else {
          contAdj.classList.add('d-none');
        }

        let badges = '';
        if (msg.Estado === 'Enviado')       badges += '<span class="badge bg-success me-1">Enviado</span>';
        else if (msg.Estado === 'Borrador') badges += '<span class="badge bg-warning text-dark me-1">Borrador</span>';
        else                                badges += '<span class="badge bg-danger me-1">Fallido</span>';
        badges += `<span class="badge px-2 py-1 me-1" style="background-color:#f3e8ff;color:#721896;">${escHtml(msg.TipoMensaje||'Manual')}</span>`;
        document.getElementById('m_badges').innerHTML = badges;

        // Acciones del footer según estado
        let acciones = '';
        if (msg.Estado === 'Borrador') {
          acciones = `
            <button class="btn btn-outline-warning btn-sm fw-semibold" onclick="bootstrap.Modal.getInstance(document.getElementById('modalVerMensaje')).hide();editarBorrador(msgActual)">
              <i class="bi bi-pencil-fill me-1"></i> Editar borrador
            </button>
            <button class="btn btn-purple btn-sm fw-semibold" onclick="bootstrap.Modal.getInstance(document.getElementById('modalVerMensaje')).hide();enviarBorrador(msgActual)">
              <i class="bi bi-send-fill me-1"></i> Enviar ahora
            </button>`;
        } else if (msg.Estado === 'Enviado') {
          acciones = `
            <button class="btn btn-outline-secondary btn-sm fw-semibold" onclick="bootstrap.Modal.getInstance(document.getElementById('modalVerMensaje')).hide();reenviarMensaje(msgActual)">
              <i class="bi bi-reply-fill me-1"></i> Reenviar
            </button>`;
        } else {
          acciones = `
            <button class="btn btn-outline-primary btn-sm fw-semibold" onclick="bootstrap.Modal.getInstance(document.getElementById('modalVerMensaje')).hide();reenviarMensaje(msgActual)">
              <i class="bi bi-arrow-clockwise me-1"></i> Reintentar envío
            </button>`;
        }
        document.getElementById('m_footer_acciones').innerHTML = acciones;

        new bootstrap.Modal(document.getElementById('modalVerMensaje')).show();
      }

      // ─── REDACTAR NUEVO ───────────────────────────────────────────────────────
      function abrirModalRedactar() {
        const lbl = document.getElementById('modalEnviarCorreoLabel');
        lbl.innerHTML = `<div class="d-flex align-items-center gap-2"><div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:.5rem;display:flex;align-items:center;justify-content:center;"><i class="bi bi-envelope-paper-heart fs-5"></i></div><div><span class="fw-bold">Redactar Correo Institucional</span><br><small style="opacity:.8;font-size:.75rem;">GesMujer — Comunicación con donantes</small></div></div>`;
        document.getElementById('correo_nombre').value      = '';
        document.getElementById('correo_email').value       = '';
        document.getElementById('correo_id_donante').value  = '';
        document.getElementById('correo_id_mensaje').value  = '';
        document.getElementById('plantillaSelect').value    = 'cumpleanos';
        document.querySelectorAll('.btn-plantilla').forEach(b => b.classList.toggle('active', b.dataset.val === 'cumpleanos'));
        acMsgOcultar();
        cambiarPlantilla();
        new bootstrap.Modal(document.getElementById('modalEnviarCorreo')).show();
      }

      // ─── EDITAR BORRADOR ──────────────────────────────────────────────────────
      function editarBorrador(msg) {
        const lbl = document.getElementById('modalEnviarCorreoLabel');
        lbl.innerHTML = `<div class="d-flex align-items-center gap-2"><div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:.5rem;display:flex;align-items:center;justify-content:center;"><i class="bi bi-pencil-fill fs-5"></i></div><div><span class="fw-bold">Editar Borrador #${msg.ID_Mensaje}</span><br><small style="opacity:.8;font-size:.75rem;">GesMujer — Comunicación con donantes</small></div></div>`;
        document.getElementById('correo_nombre').value     = msg.NombreDestino || '';
        document.getElementById('correo_email').value      = msg.EmailDestino  || '';
        document.getElementById('correo_asunto').value     = msg.Asunto        || '';
        document.getElementById('correo_mensaje').value    = msg.Mensaje       || '';
        document.getElementById('correo_id_donante').value = msg.ID_Donante    || '';
        document.getElementById('correo_id_mensaje').value = msg.ID_Mensaje    || '';
        // Marcar plantilla como personalizada
        document.getElementById('plantillaSelect').value = 'personalizado';
        document.querySelectorAll('.btn-plantilla').forEach(b => b.classList.toggle('active', b.dataset.val === 'personalizado'));
        new bootstrap.Modal(document.getElementById('modalEnviarCorreo')).show();
      }

      // ─── ENVIAR BORRADOR (pre-carga y el usuario confirma con "Enviar") ───────
      function enviarBorrador(msg) {
        editarBorrador(msg);
      }

      // ─── REENVIAR / REINTENTAR ────────────────────────────────────────────────
      function reenviarMensaje(msg) {
        const lbl = document.getElementById('modalEnviarCorreoLabel');
        lbl.innerHTML = `<div class="d-flex align-items-center gap-2"><div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:.5rem;display:flex;align-items:center;justify-content:center;"><i class="bi bi-reply-fill fs-5"></i></div><div><span class="fw-bold">Reenviar Correo</span><br><small style="opacity:.8;font-size:.75rem;">GesMujer — Comunicación con donantes</small></div></div>`;
        document.getElementById('correo_nombre').value     = msg.NombreDestino || '';
        document.getElementById('correo_email').value      = msg.EmailDestino  || '';
        document.getElementById('correo_asunto').value     = msg.Asunto        || '';
        document.getElementById('correo_mensaje').value    = msg.Mensaje       || '';
        document.getElementById('correo_id_donante').value = msg.ID_Donante    || '';
        document.getElementById('correo_id_mensaje').value = ''; // Siempre crea registro nuevo
        document.getElementById('plantillaSelect').value   = 'personalizado';
        document.querySelectorAll('.btn-plantilla').forEach(b => b.classList.toggle('active', b.dataset.val === 'personalizado'));
        new bootstrap.Modal(document.getElementById('modalEnviarCorreo')).show();
      }

      // ─── ELIMINAR ─────────────────────────────────────────────────────────────
      function eliminarMensaje(id, estado) {
        Swal.fire({
          title: estado === 'Borrador' ? '¿Eliminar borrador?' : '¿Eliminar mensaje fallido?',
          text: 'Esta acción no se puede deshacer.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar'
        }).then(result => {
          if (!result.isConfirmed) return;
          fetch('acciones/eliminar-mensaje.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id_mensaje=' + encodeURIComponent(id)
          })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              Swal.fire({icon:'success', title:'Eliminado', text: data.mensaje || 'Mensaje eliminado.', timer:2000, showConfirmButton:false});
              setTimeout(() => buscarMensajes(), 500);
            } else {
              Swal.fire({icon:'error', title:'Error', text: data.error || 'No se pudo eliminar.'});
            }
          })
          .catch(() => Swal.fire({icon:'error', title:'Error de conexión'}));
        });
      }

      // ─── REDACTAR / EDITAR: resetear autocomplete al abrir ───────────────────
      document.getElementById('modalEnviarCorreo').addEventListener('shown.bs.modal', () => {
        acMsgOcultar();
      });
      document.getElementById('modalEnviarCorreo').addEventListener('hidden.bs.modal', () => {
        acMsgOcultar();
      });

      // ─── FILTRO RÁPIDO ────────────────────────────────────────────────────────
      function filtrarRapido(estado) {
        document.getElementById('filtroEstado').value = estado;
        paginaActual = 1;
        buscarMensajes();
      }

      // ─── AUTOCOMPLETAR ────────────────────────────────────────────────────────
      function manejarInputNombre(val) {
        const nombre = (val||'').trim();
        const datalist   = document.getElementById('lista_donantes_datalist');
        const emailInput = document.getElementById('correo_email');
        const idInput    = document.getElementById('correo_id_donante');
        actualizarNombreEnMensaje();
        if (!datalist) return;
        for (const opt of datalist.querySelectorAll('option')) {
          if (opt.value.toLowerCase() === nombre.toLowerCase()) {
            const email = opt.getAttribute('data-email')||'';
            const idDon = opt.getAttribute('data-id')||'';
            if (email) emailInput.value = email;
            if (idDon) idInput.value    = idDon;
            return;
          }
        }
        if (nombre.length >= 2) buscarDonantesDatalist(nombre);
      }

      let timeoutDatalist = null;
      async function buscarDonantesDatalist(q) {
        clearTimeout(timeoutDatalist);
        timeoutDatalist = setTimeout(async () => {
          try {
            const donantes = await (await fetch('ajax/buscar_donante.php?q=' + encodeURIComponent(q))).json();
            if (!Array.isArray(donantes)) return;
            const datalist = document.getElementById('lista_donantes_datalist');
            donantes.forEach(d => {
              const existe = Array.from(datalist.options).some(o => o.value.toLowerCase() === d.NombreCompleto.toLowerCase());
              if (!existe) {
                const opt = document.createElement('option');
                opt.value = d.NombreCompleto;
                opt.setAttribute('data-email', d.Email||'');
                opt.setAttribute('data-id', d.ID_Donante);
                opt.textContent = d.Email||'Sin correo';
                datalist.appendChild(opt);
              }
            });
          } catch(e) {}
        }, 220);
      }

      function actualizarNombreEnMensaje() {
        const nom = document.getElementById('correo_nombre').value.trim() || '[Nombre del Donante]';
        const txt = document.getElementById('correo_mensaje');
        if (!txt) return;
        const sel = document.getElementById('plantillaSelect').value;
        if (sel === 'cumpleanos' && txt.value.includes('en el GESMujer no solo celebramos')) {
          txt.value = txt.value.replace(/^[^\n,]+, en el GESMujer no solo celebramos/m, `${nom}, en el GESMujer no solo celebramos`);
        } else if (sel === 'agradecimiento' && txt.value.includes('Querido(a)')) {
          txt.value = txt.value.replace(/Querido\(a\) [^,\n]+,/g, `Querido(a) ${nom},`);
        } else if ((sel === 'recibo'||sel === 'informativo') && txt.value.includes('Estimado(a)')) {
          txt.value = txt.value.replace(/Estimado\(a\) [^,\n]+,/g, `Estimado(a) ${nom},`);
        }
      }

      function cambiarPlantilla() {
        const sel    = document.getElementById('plantillaSelect').value;
        const txt    = document.getElementById('correo_mensaje');
        const asunto = document.getElementById('correo_asunto');
        let nom = document.getElementById('correo_nombre').value.trim() || '[Nombre del Donante]';
        const meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        const hoy   = new Date();
        const fecha = `Oaxaca de Juárez, Oaxaca a ${hoy.getDate()} de ${meses[hoy.getMonth()]} de ${hoy.getFullYear()}`;

        if (sel === 'agradecimiento') {
          asunto.value = '¡Gracias por sumarte a transformar vidas en Oaxaca! - GesMujer';
          txt.value = `${fecha}\n\nQuerido(a) ${nom}, ¡gracias por sumarte a transformar vidas en Oaxaca!\n\n`+
            `A nombre de todas las personas que formamos parte del Grupo de Estudios sobre la Mujer Rosario Castellanos, GesMujer, quiero expresarte nuestro más sincero agradecimiento por tu generoso donativo.\n\n`+
            `Acciones como la tuya hacen posible que sigamos caminando junto a mujeres, jóvenes y niñas de diversas comunidades indígenas y rurales del estado de Oaxaca. Gracias a tu valioso respaldo, podemos fortalecer nuestras acciones de acompañamiento, formación, defensa de sus derechos e impulso a su autonomía, brindándoles herramientas para construir proyectos de vida con dignidad, justicia y libres de violencia.\n\n`+
            `En GesMujer estamos convencidas de que la transformación comunitaria se logra uniendo voluntades. Tu contribución no solo representa un apoyo económico indispensable, sino también un mensaje directo de esperanza y solidaridad para quienes más lo necesitan.\n\n`+
            `Nos comprometemos firmemente a administrar tu aportación con total transparencia y con la dedicación que nos ha caracterizado por más de cuatro décadas de trabajo en favor de las mujeres oaxaqueñas.\n\n`+
            `Te reiteramos nuestra gratitud y dejamos nuestras puertas abiertas para mantenerte informado sobre el impacto y los logros que tu donativo ayuda a construir día con día.\n\nCon aprecio y respeto,\n\nMaría del Rosario Martínez Miguel\nGrupo de Estudios sobre la Mujer Rosario Castellanos (GesMujer)`;
        } else if (sel === 'recibo') {
          asunto.value = 'Envío de Recibo de Donativo - GesMujer Oaxaca';
          txt.value = `Estimado(a) ${nom},\n\nLe hacemos llegar adjunto a este correo su recibo/comprobante oficial correspondiente a su reciente donativo.\n\nReiteramos nuestra gratitud por su generosidad y confianza en nuestras causas.\n\nAtentamente,\nEquipo GesMujer Oaxaca`;
        } else if (sel === 'cumpleanos') {
          asunto.value = 'En GESMujer Te deseamos... ¡Feliz Cumpleaños!';
          txt.value = `¡Que tengas el mejor de los días!\n\n${nom}, en el GESMujer no solo celebramos tu cumpleaños, ¡También el tiempo de amistad y cariño! Gracias por ser parte de nuestra historia, tu apoyo siempre constante nos llena de alegría la vida. Deseamos con cariño que la sigas pasando... ¡Estupendo! Disfruta tu día en compañía de quienes más quieres, dedícate tiempo y déjate querer mucho.\n\nRecibe los mejores deseos y muchos abrazos con cariño.\n\n¡ Felicidades !\n\nAtentamente.\nGrupo de Estudios Sobre la Mujer "Rosario Castellanos" A.C.`;
        } else if (sel === 'informativo') {
          asunto.value = 'Informe de Actividades y Resultados - GesMujer Oaxaca';
          txt.value = `Estimado(a) ${nom},\n\nLe compartimos nuestro reporte de actividades y avances en los programas de apoyo a mujeres. Gracias a su solidaridad esto es posible.\n\nQuedamos a su disposición para cualquier duda.`;
        } else {
          asunto.value = 'Mensaje de la Familia GesMujer Oaxaca';
          txt.value = '';
        }
      }

      // ─── BÚSQUEDA EN TIEMPO REAL ──────────────────────────────────────────────
      let searchTimeout = null;
      let paginaActual  = <?= $pagina ?>;

      const inputSearch   = document.getElementById('liveSearch');
      const selEstado     = document.getElementById('filtroEstado');
      const selTipo       = document.getElementById('filtroTipo');
      const spinner       = document.getElementById('searchSpinner');
      const searchHint    = document.getElementById('searchHint');
      const searchHintTxt = document.getElementById('searchHintText');
      const btnLimpiar    = document.getElementById('btnLimpiar');

      inputSearch.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { paginaActual = 1; buscarMensajes(); }, 320);
        btnLimpiar.style.display = inputSearch.value.trim() ? '' : 'none';
      });

      function limpiarBusqueda() {
        inputSearch.value = '';
        btnLimpiar.style.display = 'none';
        searchHint.style.display = 'none';
        paginaActual = 1;
        buscarMensajes();
      }

      function irPagina(p) { paginaActual = p; buscarMensajes(); }

      function buscarMensajes() {
        const q      = inputSearch.value.trim();
        const estado = selEstado.value;
        const tipo   = selTipo.value;
        const url    = `ajax/buscar_mensajes.php?search=${encodeURIComponent(q)}&estado=${encodeURIComponent(estado)}&tipo=${encodeURIComponent(tipo)}&pagina=${paginaActual}`;
        spinner.style.removeProperty('display');
        fetch(url)
          .then(r => r.json())
          .then(data => {
            spinner.style.display = 'none';
            if (!data.success) return;
            renderizarTabla(data);
            if (q) { searchHintTxt.textContent = q; searchHint.style.display = ''; }
            else   { searchHint.style.display = 'none'; }
          })
          .catch(() => { spinner.style.display = 'none'; });
      }

      function badgeEstado(estado) {
        if (estado === 'Enviado')  return '<span class="badge bg-success-subtle text-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>Enviado</span>';
        if (estado === 'Borrador') return '<span class="badge bg-warning-subtle text-warning px-2 py-1"><i class="bi bi-file-earmark-text me-1"></i>Borrador</span>';
        return '<span class="badge bg-danger-subtle text-danger px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i>Fallido</span>';
      }

      function badgeTipo(tipo) {
        if (tipo === 'Cumpleaños')     return '<span class="badge bg-danger-subtle text-danger"><i class="bi bi-gift me-1"></i>Cumpleaños</span>';
        if (tipo === 'Agradecimiento') return '<span class="badge px-2 py-1" style="background-color:#f3e8ff;color:#721896;"><i class="bi bi-heart-fill me-1"></i>Agradecimiento</span>';
        return '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-send me-1"></i>Manual</span>';
      }

      function renderizarTabla(data) {
        const tbody = document.getElementById('tbodyMensajes');
        document.getElementById('contadorTotal').textContent = Number(data.totalRegistros).toLocaleString();
        const lbl = document.getElementById('paginaLabel');
        if (lbl) lbl.textContent = `Página ${data.pagina} de ${Math.max(1,data.totalPaginas)}`;

        if (!data.mensajes || data.mensajes.length === 0) {
          tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>No se encontraron mensajes registrados con los filtros aplicados.</td></tr>`;
          document.getElementById('paginacionContainer').innerHTML = '';
          return;
        }

        tbody.innerHTML = data.mensajes.map(m => {
          const nombre     = escHtml(m.DestinatarioCompleto || m.NombreDestino || 'Sin destinatario');
          const adjuntoHtml = m.ArchivoAdjunto
            ? `<span class="badge bg-light text-primary border mt-1"><i class="bi bi-paperclip me-1"></i>${escHtml(m.ArchivoAdjunto)}</span>`
            : '';
          let fechaStr = '';
          if (m.FechaRegistro) {
            const d = new Date(m.FechaRegistro.replace(/-/g,'/'));
            fechaStr = d.toLocaleString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true});
          }
          const json = JSON.stringify(m).replace(/'/g,"&#39;");

          let acciones = '';
          if (m.Estado === 'Borrador') {
            acciones = `
              <button class="btn-accion btn-ver"    onclick='verDetalleMensaje(${json})' title="Ver"><i class="bi bi-eye-fill"></i></button>
              <button class="btn-accion btn-edit"   onclick='editarBorrador(${json})'    title="Editar"><i class="bi bi-pencil-fill"></i></button>
              <button class="btn-accion btn-send"   onclick='enviarBorrador(${json})'    title="Enviar"><i class="bi bi-send-fill"></i></button>
              <button class="btn-accion btn-del"    onclick="eliminarMensaje(${m.ID_Mensaje},'Borrador')" title="Eliminar"><i class="bi bi-trash3-fill"></i></button>`;
          } else if (m.Estado === 'Fallido') {
            acciones = `
              <button class="btn-accion btn-ver"    onclick='verDetalleMensaje(${json})' title="Ver"><i class="bi bi-eye-fill"></i></button>
              <button class="btn-accion btn-retry"  onclick='reenviarMensaje(${json})'   title="Reintentar"><i class="bi bi-arrow-clockwise"></i></button>
              <button class="btn-accion btn-del"    onclick="eliminarMensaje(${m.ID_Mensaje},'Fallido')" title="Eliminar"><i class="bi bi-trash3-fill"></i></button>`;
          } else {
            acciones = `
              <button class="btn-accion btn-ver"    onclick='verDetalleMensaje(${json})' title="Ver"><i class="bi bi-eye-fill"></i></button>
              <button class="btn-accion btn-resend" onclick='reenviarMensaje(${json})'   title="Reenviar"><i class="bi bi-reply-fill"></i></button>`;
          }

          return `<tr>
            <td class="text-muted fw-semibold">#${m.ID_Mensaje}</td>
            <td>${badgeEstado(m.Estado)}</td>
            <td>${badgeTipo(m.TipoMensaje)}</td>
            <td><strong class="text-dark">${nombre}</strong></td>
            <td><span class="small text-muted"><i class="bi bi-envelope me-1"></i>${escHtml(m.EmailDestino||'')}</span></td>
            <td>
              <span class="fw-semibold text-dark d-block">${escHtml(m.Asunto||'')}</span>
              ${adjuntoHtml}
            </td>
            <td class="text-center"><span class="small text-secondary"><i class="bi bi-clock me-1"></i>${escHtml(fechaStr)}</span></td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center flex-wrap">${acciones}</div>
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
          links += `<li class="page-item ${i===data.pagina?'active':''}"><a class="page-link" href="#" onclick="irPagina(${i});return false;">${i}</a></li>`;
        }
        cont.innerHTML = `
          <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">Mostrando ${offset+1} a ${hasta} de ${data.totalRegistros}</small>
            <nav><ul class="pagination pagination-sm mb-0">
              <li class="page-item ${data.pagina<=1?'disabled':''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina-1});return false;">Anterior</a></li>
              ${links}
              <li class="page-item ${data.pagina>=data.totalPaginas?'disabled':''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina+1});return false;">Siguiente</a></li>
            </ul></nav>
          </div>`;
      }

      // ─── Alertas de URL ────────────────────────────────────────────────────────
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('status') === 'sent') {
        Swal.fire({icon:'success', title:'¡Correo Enviado!', text:'El mensaje ha sido enviado exitosamente.', timer:2500, showConfirmButton:false});
      } else if (urlParams.get('status') === 'draft_saved') {
        Swal.fire({icon:'success', title:'Borrador Guardado', text:'El borrador se guardó en el historial.', timer:2500, showConfirmButton:false});
      } else if (urlParams.get('status') === 'error') {
        Swal.fire({icon:'error', title:'Error al Enviar', text: urlParams.get('msg')||'No se pudo procesar el envío.'});
      }
      if (urlParams.get('status')) history.replaceState({}, '', window.location.pathname);
    </script>
  </body>
</html>
