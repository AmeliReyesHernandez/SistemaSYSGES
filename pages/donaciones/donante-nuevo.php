<?php
require_once __DIR__ . '/seccion.php';

$valNombre      = htmlspecialchars($_GET['nombre'] ?? '');
$valApPaterno   = htmlspecialchars($_GET['apellido_paterno'] ?? '');
$valApMaterno   = htmlspecialchars($_GET['apellido_materno'] ?? '');
$valEmail       = htmlspecialchars($_GET['email'] ?? '');
$valTelefono    = htmlspecialchars($_GET['telefono'] ?? '');
$valRfc         = htmlspecialchars($_GET['rfc'] ?? '');
$valCiudad      = htmlspecialchars($_GET['ciudad'] ?? '');
$msgAlerta      = trim($_GET['msg'] ?? '');
$statusAlerta   = trim($_GET['status'] ?? '');
?>
<!doctype html>
<html lang="es" data-bs-theme="auto">
  <head>
    <script src="../../assets/js/color-modes.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo Donante | GESMujer</title>

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
      .banner-duplicate-sf {
        background: #fffbeb;
        border: 1px solid #fef3c7;
        border-left: 5px solid #d97706 !important;
        border-radius: 10px;
        transition: all 0.3s ease;
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
            <h2 class="h3 fw-bold mb-0 text-dark">Registrar Nuevo Donante</h2>
            <p class="text-muted small mb-0">Captura de información para el padrón solidario de GESMujer</p>
          </div>
          
          <div>
            <a href="donantes.php" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold">
              <i class="bi bi-arrow-left me-1"></i> Volver al Directorio
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
        <li class="nav-item">
          <a class="nav-link" href="historial-cambios.php"><i class="bi bi-clock-history me-1"></i> Historial de Cambios</a>
        </li>
      </ul>

      <!-- Formulario de Registro -->
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card-sf">
            <div class="card-sf-header d-flex justify-content-between align-items-center">
              <span><i class="bi bi-person-plus-fill me-2 text-primary"></i> Datos del Donante</span>
            </div>
            <div class="card-body p-4">

              <!-- Alerta Backend si ya existe duplicado y fue redirigido -->
              <?php if (!empty($msgAlerta)): ?>
                <div class="alert alert-warning alert-dismissible fade show border-warning shadow-sm mb-4" role="alert">
                  <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-warning me-2"></i>
                    <div>
                      <strong><?= htmlspecialchars($msgAlerta) ?></strong>
                    </div>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <!-- Banner Dinámico de Alerta de Duplicados en Tiempo Real (Salesforce Duplicate Rules) -->
              <div id="bannerDuplicados" class="card banner-duplicate-sf mb-4 shadow-sm" style="display:none;">
                <div class="card-body p-3">
                  <div class="d-flex align-items-start gap-3">
                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center text-white" style="background:#d97706; width:38px; height:38px; flex-shrink:0;">
                      <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                      <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                           Posible Registro Duplicado Detectado
                        </h6>
                        <span class="badge bg-warning text-dark border border-warning" id="badgeTotalDuplicados">1 coincidencia</span>
                      </div>
                      <p class="text-muted small mb-2">
                        El sistema detectó que ya existe información similar registrada. Revisa el donante para evitar registrar a la misma persona dos veces por error:
                      </p>
                      
                      <!-- Lista de Coincidencias -->
                      <div id="listaDuplicados" class="vstack gap-2 mb-3"></div>

                      <!-- Casilla de Confirmación Explícita -->
                      <div class="form-check p-2 bg-white rounded border border-warning-subtle">
                        <input class="form-check-input ms-1" type="checkbox" id="chkIgnorarDuplicado" name="permitir_duplicado" value="1">
                        <label class="form-check-label small fw-semibold text-dark ms-2" for="chkIgnorarDuplicado">
                          He verificado los datos y confirmo que es una persona diferente (deseo continuar y registrarlo).
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <form action="acciones/guardar-donante.php" method="POST" class="needs-validation" id="formDonante" novalidate>
                
                <!-- Bloque 1: Datos Personales -->
                <div class="mb-4">
                  <h6 class="fw-bold text-uppercase text-purple mb-3" style="color:#721896; font-size: 0.85rem; letter-spacing: 0.5px;">
                    <i class="bi bi-person-badge me-1"></i> Información Personal
                  </h6>
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label for="nombre" class="form-label fw-semibold">Nombre(s) <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej. María" value="<?= $valNombre ?>" maxlength="30" required autocomplete="off">
                      <div class="invalid-feedback">Por favor ingresa el nombre (solo letras).</div>
                    </div>

                    <div class="col-md-4">
                      <label for="apellido_paterno" class="form-label fw-semibold">Apellido Paterno <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" placeholder="Ej. Gómez" value="<?= $valApPaterno ?>" maxlength="25" required autocomplete="off">
                      <div class="invalid-feedback">Por favor ingresa el apellido paterno (solo letras).</div>
                    </div>

                    <div class="col-md-4">
                      <label for="apellido_materno" class="form-label fw-semibold">Apellido Materno</label>
                      <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" placeholder="Ej. López" value="<?= $valApMaterno ?>" maxlength="25" autocomplete="off">
                    </div>

                    <div class="col-md-6">
                      <label for="fecha_nacimiento" class="form-label fw-semibold">
                        <i class="bi bi-calendar-heart text-danger me-1"></i> Fecha de Nacimiento
                      </label>
                      <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                    </div>

                    <div class="col-md-6">
                      <label for="ocupacion" class="form-label fw-semibold">
                        <i class="bi bi-briefcase me-1"></i> Ocupación / Profesión
                      </label>
                      <input type="text" class="form-control" id="ocupacion" name="ocupacion" placeholder="Ej. Abogada, Docente, Empresaria..." maxlength="40" autocomplete="off">
                    </div>
                  </div>
                </div>

                <hr class="my-4">

                <!-- Bloque 2: Contacto y Domicilio -->
                <div class="mb-4">
                  <h6 class="fw-bold text-uppercase text-purple mb-3" style="color:#721896; font-size: 0.85rem; letter-spacing: 0.5px;">
                    <i class="bi bi-geo-alt me-1"></i> Contacto y Domicilio
                  </h6>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="email" class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="correo@ejemplo.com" value="<?= $valEmail ?>" maxlength="80" required autocomplete="off">
                        <div class="invalid-feedback">Ingresa un correo electrónico válido.</div>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label for="telefono" class="form-label fw-semibold">Teléfono / Celular</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="10 dígitos" value="<?= $valTelefono ?>" maxlength="10" minlength="10" pattern="\d{10}" inputmode="numeric" autocomplete="off">
                      </div>
                    </div>

                    <div class="col-md-12">
                      <label for="domicilio" class="form-label fw-semibold">Domicilio (Calle, Número, Colonia)</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-house-door"></i></span>
                        <input type="text" class="form-control" id="domicilio" name="domicilio" placeholder="Ej. Calle Macedonio Alcalá #200, Col. Centro" maxlength="120">
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label for="ciudad" class="form-label fw-semibold">Ciudad / Municipio</label>
                      <input type="text" class="form-control" id="ciudad" name="ciudad" placeholder="Ej. Oaxaca de Juárez" value="<?= $valCiudad ?>" maxlength="35" autocomplete="off">
                    </div>

                    <div class="col-md-6">
                      <label for="estado" class="form-label fw-semibold">Estado</label>
                      <input type="text" class="form-control" id="estado" name="estado" placeholder="Ej. Oaxaca" value="Oaxaca" maxlength="25" autocomplete="off">
                    </div>
                  </div>
                </div>

                <hr class="my-4">

                <!-- Bloque 3: Aportación Inicial del Donante -->
                <div class="mb-4">
                  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                      <h6 class="fw-bold text-uppercase text-purple mb-0" style="color:#721896; font-size: 0.85rem; letter-spacing: 0.5px;">
                        <i class="bi bi-cash-coin me-1"></i> Aportación Inicial del Donante
                      </h6>
                      <small class="text-muted">Registra el primer donativo recibido al dar de alta al donante en el padrón.</small>
                    </div>
                    <div>
                    
                    </div>
                  </div>

                  <div class="p-3 rounded-3 border bg-light-subtle">
                    <div class="row g-3">
                      <!-- Método / Tipo de Donativo -->
                      <div class="col-md-6">
                        <label for="tipo_donacion" class="form-label fw-semibold">Método / Tipo de Donativo <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo_donacion" name="tipo_donacion" onchange="toggleTipoDonativo()" required>
                          <option value="">Seleccione una opción...</option>
                          <option value="Transferencia Bancaria">Transferencia Bancaria (SPEI)</option>
                          <option value="Efectivo" selected>Efectivo</option>
                          <option value="PayPal">PayPal</option>
                          <option value="Cheques">Cheques / Cheque</option>
                          <option value="Tarjeta de Crédito / Débito">Tarjeta de Crédito / Débito</option>
                          <option value="Depósito en Ventanilla">Depósito en Ventanilla</option>
                          <option value="Autogestivo">Autogestivo (Recaudación propia / Eventos / Bazar)</option>
                          <option value="En Especie">En Especie (Alimentos, Ropa, Insumos)</option>
                          <option value="Bienes / Mobiliario">Bienes / Mobiliario / Equipo</option>
                          <option value="Otro">Otro</option>
                        </select>
                        <div class="invalid-feedback">Selecciona el método o tipo de donativo.</div>
                      </div>

                      <!-- Monto Monetario (MXN) -->
                      <div class="col-md-6" id="container_monto">
                        <label for="monto_donacion" class="form-label fw-semibold">Monto de la Aportación (MXN) <span class="text-danger">*</span></label>
                        <div class="input-group">
                          <span class="input-group-text fw-bold">$</span>
                          <input type="number" step="0.01" min="1" class="form-control" id="monto_donacion" name="monto_donacion" placeholder="0.00" required>
                          <div class="invalid-feedback">Ingresa un monto válido mayor a $0.00.</div>
                        </div>
                      </div>

                      <!-- Descripción en Especie / Bienes -->
                      <div class="col-12 d-none" id="container_especie">
                        <label for="descripcion_especie" class="form-label fw-semibold">Descripción del Donativo en Especie / Bienes <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descripcion_especie" name="descripcion_especie" rows="3" placeholder="Describe detalladamente los artículos o bienes donados (Ej. 10 cobijas térmicas, 5 cajas de despensa, 2 escritorios)..."></textarea>
                        <div class="invalid-feedback">Describe lo que fue donado en especie o bienes.</div>
                      </div>
                    </div>
                  </div>
                </div>

                <hr class="my-4">

                <!-- Bloque 4: Datos Fiscales para Recibos Deducibles de Impuestos -->
                <div class="mb-4">
                  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                      <h6 class="fw-bold text-uppercase text-purple mb-0" style="color:#721896; font-size: 0.85rem; letter-spacing: 0.5px;">
                        <i class="bi bi-receipt me-1"></i> Datos Fiscales (Deducibles de Impuestos)
                      </h6>
                      <small class="text-muted">Información para emitir comprobantes o recibos deducibles ante el SAT.</small>
                    </div>
                    <div>
                      <button type="button" class="btn btn-outline-purple btn-sm fw-semibold shadow-sm" onclick="copiarDatosFacturacion()" title="Copiar nombre personal, domicilio y correo a datos fiscales">
                        <i class="bi bi-clipboard2-check me-1"></i> Copiar Datos Personales a Fiscales
                      </button>
                    </div>
                  </div>

                  <div class="p-3 rounded-3 border bg-light-subtle mb-3">
                    <div class="row g-3">
                      <!-- Tipo de Persona Fiscal -->
                      <div class="col-md-4">
                        <label for="tipo_persona_fiscal" class="form-label fw-semibold">Tipo de Persona Fiscal</label>
                        <select class="form-select" id="tipo_persona_fiscal" name="tipo_persona_fiscal" onchange="cambiarTipoPersonaFiscal(this.value)">
                          <option value="Fisica" selected>Persona Física (A título personal)</option>
                          <option value="Moral">Persona Moral (Empresa / Negocio)</option>
                        </select>
                      </div>

                      <!-- RFC -->
                      <div class="col-md-4">
                        <label for="rfc" class="form-label fw-semibold">RFC</label>
                        <input type="text" class="form-control text-uppercase" id="rfc" name="rfc" placeholder="13 caracteres" maxlength="13" value="<?= $valRfc ?>" autocomplete="off">
                        <div class="form-text text-muted" id="rfcHelp">Ej. PEPJ800101XYZ (13 caracteres)</div>
                      </div>

                      <!-- Código Postal Fiscal -->
                      <div class="col-md-4">
                        <label for="codigo_postal_fiscal" class="form-label fw-semibold">Código Postal Fiscal (SAT)</label>
                        <input type="text" class="form-control" id="codigo_postal_fiscal" name="codigo_postal_fiscal" placeholder="Ej. 68000" maxlength="5" autocomplete="off">
                      </div>

                      <!-- Razón Social Fiscal -->
                      <div class="col-md-6">
                        <label for="razon_social_fiscal" class="form-label fw-semibold">Nombre o Razón Social Fiscal</label>
                        <input type="text" class="form-control" id="razon_social_fiscal" name="razon_social_fiscal" placeholder="Nombre completo o Razón Social de empresa" autocomplete="off">
                        <div class="form-text text-muted" id="razonSocialHelp">Para personas físicas, el nombre completo; para empresas, la denominación sin régimen societario.</div>
                      </div>

                      <!-- Correo Electrónico para Facturación / Recibos -->
                      <div class="col-md-6">
                        <label for="email_fiscal" class="form-label fw-semibold">Correo para Facturación (Recibos)</label>
                        <div class="input-group">
                          <span class="input-group-text"><i class="bi bi-envelope-at"></i></span>
                          <input type="email" class="form-control" id="email_fiscal" name="email_fiscal" placeholder="facturacion@ejemplo.com" autocomplete="off">
                        </div>
                      </div>

                      <!-- Régimen Fiscal -->
                      <div class="col-md-6">
                        <label for="regimen_fiscal" class="form-label fw-semibold">Régimen Fiscal (SAT)</label>
                        <select class="form-select" id="regimen_fiscal" name="regimen_fiscal">
                          <option value="">Seleccione el régimen fiscal...</option>
                          <optgroup label="Personas Físicas" id="optgroup_fisica">
                            <option value="605 - Sueldos y Salarios e Ingresos Asimilados a Salarios">605 - Sueldos y Salarios e Ingresos Asimilados</option>
                            <option value="611 - Ingresos por Dividendos">611 - Ingresos por Dividendos</option>
                            <option value="614 - Ingresos por Intereses">614 - Ingresos por Intereses</option>
                            <option value="616 - Sin obligaciones fiscales">616 - Sin obligaciones fiscales</option>
                          </optgroup>
                          <optgroup label="Personas Morales (Empresas)" id="optgroup_moral">
                            <option value="601 - General de Ley Personas Morales">601 - General de Ley Personas Morales</option>
                            <option value="626 - Régimen Simplificado de Confianza (RESICO Moral)">626 - RESICO (Personas Morales)</option>
                          </optgroup>
                        </select>
                      </div>

                      <!-- Uso de CFDI -->
                      <div class="col-md-6">
                        <label for="uso_cfdi" class="form-label fw-semibold">Uso de CFDI</label>
                        <select class="form-select" id="uso_cfdi" name="uso_cfdi">
                          <option value="D04 - Donativos" selected>D04 - Donativos (Recomendado)</option>
                          <option value="S01 - Sin efectos fiscales">S01 - Sin efectos fiscales</option>
                          <option value="G03 - Gastos en general">G03 - Gastos en general</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <!-- Estatus del Donante -->
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="estatus" class="form-label fw-semibold">Estado del Donante <span class="text-danger">*</span></label>
                      <select class="form-select" id="estatus" name="estatus" required>
                        <option value="Activo" selected>Activo (Donante Vigente)</option>
                        <option value="Inactivo">Inactivo</option>
                      </select>
                      <div class="form-text text-muted">Los donantes sin aportaciones por más de 1 año pueden marcarse inactivos.</div>
                    </div>
                  </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                  <a href="donantes.php" class="btn btn-light px-4">Cancelar</a>
                  <button type="submit" id="btnGuardarDonante" class="btn btn-purple px-4 fw-semibold shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Guardar Donante
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
      // Función para copiar datos personales a la sección fiscal
      function copiarDatosFacturacion() {
        const nom = document.getElementById('nombre')?.value.trim() || '';
        const ap = document.getElementById('apellido_paterno')?.value.trim() || '';
        const am = document.getElementById('apellido_materno')?.value.trim() || '';
        const email = document.getElementById('email')?.value.trim() || '';
        const dom = document.getElementById('domicilio')?.value.trim() || '';
        
        let cpMatch = dom.match(/\b\d{5}\b/);
        const razonSocial = [nom, ap, am].filter(Boolean).join(' ');
        
        const inputRazon = document.getElementById('razon_social_fiscal');
        const inputEmailFisc = document.getElementById('email_fiscal');
        const inputCP = document.getElementById('codigo_postal_fiscal');
        const selectTipoPersona = document.getElementById('tipo_persona_fiscal');
        
        if (selectTipoPersona) {
          selectTipoPersona.value = 'Fisica';
          cambiarTipoPersonaFiscal('Fisica');
        }
        if (inputRazon && razonSocial) inputRazon.value = razonSocial;
        if (inputEmailFisc && email) inputEmailFisc.value = email;
        if (inputCP && cpMatch) inputCP.value = cpMatch[0];

        const toast = Swal.mixin({
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 2000,
          timerProgressBar: true
        });
        toast.fire({
          icon: 'success',
          title: 'Datos de contacto copiados a datos fiscales'
        });
      }

      // Alternar ayuda y longitud de RFC según tipo de persona
      function cambiarTipoPersonaFiscal(tipo) {
        const rfcInput = document.getElementById('rfc');
        const rfcHelp = document.getElementById('rfcHelp');
        const razonSocialHelp = document.getElementById('razonSocialHelp');
        const optFisica = document.getElementById('optgroup_fisica');
        const optMoral = document.getElementById('optgroup_moral');
        
        if (tipo === 'Moral') {
          if (rfcInput) {
            rfcInput.maxLength = 12;
            rfcInput.placeholder = "12 caracteres (Empresa)";
          }
          if (rfcHelp) rfcHelp.textContent = "Ej. COM850101ABC (12 caracteres para empresas)";
          if (razonSocialHelp) razonSocialHelp.textContent = "Denominación o razón social de la empresa (sin régimen societario como S.A. de C.V.).";
        } else {
          if (rfcInput) {
            rfcInput.maxLength = 13;
            rfcInput.placeholder = "13 caracteres (Persona física)";
          }
          if (rfcHelp) rfcHelp.textContent = "Ej. PEPJ800101XYZ (13 caracteres para personas físicas)";
          if (razonSocialHelp) razonSocialHelp.textContent = "Para personas físicas, el nombre completo tal como está registrado ante el SAT.";
        }
      }

      let debounceTimer = null;
      let duplicadosDetectados = [];

      // Función para verificar duplicados en tiempo real mediante AJAX
      function verificarDuplicados() {
        const nombre   = document.getElementById('nombre')?.value.trim() || '';
        const ap       = document.getElementById('apellido_paterno')?.value.trim() || '';
        const am       = document.getElementById('apellido_materno')?.value.trim() || '';
        const email    = document.getElementById('email')?.value.trim() || '';
        const telefono = document.getElementById('telefono')?.value.trim() || '';
        const rfc      = document.getElementById('rfc')?.value.trim() || '';

        // Solo buscar si hay al menos un campo significativo
        if (!email && (!nombre || !ap) && (!telefono || telefono.length < 7) && !rfc) {
          ocultarBannerDuplicados();
          return;
        }

        const formData = new FormData();
        formData.append('id_donante', '0');
        formData.append('nombre', nombre);
        formData.append('apellido_paterno', ap);
        formData.append('apellido_materno', am);
        formData.append('email', email);
        formData.append('telefono', telefono);
        formData.append('rfc', rfc);

        fetch('ajax/verificar_duplicado_donante.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success && data.hayDuplicados) {
            duplicadosDetectados = data.coincidencias;
            mostrarBannerDuplicados(data.coincidencias);
          } else {
            duplicadosDetectados = [];
            ocultarBannerDuplicados();
          }
        })
        .catch(err => {
          console.error('Error al verificar duplicados:', err);
        });
      }

      function mostrarBannerDuplicados(coincidencias) {
        const banner = document.getElementById('bannerDuplicados');
        const lista  = document.getElementById('listaDuplicados');
        const badge  = document.getElementById('badgeTotalDuplicados');
        if (!banner || !lista) return;

        badge.textContent = `${coincidencias.length} coincidencia${coincidencias.length > 1 ? 's' : ''}`;
        
        lista.innerHTML = coincidencias.map(c => `
          <div class="bg-white p-3 rounded border border-warning-subtle shadow-xs d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
              <div class="d-flex align-items-center gap-2">
                <strong class="text-dark" style="font-size:0.95rem;">
                  <i class="bi bi-person-circle text-purple me-1"></i> ${escHtml(c.nombre_completo)}
                </strong>
                <span class="badge ${c.estatus === 'Activo' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary'}">
                  ${escHtml(c.estatus)}
                </span>
              </div>
              <div class="small text-muted mt-1">
                ${c.email ? `<span class="me-3"><i class="bi bi-envelope me-1"></i>${escHtml(c.email)}</span>` : ''}
                ${c.telefono ? `<span class="me-3"><i class="bi bi-telephone me-1"></i>${escHtml(c.telefono)}</span>` : ''}
                ${c.rfc ? `<span><i class="bi bi-receipt me-1"></i>RFC: ${escHtml(c.rfc)}</span>` : ''}
              </div>
              <div class="mt-2 d-flex flex-wrap gap-1">
                ${c.motivos.map(m => `
                  <span class="badge bg-warning-subtle text-dark border border-warning" style="font-size:0.75rem;">
                    <i class="bi bi-exclamation-circle-fill text-warning me-1"></i>${escHtml(m)}
                  </span>
                `).join('')}
              </div>
            </div>
            <div class="text-nowrap mt-2 mt-md-0">
              <a href="donante-editar.php?id=${c.id}" target="_blank" class="btn btn-outline-dark btn-sm py-1 px-3 fw-semibold shadow-xs" title="Abrir expediente del donante existente">
                <i class="bi bi-box-arrow-up-right me-1"></i> Ver Donante Existente
              </a>
            </div>
          </div>
        `).join('');

        banner.style.display = 'block';
      }

      function ocultarBannerDuplicados() {
        const banner = document.getElementById('bannerDuplicados');
        if (banner) {
          banner.style.display = 'none';
        }
      }

      function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }

      // Escuchar eventos en los campos para detección en tiempo real
      const camposDuplicados = ['nombre', 'apellido_paterno', 'apellido_materno', 'email', 'telefono', 'rfc'];
      camposDuplicados.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
          el.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(verificarDuplicados, 350);
          });
          el.addEventListener('blur', verificarDuplicados);
        }
      });

      // Alternar campos de donativo según tipo (Monetario vs En Especie)
      function toggleTipoDonativo() {
        const tipo = document.getElementById('tipo_donacion')?.value || '';
        const containerMonto = document.getElementById('container_monto');
        const containerEspecie = document.getElementById('container_especie');
        const inputMonto = document.getElementById('monto_donacion');
        const textareaEspecie = document.getElementById('descripcion_especie');

        const esEspecie = (tipo === 'En Especie' || tipo === 'Bienes / Mobiliario');

        if (esEspecie) {
          if (containerMonto) containerMonto.classList.add('d-none');
          if (containerEspecie) containerEspecie.classList.remove('d-none');
          if (inputMonto) {
            inputMonto.removeAttribute('required');
            inputMonto.value = '';
          }
          if (textareaEspecie) {
            textareaEspecie.setAttribute('required', 'required');
          }
        } else {
          if (containerMonto) containerMonto.classList.remove('d-none');
          if (containerEspecie) containerEspecie.classList.add('d-none');
          if (inputMonto) {
            inputMonto.setAttribute('required', 'required');
          }
          if (textareaEspecie) {
            textareaEspecie.removeAttribute('required');
            textareaEspecie.value = '';
          }
        }
      }

      // Indicador discreto de 3 puntitos
      function mostrarPantallaCargando(titulo = 'Guardando') {
        if (window.mostrarCargandoDiscreto) window.mostrarCargandoDiscreto(titulo);
      }

      // Validación detallada que extrae nombre del campo y motivo de error
      function validarCamposFormulario(form) {
        const errores = [];
        const elementos = form.elements;

        for (let i = 0; i < elementos.length; i++) {
          const el = elementos[i];
          if (el.disabled || el.type === 'button' || el.type === 'submit' || el.type === 'reset' || el.type === 'hidden') continue;
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
            } else if (el.validity.tooShort || el.validity.patternMismatch) {
              if (el.id === 'telefono') motivo = 'Debe tener exactamente 10 dígitos numéricos.';
              else if (el.minLength > 0) motivo = `Debe tener al menos ${el.minLength} caracteres.`;
              else motivo = 'El formato ingresado no es válido.';
            } else {
              motivo = 'Por favor verifica la información ingresada en este campo.';
            }

            errores.push({ elemento: el, nombre: nombreCampo, motivo: motivo });
          }
        }
        return errores;
      }

      // Validación nativa y confirmación SweetAlert2 antes de enviar
      (() => {
        'use strict'
        const form = document.getElementById('formDonante');
        if (!form) return;

        form.addEventListener('submit', event => {
          // 1. Revisar si hay campos obligatorios faltantes o con error
          const errores = validarCamposFormulario(form);
          if (errores.length > 0) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated');

            // Enfocar y desplazar al primer campo con error
            const primerError = errores[0].elemento;
            primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            primerError.focus();

            Swal.fire({
              icon: 'warning',
              title: 'Faltan campos por completar',
              html: `
                <div class="text-start" style="font-size: 0.92rem;">
                  <p class="text-muted mb-2">Por favor revisa los siguientes campos requeridos antes de continuar:</p>
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

          // 2. Si hay duplicados detectados y el usuario no marcó la casilla de confirmación
          const chkConfirmar = document.getElementById('chkIgnorarDuplicado');
          if (duplicadosDetectados.length > 0 && chkConfirmar && !chkConfirmar.checked) {
            event.preventDefault();
            event.stopPropagation();

            const primerDup = duplicadosDetectados[0];
            const motivosList = primerDup.motivos.join(', ');

            Swal.fire({
              icon: 'warning',
              title: '¡Posible Donante Duplicado!',
              html: `
                <div class="text-start" style="font-size:0.92rem;">
                  <p class="mb-2">El sistema detectó que ya existe una persona registrada con datos coincidentes:</p>
                  <div class="p-3 bg-light rounded border mb-3">
                    <strong class="d-block text-dark fs-6"><i class="bi bi-person-fill text-purple me-1"></i> ${escHtml(primerDup.nombre_completo)}</strong>
                    <div class="small text-muted mt-1">
                      ${primerDup.email ? `<div><i class="bi bi-envelope me-1"></i> ${escHtml(primerDup.email)}</div>` : ''}
                      ${primerDup.telefono ? `<div><i class="bi bi-telephone me-1"></i> ${escHtml(primerDup.telefono)}</div>` : ''}
                    </div>
                    <div class="mt-2">
                      <span class="badge bg-warning text-dark border border-warning">${escHtml(motivosList)}</span>
                    </div>
                  </div>
                  <p class="mb-0 text-muted small">Para evitar duplicar registros por error, por favor confirma si es una persona diferente.</p>
                </div>
              `,
              showCancelButton: true,
              confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Sí, guardar de todos modos',
              cancelButtonText: '<i class="bi bi-pencil me-1"></i> Volver y revisar datos',
              confirmButtonColor: '#721896',
              cancelButtonColor: '#6c757d',
              reverseButtons: true
            }).then((result) => {
              if (result.isConfirmed) {
                chkConfirmar.checked = true;
                mostrarPantallaCargando('Guardando nuevo donante y aportación...');
                const btnSubmit = form.querySelector('button[type="submit"]');
                if (btnSubmit) {
                  btnSubmit.disabled = true;
                  btnSubmit.innerHTML = window.htmlTresPuntitos ? window.htmlTresPuntitos('Guardando') : 'Guardando...';
                }
                form.submit();
              }
            });
            return;
          }

          // 3. Todo correcto: Mostrar animación de carga y deshabilitar botón
          form.classList.add('was-validated');
          mostrarPantallaCargando('Guardando nuevo donante y aportación inicial...');
          const btnSubmit = form.querySelector('button[type="submit"]');
          if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = window.htmlTresPuntitos ? window.htmlTresPuntitos('Guardando') : 'Guardando...';
          }
        }, false);

        // Inicializar visibilidad de campos de donativo
        toggleTipoDonativo();

        // Si ya venían datos precargados (por ejemplo al volver con error), disparar chequeo inicial
        if (document.getElementById('email')?.value || document.getElementById('nombre')?.value) {
          verificarDuplicados();
        }
      })();
    </script>
  </body>
</html>
