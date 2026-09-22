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
    <title>Editar Donante | GesMujer</title>

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
          <a class="nav-link" href="donativos.php"><i class="bi bi-coin me-1"></i> Historial de Donaciones</a>
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
                      <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($donante['Nombre']) ?>" required>
                      <div class="invalid-feedback">Por favor ingresa el nombre.</div>
                    </div>

                    <div class="col-md-4">
                      <label for="apellido_paterno" class="form-label fw-semibold">Apellido Paterno <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="<?= htmlspecialchars($donante['ApellidoPaterno']) ?>" required>
                      <div class="invalid-feedback">Por favor ingresa el apellido paterno.</div>
                    </div>

                    <div class="col-md-4">
                      <label for="apellido_materno" class="form-label fw-semibold">Apellido Materno</label>
                      <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($donante['ApellidoMaterno']) ?>">
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
                      <input type="text" class="form-control" id="ocupacion" name="ocupacion" value="<?= htmlspecialchars($donante['Ocupacion'] ?? '') ?>" placeholder="Ej. Abogada, Docente, Empresaria...">
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
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($donante['Email']) ?>" required>
                        <div class="invalid-feedback">Ingresa un correo electrónico válido.</div>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label for="telefono" class="form-label fw-semibold">Teléfono / Celular</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($donante['Telefono'] ?? '') ?>" placeholder="10 dígitos">
                      </div>
                    </div>

                    <div class="col-md-12">
                      <label for="domicilio" class="form-label fw-semibold">Domicilio (Calle, Número, Colonia)</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-house-door"></i></span>
                        <input type="text" class="form-control" id="domicilio" name="domicilio" value="<?= htmlspecialchars($donante['Domicilio'] ?? '') ?>" placeholder="Ej. Calle Macedonio Alcalá #200, Col. Centro">
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label for="ciudad" class="form-label fw-semibold">Ciudad / Municipio</label>
                      <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?= htmlspecialchars($donante['Ciudad'] ?? '') ?>" placeholder="Ej. Oaxaca de Juárez">
                    </div>

                    <div class="col-md-6">
                      <label for="estado" class="form-label fw-semibold">Estado</label>
                      <input type="text" class="form-control" id="estado" name="estado" value="<?= htmlspecialchars($donante['Estado'] ?? 'Oaxaca') ?>" placeholder="Ej. Oaxaca">
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
                      <input type="text" class="form-control" id="rfc" name="rfc" value="<?= htmlspecialchars($donante['RFC'] ?? '') ?>" placeholder="12 o 13 caracteres" maxlength="20" style="text-transform: uppercase;">
                      <div class="form-text text-muted">Para emisión de comprobantes o recibos deducibles.</div>
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
    <script>
      (() => {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        Array.from(forms).forEach(form => {
          form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
              event.preventDefault()
              event.stopPropagation()
            }
            form.classList.add('was-validated')
          }, false)
        })
      })()
    </script>
  </body>
</html>

