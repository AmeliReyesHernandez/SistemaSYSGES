<?php
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: donativos.php?status=error&msg=" . urlencode("ID de donación no válido."));
    exit();
}

$idDonativo = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("
        SELECT d.*, CONCAT(dn.Nombre, ' ', dn.ApellidoPaterno, ' ', dn.ApellidoMaterno) AS NombreDonante
        FROM donativos d
        LEFT JOIN donantes dn ON d.ID_Donante = dn.ID_Donante
        WHERE d.ID_Donativo = :id
    ");
    $stmt->bindParam(':id', $idDonativo, PDO::PARAM_INT);
    $stmt->execute();
    $donativo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donativo) {
        header("Location: donativos.php?status=error&msg=" . urlencode("Donación no encontrada."));
        exit();
    }

    // Obtener también los datos fiscales actuales del donante
    $stmtDonante = $conn->prepare("
        SELECT ID_Donante, Nombre, 
               TRIM(CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno, ''))) AS NombreCompleto,
               Email, RFC,
               COALESCE(TipoPersonaFiscal, 'Fisica') AS TipoPersonaFiscal,
               COALESCE(RazonSocialFiscal, '') AS RazonSocialFiscal,
               COALESCE(CodigoPostalFiscal, '') AS CodigoPostalFiscal,
               COALESCE(RegimenFiscal, '') AS RegimenFiscal,
               COALESCE(UsoCFDI, 'D04 - Donativos') AS UsoCFDI,
               COALESCE(EmailFiscal, '') AS EmailFiscal
        FROM donantes
        WHERE ID_Donante = :id
    ");
    $stmtDonante->execute([':id' => $donativo['ID_Donante']]);
    $donanteFiscalData = $stmtDonante->fetch(PDO::FETCH_ASSOC) ?: null;

    $requiereRecibo = (int)($donativo['RequiereRecibo'] ?? 0);
    $facturacionDiferente = (int)($donativo['FacturacionDiferente'] ?? 0);
    $factTipoPersona = $donativo['TipoPersonaFiscal'] ?: 'Moral';
    $factRFC = $donativo['RFC_Fiscal'] ?? '';
    $factRazonSocial = $donativo['RazonSocial_Fiscal'] ?? '';
    $factCP = $donativo['CP_Fiscal'] ?? '';
    $factRegimen = $donativo['Regimen_Fiscal'] ?? '';
    $factUsoCFDI = $donativo['UsoCFDI_Fiscal'] ?? 'D04 - Donativos';
    $factEmail = $donativo['Email_Fiscal'] ?? '';
} catch (PDOException $e) {
    header("Location: donativos.php?status=error&msg=" . urlencode($e->getMessage()));
    exit();
}
?>
<!doctype html>
<html lang="es" data-bs-theme="auto">
  <head>
    <script src="../../assets/js/color-modes.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Donación | GesMujer</title>

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
      #sug_donante .list-group-item {
        cursor: pointer;
      }
      #sug_donante .list-group-item:hover {
        background-color: #f3e8ff;
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
            <h2 class="h3 fw-bold mb-0 text-dark">Editar Donativo #<?= $idDonativo ?></h2>
            <p class="text-muted small mb-0">Modificación de la aportación económica registrada</p>
          </div>
          
          <div>
            <a href="donativos.php" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold">
              <i class="bi bi-arrow-left me-1"></i> Volver al Historial
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

      <!-- Formulario de Edición -->
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card-sf">
            <div class="card-sf-header">
              <span><i class="bi bi-pencil-square me-2 text-primary"></i> Modificar Aportación</span>
            </div>
            <div class="card-body p-4">
              <form action="acciones/guardar-donativo.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id_donativo" value="<?= $idDonativo ?>">
                
                <div class="row g-3">
                  <!-- Selector de Donante -->
                  <div class="col-12 position-relative">
                    <label for="input_donante" class="form-label fw-semibold">Donante <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="bi bi-person"></i></span>
                      <input type="text" id="input_donante" class="form-control" value="<?= htmlspecialchars($donativo['NombreDonante'] ?: 'Donante #' . $donativo['ID_Donante']) ?>" required>
                      <button type="button" class="btn btn-outline-secondary" onclick="limpiarDonante()" title="Cambiar donante">
                        <i class="bi bi-x-lg"></i>
                      </button>
                    </div>
                    <div id="sug_donante" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1050; max-height: 220px; overflow-y: auto;"></div>
                    <input type="hidden" id="id_donante" name="id_donante" value="<?= $donativo['ID_Donante'] ?>" required>
                    <div class="invalid-feedback">Debes seleccionar un donante válido.</div>
                  </div>

                  <!-- Indicador de Fecha Automática -->
                  <div class="col-12">
                    <div class="alert alert-light border d-flex align-items-center justify-content-between p-2 px-3 mb-0 rounded-3">
                      <span class="small text-secondary"><i class="bi bi-calendar-check-fill text-purple me-2"></i><strong>Fecha de Registro:</strong> <?= !empty($donativo['FechaDonacion']) ? date('d/m/Y h:i A', strtotime($donativo['FechaDonacion'])) : 'Asignada automáticamente' ?>.</span>
                      <span class="badge px-2 py-1" style="background-color: #f3e8ff; color: #721896; border: 1px solid #d1a8e7; font-weight: 600;">Registrada</span>
                    </div>
                  </div>

                  <!-- Método de donación -->
                  <div class="col-md-6">
                    <label for="tipo_donacion" class="form-label fw-semibold">Método / Tipo de Donativo <span class="text-danger">*</span></label>
                    <select class="form-select" id="tipo_donacion" name="tipo_donacion" onchange="toggleTipoDonativo()" required>
                      <?php
                      $metodos = [
                          'Transferencia Bancaria',
                          'Efectivo',
                          'PayPal',
                          'Cheques',
                          'Cheque',
                          'Tarjeta de Crédito / Débito',
                          'Depósito en Ventanilla',
                          'Autogestivo',
                          'En Especie',
                          'Bienes / Mobiliario',
                          'Otro'
                      ];
                      foreach ($metodos as $metodo) {
                          $sel = ($donativo['TipoDonacion'] === $metodo || ($metodo === 'Cheques' && $donativo['TipoDonacion'] === 'Cheque')) ? 'selected' : '';
                          echo "<option value=\"$metodo\" $sel>$metodo</option>";
                      }
                      ?>
                    </select>
                    <div class="invalid-feedback">Selecciona el método de aportación.</div>
                  </div>

                  <!-- Campo Dinámico 1: Monto (Monetario) -->
                  <div class="col-md-6" id="container_monto">
                    <label for="monto_donacion" class="form-label fw-semibold">Monto del Donativo (MXN) <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text fw-bold">$</span>
                      <input type="number" step="0.01" min="1" class="form-control" id="monto_donacion" name="monto_donacion" value="<?= htmlspecialchars($donativo['MontoDonacion']) ?>">
                      <div class="invalid-feedback">Ingresa un monto válido mayor a 0.</div>
                    </div>
                  </div>

                  <!-- Campo Dinámico 2: Descripción en Especie / Bienes -->
                  <div class="col-12 d-none" id="container_especie">
                    <label for="descripcion_especie" class="form-label fw-semibold">Descripción del Donativo en Especie / Bienes <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="descripcion_especie" name="descripcion_especie" rows="3" placeholder="Describe los artículos o bienes donados..."><?= htmlspecialchars($donativo['DescripcionEspecie'] ?? '') ?></textarea>
                    <div class="invalid-feedback">Describe lo que fue donado en especie o bienes.</div>
                  </div>

                  <!-- ========================================== -->
                  <!-- SECCIÓN: DATOS FISCALES / RECIBO DEDUCIBLE -->
                  <!-- ========================================== -->
                  <div class="col-12 mt-4">
                    <div class="card border rounded-3 p-3 bg-light shadow-sm">
                      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                          <div class="form-check form-switch fs-4 mb-0">
                            <input class="form-check-input cursor-pointer" type="checkbox" role="switch" id="requiere_recibo" name="requiere_recibo" value="1" <?= $requiereRecibo === 1 ? 'checked' : '' ?> onchange="toggleRequiereRecibo(this.checked)">
                          </div>
                          <div>
                            <label class="form-check-label fw-bold text-dark mb-0 cursor-pointer" for="requiere_recibo">
                              <i class="bi bi-receipt text-purple me-1"></i> ¿El donante requiere Recibo Deducible de Impuestos (CFDI)?
                            </label>
                            <div class="small text-muted">Active esta opción si el donante solicita recibo deducible oficial ante el SAT.</div>
                          </div>
                        </div>
                        <span class="badge <?= $requiereRecibo === 1 ? 'bg-purple-subtle text-purple border border-purple' : 'bg-secondary-subtle text-secondary' ?> px-2 py-1" id="badgeEstadoRecibo">
                          <?= $requiereRecibo === 1 ? '<i class="bi bi-check-circle-fill me-1"></i> Recibo Solicitado' : 'No solicitado' ?>
                        </span>
                      </div>

                      <!-- Contenedor Desplegable Fiscal -->
                      <div id="seccion_fiscal" class="<?= $requiereRecibo === 1 ? '' : 'd-none' ?> mt-3 pt-3 border-top">
                        
                        <!-- Modo de facturación -->
                        <div class="mb-3">
                          <label class="form-label fw-semibold small text-uppercase text-muted">¿A nombre de quién se emitirá el recibo deducible?</label>
                          <div class="row g-2">
                            <div class="col-md-6">
                              <label class="p-3 border rounded-3 bg-white d-flex align-items-start gap-2 cursor-pointer h-100" for="modo_donante">
                                <input class="form-check-input mt-1" type="radio" name="facturacion_modo" id="modo_donante" value="donante" <?= $facturacionDiferente === 0 ? 'checked' : '' ?> onchange="cambiarModoFacturacion('donante')">
                                <div>
                                  <strong class="d-block text-dark small"><i class="bi bi-person-check text-purple me-1"></i> Datos fiscales habituales del Donante</strong>
                                  <span class="text-muted small" style="font-size: 0.8rem;">Emitir con el RFC y razón social guardados en el perfil de este donante.</span>
                                </div>
                              </label>
                            </div>
                            <div class="col-md-6">
                              <label class="p-3 border rounded-3 bg-white d-flex align-items-start gap-2 cursor-pointer h-100" for="modo_diferente">
                                <input class="form-check-input mt-1" type="radio" name="facturacion_modo" id="modo_diferente" value="diferente" <?= $facturacionDiferente === 1 ? 'checked' : '' ?> onchange="cambiarModoFacturacion('diferente')">
                                <div>
                                  <strong class="d-block text-dark small"><i class="bi bi-building text-primary me-1"></i> Facturar a nombre de Empresa u otro RFC</strong>
                                  <span class="text-muted small" style="font-size: 0.8rem;">El donante aporta personalmente pero el recibo sale a nombre de su empresa o un tercero.</span>
                                </div>
                              </label>
                            </div>
                          </div>
                        </div>

                        <!-- Resumen datos del donante -->
                        <div id="resumen_fiscal_donante" class="<?= $facturacionDiferente === 0 ? '' : 'd-none' ?> p-3 rounded-3 bg-white border">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-dark"><i class="bi bi-file-earmark-person text-purple me-1"></i> Datos Fiscales del Perfil del Donante</span>
                            <span id="badge_tiene_fiscales" class="badge bg-secondary-subtle text-secondary">Verificando...</span>
                          </div>
                          <div id="detalle_fiscal_donante" class="small text-secondary">
                            Cargando datos fiscales del donante...
                          </div>
                        </div>

                        <!-- Campos para Facturación Diferente / Empresa -->
                        <div id="campos_fiscales_diferente" class="<?= $facturacionDiferente === 1 ? '' : 'd-none' ?> p-3 rounded-3 bg-white border">
                          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3 pb-2 border-bottom">
                            <div>
                              <h6 class="fw-bold mb-0 text-purple" style="font-size:0.88rem;">
                                <i class="bi bi-building-gear me-1"></i> Datos de Facturación para este Donativo (Empresa o Tercero)
                              </h6>
                              <small class="text-muted">Estos datos fiscales se aplicarán únicamente a esta aportación.</small>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copiarFiscalesDonanteACamposDiferente()" title="Cargar los datos del donante para modificarlos">
                              <i class="bi bi-clipboard-data me-1"></i> Cargar base del donante
                            </button>
                          </div>

                          <div class="row g-3">
                            <div class="col-md-4">
                              <label for="fact_tipo_persona" class="form-label fw-semibold small">Tipo de Persona Fiscal</label>
                              <select class="form-select form-select-sm" id="fact_tipo_persona" name="fact_tipo_persona" onchange="cambiarTipoPersonaFiscalDonativo(this.value)">
                                <option value="Moral" <?= $factTipoPersona === 'Moral' ? 'selected' : '' ?>>Persona Moral (Empresa / Negocio)</option>
                                <option value="Fisica" <?= $factTipoPersona === 'Fisica' ? 'selected' : '' ?>>Persona Física (A título personal / Tercero)</option>
                              </select>
                            </div>

                            <div class="col-md-4">
                              <label for="fact_rfc" class="form-label fw-semibold small">RFC Receptor <span class="text-danger">*</span></label>
                              <input type="text" class="form-control form-select-sm text-uppercase" id="fact_rfc" name="fact_rfc" value="<?= htmlspecialchars($factRFC) ?>" placeholder="12 caracteres (Moral)" maxlength="<?= $factTipoPersona === 'Fisica' ? 13 : 12 ?>" autocomplete="off">
                              <div class="form-text small text-muted" id="fact_rfc_help"><?= $factTipoPersona === 'Fisica' ? '13 caracteres para Persona Física' : '12 caracteres para Persona Moral (Empresas)' ?></div>
                              <div class="invalid-feedback">Ingresa un RFC válido.</div>
                            </div>

                            <div class="col-md-4">
                              <label for="fact_cp" class="form-label fw-semibold small">C.P. Fiscal (SAT) <span class="text-danger">*</span></label>
                              <input type="text" class="form-control form-select-sm" id="fact_cp" name="fact_cp" value="<?= htmlspecialchars($factCP) ?>" placeholder="5 dígitos" maxlength="5" autocomplete="off">
                              <div class="invalid-feedback">Ingresa el C.P. fiscal de 5 dígitos.</div>
                            </div>

                            <div class="col-md-6">
                              <label for="fact_razon_social" class="form-label fw-semibold small">Nombre o Razón Social Fiscal <span class="text-danger">*</span></label>
                              <input type="text" class="form-control form-select-sm" id="fact_razon_social" name="fact_razon_social" value="<?= htmlspecialchars($factRazonSocial) ?>" placeholder="Denominación o Razón Social registrada ante SAT" autocomplete="off">
                              <div class="form-text small text-muted">Sin régimen societario (ej. sin S.A. de C.V.).</div>
                              <div class="invalid-feedback">Ingresa la razón social.</div>
                            </div>

                            <div class="col-md-6">
                              <label for="fact_email" class="form-label fw-semibold small">Correo para Envío del CFDI / Recibo <span class="text-danger">*</span></label>
                              <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-envelope-at"></i></span>
                                <input type="email" class="form-control" id="fact_email" name="fact_email" value="<?= htmlspecialchars($factEmail) ?>" placeholder="facturacion@empresa.com" autocomplete="off">
                                <div class="invalid-feedback">Ingresa un correo electrónico para el envío.</div>
                              </div>
                            </div>

                            <div class="col-md-6">
                              <label for="fact_regimen" class="form-label fw-semibold small">Régimen Fiscal (SAT) <span class="text-danger">*</span></label>
                              <select class="form-select form-select-sm" id="fact_regimen" name="fact_regimen">
                                <option value="">Seleccione el régimen fiscal...</option>
                                <optgroup label="Personas Morales (Empresas)" id="optgroup_fact_moral">
                                  <option value="601 - General de Ley Personas Morales" <?= strpos($factRegimen, '601') === 0 ? 'selected' : '' ?>>601 - General de Ley Personas Morales</option>
                                  <option value="603 - Personas Morales con Fines no Lucrativos" <?= strpos($factRegimen, '603') === 0 ? 'selected' : '' ?>>603 - Personas Morales con Fines no Lucrativos</option>
                                  <option value="626 - Régimen Simplificado de Confianza (RESICO Moral)" <?= (strpos($factRegimen, '626') === 0 && $factTipoPersona === 'Moral') ? 'selected' : '' ?>>626 - RESICO (Personas Morales)</option>
                                </optgroup>
                                <optgroup label="Personas Físicas" id="optgroup_fact_fisica">
                                  <option value="605 - Sueldos y Salarios e Ingresos Asimilados a Salarios" <?= strpos($factRegimen, '605') === 0 ? 'selected' : '' ?>>605 - Sueldos y Salarios e Ingresos Asimilados</option>
                                  <option value="612 - Personas Físicas con Actividades Empresariales y Profesionales" <?= strpos($factRegimen, '612') === 0 ? 'selected' : '' ?>>612 - Actividades Empresariales y Profesionales</option>
                                  <option value="626 - Régimen Simplificado de Confianza (RESICO)" <?= (strpos($factRegimen, '626') === 0 && $factTipoPersona === 'Fisica') ? 'selected' : '' ?>>626 - Régimen Simplificado de Confianza (RESICO)</option>
                                  <option value="611 - Ingresos por Dividendos" <?= strpos($factRegimen, '611') === 0 ? 'selected' : '' ?>>611 - Ingresos por Dividendos</option>
                                  <option value="614 - Ingresos por Intereses" <?= strpos($factRegimen, '614') === 0 ? 'selected' : '' ?>>614 - Ingresos por Intereses</option>
                                  <option value="616 - Sin obligaciones fiscales" <?= strpos($factRegimen, '616') === 0 ? 'selected' : '' ?>>616 - Sin obligaciones fiscales</option>
                                </optgroup>
                              </select>
                              <div class="invalid-feedback">Selecciona el régimen fiscal.</div>
                            </div>

                            <div class="col-md-6">
                              <label for="fact_uso_cfdi" class="form-label fw-semibold small">Uso de CFDI</label>
                              <select class="form-select form-select-sm" id="fact_uso_cfdi" name="fact_uso_cfdi">
                                <option value="D04 - Donativos" <?= strpos($factUsoCFDI, 'D04') === 0 ? 'selected' : '' ?>>D04 - Donativos (Recomendado)</option>
                                <option value="S01 - Sin efectos fiscales" <?= strpos($factUsoCFDI, 'S01') === 0 ? 'selected' : '' ?>>S01 - Sin efectos fiscales</option>
                                <option value="G03 - Gastos en general" <?= strpos($factUsoCFDI, 'G03') === 0 ? 'selected' : '' ?>>G03 - Gastos en general</option>
                              </select>
                            </div>
                          </div>
                        </div>

                      </div>
                    </div>
                  </div>

                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                  <a href="donativos.php" class="btn btn-light px-4">Cancelar</a>
                  <button type="submit" class="btn btn-purple px-4 fw-semibold shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Actualizar Donativo
                  </button>
                </div>

              </form>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

    <script src="../../assets/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      let donanteFiscalActual = <?= $donanteFiscalData ? json_encode($donanteFiscalData) : 'null' ?>;

      document.addEventListener("DOMContentLoaded", () => {
        const input = document.getElementById("input_donante");
        const sugerencias = document.getElementById("sug_donante");
        const inputHidden = document.getElementById("id_donante");

        toggleTipoDonativo();
        if (donanteFiscalActual) {
          actualizarResumenFiscalDonante();
        }

        const modoDiferenteActivo = document.getElementById('modo_diferente').checked;
        const requiereReciboActivo = document.getElementById('requiere_recibo').checked;
        if (requiereReciboActivo && modoDiferenteActivo) {
          asignarRequiredFiscalesDiferente();
        }

        input.addEventListener("input", async () => {
          const q = input.value.trim();
          sugerencias.innerHTML = "";

          if (q.length < 2) return;

          try {
            const response = await fetch("ajax/buscar_donante.php?q=" + encodeURIComponent(q));
            const data = await response.json();

            if (!Array.isArray(data) || data.length === 0) {
              sugerencias.innerHTML = `<div class="list-group-item text-muted small py-2">No se encontraron donantes con "${q}"</div>`;
              return;
            }

            data.forEach(donante => {
              const item = document.createElement("a");
              item.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2";
              item.innerHTML = `
                <div>
                  <strong>${donante.NombreCompleto}</strong>
                  <small class="text-muted d-block">${donante.Email || 'Sin correo'}${donante.RFC ? ' • RFC: ' + donante.RFC : ''}</small>
                </div>
                <span class="badge bg-secondary-subtle text-secondary small">#${donante.ID_Donante}</span>
              `;
              item.addEventListener("click", () => {
                input.value = donante.NombreCompleto;
                inputHidden.value = donante.ID_Donante;
                sugerencias.innerHTML = "";
                input.classList.remove("is-invalid");
                input.classList.add("is-valid");

                donanteFiscalActual = donante;
                actualizarResumenFiscalDonante();
              });
              sugerencias.appendChild(item);
            });
          } catch (error) {
            console.error("Error buscando donantes:", error);
          }
        });

        document.addEventListener("click", (e) => {
          if (!input.contains(e.target) && !sugerencias.contains(e.target)) {
            sugerencias.innerHTML = "";
          }
        });
      });

      function toggleTipoDonativo() {
        const sel = document.getElementById('tipo_donacion').value;
        const cMonto = document.getElementById('container_monto');
        const cEspecie = document.getElementById('container_especie');
        const inMonto = document.getElementById('monto_donacion');
        const inEspecie = document.getElementById('descripcion_especie');

        if (sel === 'En Especie' || sel === 'Bienes / Mobiliario') {
          cMonto.classList.add('d-none');
          inMonto.removeAttribute('required');

          cEspecie.classList.remove('d-none');
          inEspecie.setAttribute('required', 'required');
        } else {
          cEspecie.classList.add('d-none');
          inEspecie.removeAttribute('required');

          cMonto.classList.remove('d-none');
          inMonto.setAttribute('required', 'required');
        }
      }

      function limpiarDonante() {
        document.getElementById("input_donante").value = "";
        document.getElementById("id_donante").value = "";
        document.getElementById("input_donante").classList.remove("is-valid");
        document.getElementById("sug_donante").innerHTML = "";
        donanteFiscalActual = null;
        actualizarResumenFiscalDonante();
        document.getElementById("input_donante").focus();
      }

      function toggleRequiereRecibo(activo) {
        const seccion = document.getElementById('seccion_fiscal');
        const badge = document.getElementById('badgeEstadoRecibo');
        if (activo) {
          seccion.classList.remove('d-none');
          badge.className = 'badge bg-purple-subtle text-purple border border-purple px-2 py-1';
          badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Recibo Solicitado';
          actualizarResumenFiscalDonante();
          if (document.getElementById('modo_diferente').checked) {
            asignarRequiredFiscalesDiferente();
          }
        } else {
          seccion.classList.add('d-none');
          badge.className = 'badge bg-secondary-subtle text-secondary px-2 py-1';
          badge.textContent = 'No solicitado';
          removerRequiredFiscalesDiferente();
        }
      }

      function cambiarModoFacturacion(modo) {
        const resumenDonante = document.getElementById('resumen_fiscal_donante');
        const camposDiferente = document.getElementById('campos_fiscales_diferente');

        if (modo === 'donante') {
          resumenDonante.classList.remove('d-none');
          camposDiferente.classList.add('d-none');
          removerRequiredFiscalesDiferente();
        } else {
          resumenDonante.classList.add('d-none');
          camposDiferente.classList.remove('d-none');
          if (document.getElementById('requiere_recibo').checked) {
            asignarRequiredFiscalesDiferente();
          }
        }
      }

      function asignarRequiredFiscalesDiferente() {
        document.getElementById('fact_rfc').setAttribute('required', 'required');
        document.getElementById('fact_cp').setAttribute('required', 'required');
        document.getElementById('fact_razon_social').setAttribute('required', 'required');
        document.getElementById('fact_email').setAttribute('required', 'required');
        document.getElementById('fact_regimen').setAttribute('required', 'required');
      }

      function removerRequiredFiscalesDiferente() {
        document.getElementById('fact_rfc').removeAttribute('required');
        document.getElementById('fact_cp').removeAttribute('required');
        document.getElementById('fact_razon_social').removeAttribute('required');
        document.getElementById('fact_email').removeAttribute('required');
        document.getElementById('fact_regimen').removeAttribute('required');
      }

      function cambiarTipoPersonaFiscalDonativo(tipo) {
        const rfcInput = document.getElementById('fact_rfc');
        const rfcHelp = document.getElementById('fact_rfc_help');
        const optMoral = document.getElementById('optgroup_fact_moral');
        const optFisica = document.getElementById('optgroup_fact_fisica');
        const regimenSelect = document.getElementById('fact_regimen');

        if (tipo === 'Moral') {
          rfcInput.maxLength = 12;
          rfcInput.placeholder = '12 caracteres (Moral)';
          rfcHelp.textContent = '12 caracteres alfanuméricos para Persona Moral (Empresas)';
          optMoral.disabled = false;
          optFisica.disabled = true;
          if (regimenSelect.value.startsWith('605') || regimenSelect.value.startsWith('612') || regimenSelect.value.startsWith('611') || regimenSelect.value.startsWith('614') || regimenSelect.value.startsWith('616')) {
            regimenSelect.value = '601 - General de Ley Personas Morales';
          }
        } else {
          rfcInput.maxLength = 13;
          rfcInput.placeholder = '13 caracteres (Física)';
          rfcHelp.textContent = '13 caracteres alfanuméricos para Persona Física';
          optMoral.disabled = true;
          optFisica.disabled = false;
          if (regimenSelect.value.startsWith('601') || regimenSelect.value.startsWith('603')) {
            regimenSelect.value = '605 - Sueldos y Salarios e Ingresos Asimilados a Salarios';
          }
        }
      }

      function actualizarResumenFiscalDonante() {
        const detalle = document.getElementById('detalle_fiscal_donante');
        const badge = document.getElementById('badge_tiene_fiscales');

        if (!donanteFiscalActual) {
          badge.className = 'badge bg-secondary-subtle text-secondary';
          badge.textContent = 'Sin donante seleccionado';
          detalle.innerHTML = '<span class="text-muted"><i class="bi bi-info-circle me-1"></i> Selecciona un donante en el campo de arriba para ver sus datos fiscales registrados.</span>';
          return;
        }

        const d = donanteFiscalActual;
        const tieneRfc = d.RFC && d.RFC.trim() !== '';
        const tieneRazon = d.RazonSocialFiscal && d.RazonSocialFiscal.trim() !== '';

        if (tieneRfc || tieneRazon) {
          badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
          badge.innerHTML = '<i class="bi bi-check-circle me-1"></i> Perfil fiscal configurado';

          const razon = d.RazonSocialFiscal || d.NombreCompleto;
          const rfc = d.RFC || 'Sin RFC';
          const cp = d.CodigoPostalFiscal ? `C.P. ${d.CodigoPostalFiscal}` : 'Sin C.P.';
          const regimen = d.RegimenFiscal || 'Sin régimen especificado';
          const email = d.EmailFiscal || d.Email || 'Sin correo de facturación';
          const tipo = d.TipoPersonaFiscal || 'Fisica';

          detalle.innerHTML = `
            <div class="row g-2 pt-1">
              <div class="col-md-6">
                <strong>Razón Social:</strong> <span class="text-dark">${escapeHtml(razon)}</span>
              </div>
              <div class="col-md-6">
                <strong>RFC:</strong> <span class="badge bg-light text-dark border font-monospace">${escapeHtml(rfc)}</span>
                <span class="badge bg-light text-secondary border ms-1">${tipo === 'Moral' ? 'Moral' : 'Física'}</span>
              </div>
              <div class="col-md-6">
                <strong>Código Postal SAT:</strong> <span class="text-dark">${escapeHtml(cp)}</span>
              </div>
              <div class="col-md-6">
                <strong>Correo CFDI:</strong> <span class="text-dark">${escapeHtml(email)}</span>
              </div>
              <div class="col-12">
                <strong>Régimen Fiscal:</strong> <span class="text-dark">${escapeHtml(regimen)}</span>
              </div>
            </div>
          `;
        } else {
          badge.className = 'badge bg-warning-subtle text-dark border border-warning-subtle';
          badge.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Sin datos fiscales completos';

          detalle.innerHTML = `
            <div class="text-muted">
              El donante <strong>${escapeHtml(d.NombreCompleto)}</strong> aún no tiene RFC o datos de facturación guardados en su perfil.
              <div class="mt-2 d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-purple" onclick="document.getElementById('modo_diferente').click();">
                  <i class="bi bi-building me-1"></i> Capturar datos fiscales para este donativo
                </button>
                <a href="donante-editar.php?id=${d.ID_Donante}" target="_blank" class="btn btn-sm btn-light border text-decoration-none">
                  <i class="bi bi-pencil-square me-1"></i> Actualizar perfil del donante
                </a>
              </div>
            </div>
          `;
        }
      }

      function copiarFiscalesDonanteACamposDiferente() {
        if (!donanteFiscalActual) {
          Swal.fire({
            icon: 'info',
            title: 'Sin donante',
            text: 'Primero selecciona un donante arriba para copiar sus datos base.'
          });
          return;
        }

        const d = donanteFiscalActual;
        const tipoPersona = d.TipoPersonaFiscal || 'Moral';
        document.getElementById('fact_tipo_persona').value = tipoPersona;
        cambiarTipoPersonaFiscalDonativo(tipoPersona);

        document.getElementById('fact_rfc').value = d.RFC || '';
        document.getElementById('fact_cp').value = d.CodigoPostalFiscal || '';
        document.getElementById('fact_razon_social').value = d.RazonSocialFiscal || d.NombreCompleto || '';
        document.getElementById('fact_email').value = d.EmailFiscal || d.Email || '';
        if (d.RegimenFiscal) {
          document.getElementById('fact_regimen').value = d.RegimenFiscal;
        }
        if (d.UsoCFDI) {
          document.getElementById('fact_uso_cfdi').value = d.UsoCFDI;
        }

        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'Datos base cargados en el formulario de facturación',
          showConfirmButton: false,
          timer: 2000
        });
      }

      function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }

      function escapeHtml(str) {
        return escHtml(str);
      }

      // Pantalla de carga profesional con puntitos animados y barra de progreso
      function mostrarPantallaCargando(titulo = 'Actualizando donativo...') {
        Swal.fire({
          title: `<span style="color:#721896; font-size:1.25rem; font-weight:700;">${escHtml(titulo)}</span>`,
          html: `
            <div class="py-3 text-center">
              <div class="mb-3" style="color: #721896;">
                <span class="spinner-grow spinner-grow-sm me-1" role="status" style="animation-duration: 0.8s;"></span>
                <span class="spinner-grow spinner-grow-sm me-1" role="status" style="animation-duration: 0.8s; animation-delay: 0.2s;"></span>
                <span class="spinner-grow spinner-grow-sm" role="status" style="animation-duration: 0.8s; animation-delay: 0.4s;"></span>
              </div>
              <p class="text-muted small mb-3">Estamos procesando y actualizando la información de la aportación en el sistema, por favor espera un momento...</p>
              <div class="progress" style="height: 10px; border-radius: 8px; background-color: #f3e8ff;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%; background-color: #721896;"></div>
              </div>
            </div>
          `,
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false
        });
      }

      // Validación detallada que extrae nombre del campo y motivo de error
      function validarCamposFormulario(form) {
        const errores = [];
        const elementos = form.elements;

        // Validar donante seleccionado específicamente
        const hiddenId = document.getElementById('id_donante');
        const inputDonante = document.getElementById('input_donante');
        if (!hiddenId || !hiddenId.value || hiddenId.value === '') {
          if (inputDonante) inputDonante.classList.add('is-invalid');
          errores.push({
            elemento: inputDonante || hiddenId,
            nombre: 'Donante',
            motivo: 'Debes buscar y seleccionar un donante válido del padrón.'
          });
        }

        for (let i = 0; i < elementos.length; i++) {
          const el = elementos[i];
          if (el.disabled || el.type === 'button' || el.type === 'submit' || el.type === 'reset' || el.type === 'hidden') continue;
          if (el.id === 'input_donante') continue;
          if (el.closest('.d-none') || el.offsetParent === null) continue;

          if (!el.checkValidity()) {
            let nombreCampo = el.getAttribute('data-nombre-campo');
            if (!nombreCampo) {
              const label = form.querySelector(`label[for="${el.id}"]`);
              if (label) {
                nombreCampo = label.textContent.replace('*', '').trim();
              } else {
                nombreCampo = el.placeholder || el.name || 'Campo requerido';
              }
            }

            let motivo = '';
            if (el.validity.valueMissing) {
              motivo = 'Es obligatorio y se encuentra vacío.';
            } else if (el.validity.typeMismatch) {
              if (el.type === 'email') motivo = 'Formato de correo no válido (ej. correo@ejemplo.com).';
              else motivo = 'El formato ingresado es incorrecto.';
            } else if (el.validity.rangeUnderflow) {
              motivo = `El monto debe ser mayor a 0 (mínimo $${el.min || 1}).`;
            } else if (el.validity.tooShort) {
              motivo = `Debe tener al menos ${el.minLength} caracteres.`;
            } else {
              motivo = 'Por favor verifica la información ingresada en este campo.';
            }

            errores.push({ elemento: el, nombre: nombreCampo, motivo: motivo });
          }
        }
        return errores;
      }

      // Validación del formulario
      (() => {
        'use strict'
        const form = document.querySelector('form.needs-validation');
        if (!form) return;

        form.addEventListener('submit', event => {
          const switchRecibo = document.getElementById('requiere_recibo');
          const modoDiferente = document.getElementById('modo_diferente')?.checked;

          // Si solicita recibo deducible con datos del donante pero el donante no tiene RFC registrado
          if (switchRecibo && switchRecibo.checked && !modoDiferente) {
            if (donanteFiscalActual && (!donanteFiscalActual.RFC || donanteFiscalActual.RFC.trim() === '')) {
              event.preventDefault();
              event.stopPropagation();
              Swal.fire({
                icon: 'warning',
                title: 'RFC Faltante',
                html: `El donante <b>${escHtml(donanteFiscalActual.NombreCompleto)}</b> no tiene RFC registrado en su perfil.<br><br>Por favor selecciona <b>"Facturar a nombre de Empresa u otro RFC"</b> para ingresar los datos fiscales de esta aportación, o actualiza su perfil de donante.`,
                confirmButtonColor: '#721896',
                confirmButtonText: 'Entendido'
              });
              return;
            }
          }

          // Validar todos los campos obligatorios
          const errores = validarCamposFormulario(form);
          if (errores.length > 0) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated');

            const primerError = errores[0].elemento;
            primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            primerError.focus();

            Swal.fire({
              icon: 'warning',
              title: 'Faltan campos por completar',
              html: `
                <div class="text-start" style="font-size: 0.92rem;">
                  <p class="text-muted mb-2">Por favor revisa los siguientes campos requeridos antes de guardar:</p>
                  <div class="p-3 bg-light rounded border mb-2" style="max-height: 220px; overflow-y: auto;">
                    <ul class="mb-0 ps-3">
                      ${errores.map(err => `
                        <li class="mb-1">
                          <strong class="text-dark">${escHtml(err.nombre)}:</strong>
                          <span class="text-danger small d-block">${escHtml(err.motivo)}</span>
                        </li>
                      `).join('')}
                    </ul>
                  </div>
                  <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Los campos con observaciones se han resaltado en rojo.</small>
                </div>
              `,
              confirmButtonColor: '#721896',
              confirmButtonText: '<i class="bi bi-pencil-square me-1"></i> Corregir campos'
            });
            return;
          }

          form.classList.add('was-validated');
          mostrarPantallaCargando('Actualizando información de la aportación...');
          const btnSubmit = form.querySelector('button[type="submit"]');
          if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status"></span>Actualizando...`;
          }
        }, false);
      })();
    </script>
  </body>
</html>

