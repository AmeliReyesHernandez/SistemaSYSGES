<?php
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: donantes.php?status=error&msg=" . urlencode("ID de donante inválido."));
    exit();
}

$idDonante = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("SELECT * FROM donantes WHERE ID_Donante = :id");
    $stmt->bindParam(':id', $idDonante, PDO::PARAM_INT);
    $stmt->execute();
    $donante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donante) {
        header("Location: donantes.php?status=error&msg=" . urlencode("Donante no encontrado."));
        exit();
    }
} catch (PDOException $e) {
    header("Location: donantes.php?status=error&msg=" . urlencode($e->getMessage()));
    exit();
}
?>
<!doctype html>
<html lang="es" data-bs-theme="auto">
  <head>
    <script src="../../assets/js/color-modes.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Donante | GESMujer</title>

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
            <h2 class="h3 fw-bold mb-0 text-dark">Editar Donante #<?= $idDonante ?></h2>
            <p class="text-muted small mb-0">Actualización de datos generales del donante</p>
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

      <!-- Formulario de Edición -->
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card-sf">
            <div class="card-sf-header">
              <span><i class="bi bi-pencil-square me-2 text-primary"></i> Modificar Información de <?= htmlspecialchars($donante['Nombre']) ?></span>
            </div>
            <div class="card-body p-4">
              <form action="acciones/guardar-donante.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id_donante" value="<?= $idDonante ?>">
                
                <!-- Bloque 1: Datos Personales -->
                <div class="mb-4">
                  <h6 class="fw-bold text-uppercase text-purple mb-3" style="color:#721896; font-size: 0.85rem; letter-spacing: 0.5px;">
                    <i class="bi bi-person-badge me-1"></i> Información Personal
                  </h6>
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label for="nombre" class="form-label fw-semibold">Nombre(s) <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($donante['Nombre']) ?>" maxlength="30" required autocomplete="off">
                      <div class="invalid-feedback">Por favor ingresa el nombre (solo letras).</div>
                    </div>

                    <div class="col-md-4">
                      <label for="apellido_paterno" class="form-label fw-semibold">Apellido Paterno <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="<?= htmlspecialchars($donante['ApellidoPaterno']) ?>" maxlength="25" required autocomplete="off">
                      <div class="invalid-feedback">Por favor ingresa el apellido paterno (solo letras).</div>
                    </div>

                    <div class="col-md-4">
                      <label for="apellido_materno" class="form-label fw-semibold">Apellido Materno</label>
                      <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($donante['ApellidoMaterno']) ?>" maxlength="25" autocomplete="off">
                    </div>

                    <div class="col-md-6">
                      <label for="fecha_nacimiento" class="form-label fw-semibold">
                        <i class="bi bi-calendar-heart text-danger me-1"></i> Fecha de Nacimiento
                      </label>
                      <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= htmlspecialchars($donante['FechaNacimiento'] ?? '') ?>">
                      <div class="form-text text-muted">Para felicitaciones automáticas de cumpleaños.</div>
                    </div>

                    <div class="col-md-6">
                      <label for="ocupacion" class="form-label fw-semibold">
                        <i class="bi bi-briefcase me-1"></i> Ocupación / Profesión
                      </label>
                      <input type="text" class="form-control" id="ocupacion" name="ocupacion" value="<?= htmlspecialchars($donante['Ocupacion'] ?? '') ?>" placeholder="Ej. Abogada, Docente, Empresaria..." maxlength="40" autocomplete="off">
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
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($donante['Email']) ?>" maxlength="80" required>
                        <div class="invalid-feedback">Ingresa un correo electrónico válido.</div>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label for="telefono" class="form-label fw-semibold">Teléfono / Celular</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($donante['Telefono'] ?? '') ?>" placeholder="10 dígitos" maxlength="10" minlength="10" pattern="\d{10}" inputmode="numeric" autocomplete="off">
                      </div>
                    </div>

                    <div class="col-md-12">
                      <label for="domicilio" class="form-label fw-semibold">Domicilio (Calle, Número, Colonia)</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-house-door"></i></span>
                        <input type="text" class="form-control" id="domicilio" name="domicilio" value="<?= htmlspecialchars($donante['Domicilio'] ?? '') ?>" placeholder="Ej. Calle Macedonio Alcalá #200, Col. Centro" maxlength="120">
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label for="ciudad" class="form-label fw-semibold">Ciudad / Municipio</label>
                      <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?= htmlspecialchars($donante['Ciudad'] ?? '') ?>" placeholder="Ej. Oaxaca de Juárez" maxlength="35" autocomplete="off">
                    </div>

                    <div class="col-md-6">
                      <label for="estado" class="form-label fw-semibold">Estado</label>
                      <input type="text" class="form-control" id="estado" name="estado" value="<?= htmlspecialchars($donante['Estado'] ?? 'Oaxaca') ?>" placeholder="Ej. Oaxaca" maxlength="25" autocomplete="off">
                    </div>
                  </div>
                </div>

                <hr class="my-4">

                <!-- Bloque 3: Datos Fiscales para Recibos Deducibles de Impuestos -->
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
                        <?php $tpf = $donante['TipoPersonaFiscal'] ?? 'Fisica'; ?>
                        <select class="form-select" id="tipo_persona_fiscal" name="tipo_persona_fiscal" onchange="cambiarTipoPersonaFiscal(this.value)">
                          <option value="Fisica" <?= $tpf === 'Fisica' ? 'selected' : '' ?>>Persona Física (A título personal)</option>
                          <option value="Moral" <?= $tpf === 'Moral' ? 'selected' : '' ?>>Persona Moral (Empresa / Negocio)</option>
                        </select>
                      </div>

                      <!-- RFC -->
                      <div class="col-md-4">
                        <label for="rfc" class="form-label fw-semibold">RFC</label>
                        <input type="text" class="form-control text-uppercase" id="rfc" name="rfc" value="<?= htmlspecialchars($donante['RFC'] ?? '') ?>" placeholder="<?= $tpf === 'Moral' ? '12 caracteres' : '13 caracteres' ?>" maxlength="13" autocomplete="off">
                        <div class="form-text text-muted" id="rfcHelp">
                          <?= $tpf === 'Moral' ? 'Ej. COM850101ABC (12 caracteres)' : 'Ej. PEPJ800101XYZ (13 caracteres)' ?>
                        </div>
                      </div>

                      <!-- Código Postal Fiscal -->
                      <div class="col-md-4">
                        <label for="codigo_postal_fiscal" class="form-label fw-semibold">Código Postal Fiscal (SAT)</label>
                        <input type="text" class="form-control" id="codigo_postal_fiscal" name="codigo_postal_fiscal" value="<?= htmlspecialchars($donante['CodigoPostalFiscal'] ?? '') ?>" placeholder="Ej. 68000" maxlength="5" autocomplete="off">
                      </div>

                      <!-- Razón Social Fiscal -->
                      <div class="col-md-6">
                        <label for="razon_social_fiscal" class="form-label fw-semibold">Nombre o Razón Social Fiscal</label>
                        <input type="text" class="form-control" id="razon_social_fiscal" name="razon_social_fiscal" value="<?= htmlspecialchars($donante['RazonSocialFiscal'] ?? '') ?>" placeholder="Nombre completo o Razón Social de empresa" autocomplete="off">
                        <div class="form-text text-muted" id="razonSocialHelp">Para personas físicas, el nombre completo; para empresas, la denominación sin régimen societario.</div>
                      </div>

                      <!-- Correo Electrónico para Facturación / Recibos -->
                      <div class="col-md-6">
                        <label for="email_fiscal" class="form-label fw-semibold">Correo para Facturación (Recibos)</label>
                        <div class="input-group">
                          <span class="input-group-text"><i class="bi bi-envelope-at"></i></span>
                          <input type="email" class="form-control" id="email_fiscal" name="email_fiscal" value="<?= htmlspecialchars($donante['EmailFiscal'] ?? '') ?>" placeholder="facturacion@ejemplo.com" autocomplete="off">
                        </div>
                      </div>

                      <!-- Régimen Fiscal -->
                      <div class="col-md-6">
                        <label for="regimen_fiscal" class="form-label fw-semibold">Régimen Fiscal (SAT)</label>
                        <?php $rfVal = $donante['RegimenFiscal'] ?? ''; ?>
                        <select class="form-select" id="regimen_fiscal" name="regimen_fiscal">
                          <option value="">Seleccione el régimen fiscal...</option>
                          <optgroup label="Personas Físicas" id="optgroup_fisica">
                            <option value="605 - Sueldos y Salarios e Ingresos Asimilados a Salarios" <?= strpos($rfVal, '605') !== false ? 'selected' : '' ?>>605 - Sueldos y Salarios e Ingresos Asimilados</option>
                            <option value="611 - Ingresos por Dividendos" <?= strpos($rfVal, '611') !== false ? 'selected' : '' ?>>611 - Ingresos por Dividendos</option>
                            <option value="614 - Ingresos por Intereses" <?= strpos($rfVal, '614') !== false ? 'selected' : '' ?>>614 - Ingresos por Intereses</option>
                            <option value="616 - Sin obligaciones fiscales" <?= strpos($rfVal, '616') !== false ? 'selected' : '' ?>>616 - Sin obligaciones fiscales</option>
                          </optgroup>
                          <optgroup label="Personas Morales (Empresas)" id="optgroup_moral">
                            <option value="601 - General de Ley Personas Morales" <?= strpos($rfVal, '601') !== false ? 'selected' : '' ?>>601 - General de Ley Personas Morales</option>
                            <option value="626 - Régimen Simplificado de Confianza (RESICO Moral)" <?= ($rfVal === '626 - Régimen Simplificado de Confianza (RESICO Moral)') ? 'selected' : '' ?>>626 - RESICO (Personas Morales)</option>
                          </optgroup>
                        </select>
                      </div>

                      <!-- Uso de CFDI -->
                      <div class="col-md-6">
                        <label for="uso_cfdi" class="form-label fw-semibold">Uso de CFDI</label>
                        <?php $usoVal = $donante['UsoCFDI'] ?? 'D04 - Donativos'; ?>
                        <select class="form-select" id="uso_cfdi" name="uso_cfdi">
                          <option value="D04 - Donativos" <?= strpos($usoVal, 'D04') !== false ? 'selected' : '' ?>>D04 - Donativos (Recomendado)</option>
                          <option value="S01 - Sin efectos fiscales" <?= strpos($usoVal, 'S01') !== false ? 'selected' : '' ?>>S01 - Sin efectos fiscales</option>
                          <option value="G03 - Gastos en general" <?= strpos($usoVal, 'G03') !== false ? 'selected' : '' ?>>G03 - Gastos en general</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <!-- Estatus del Donante -->
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="estatus" class="form-label fw-semibold">Estado del Donante <span class="text-danger">*</span></label>
                      <select class="form-select" id="estatus" name="estatus" required>
                        <option value="Activo" <?= (($donante['Estatus'] ?? 'Activo') === 'Activo') ? 'selected' : '' ?>>Activo (Donante Vigente)</option>
                        <option value="Inactivo" <?= (($donante['Estatus'] ?? 'Activo') === 'Inactivo') ? 'selected' : '' ?>>Inactivo (Sin aportaciones recientes)</option>
                      </select>
                      <div class="form-text text-muted">Puedes reactivar o pausar el estatus de este donante.</div>
                    </div>
                  </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                  <a href="donantes.php" class="btn btn-light px-4">Cancelar</a>
                  <button type="submit" class="btn btn-purple px-4 fw-semibold shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Actualizar Donante
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

      function cambiarTipoPersonaFiscal(tipo) {
        const rfcInput = document.getElementById('rfc');
        const rfcHelp = document.getElementById('rfcHelp');
        const razonSocialHelp = document.getElementById('razonSocialHelp');
        
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

      function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }

      // Indicador discreto de 3 puntitos
      function mostrarPantallaCargando(titulo = 'Actualizando') {
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

      (() => {
        'use strict'
        const form = document.getElementById('formDonante') || document.querySelector('.needs-validation');
        if (!form) return;

        form.addEventListener('submit', event => {
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
          mostrarPantallaCargando('Actualizando expediente del donante...');
          const btnSubmit = form.querySelector('button[type="submit"]');
          if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = window.htmlTresPuntitos ? window.htmlTresPuntitos('Actualizando') : 'Actualizando...';
          }
        }, false);
      })();
    </script>
  </body>
</html>

