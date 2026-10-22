<?php
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';

// Parámetros de paginación y búsqueda
$registrosPorPagina = 10;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $registrosPorPagina;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtroEstatus = isset($_GET['estatus']) ? trim($_GET['estatus']) : '';

try {
    $whereConditions = [];
    $params = [];

    if ($search !== '') {
        $whereConditions[] = "(d.Nombre LIKE :s OR d.ApellidoPaterno LIKE :s OR d.ApellidoMaterno LIKE :s OR d.Email LIKE :s OR d.Telefono LIKE :s OR d.Ocupacion LIKE :s OR d.Ciudad LIKE :s OR d.RFC LIKE :s)";
        $params[':s'] = "%$search%";
    }

    if ($filtroEstatus !== '') {
        $whereConditions[] = "d.Estatus = :estatus";
        $params[':estatus'] = $filtroEstatus;
    }

    $whereSQL = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    // Conteo total
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM donantes d" . $whereSQL);
    foreach ($params as $key => $val) {
        $stmtCount->bindValue($key, $val);
    }
    $stmtCount->execute();
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

    // Consulta con total aportado acumulado, estatus y última donación
    $sql = "
        SELECT d.ID_Donante, d.Nombre, d.ApellidoPaterno, d.ApellidoMaterno, d.Email, d.Telefono,
               d.FechaNacimiento, d.Ocupacion, d.Domicilio, d.Ciudad, d.Estado, d.RFC,
               COALESCE(d.Estatus, 'Activo') AS Estatus,
               COALESCE(SUM(dn.MontoDonacion), 0) AS TotalAportado,
               COUNT(dn.ID_Donativo) AS NumeroDonaciones,
               MAX(dn.FechaDonacion) AS UltimaDonacion
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

    // Lista de donantes para selector del modal
    $todosDonantesSelect = $conn->query("SELECT ID_Donante, Nombre, CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno, '')) AS NombreCompleto, Email FROM donantes ORDER BY Nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $donantes = [];
    $todosDonantesSelect = [];
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
    <?php require_once __DIR__ . '/header.php'; ?>

    <!-- Menú lateral -->
    <?php require_once __DIR__ . '/footer.php'; ?>

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
              <i class="bi bi-cash-stack me-1"></i> Registrar Donativo
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
          <a class="nav-link" href="donativos.php"><i class="bi bi-coin me-1"></i> Historial de Donativos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="mensajes.php"><i class="bi bi-envelope-paper-fill me-1"></i> Historial de Mensajes</a>
        </li>
      </ul>

      <!-- Filtro y buscador (búsqueda en tiempo real) -->
      <div class="card-sf p-3 mb-4">
        <div class="row g-2 align-items-center">
          <div class="col-md-7 col-sm-7">
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-search text-purple"></i></span>
              <input type="text" class="form-control" id="liveSearch"
                     placeholder="Buscar por nombre, email, teléfono o RFC..."
                     value="<?= htmlspecialchars($search) ?>"
                     autocomplete="off">
              <span class="input-group-text bg-white border-start-0" id="searchSpinner" style="display:none!important;">
                <span class="spinner-border spinner-border-sm text-purple" role="status"></span>
              </span>
              <button class="btn btn-outline-secondary" id="btnLimpiar" title="Limpiar búsqueda" style="display:none;" onclick="limpiarBusqueda()">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>
          <div class="col-md-5 col-sm-5">
            <select class="form-select" id="filtroEstatus" onchange="buscarDonantes()">
              <option value="">Todos los Estados (Activos / Inactivos)</option>
              <option value="Activo" <?= ($filtroEstatus === 'Activo') ? 'selected' : '' ?>>Activos</option>
              <option value="Inactivo" <?= ($filtroEstatus === 'Inactivo') ? 'selected' : '' ?>>Inactivos</option>
            </select>
          </div>
        </div>
        <div class="mt-2" id="searchHint" style="display:none;">
          <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Mostrando resultados para: <strong id="searchHintText"></strong></small>
        </div>
      </div>

      <!-- Tabla de Donantes (renderizada por JS o PHP en carga inicial) -->
      <div class="card-sf" id="tablaCard">
        <div class="card-sf-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-person-lines-fill me-2 text-primary"></i> Directorio de Donantes (<span id="contadorTotal"><?= $totalRegistros ?></span>)</span>
          <span class="badge bg-light text-dark border" id="paginaLabel">Página <?= $pagina ?> de <?= max(1, $totalPaginas) ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaDonantes">
              <thead class="table-light small text-uppercase">
                <tr>
                  <th style="width: 50px;">ID</th>
                  <th>Donante / Ocupación</th>
                  <th>Contacto</th>
                  <th class="text-center">Estado</th>
                  <th class="text-end">Total Donado</th>
                  <th class="text-center" style="width: 130px;">Acciones</th>
                </tr>
              </thead>
              <tbody id="tbodyDonantes">
                <?php if (empty($donantes)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                      <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                      No se encontraron donantes con los filtros seleccionados.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($donantes as $d): ?>
                    <?php $esActivo = (($d['Estatus'] ?? 'Activo') === 'Activo'); ?>
                    <tr>
                      <td class="text-muted fw-semibold">#<?= $d['ID_Donante'] ?></td>
                      <td>
                        <div class="fw-semibold text-dark">
                          <a href="javascript:void(0)" onclick="verFichaDonante(<?= $d['ID_Donante'] ?>)" class="text-dark text-decoration-none hover-purple">
                            <?= htmlspecialchars($d['Nombre'] . ' ' . $d['ApellidoPaterno'] . ' ' . $d['ApellidoMaterno']) ?>
                          </a>
                        </div>
                        <?php if (!empty($d['Ocupacion'])): ?>
                          <small class="text-muted"><i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($d['Ocupacion']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($d['RFC'])): ?>
                          <span class="badge bg-light text-secondary border ms-1" style="font-size:0.7rem;">RFC: <?= htmlspecialchars($d['RFC']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($d['Email']): ?>
                          <div>
                            <a href="mailto:<?= htmlspecialchars($d['Email']) ?>" class="text-decoration-none text-muted small">
                              <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($d['Email']) ?>
                            </a>
                          </div>
                        <?php endif; ?>
                        <?php if ($d['Telefono']): ?>
                          <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($d['Telefono']) ?></small>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <?php if ($esActivo): ?>
                          <span class="badge bg-success-subtle text-success px-2 py-1" style="cursor:pointer;" onclick="cambiarEstatus(<?= $d['ID_Donante'] ?>, 'Inactivo')" title="Clic para marcar como Inactivo">
                            <i class="bi bi-check-circle-fill me-1"></i>Activo
                          </span>
                        <?php else: ?>
                          <span class="badge bg-secondary-subtle text-secondary px-2 py-1" style="cursor:pointer;" onclick="cambiarEstatus(<?= $d['ID_Donante'] ?>, 'Activo')" title="Clic para Reactivar">
                            <i class="bi bi-pause-circle-fill me-1"></i>Inactivo
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <span class="fw-bold text-success">$<?= number_format((float)$d['TotalAportado'], 2) ?></span>
                      </td>
                      <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                          <button type="button" class="btn btn-outline-purple" title="Ver Ficha del Donante" onclick="verFichaDonante(<?= $d['ID_Donante'] ?>)">
                            <i class="bi bi-eye-fill"></i>
                          </button>
                          <a href="donativo-nuevo.php?id_donante=<?= $d['ID_Donante'] ?>" class="btn btn-outline-success" title="Registrar Aportación">
                            <i class="bi bi-plus-circle-fill"></i>
                          </a>
                        </div>
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
            <small class="text-muted" id="paginaInfo">Mostrando <?= $offset + 1 ?> a <?= min($offset + $registrosPorPagina, $totalRegistros) ?> de <?= $totalRegistros ?></small>
            <nav><ul class="pagination pagination-sm mb-0" id="paginacionLinks">
              <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="#" onclick="irPagina(<?= $pagina - 1 ?>); return false;">Anterior</a>
              </li>
              <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                  <a class="page-link" href="#" onclick="irPagina(<?= $i ?>); return false;"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>">
                <a class="page-link" href="#" onclick="irPagina(<?= $pagina + 1 ?>); return false;">Siguiente</a>
              </li>
            </ul></nav>
          </div>
        <?php endif; ?>
        </div>
      </div>

    </main>
  </div>
</div>

    <!-- Modal: Ficha del Donante e Historial de Donaciones -->
    <div class="modal fade" id="modalFichaDonante" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
          <div class="modal-header text-white" style="background:#721896;">
            <h5 class="modal-title" id="fd_titulo"><i class="bi bi-person-vcard-fill me-2"></i> Ficha Integral del Donante</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4" id="fd_cargando" style="text-align: center; padding: 40px;">
            <div class="spinner-border text-purple" role="status"></div>
            <p class="text-muted mt-2">Cargando información y donaciones del donante...</p>
          </div>
          <div class="modal-body p-4 d-none" id="fd_contenido">
            
            <!-- Encabezado de la Ficha -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pb-3 mb-3 border-bottom">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-3" style="width: 58px; height: 58px; background: #8e3ec0;" id="fd_avatar">
                  D
                </div>
                <div>
                  <h4 class="fw-bold mb-0 text-dark" id="fd_nombre">Nombre Donante</h4>
                  <div class="d-flex align-items-center gap-2 mt-1">
                    <span id="fd_badge_estatus"></span>
                    <span class="text-muted small" id="fd_rfc"></span>
                  </div>
                </div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="fd_btn_toggle_estatus" onclick="toggleEstatusModal()">
                  <i class="bi bi-arrow-repeat me-1"></i> Cambiar Estado
                </button>
                <a href="#" class="btn btn-sm btn-purple" id="fd_btn_donar">
                  <i class="bi bi-cash-stack me-1"></i> + Nueva Aportación
                </a>
                <button class="btn btn-sm btn-outline-purple" id="fd_btn_correo">
                  <i class="bi bi-envelope-paper-heart me-1"></i> Redactar Correo
                </button>
              </div>
            </div>

            <!-- KPIs Rápidos del Donante -->
            <div class="row g-3 mb-4">
              <div class="col-md-4 col-sm-4 col-12">
                <div class="p-3 rounded-3 bg-light border text-center">
                  <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 0.72rem;">Total Aportado</small>
                  <h4 class="fw-bold text-success mb-0 mt-1" id="fd_kpi_total">$0.00</h4>
                </div>
              </div>
              <div class="col-md-4 col-sm-4 col-12">
                <div class="p-3 rounded-3 bg-light border text-center">
                  <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 0.72rem;">N° Donativos</small>
                  <h4 class="fw-bold text-dark mb-0 mt-1" id="fd_kpi_donativos">0</h4>
                </div>
              </div>
              <div class="col-md-4 col-sm-4 col-12">
                <div class="p-3 rounded-3 bg-light border text-center">
                  <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 0.72rem;">Última Aportación</small>
                  <h5 class="fw-bold text-secondary mb-0 mt-1" id="fd_kpi_ultima">Sin registro</h5>
                </div>
              </div>
            </div>

            <!-- Fila de Información Personal y de Contacto -->
            <div class="card border mb-4 bg-light">
              <div class="card-body p-3">
                <div class="row g-3 small">
                  <div class="col-md-4">
                    <span class="text-muted d-block">Correo Electrónico:</span>
                    <strong class="text-dark" id="fd_email">-</strong>
                  </div>
                  <div class="col-md-4">
                    <span class="text-muted d-block">Teléfono:</span>
                    <strong class="text-dark" id="fd_telefono">-</strong>
                  </div>
                  <div class="col-md-4">
                    <span class="text-muted d-block">Fecha de Cumpleaños:</span>
                    <strong class="text-dark" id="fd_cumple">-</strong>
                  </div>
                  <div class="col-md-4">
                    <span class="text-muted d-block">Ocupación / Profesión:</span>
                    <strong class="text-dark" id="fd_ocupacion">-</strong>
                  </div>
                  <div class="col-md-8">
                    <span class="text-muted d-block">Domicilio / Ubicación:</span>
                    <strong class="text-dark" id="fd_domicilio">-</strong>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tabla del Historial de Donativos -->
            <h6 class="fw-bold text-purple mb-3 text-uppercase" style="color:#721896; letter-spacing: 0.5px;">
              <i class="bi bi-clock-history me-1"></i> Historial Completo de Aportaciones
            </h6>
            <div class="table-responsive border rounded-3 bg-white mb-2">
              <table class="table table-hover align-middle mb-0" id="fd_tabla_donativos">
                <thead class="table-light small text-uppercase">
                  <tr>
                    <th style="width: 70px;">Folio</th>
                    <th>Fecha y Hora</th>
                    <th>Método / Tipo</th>
                    <th class="text-end">Monto / Detalle</th>
                    <th class="text-center" style="width: 110px;">Carta PDF</th>
                  </tr>
                </thead>
                <tbody id="fd_tbody_donativos">
                  <!-- Se llena dinámicamente -->
                </tbody>
              </table>
            </div>

          </div>
          <div class="modal-footer bg-light justify-content-between">
            <a href="#" class="btn btn-outline-primary" id="fd_btn_editar_link"><i class="bi bi-pencil-square me-1"></i> Editar Datos</a>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar Ficha</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Enviar Correo con Archivo Adjunto (Recibo PDF / Imagen / Doc) -->
    <div class="modal fade" id="modalEnviarCorreo" tabindex="-1" aria-labelledby="modalEnviarCorreoLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
          <div class="modal-header text-white" style="background:#721896;">
            <h5 class="modal-title" id="modalEnviarCorreoLabel"><i class="bi bi-envelope-paper-heart me-2"></i> Enviar Correo</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="acciones/enviar-correo.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="return_url" value="../donantes.php">
            <input type="hidden" name="id_donante" id="correo_id_donante" value="">
            <div class="modal-body p-4">
              
              <div class="mb-3">
                <label class="form-label fw-semibold">Seleccionar Plantilla Rápida</label>
                <select class="form-select" id="plantillaSelect" onchange="cambiarPlantilla()">
                  <option value="cumpleanos">Tarjeta de Feliz Cumpleaños</option>
                  <option value="agradecimiento">Carta de Agradecimiento por Aportación</option>
                  <option value="recibo">Envío de Recibo de Donativo / Comprobante</option>
                  <option value="informativo">Informe de Actividades y Resultados</option>
                  <option value="personalizado">Mensaje Personalizado</option>
                </select>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Nombre del Donante<span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="nombre" id="correo_nombre" 
                         list="lista_donantes_datalist_donantes" 
                         placeholder="Nombre completo o de pila" 
                         oninput="manejarInputNombre(this.value)" 
                         onchange="manejarInputNombre(this.value)" 
                         autocomplete="off" required>
                  <datalist id="lista_donantes_datalist_donantes">
                    <?php foreach ($todosDonantesSelect as $dn): ?>
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
                <textarea class="form-control" name="mensaje" id="correo_mensaje" rows="5" required placeholder="Escribe el mensaje aquí..."></textarea>
              </div>

              <!-- Campo de Archivo Adjunto (Recibo PDF / Imagen / Doc) -->
              <div class="mb-2 p-3 bg-light rounded-3 border">
                <label class="form-label fw-semibold mb-1"><i class="bi bi-paperclip text-purple me-1"></i> Adjuntar Recibo, Comprobante o Documento</label>
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
      // ─── BÚSQUEDA EN TIEMPO REAL ────────────────────────────────────────────
      let searchTimeout = null;
      let paginaActual = <?= $pagina ?>;

      const inputSearch  = document.getElementById('liveSearch');
      const selEstatus   = document.getElementById('filtroEstatus');
      const btnLimpiar   = document.getElementById('btnLimpiar');
      const searchHint   = document.getElementById('searchHint');
      const searchHintTxt= document.getElementById('searchHintText');
      const spinner      = document.getElementById('searchSpinner');

      inputSearch.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          paginaActual = 1;
          buscarDonantes();
        }, 320);

        // Mostrar/ocultar botón limpiar
        btnLimpiar.style.display = inputSearch.value.trim() !== '' ? '' : 'none';
      });

      function limpiarBusqueda() {
        inputSearch.value = '';
        btnLimpiar.style.display = 'none';
        searchHint.style.display = 'none';
        paginaActual = 1;
        buscarDonantes();
      }

      function irPagina(p) {
        paginaActual = p;
        buscarDonantes();
      }

      function buscarDonantes() {
        const q       = inputSearch.value.trim();
        const estatus = selEstatus.value;
        const url     = `ajax/buscar_directorio.php?search=${encodeURIComponent(q)}&estatus=${encodeURIComponent(estatus)}&pagina=${paginaActual}`;

        spinner.style.removeProperty('display');
        fetch(url)
          .then(r => r.json())
          .then(data => {
            spinner.style.display = 'none';
            if (!data.success) return;
            renderizarTabla(data);

            // Hint de búsqueda
            if (q) {
              searchHintTxt.textContent = q;
              searchHint.style.display = '';
            } else {
              searchHint.style.display = 'none';
            }
          })
          .catch(() => { spinner.style.display = 'none'; });
      }

      function renderizarTabla(data) {
        const tbody = document.getElementById('tbodyDonantes');
        document.getElementById('contadorTotal').textContent = data.totalRegistros;
        document.getElementById('paginaLabel').textContent  = `Página ${data.pagina} de ${data.totalPaginas}`;

        if (!data.donantes || data.donantes.length === 0) {
          tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
            No se encontraron donantes con los filtros seleccionados.</td></tr>`;
          document.getElementById('paginacionContainer').innerHTML = '';
          return;
        }

        tbody.innerHTML = data.donantes.map(d => {
          const nombre = `${d.Nombre || ''} ${d.ApellidoPaterno || ''} ${d.ApellidoMaterno || ''}`.trim();
          const esActivo = (d.Estatus || 'Activo') === 'Activo';
          const total   = parseFloat(d.TotalAportado || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });
          const ocupBadge = d.Ocupacion ? `<small class="text-muted"><i class="bi bi-briefcase me-1"></i>${escHtml(d.Ocupacion)}</small>` : '';
          const rfcBadge  = d.RFC ? `<span class="badge bg-light text-secondary border ms-1" style="font-size:0.7rem;">RFC: ${escHtml(d.RFC)}</span>` : '';
          const emailHtml = d.Email ? `<div><a href="mailto:${escHtml(d.Email)}" class="text-decoration-none text-muted small"><i class="bi bi-envelope me-1"></i>${escHtml(d.Email)}</a></div>` : '';
          const telHtml   = d.Telefono ? `<small class="text-muted"><i class="bi bi-telephone me-1"></i>${escHtml(d.Telefono)}</small>` : '';
          const estatusBadge = esActivo
            ? `<span class="badge bg-success-subtle text-success px-2 py-1" style="cursor:pointer;" onclick="cambiarEstatus(${d.ID_Donante},'Inactivo')" title="Clic para marcar Inactivo"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>`
            : `<span class="badge bg-secondary-subtle text-secondary px-2 py-1" style="cursor:pointer;" onclick="cambiarEstatus(${d.ID_Donante},'Activo')" title="Clic para Reactivar"><i class="bi bi-pause-circle-fill me-1"></i>Inactivo</span>`;

          return `<tr>
            <td class="text-muted fw-semibold">#${d.ID_Donante}</td>
            <td>
              <div class="fw-semibold text-dark">
                <a href="javascript:void(0)" onclick="verFichaDonante(${d.ID_Donante})" class="text-dark text-decoration-none">${escHtml(nombre)}</a>
              </div>
              ${ocupBadge}${rfcBadge}
            </td>
            <td>${emailHtml}${telHtml}</td>
            <td class="text-center">${estatusBadge}</td>
            <td class="text-end"><span class="fw-bold text-success">$${total}</span></td>
            <td class="text-center">
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-purple" title="Ver Ficha del Donante" onclick="verFichaDonante(${d.ID_Donante})">
                  <i class="bi bi-eye-fill"></i>
                </button>
                <a href="donativo-nuevo.php?id_donante=${d.ID_Donante}" class="btn btn-outline-success" title="Registrar Aportación">
                  <i class="bi bi-plus-circle-fill"></i>
                </a>
              </div>
            </td>
          </tr>`;
        }).join('');

        // Paginación dinámica
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
            <small class="text-muted">Mostrando ${offset + 1} a ${hasta} de ${data.totalRegistros}</small>
            <nav><ul class="pagination pagination-sm mb-0">
              <li class="page-item ${data.pagina <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina - 1});return false;">Anterior</a></li>
              ${links}
              <li class="page-item ${data.pagina >= data.totalPaginas ? 'disabled' : ''}"><a class="page-link" href="#" onclick="irPagina(${data.pagina + 1});return false;">Siguiente</a></li>
            </ul></nav>
          </div>`;
      }

      function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
      }
      // ────────────────────────────────────────────────────────────────────────

      let currentDonanteId = null;
      let currentDonanteEstatus = 'Activo';

      // Función para abrir la Ficha y desplegar datos e historial del donante
      function verFichaDonante(id) {
        currentDonanteId = id;
        document.getElementById('fd_cargando').classList.remove('d-none');
        document.getElementById('fd_contenido').classList.add('d-none');
        
        const modal = new bootstrap.Modal(document.getElementById('modalFichaDonante'));
        modal.show();

        fetch(`ajax/historial_donante.php?id=${id}`)
          .then(res => res.json())
          .then(data => {
            if (!data.success) {
              Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo cargar la información.' });
              modal.hide();
              return;
            }

            const d = data.donante;
            const donativos = data.donativos;
            currentDonanteEstatus = d.Estatus || 'Activo';

            // Datos principales
            document.getElementById('fd_nombre').innerText = d.NombreCompleto;
            document.getElementById('fd_avatar').innerText = (d.Nombre ? d.Nombre.charAt(0).toUpperCase() : 'D');
            document.getElementById('fd_rfc').innerText = d.RFC ? `RFC: ${d.RFC}` : 'Sin RFC';
            
            // Badge de estatus
            const badgeContainer = document.getElementById('fd_badge_estatus');
            if (currentDonanteEstatus === 'Activo') {
              badgeContainer.innerHTML = `<span class="badge bg-success-subtle text-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Activo</span>`;
            } else {
              badgeContainer.innerHTML = `<span class="badge bg-secondary-subtle text-secondary px-2 py-1"><i class="bi bi-pause-circle-fill me-1"></i> Inactivo</span>`;
            }

            // KPIs
            document.getElementById('fd_kpi_total').innerText = '$' + parseFloat(d.TotalAportado || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('fd_kpi_donativos').innerText = d.NumeroDonaciones || '0';
            document.getElementById('fd_kpi_ultima').innerText = d.UltimaDonacion ? new Date(d.UltimaDonacion).toLocaleDateString('es-MX') : 'Sin aportaciones';

            // Datos de contacto
            document.getElementById('fd_email').innerText = d.Email || 'Sin correo registrado';
            document.getElementById('fd_telefono').innerText = d.Telefono || 'Sin teléfono';
            document.getElementById('fd_cumple').innerText = d.FechaNacimiento ? new Date(d.FechaNacimiento).toLocaleDateString('es-MX') : 'Sin fecha';
            document.getElementById('fd_ocupacion').innerText = d.Ocupacion || 'No especificada';
            document.getElementById('fd_domicilio').innerText = [d.Domicilio, d.Ciudad, d.Estado].filter(Boolean).join(', ') || 'No registrado';

            // Botones de acción del modal
            document.getElementById('fd_btn_donar').href = `donativo-nuevo.php?id_donante=${d.ID_Donante}`;
            document.getElementById('fd_btn_editar_link').href = `donante-editar.php?id=${d.ID_Donante}`;
            document.getElementById('fd_btn_correo').onclick = () => {
              modal.hide();
              prepararCorreoDonante(d.NombreCompleto, d.Email, d.ID_Donante);
            };

            // Tabla de donativos
            const tbody = document.getElementById('fd_tbody_donativos');
            tbody.innerHTML = '';
            if (donativos.length === 0) {
              tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted">Este donante aún no tiene aportaciones registradas.</td></tr>`;
            } else {
              donativos.forEach(item => {
                const montoHtml = parseFloat(item.MontoDonacion) > 0 
                  ? `<span class="badge bg-success-subtle text-success fs-6 fw-bold px-2 py-1">$${parseFloat(item.MontoDonacion).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</span>`
                  : `<span class="text-dark small fw-semibold"><i class="bi bi-box-seam me-1 text-purple"></i>${item.DescripcionEspecie || 'En especie'}</span>`;

                const fechaStr = item.FechaDonacion ? new Date(item.FechaDonacion).toLocaleString('es-MX') : 'Automática';
                let badgeMetodo = `<span class="badge px-2 py-1" style="background-color: #f8f9fa; color: #212529; border: 1px solid #dee2e6; font-weight: 600;"><i class="bi bi-credit-card-2-front me-1 text-primary"></i>${item.TipoDonacion || 'No especificado'}</span>`;
                if (item.TipoDonacion === 'En Especie' || item.TipoDonacion === 'Bienes / Mobiliario') {
                  badgeMetodo = `<span class="badge px-2 py-1" style="background-color: #f3e8ff; color: #721896; border: 1px solid #d1a8e7; font-weight: 600;"><i class="bi bi-box-seam me-1" style="color: #721896;"></i>${item.TipoDonacion}</span>`;
                } else if (item.TipoDonacion === 'Efectivo') {
                  badgeMetodo = `<span class="badge px-2 py-1" style="background-color: #eafaf1; color: #0f5132; border: 1px solid #c3e6cb; font-weight: 600;"><i class="bi bi-cash me-1 text-success"></i>Efectivo</span>`;
                }

                tbody.innerHTML += `
                  <tr>
                    <td class="fw-semibold text-muted">#${item.ID_Donativo}</td>
                    <td><i class="bi bi-calendar-event me-1 text-secondary"></i>${fechaStr}</td>
                    <td>${badgeMetodo}</td>
                    <td class="text-end">${montoHtml}</td>
                    <td class="text-center">
                      <a href="carta-agradecimiento.php?id=${item.ID_Donativo}" target="_blank"
                         style="display:inline-flex;align-items:center;gap:4px;font-size:0.78rem;padding:4px 8px;border-radius:6px;border:1.5px solid #721896;color:#721896;background:transparent;text-decoration:none;font-weight:600;white-space:nowrap;"
                         title="Ver / Descargar Carta de Agradecimiento PDF"
                         onmouseover="this.style.background='#721896';this.style.color='#fff';"
                         onmouseout="this.style.background='transparent';this.style.color='#721896';">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" style="margin-right:4px;vertical-align:-2px;"><path d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2zm5.5 1.5v2a1 1 0 0 0 1 1h2l-3-3zM8 7.993c1.664-1.711 5.825 1.283 0 5.132-5.825-3.85-1.664-6.843 0-5.132z"/></svg> Carta
                      </a>
                    </td>
                  </tr>
                `;
              });
            }

            document.getElementById('fd_cargando').classList.add('d-none');
            document.getElementById('fd_contenido').classList.remove('d-none');
          })
          .catch(err => {
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo comunicar con el servidor.' });
            modal.hide();
          });
      }

      // Cambiar estatus de donante
      function cambiarEstatus(id, nuevoEstatus) {
        const texto = (nuevoEstatus === 'Activo') 
          ? '¿Deseas reactivar a este donante como Vigente?' 
          : '¿Deseas marcar a este donante como Inactivo?';

        Swal.fire({
          title: 'Estado del Donante',
          text: texto,
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#721896',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Sí, cambiar',
          cancelButtonText: 'Cancelar'
        }).then(result => {
          if (result.isConfirmed) {
            fetch(`acciones/cambiar-estatus-donante.php?id=${id}&estatus=${nuevoEstatus}&ajax=1`)
              .then(res => res.json())
              .then(data => {
                if (data.success) {
                  Swal.fire({ icon: 'success', title: 'Actualizado', text: 'El estado del donante fue modificado.', timer: 1800, showConfirmButton: false })
                    .then(() => window.location.reload());
                } else {
                  Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo actualizar.' });
                }
              });
          }
        });
      }

      function toggleEstatusModal() {
        if (!currentDonanteId) return;
        const nuevo = (currentDonanteEstatus === 'Activo') ? 'Inactivo' : 'Activo';
        cambiarEstatus(currentDonanteId, nuevo);
      }

      // Preparar modal de correo para donante específico
      function prepararCorreoDonante(nombre, email, idDonante) {
        document.getElementById('correo_nombre').value = nombre || '';
        document.getElementById('correo_email').value = email || '';
        const idInput = document.getElementById('correo_id_donante');
        if (idInput) idInput.value = idDonante || '';
        cambiarPlantilla();
        new bootstrap.Modal(document.getElementById('modalEnviarCorreo')).show();
      }

      function enviarTarjetaCumpleDonante(nombre, email, idDonante) {
        document.getElementById('correo_nombre').value = nombre || '';
        document.getElementById('correo_email').value = email || '';
        const idInput = document.getElementById('correo_id_donante');
        if (idInput) idInput.value = idDonante || '';
        document.getElementById('plantillaSelect').value = 'cumpleanos';
        cambiarPlantilla();
        new bootstrap.Modal(document.getElementById('modalEnviarCorreo')).show();
      }

      function manejarInputNombre(val) {
        const nombre = (val || '').trim();
        const datalist = document.getElementById('lista_donantes_datalist_donantes');
        const emailInput = document.getElementById('correo_email');
        const idInput = document.getElementById('correo_id_donante');

        actualizarNombreEnMensaje();

        if (!datalist) return;

        const options = datalist.querySelectorAll('option');
        for (let i = 0; i < options.length; i++) {
          const opt = options[i];
          if (opt.value.toLowerCase() === nombre.toLowerCase()) {
            const email = opt.getAttribute('data-email') || '';
            const idDonante = opt.getAttribute('data-id') || '';
            if (emailInput && email) emailInput.value = email;
            if (idInput) idInput.value = idDonante;
            break;
          }
        }
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
      } else if (urlParams.get('status') === 'status_updated') {
        Swal.fire({ icon: 'success', title: 'Actualizado', text: 'El estado del donante fue actualizado.', timer: 2500, showConfirmButton: false });
      } else if (urlParams.get('status') === 'sent') {
        Swal.fire({ icon: 'success', title: '¡Correo Enviado!', text: 'El mensaje fue enviado exitosamente.', timer: 2500, showConfirmButton: false });
      } else if (urlParams.get('status') === 'error') {
        Swal.fire({ icon: 'error', title: 'Error', text: urlParams.get('msg') || 'Ocurrió un error en la operación.' });
      }
    </script>
  </body>
</html>

