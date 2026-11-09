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
    <title>Nuevo Donante | GesMujer</title>

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
            <p class="text-muted small mb-0">Captura de información para el padrón solidario de GesMujer</p>
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
              <span class="badge bg-light text-muted border fw-normal" style="font-size:0.75rem;">
                <i class="bi bi-shield-check text-success me-1"></i> Verificación Anti-duplicados activa
              </span>
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
                          ⚠️ Posible Registro Duplicado Detectado
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
                      <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej. María" value="<?= $valNombre ?>" required autocomplete="off">
                      <div class="invalid-feedback">Por favor ingresa el nombre.</div>
                    </div>

                    <div class="col-md-4">
                      <label for="apellido_paterno" class="form-label fw-semibold">Apellido Paterno <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" placeholder="Ej. Gómez" value="<?= $valApPaterno ?>" required autocomplete="off">
                      <div class="invalid-feedback">Por favor ingresa el apellido paterno.</div>
                    </div>

                    <div class="col-md-4">
                      <label for="apellido_materno" class="form-label fw-semibold">Apellido Materno</label>
                      <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" placeholder="Ej. López" value="<?= $valApMaterno ?>" autocomplete="off">
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
                      <input type="text" class="form-control" id="ocupacion" name="ocupacion" placeholder="Ej. Abogada, Docente, Empresaria...">
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
                        <input type="email" class="form-control" id="email" name="email" placeholder="correo@ejemplo.com" value="<?= $valEmail ?>" required autocomplete="off">
                        <div class="invalid-feedback">Ingresa un correo electrónico válido.</div>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label for="telefono" class="form-label fw-semibold">Teléfono / Celular</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="10 dígitos" value="<?= $valTelefono ?>" autocomplete="off">
                      </div>
                    </div>

                    <div class="col-md-12">
                      <label for="domicilio" class="form-label fw-semibold">Domicilio (Calle, Número, Colonia)</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-house-door"></i></span>
                        <input type="text" class="form-control" id="domicilio" name="domicilio" placeholder="Ej. Calle Macedonio Alcalá #200, Col. Centro">
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label for="ciudad" class="form-label fw-semibold">Ciudad / Municipio</label>
                      <input type="text" class="form-control" id="ciudad" name="ciudad" placeholder="Ej. Oaxaca de Juárez" value="<?= $valCiudad ?>">
                    </div>

                    <div class="col-md-6">
                      <label for="estado" class="form-label fw-semibold">Estado</label>
                      <input type="text" class="form-control" id="estado" name="estado" placeholder="Ej. Oaxaca" value="Oaxaca">
                    </div>
                  </div>
                </div>

                <hr class="my-4">

                <!-- Bloque 3: Datos Fiscales Opcionales -->
                <div class="mb-4">
                  <h6 class="fw-bold text-uppercase text-purple mb-3" style="color:#721896; font-size: 0.85rem; letter-spacing: 0.5px;">
                    <i class="bi bi-receipt me-1"></i> Información Fiscal (Opcional)
                  </h6>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="rfc" class="form-label fw-semibold">RFC</label>
                      <input type="text" class="form-control" id="rfc" name="rfc" placeholder="12 o 13 caracteres" maxlength="20" style="text-transform: uppercase;" value="<?= $valRfc ?>" autocomplete="off">
                      <div class="form-text text-muted">Para emisión de comprobantes o recibos deducibles.</div>
                    </div>

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

      // Validación nativa y confirmación SweetAlert2 antes de enviar
      (() => {
        'use strict'
        const form = document.getElementById('formDonante');
        if (!form) return;

        form.addEventListener('submit', event => {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated');
            return;
          }

          // Si hay duplicados detectados y el usuario no marcó la casilla de confirmación
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
                form.submit();
              }
            });
            return;
          }

          form.classList.add('was-validated');
        }, false);

        // Si ya venían datos precargados (por ejemplo al volver con error), disparar chequeo inicial
        if (document.getElementById('email')?.value || document.getElementById('nombre')?.value) {
          verificarDuplicados();
        }
      })();
    </script>
  </body>
</html>
