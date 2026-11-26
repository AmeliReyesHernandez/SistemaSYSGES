<?php
ob_start();
require_once __DIR__ . '/../footer.php';
$html = ob_get_clean();

// Corregir enlaces del menú lateral para que naveguen a la raíz desde pages/donaciones/
$html = str_replace(
    [
        'href="../pages/',
        'href="../checkout/',
        'href="../sign-in/',
        'href="../justicia/',
        'href="../psicologia/',
        'href="../Soporte/',
        'src="../assets/'
    ],
    [
        'href="../../pages/',
        'href="../../checkout/',
        'href="../../sign-in/',
        'href="../../justicia/',
        'href="../../psicologia/',
        'href="../../Soporte/',
        'src="../../assets/'
    ],
    $html
);

echo $html;
?>
<style>
  /* Números de ID más pequeños y legibles en las tablas del módulo */
  .tabla-con-id th:first-child {
    width: 52px;
    font-size: .72rem !important;
    white-space: nowrap;
  }
  .tabla-con-id td:first-child {
    font-size: .78rem !important;
    white-space: nowrap;
  }
  .tabla-con-id td:first-child .badge {
    font-size: .74rem !important;
  }

  /* Indicador discreto de 3 puntitos */
  .sysges-dots {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    vertical-align: middle;
    margin-left: 4px;
  }
  .sysges-dots span {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background-color: currentColor;
    display: inline-block;
    opacity: .35;
    animation: sysgesDotPulse 1.1s infinite ease-in-out;
  }
  .sysges-dots span:nth-child(2) { animation-delay: .18s; }
  .sysges-dots span:nth-child(3) { animation-delay: .36s; }

  @keyframes sysgesDotPulse {
    0%, 80%, 100% { transform: scale(.75); opacity: .3; }
    40% { transform: scale(1.15); opacity: 1; }
  }

  #sysgesDiscreetLoader {
    position: fixed;
    bottom: 20px;
    right: 24px;
    z-index: 1080;
    background: rgba(255, 255, 255, .96);
    border: 1px solid #e6d8f2;
    border-radius: 999px;
    padding: 7px 14px;
    box-shadow: 0 4px 14px rgba(114, 24, 150, .14);
    display: none;
    align-items: center;
    gap: 6px;
    font-size: .8rem;
    font-weight: 600;
    color: #721896;
    pointer-events: none;
  }
</style>

<div id="sysgesDiscreetLoader" aria-live="polite">
  <span id="sysgesDiscreetLoaderText">Cargando</span>
  <span class="sysges-dots"><span></span><span></span><span></span></span>
</div>

<script>
  window.mostrarCargandoDiscreto = function(texto = 'Cargando') {
    const el  = document.getElementById('sysgesDiscreetLoader');
    const txt = document.getElementById('sysgesDiscreetLoaderText');
    if (txt) txt.textContent = texto.replace(/\.+$/, '');
    if (el)  el.style.display = 'inline-flex';
  };

  window.ocultarCargandoDiscreto = function() {
    const el = document.getElementById('sysgesDiscreetLoader');
    if (el) el.style.display = 'none';
  };

  window.htmlTresPuntitos = function(texto = 'Cargando') {
    return `${texto} <span class="sysges-dots"><span></span><span></span><span></span></span>`;
  };

  document.addEventListener('DOMContentLoaded', () => {
    // Filtrado en tiempo real para campos de solo letras (sin números ni símbolos)
    const camposSoloLetras = document.querySelectorAll('#nombre, #apellido_paterno, #apellido_materno, #ocupacion, #ciudad, #estado');
    camposSoloLetras.forEach(inp => {
      inp.addEventListener('input', () => {
        const limpio = inp.value.replace(/[^a-zA-ZÁÉÍÓÚáéíóúÜüÑñ\s]/g, '');
        if (inp.value !== limpio) inp.value = limpio;
      });
    });

    // Filtrado en tiempo real para campos numéricos (teléfono 10 dígitos, CP 5 dígitos)
    const camposSoloNumeros = document.querySelectorAll('#telefono, #codigo_postal_fiscal, #fact_cp');
    camposSoloNumeros.forEach(inp => {
      inp.addEventListener('input', () => {
        const limpio = inp.value.replace(/\D/g, '');
        if (inp.value !== limpio) inp.value = limpio;
      });
    });

    // Filtrado alfanumérico para RFC
    const camposRfc = document.querySelectorAll('#rfc, #fact_rfc');
    camposRfc.forEach(inp => {
      inp.addEventListener('input', () => {
        const limpio = inp.value.toUpperCase().replace(/[^A-Z0-9Ñ&]/g, '');
        if (inp.value !== limpio) inp.value = limpio;
      });
    });

    const formCorreo = document.getElementById('formCorreo');
    if (formCorreo) {
      formCorreo.addEventListener('submit', function(e) {
        if (!formCorreo.checkValidity()) return;
        const submitter = e.submitter;
        const esBorrador = submitter && submitter.value === 'borrador';
        const label = esBorrador ? 'Guardando' : 'Enviando';

        if (submitter && submitter.name) {
          let hidden = formCorreo.querySelector('input[type="hidden"][data-submitter-accion]');
          if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.setAttribute('data-submitter-accion', '1');
            formCorreo.appendChild(hidden);
          }
          hidden.name = submitter.name;
          hidden.value = submitter.value;
        }

        window.mostrarCargandoDiscreto(esBorrador ? 'Guardando borrador' : 'Enviando correo');
        setTimeout(() => {
          formCorreo.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = true);
          if (submitter) submitter.innerHTML = window.htmlTresPuntitos(label);
        }, 0);
      });
    }
  });
</script>
