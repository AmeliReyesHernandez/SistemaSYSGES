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
            <h2 class="h3 fw-bold mb-0 text-dark">Editar Donación #<?= $idDonativo ?></h2>
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
          <a class="nav-link active" href="donativos.php"><i class="bi bi-coin me-1"></i> Historial de Donaciones</a>
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

                  <!-- Monto -->
                  <div class="col-md-6">
                    <label for="monto_donacion" class="form-label fw-semibold">Monto de la Donación (MXN) <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text fw-bold">$</span>
                      <input type="number" step="0.01" min="1" class="form-control" id="monto_donacion" name="monto_donacion" value="<?= htmlspecialchars($donativo['MontoDonacion']) ?>" required>
                      <div class="invalid-feedback">Ingresa un monto válido mayor a 0.</div>
                    </div>
                  </div>

                  <!-- Método de donación -->
                  <div class="col-md-6">
                    <label for="tipo_donacion" class="form-label fw-semibold">Método / Tipo de Donación <span class="text-danger">*</span></label>
                    <select class="form-select" id="tipo_donacion" name="tipo_donacion" required>
                      <?php
                      $metodos = [
                          'Transferencia Bancaria',
                          'Efectivo',
                          'Depósito en Ventanilla',
                          'Tarjeta de Crédito / Débito',
                          'Cheque',
                          'En Especie',
                          'Otro'
                      ];
                      foreach ($metodos as $metodo) {
                          $sel = ($donativo['TipoDonacion'] === $metodo) ? 'selected' : '';
                          echo "<option value=\"$metodo\" $sel>$metodo</option>";
                      }
                      ?>
                    </select>
                    <div class="invalid-feedback">Selecciona el método de aportación.</div>
                  </div>

                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                  <a href="donativos.php" class="btn btn-light px-4">Cancelar</a>
                  <button type="submit" class="btn btn-purple px-4 fw-semibold shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Actualizar Donación
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
      document.addEventListener("DOMContentLoaded", () => {
        const input = document.getElementById("input_donante");
        const sugerencias = document.getElementById("sug_donante");
        const inputHidden = document.getElementById("id_donante");

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
                  <small class="text-muted d-block">${donante.Email || 'Sin correo'}</small>
                </div>
                <span class="badge bg-secondary-subtle text-secondary small">#${donante.ID_Donante}</span>
              `;
              item.addEventListener("click", () => {
                input.value = donante.NombreCompleto;
                inputHidden.value = donante.ID_Donante;
                sugerencias.innerHTML = "";
                input.classList.remove("is-invalid");
                input.classList.add("is-valid");
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

      function limpiarDonante() {
        document.getElementById("input_donante").value = "";
        document.getElementById("id_donante").value = "";
        document.getElementById("input_donante").classList.remove("is-valid");
        document.getElementById("sug_donante").innerHTML = "";
        document.getElementById("input_donante").focus();
      }

      // Validación
      (() => {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        Array.from(forms).forEach(form => {
          form.addEventListener('submit', event => {
            const hiddenId = document.getElementById('id_donante');
            const inputDonante = document.getElementById('input_donante');
            if (!hiddenId.value) {
              inputDonante.classList.add('is-invalid');
              event.preventDefault();
              event.stopPropagation();
            }
            if (!form.checkValidity()) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add('was-validated');
          }, false);
        });
      })();
    </script>
  </body>
</html>

