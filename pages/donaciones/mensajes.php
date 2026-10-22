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
    $where[] = "(
        m.NombreDestino LIKE :search 
        OR m.EmailDestino LIKE :search 
        OR m.Asunto LIKE :search 
        OR m.Mensaje LIKE :search
        OR dn.Nombre LIKE :search
        OR dn.ApellidoPaterno LIKE :search
        OR dn.ApellidoMaterno LIKE :search
        OR dn_name.Nombre LIKE :search
        OR dn_name.ApellidoPaterno LIKE :search
        OR dn_name.ApellidoMaterno LIKE :search
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

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$fromJoins = "
    FROM donaciones_mensajes m
    LEFT JOIN donantes dn ON m.ID_Donante = dn.ID_Donante
    LEFT JOIN (
        SELECT ID_Donante, Nombre, ApellidoPaterno, ApellidoMaterno, Email
        FROM donantes
        GROUP BY ID_Donante
    ) dn_name ON (m.ID_Donante IS NULL AND (
        m.NombreDestino = CONCAT(dn_name.Nombre, ' ', dn_name.ApellidoPaterno, ' ', COALESCE(dn_name.ApellidoMaterno,''))
        OR m.NombreDestino = CONCAT(dn_name.Nombre, ' ', dn_name.ApellidoPaterno)
        OR m.NombreDestino = dn_name.Nombre
    ))
";

// Métricas de Mensajes
try {
    $totalMensajes = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes")->fetchColumn();
    $totalEnviados = (int)$conn->query("SELECT COUNT(*) FROM donaciones_mensajes WHERE Estado = 'Enviado'")->fetchColumn();
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
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$registrosPorPagina = 10;
$offset = ($pagina - 1) * $registrosPorPagina;

// Conteo filtrado
try {
    $stmtCount = $conn->prepare("SELECT COUNT(DISTINCT m.ID_Mensaje) $fromJoins $whereSql");
    $stmtCount->execute($params);
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = max(1, (int)ceil($totalRegistros / $registrosPorPagina));
} catch (PDOException $e) {
    $totalRegistros = 0;
    $totalPaginas = 1;
}

// Lista de mensajes
try {
    $sqlList = "
        SELECT m.ID_Mensaje, m.ID_Donante, m.NombreDestino, m.EmailDestino, m.Asunto, m.Mensaje,
               m.ArchivoAdjunto, m.TipoMensaje, m.Estado, m.FechaRegistro,
               COALESCE(
                   NULLIF(TRIM(CONCAT(dn.Nombre, ' ', dn.ApellidoPaterno, ' ', COALESCE(dn.ApellidoMaterno,''))), ''),
                   NULLIF(TRIM(CONCAT(dn_name.Nombre, ' ', dn_name.ApellidoPaterno, ' ', COALESCE(dn_name.ApellidoMaterno,''))), ''),
                   m.NombreDestino
               ) AS DestinatarioCompleto
        $fromJoins
        $whereSql
        GROUP BY m.ID_Mensaje
        ORDER BY m.ID_Mensaje DESC
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
                <button type="button" class="btn btn-purple btn-sm px-3 py-2 fw-semibold" onclick="abrirModalRedactar()">
                  <i class="bi bi-plus-circle me-1"></i> Redactar Nuevo Correo
                </button>
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

          <!-- Filtro y buscador en tiempo real -->
          <div class="card-sf mb-4">
            <div class="card-body p-3">
              <div class="row g-2 align-items-center">
                <div class="col-md-6 col-sm-6">
                  <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-purple"></i></span>
                    <input type="text" class="form-control" id="liveSearch"
                           placeholder="Buscar por destinatario, correo o asunto..."
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
                <div class="col-md-3 col-sm-3">
                  <select id="filtroEstado" class="form-select" onchange="buscarMensajes()">
                    <option value="">Todos los Estados</option>
                    <option value="Enviado" <?= $filtroEstado === 'Enviado' ? 'selected' : '' ?>>Enviados</option>
                    <option value="Borrador" <?= $filtroEstado === 'Borrador' ? 'selected' : '' ?>>Borradores</option>
                    <option value="Fallido" <?= $filtroEstado === 'Fallido' ? 'selected' : '' ?>>Fallidos</option>
                  </select>
                </div>
                <div class="col-md-3 col-sm-3">
                  <select id="filtroTipo" class="form-select" onchange="buscarMensajes()">
                    <option value="">Todos los Tipos</option>
                    <option value="Manual" <?= $filtroTipo === 'Manual' ? 'selected' : '' ?>>Manuales</option>
                    <option value="Agradecimiento" <?= $filtroTipo === 'Agradecimiento' ? 'selected' : '' ?>>Agradecimientos</option>
                    <option value="Cumpleaños" <?= $filtroTipo === 'Cumpleaños' ? 'selected' : '' ?>>Cumpleaños</option>
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
              <span><i class="bi bi-envelope-paper-heart me-2"></i> Registro de Mensajes</span>
              <small class="text-muted fw-normal">Total: <span id="contadorTotal"><?= number_format($totalRegistros) ?></span> registros</small>
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
                      <th>Asunto / Adjunto</th>
                      <th class="text-center">Fecha de Registro</th>
                      <th class="text-center" style="width: 70px;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyMensajes">
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
                            <strong class="text-dark"><?= htmlspecialchars($m['DestinatarioCompleto'] ?: $m['NombreDestino']) ?></strong>
                          </td>
                          <td>
                            <span class="small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($m['EmailDestino']) ?></span>
                          </td>
                          <td>
                            <span class="fw-semibold text-dark d-block"><?= htmlspecialchars($m['Asunto']) ?></span>
                            <?php if (!empty($m['ArchivoAdjunto'])): ?>
                              <span class="badge bg-light text-primary border mt-1">
                                <i class="bi bi-paperclip me-1"></i><?= htmlspecialchars($m['ArchivoAdjunto']) ?>
                              </span>
                            <?php endif; ?>
                          </td>
                          <td class="text-center">
                            <span class="small text-secondary">
                              <i class="bi bi-clock me-1"></i><?= date('d/m/Y h:i A', strtotime($m['FechaRegistro'])) ?>
                            </span>
                          </td>
                          <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-purple" onclick="verDetalleMensaje(<?= htmlspecialchars(json_encode($m)) ?>)" title="Ver Detalle" style="padding: 4px 9px;">
                              <i class="bi bi-eye-fill"></i>
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
              <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
                <small class="text-muted">Mostrando registros <?= $offset + 1 ?> a <?= min($offset + $registrosPorPagina, $totalRegistros) ?> de <?= $totalRegistros ?></small>
                <nav>
                  <ul class="pagination pagination-sm mb-0">
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
                  </ul>
                </nav>
              </div>
            <?php endif; ?>
            </div>
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
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Redactar Nuevo Correo Directo -->
    <div class="modal fade" id="modalEnviarCorreo" tabindex="-1" aria-labelledby="modalEnviarCorreoLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
          <div class="modal-header text-white" style="background:#721896;">
            <h5 class="modal-title" id="modalEnviarCorreoLabel"><i class="bi bi-envelope-paper-heart me-2"></i> Redactar Correo Institucional</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="acciones/enviar-correo.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="return_url" value="../mensajes.php">
            <input type="hidden" name="id_donante" id="correo_id_donante" value="">
            <div class="modal-body p-4">
              
              <div class="mb-3">
                <label class="form-label fw-semibold">Seleccionar Plantilla Rápida</label>
                <select class="form-select" id="plantillaSelect" onchange="cambiarPlantilla()">
                  <option value="cumpleanos">Tarjeta de Feliz Cumpleaños</option>
                  <option value="agradecimiento">Carta de Agradecimiento por Donativo</option>
                  <option value="recibo">Envío de Recibo de Donativo / Comprobante</option>
                  <option value="informativo">Informe de Actividades y Resultados</option>
                  <option value="personalizado">Mensaje Personalizado</option>
                </select>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Nombre del Donante / Saludo <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="nombre" id="correo_nombre" 
                         list="lista_donantes_datalist" 
                         placeholder="Nombre completo o de pila" 
                         oninput="manejarInputNombre(this.value)" 
                         onchange="manejarInputNombre(this.value)" 
                         autocomplete="off" required>
                  <datalist id="lista_donantes_datalist">
                    <?php foreach ($todosDonantesLista as $dn): ?>
                      <option value="<?= htmlspecialchars($dn['NombreCompleto']) ?>" 
                              data-email="<?= htmlspecialchars($dn['Email'] ?? '') ?>" 
                              data-id="<?= $dn['ID_Donante'] ?>">
                        <?= htmlspecialchars($dn['Email'] ? $dn['Email'] : 'Sin correo') ?>
                      </option>
                    <?php endforeach; ?>
                  </datalist>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                  <input type="email" class="form-control" name="email" id="correo_email" placeholder="correo@ejemplo.com" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Asunto del Correo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="asunto" id="correo_asunto" value="Mensaje de la Familia GesMujer Oaxaca" required>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Mensaje <span class="text-danger">*</span></label>
                <textarea class="form-control" name="mensaje" id="correo_mensaje" rows="5" required placeholder="Escribe el contenido del mensaje..."></textarea>
              </div>

              <!-- Campo de Archivo Adjunto (Recibo PDF / Imagen / Doc) -->
              <div class="mb-2 p-3 bg-light rounded-3 border">
                <label class="form-label fw-semibold mb-1"><i class="bi bi-paperclip text-purple me-1"></i> Adjuntar Recibo, Comprobante o Documento (Opcional)</label>
                <input type="file" name="adjunto" id="correo_adjunto" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xls,.xlsx">
                <div class="form-text text-muted">Formatos permitidos: PDF, Word (DOC/DOCX), Imágenes (JPG, PNG) o Excel (hasta 15MB).</div>
              </div>

            </div>
            <div class="modal-footer bg-light justify-content-between">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <div class="d-flex gap-2">
                <button type="submit" name="accion" value="borrador" class="btn btn-outline-warning fw-semibold"><i class="bi bi-file-earmark-text me-1"></i> Guardar Borrador</button>
                <button type="submit" name="accion" value="enviar" class="btn btn-purple fw-semibold"><i class="bi bi-send-fill me-1"></i> Enviar</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="../../assets/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      function abrirModalRedactar() {
        document.getElementById('correo_nombre').value = '';
        document.getElementById('correo_email').value = '';
        const idInput = document.getElementById('correo_id_donante');
        if (idInput) idInput.value = '';
        document.getElementById('plantillaSelect').value = 'cumpleanos';
        cambiarPlantilla();
        new bootstrap.Modal(document.getElementById('modalEnviarCorreo')).show();
      }

      function manejarInputNombre(val) {
        const nombre = (val || '').trim();
        const datalist = document.getElementById('lista_donantes_datalist');
        const emailInput = document.getElementById('correo_email');
        const idInput = document.getElementById('correo_id_donante');

        actualizarNombreEnMensaje();

        if (!datalist) return;

        // Comprobar coincidencia exacta con opciones del datalist para autocompletar correo e ID
        const options = datalist.querySelectorAll('option');
        let encontrado = false;

        for (let i = 0; i < options.length; i++) {
          const opt = options[i];
          if (opt.value.toLowerCase() === nombre.toLowerCase()) {
            const email = opt.getAttribute('data-email') || '';
            const idDonante = opt.getAttribute('data-id') || '';
            if (emailInput && email) emailInput.value = email;
            if (idInput) idInput.value = idDonante;
            encontrado = true;
            break;
          }
        }

        // Si no coincide exactamente pero tiene al menos 2 letras, cargar donantes dinámicamente al datalist
        if (!encontrado && nombre.length >= 2) {
          buscarDonantesDatalist(nombre);
        }
      }

      let timeoutDatalist = null;
      async function buscarDonantesDatalist(q) {
        clearTimeout(timeoutDatalist);
        timeoutDatalist = setTimeout(async () => {
          try {
            const res = await fetch('ajax/buscar_donante.php?q=' + encodeURIComponent(q));
            const donantes = await res.json();
            if (!Array.isArray(donantes)) return;

            const datalist = document.getElementById('lista_donantes_datalist');
            if (!datalist) return;

            donantes.forEach(d => {
              const existe = Array.from(datalist.options).some(o => o.value.toLowerCase() === d.NombreCompleto.toLowerCase());
              if (!existe) {
                const opt = document.createElement('option');
                opt.value = d.NombreCompleto;
                opt.setAttribute('data-email', d.Email || '');
                opt.setAttribute('data-id', d.ID_Donante);
                opt.textContent = d.Email ? d.Email : 'Sin correo';
                datalist.appendChild(opt);
              }
            });
          } catch (e) {
            console.error("Error al buscar donantes:", e);
          }
        }, 220);
      }

      function actualizarNombreEnMensaje() {
        const nom = document.getElementById('correo_nombre').value.trim() || '[Nombre del Donante]';
        const txt = document.getElementById('correo_mensaje');
        if (!txt) return;

        const sel = document.getElementById('plantillaSelect').value;
        if (sel === 'cumpleanos') {
          if (!txt.value.includes('en el GESMujer no solo celebramos')) {
            cambiarPlantilla();
            return;
          }
          txt.value = txt.value.replace(/^[^\n,]+, en el GESMujer no solo celebramos/m, `${nom}, en el GESMujer no solo celebramos`);
          txt.value = txt.value.replace(/\[Nombre del Donante\]/g, nom);
        } else if (sel === 'agradecimiento') {
          if (!txt.value.includes('Querido(a)')) {
            cambiarPlantilla();
            return;
          }
          txt.value = txt.value.replace(/Querido\(a\) [^,\n]+,/g, `Querido(a) ${nom},`);
          txt.value = txt.value.replace(/\[Nombre del Donante\]/g, nom);
        } else if (sel === 'recibo' || sel === 'informativo') {
          if (!txt.value.includes('Estimado(a)')) {
            cambiarPlantilla();
            return;
          }
          txt.value = txt.value.replace(/Estimado\(a\) [^,\n]+,/g, `Estimado(a) ${nom},`);
          txt.value = txt.value.replace(/\[Nombre del Donante\]/g, nom);
        }
      }

      function cambiarPlantilla() {
        const sel = document.getElementById('plantillaSelect').value;
        const txt = document.getElementById('correo_mensaje');
        const asunto = document.getElementById('correo_asunto');
        let nom = document.getElementById('correo_nombre').value.trim();
        if (!nom) nom = '[Nombre del Donante]';

        const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        const hoy = new Date();
        const fechaCarta = `Oaxaca de Juárez, Oaxaca a ${hoy.getDate()} de ${meses[hoy.getMonth()]} de ${hoy.getFullYear()}`;

        if (sel === 'agradecimiento') {
          asunto.value = '¡Gracias por sumarte a transformar vidas en Oaxaca! - GesMujer';
          txt.value = `${fechaCarta}\n\n` +
            `Querido(a) ${nom}, ¡gracias por sumarte a transformar vidas en Oaxaca!\n\n` +
            `A nombre de todas las personas que formamos parte del Grupo de Estudios sobre la Mujer Rosario Castellanos, GesMujer, quiero expresarte nuestro más sincero agradecimiento por tu generoso donativo.\n\n` +
            `Acciones como la tuya hacen posible que sigamos caminando junto a mujeres, jóvenes y niñas de diversas comunidades indígenas y rurales del estado de Oaxaca. Gracias a tu valioso respaldo, podemos fortalecer nuestras acciones de acompañamiento, formación, defensa de sus derechos e impulso a su autonomía, brindándoles herramientas para construir proyectos de vida con dignidad, justicia y libres de violencia.\n\n` +
            `En GesMujer estamos convencidas de que la transformación comunitaria se logra uniendo voluntades. Tu contribución no solo representa un apoyo económico indispensable, sino también un mensaje directo de esperanza y solidaridad para quienes más lo necesitan.\n\n` +
            `Nos comprometemos firmemente a administrar tu aportación con total transparencia y con la dedicación que nos ha caracterizado por más de cuatro décadas de trabajo en favor de las mujeres oaxaqueñas.\n\n` +
            `Te reiteramos nuestra gratitud y dejamos nuestras puertas abiertas para mantenerte informado sobre el impacto y los logros que tu donativo ayuda a construir día con día.\n\n` +
            `Con aprecio y respeto,\n\n` +
            `María del Rosario Martínez Miguel\n` +
            `Grupo de Estudios sobre la Mujer Rosario Castellanos (GesMujer)`;
        } else if (sel === 'recibo') {
          asunto.value = 'Envío de Recibo de Donativo - GesMujer Oaxaca';
          txt.value = `Estimado(a) ${nom},\n\nLe hacemos llegar adjunto a este correo su recibo/comprobante oficial correspondiente a su reciente donativo.\n\nReiteramos nuestra gratitud por su generosidad y confianza en nuestras causas.\n\nAtentamente,\nEquipo GesMujer Oaxaca`;
        } else if (sel === 'cumpleanos') {
          asunto.value = 'En GESMujer Te deseamos... ¡Feliz Cumpleaños!';
          txt.value = `¡Que tengas el mejor de los días!\n\n` +
            `${nom}, en el GESMujer no solo celebramos tu cumpleaños, ¡También el tiempo de amistad y cariño! Gracias por ser parte de nuestra historia, tu apoyo siempre constante nos llena de alegría la vida. Deseamos con cariño que la sigas pasando... ¡Estupendo! Disfruta tu día en compañía de quienes más quieres, dedícate tiempo y déjate querer mucho.\n\n` +
            `Recibe los mejores deseos y muchos abrazos con cariño.\n\n` +
            `¡ Felicidades !\n\n` +
            `Atentamente.\n` +
            `Grupo de Estudios Sobre la Mujer "Rosario Castellanos" A.C.`;
        } else if (sel === 'informativo') {
          asunto.value = 'Informe de Actividades y Resultados - GesMujer Oaxaca';
          txt.value = `Estimado(a) ${nom},\n\nLe compartimos nuestro reporte de actividades y avances en los programas de apoyo a mujeres. Gracias a su solidaridad esto es posible.\n\nQuedamos a su disposición para cualquier duda.`;
        } else if (sel === 'personalizado') {
          asunto.value = 'Mensaje de la Familia GesMujer Oaxaca';
          txt.value = '';
        }
      }

      function verDetalleMensaje(msg) {
        const nombreDest = msg.DestinatarioCompleto || msg.NombreDestino || 'Sin destinatario';
        document.getElementById('m_destino_nombre').innerText = nombreDest;
        document.getElementById('m_destino_email').innerText = 'Correo: ' + (msg.EmailDestino || 'Sin correo');
        document.getElementById('m_asunto').innerText = msg.Asunto || '';
        document.getElementById('m_contenido').innerText = msg.Mensaje || '';
        document.getElementById('m_fecha').innerText = 'Fecha de Registro: ' + (msg.FechaRegistro || '');

        const contAdj = document.getElementById('m_container_adjunto');
        if (msg.ArchivoAdjunto) {
          document.getElementById('m_adjunto_nombre').innerText = msg.ArchivoAdjunto;
          contAdj.classList.remove('d-none');
        } else {
          contAdj.classList.add('d-none');
        }

        let badges = '';
        if (msg.Estado === 'Enviado') {
          badges += '<span class="badge bg-success me-1">Enviado</span>';
        } else if (msg.Estado === 'Borrador') {
          badges += '<span class="badge bg-warning me-1 text-dark">Borrador</span>';
        } else {
          badges += '<span class="badge bg-danger me-1">Fallido</span>';
        }

        badges += `<span class="badge px-2 py-1" style="background-color:#f3e8ff; color:#721896;">${msg.TipoMensaje}</span>`;
        document.getElementById('m_badges').innerHTML = badges;

        new bootstrap.Modal(document.getElementById('modalVerMensaje')).show();
      }

      // ─── BÚSQUEDA EN TIEMPO REAL ─────────────────────────────────────────
      let searchTimeout = null;
      let paginaActual = <?= $pagina ?>;

      const inputSearch   = document.getElementById('liveSearch');
      const selEstado     = document.getElementById('filtroEstado');
      const selTipo       = document.getElementById('filtroTipo');
      const btnLimpiar    = document.getElementById('btnLimpiar');
      const searchHint    = document.getElementById('searchHint');
      const searchHintTxt = document.getElementById('searchHintText');
      const spinner       = document.getElementById('searchSpinner');

      inputSearch.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { paginaActual = 1; buscarMensajes(); }, 320);
        btnLimpiar.style.display = inputSearch.value.trim() !== '' ? '' : 'none';
      });

      function limpiarBusqueda() {
        inputSearch.value = '';
        btnLimpiar.style.display = 'none';
        searchHint.style.display = 'none';
        paginaActual = 1;
        buscarMensajes();
      }

      function irPagina(p) { 
        paginaActual = p; 
        buscarMensajes(); 
      }

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
            else { searchHint.style.display = 'none'; }
          })
          .catch(() => { spinner.style.display = 'none'; });
      }

      function badgeEstado(estado) {
        if (estado === 'Enviado') {
          return '<span class="badge bg-success-subtle text-success px-2 py-1"><i class="bi bi-check-circle me-1"></i> Enviado</span>';
        } else if (estado === 'Borrador') {
          return '<span class="badge bg-warning-subtle text-warning px-2 py-1"><i class="bi bi-file-earmark-text me-1"></i> Borrador</span>';
        } else {
          return '<span class="badge bg-danger-subtle text-danger px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i> Fallido</span>';
        }
      }

      function badgeTipo(tipo) {
        if (tipo === 'Cumpleaños') {
          return '<span class="badge bg-danger-subtle text-danger"><i class="bi bi-gift me-1"></i> Cumpleaños</span>';
        } else if (tipo === 'Agradecimiento') {
          return '<span class="badge px-2 py-1" style="background-color:#f3e8ff; color:#721896;"><i class="bi bi-heart-fill me-1"></i> Agradecimiento</span>';
        } else {
          return '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-send me-1"></i> Manual</span>';
        }
      }

      function renderizarTabla(data) {
        const tbody = document.getElementById('tbodyMensajes');
        document.getElementById('contadorTotal').textContent = data.totalRegistros;

        if (!data.mensajes || data.mensajes.length === 0) {
          tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>No se encontraron mensajes registrados con los filtros aplicados.</td></tr>`;
          document.getElementById('paginacionContainer').innerHTML = '';
          return;
        }

        tbody.innerHTML = data.mensajes.map(m => {
          const nombreCompleto = escHtml(m.DestinatarioCompleto || m.NombreDestino || 'Sin destinatario');
          const adjuntoHtml = m.ArchivoAdjunto 
            ? `<span class="badge bg-light text-primary border mt-1"><i class="bi bi-paperclip me-1"></i>${escHtml(m.ArchivoAdjunto)}</span>` 
            : '';

          let fechaStr = '';
          if (m.FechaRegistro) {
            const dateObj = new Date(m.FechaRegistro.replace(/-/g, '/'));
            fechaStr = dateObj.toLocaleString('es-MX', {
              day: '2-digit', month: '2-digit', year: 'numeric',
              hour: '2-digit', minute: '2-digit', hour12: true
            });
          }

          const msgJsonSafe = JSON.stringify(m).replace(/'/g, "&#39;");

          return `<tr>
            <td>${badgeEstado(m.Estado)}</td>
            <td>${badgeTipo(m.TipoMensaje)}</td>
            <td><strong class="text-dark">${nombreCompleto}</strong></td>
            <td><span class="small text-muted"><i class="bi bi-envelope me-1"></i>${escHtml(m.EmailDestino || '')}</span></td>
            <td>
              <span class="fw-semibold text-dark d-block">${escHtml(m.Asunto || '')}</span>
              ${adjuntoHtml}
            </td>
            <td class="text-center">
              <span class="small text-secondary"><i class="bi bi-clock me-1"></i>${escHtml(fechaStr)}</span>
            </td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-outline-purple" onclick='verDetalleMensaje(${msgJsonSafe})' title="Ver Detalle" style="padding: 4px 9px;">
                <i class="bi bi-eye-fill"></i>
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
          <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">Mostrando registros ${offset + 1} a ${hasta} de ${data.totalRegistros}</small>
            <nav><ul class="pagination pagination-sm mb-0">
              <li class="page-item ${data.pagina <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina - 1});return false;">Anterior</a></li>
              ${links}
              <li class="page-item ${data.pagina >= data.totalPaginas ? 'disabled' : ''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina + 1});return false;">Siguiente</a></li>
            </ul></nav>
          </div>`;
      }

      function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }
      // ─────────────────────────────────────────────────────────────────────

      // Alertas de URL
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('status') === 'sent') {
        Swal.fire({ icon: 'success', title: '¡Correo Enviado!', text: 'El mensaje ha sido enviado exitosamente.', timer: 2500, showConfirmButton: false });
      } else if (urlParams.get('status') === 'draft_saved') {
        Swal.fire({ icon: 'success', title: 'Borrador Guardado', text: 'El borrador se guardó en el historial.', timer: 2500, showConfirmButton: false });
      } else if (urlParams.get('status') === 'error') {
        Swal.fire({ icon: 'error', title: 'Error al Enviar', text: urlParams.get('msg') || 'No se pudo procesar el envío.' });
      }
    </script>
  </body>
</html>
