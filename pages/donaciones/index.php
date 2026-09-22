<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../db/config.php';

// Consultas estadísticas
try {
    // Total recaudado
    $stmtTotal = $conn->query("SELECT COALESCE(SUM(MontoDonacion), 0) as total FROM donativos");
    $totalRecaudado = (float)$stmtTotal->fetchColumn();

    // Total de donantes
    $stmtDonantes = $conn->query("SELECT COUNT(*) FROM donantes");
    $totalDonantes = (int)$stmtDonantes->fetchColumn();

    // Total de donativos
    $stmtDonativosCount = $conn->query("SELECT COUNT(*) FROM donativos");
    $totalDonativos = (int)$stmtDonativosCount->fetchColumn();

    // Donación promedio
    $promedioDonacion = $totalDonativos > 0 ? ($totalRecaudado / $totalDonativos) : 0;

    // Donaciones recientes (últimas 5)
    $stmtRecientes = $conn->query("
        SELECT d.ID_Donativo, d.MontoDonacion, d.TipoDonacion, 
               CONCAT(dn.Nombre, ' ', dn.ApellidoPaterno, ' ', dn.ApellidoMaterno) as Donante,
               dn.Email
        FROM donativos d
        INNER JOIN donantes dn ON d.ID_Donante = dn.ID_Donante
        ORDER BY d.ID_Donativo DESC
        LIMIT 5
    ");
    $donacionesRecientes = $stmtRecientes->fetchAll(PDO::FETCH_ASSOC);

    // Donantes recientes (últimos 5)
    $stmtDonantesRecientes = $conn->query("
        SELECT ID_Donante, CONCAT(Nombre, ' ', ApellidoPaterno, ' ', ApellidoMaterno) as NombreCompleto, Email, Telefono
        FROM donantes
        ORDER BY ID_Donante DESC
        LIMIT 5
    ");
    $donantesRecientes = $stmtDonantesRecientes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $totalRecaudado = 0;
    $totalDonantes = 0;
    $totalDonativos = 0;
    $promedioDonacion = 0;
    $donacionesRecientes = [];
    $donantesRecientes = [];
}
?>
<!doctype html>
<html lang="es" data-bs-theme="auto">
  <head>
    <script src="../../assets/js/color-modes.js"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Módulo de Donaciones">
    <title>Módulo de Donaciones | GesMujer</title>

    <link rel="canonical" href="https://getbootstrap.com/docs/5.3/examples/dashboard/">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@docsearch/css@3">
    <link href="../../assets/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Custom styles for this template -->
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

      .bd-placeholder-img {
        font-size: 1.125rem;
        text-anchor: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
      }

      @media (min-width: 768px) {
        .bd-placeholder-img-lg {
          font-size: 3.5rem;
        }
      }

      .b-example-divider {
        width: 100%;
        height: 3rem;
        background-color: rgba(0, 0, 0, .1);
        border: solid rgba(0, 0, 0, .15);
        border-width: 1px 0;
        box-shadow: inset 0 .5em 1.5em rgba(0, 0, 0, .1), inset 0 .125em .5em rgba(0, 0, 0, .15);
      }

      .b-example-vr {
        flex-shrink: 0;
        width: 1.5rem;
        height: 100vh;
      }

      .nav-scroller {
        position: relative;
        z-index: 2;
        height: 2.75rem;
        overflow-y: hidden;
      }

      .nav-scroller .nav {
        display: flex;
        flex-wrap: nowrap;
        padding-bottom: 1rem;
        margin-top: -1px;
        overflow-x: auto;
        text-align: center;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
      }

      .btn-bd-primary {
        --bd-violet-bg: #712cf9;
        --bd-violet-rgb: 112.520718, 44.062154, 249.437846;

        --bs-btn-font-weight: 600;
        --bs-btn-color: var(--bs-white);
        --bs-btn-bg: var(--bd-violet-bg);
        --bs-btn-border-color: var(--bd-violet-bg);
        --bs-btn-hover-color: var(--bs-white);
        --bs-btn-hover-bg: #6528e0;
        --bs-btn-hover-border-color: #6528e0;
        --bs-btn-focus-shadow-rgb: var(--bd-violet-rgb);
        --bs-btn-active-color: var(--bs-btn-hover-color);
        --bs-btn-active-bg: #5a23c8;
        --bs-btn-active-border-color: #5a23c8;
      }

      .bd-mode-toggle {
        z-index: 1500;
      }

      /* Estilos personalizados del Dashboard Salesforce */
      .sf-header {
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        padding: 16px 24px;
        border-radius: 12px;
        margin-bottom: 20px;
      }
      .sf-badge-app {
        background: #721896;
        color: white;
        font-weight: 600;
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 6px;
        display: inline-block;
        margin-bottom: 4px;
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
        transition: all 0.2s;
      }
      .sf-nav-tabs .nav-link.active {
        color: #721896;
        border-bottom: 3px solid #721896;
        background: transparent;
      }
      .sf-nav-tabs .nav-link:hover:not(.active) {
        border-bottom: 3px solid #cbd5e0;
        color: #2d3748;
      }
      .kpi-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }
      .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.08);
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
        display: flex;
        align-items: center;
        justify-content: space-between;
      }
      .birthday-badge {
        background: linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
      }
      .avatar-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
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

    <!-- Definición de Iconos SVG requeridos por el sidebar -->
    <svg xmlns="http://www.w3.org/2000/svg" class="d-none">
      <symbol id="check2" viewBox="0 0 16 16">
        <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
      </symbol>
      <symbol id="circle-half" viewBox="0 0 16 16">
        <path d="M8 15A7 7 0 1 0 8 1v14zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16z"/>
      </symbol>
      <symbol id="moon-stars-fill" viewBox="0 0 16 16">
        <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>
        <path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"/>
      </symbol>
      <symbol id="sun-fill" viewBox="0 0 16 16">
        <path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>
      </symbol>
      <symbol id="calendar3" viewBox="0 0 16 16">
        <path d="M14 0H2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zM1 3.857C1 3.384 1.448 3 2 3h12c.552 0 1 .384 1 .857v10.286c0 .473-.448.857-1 .857H2c-.552 0-1-.384-1-.857V3.857z"/>
        <path d="M6.5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
      </symbol>
      <symbol id="cart" viewBox="0 0 16 16">
        <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .49.598l-1 5a.5.5 0 0 1-.465.401l-9.397.472L4.415 11H13a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l.84 4.479 9.144-.459L13.89 4H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
      </symbol>
      <symbol id="chevron-right" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
      </symbol>
      <symbol id="door-closed" viewBox="0 0 16 16">
        <path d="M3 2a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v13h1.5a.5.5 0 0 1 0 1h-13a.5.5 0 0 1 0-1H3V2zm1 13h8V2H4v13z"/>
        <path d="M9 9a1 1 0 1 0 2 0 1 1 0 0 0-2 0z"/>
      </symbol>
      <symbol id="file-earmark" viewBox="0 0 16 16">
        <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
      </symbol>
      <symbol id="file-earmark-text" viewBox="0 0 16 16">
        <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z"/>
        <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5L9.5 0zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
      </symbol>
      <symbol id="gear-wide-connected" viewBox="0 0 16 16">
        <path d="M7.068.727c.243-.97 1.62-.97 1.864 0l.071.286a.96.96 0 0 0 1.622.434l.205-.211c.695-.719 1.888-.03 1.613.931l-.08.284a.96.96 0 0 0 1.187 1.187l.283-.081c.96-.275 1.65.918.931 1.613l-.211.205a.96.96 0 0 0 .434 1.622l.286.071c.97.243.97 1.62 0 1.864l-.286.071a.96.96 0 0 0-.434 1.622l.211.205c.719.695.03 1.888-.931 1.613l-.284-.08a.96.96 0 0 0-1.187 1.187l.081.283c.275.96-.918 1.65-1.613.931l-.205-.211a.96.96 0 0 0-1.622.434l-.071.286c-.243.97-1.62.97-1.864 0l-.071-.286a.96.96 0 0 0-1.622-.434l-.205.211c-.695.719-1.888.03-1.613-.931l.08-.284a.96.96 0 0 0-1.186-1.187l-.284.081c-.96.275-1.65-.918-.931-1.613l.211-.205a.96.96 0 0 0-.434-1.622l-.286-.071c-.97-.243-.97-1.62 0-1.864l.286-.071a.96.96 0 0 0 .434-1.622l-.211-.205c-.719-.695-.03-1.888.931-1.613l.284.08a.96.96 0 0 0 1.187-1.186l-.081-.284c-.275-.96.918-1.65 1.613-.931l.205.211a.96.96 0 0 0 1.622-.434l.071-.286zM12.973 8.5H8.25l-2.834 3.779A4.998 4.998 0 0 0 12.973 8.5zm0-1a4.998 4.998 0 0 0-7.557-3.779l2.834 3.78h4.723zM5.048 3.967c-.03.021-.058.043-.087.065l.087-.065zm-.431.355A4.984 4.984 0 0 0 3.002 8c0 1.455.622 2.765 1.615 3.678L7.375 8 4.617 4.322zm.344 7.646.087.065-.087-.065z"/>
      </symbol>
      <symbol id="graph-up" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M0 0h1v15h15v1H0V0Zm14.817 3.113a.5.5 0 0 1 .07.704l-4.5 5.5a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61 4.15-5.073a.5.5 0 0 1 .704-.07Z"/>
      </symbol>
      <symbol id="personita" viewBox="0 0 16 16">
        <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.5-5v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0m-2-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0M8 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4"/>
        <path d="M8.256 14a4.5 4.5 0 0 1-.229-1.004H3c.001-.246.154-.986.832-1.664C4.484 10.68 5.711 10 8 10q.39 0 .74.025c.226-.341.496-.65.804-.918Q8.844 9.002 8 9c-5 0-6 3-6 4s1 1 1 1z"/>
      </symbol>
      <symbol id="house-fill" viewBox="0 0 16 16">
        <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5Z"/>
        <path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293l6-6Z"/>
      </symbol>
      <symbol id="list" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
      </symbol>
      <symbol id="people" viewBox="0 0 16 16">
        <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
      </symbol>
      <symbol id="plus-circle" viewBox="0 0 16 16">
        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
      </symbol>
      <symbol id="puzzle" viewBox="0 0 16 16">
        <path d="M3.112 3.645A1.5 1.5 0 0 1 4.605 2H7a.5.5 0 0 1 .5.5v.382c0 .696-.497 1.182-.872 1.469a.459.459 0 0 0-.115.118.113.113 0 0 0-.012.025L6.5 4.5v.003l.003.01c.004.01.014.028.036.053a.86.86 0 0 0 .27.194C7.09 4.9 7.51 5 8 5c.492 0 .912-.1 1.19-.24a.86.86 0 0 0 .271-.194.213.213 0 0 0 .039-.063v-.009a.112.112 0 0 0-.012-.025.459.459 0 0 0-.115-.118c-.375-.287-.872-.773-.872-1.469V2.5A.5.5 0 0 1 9 2h2.395a1.5 1.5 0 0 1 1.493 1.645L12.645 6.5h.237c.195 0 .42-.147.675-.48.21-.274.528-.52.943-.52.568 0 .947.447 1.154.862C15.877 6.807 16 7.387 16 8s-.123 1.193-.346 1.638c-.207.415-.586.862-1.154.862-.415 0-.733-.246-.943-.52-.255-.333-.48-.48-.675-.48h-.237l.243 2.855A1.5 1.5 0 0 1 11.395 14H9a.5.5 0 0 1-.5-.5v-.382c0-.696.497-1.182.872-1.469a.459.459 0 0 0 .115-.118.113.113 0 0 0 .012-.025L9.5 11.5v-.003a.214.214 0 0 0-.039-.064.859.859 0 0 0-.27-.193C8.91 11.1 8.49 11 8 11c-.491 0-.912.1-1.19.24a.859.859 0 0 0-.271.194.214.214 0 0 0-.039.063v.003l.001.006a.113.113 0 0 0 .012.025c.016.027.05.068.115.118.375.287.872.773.872 1.469v.382a.5.5 0 0 1-.5.5H4.605a1.5 1.5 0 0 1-1.493-1.645L3.356 9.5h-.238c-.195 0-.42.147-.675.48-.21.274-.528.52-.943.52-.568 0-.947.447-1.154.862C.123 9.193 0 8.613 0 8s.123-1.193.346-1.638C.553 5.947.932 5.5 1.5 5.5c.415 0 .733.246.943.52.255.333.48.48.675.48h.238l-.244-2.855z"/>
      </symbol>
    </svg>

    <!-- Menú superior -->
    <?php require_once __DIR__ . '/../header.php'; ?>

    <!-- Menú lateral -->
    <?php require_once __DIR__ . '/../footer.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
      
      <!-- Encabezado Salesforce Header -->
      <div class="sf-header shadow-sm">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <h2 class="h3 fw-bold mb-0 text-dark">Panel de Donaciones</h2>
            <p class="text-muted small mb-0">Gestión integral de donantes y aportaciones económicas</p>
          </div>
          
          <!-- Botones de Acción Rápida -->
          <div class="d-flex flex-wrap gap-2">
            <a href="donante-nuevo.php" class="btn btn-outline-purple btn-sm px-3 py-2 fw-semibold">
              <i class="bi bi-person-plus-fill me-1"></i> Nuevo Donante
            </a>
            <a href="donativo-nuevo.php" class="btn btn-purple btn-sm px-3 py-2 fw-semibold shadow-sm">
              <i class="bi bi-cash-stack me-1"></i> Registrar Donación
            </a>
            <button class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalEnviarCorreo">
              <i class="bi bi-envelope-paper-heart me-1"></i> Redactar Correo
            </button>
          </div>
        </div>
      </div>

      <!-- Barra de pestañas estilo Salesforce -->
      <ul class="nav sf-nav-tabs">
        <li class="nav-item">
          <a class="nav-link active" href="index.php"><i class="bi bi-grid-1x2-fill me-1"></i> Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="donantes.php"><i class="bi bi-people-fill me-1"></i> Directorio de Donantes</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="donativos.php"><i class="bi bi-coin me-1"></i> Historial de Donaciones</a>
        </li>
      </ul>

      <!-- Tarjetas de Métricas (KPIs) -->
      <div class="row g-3 mb-4">
        
        <div class="col-12 col-md-4">
          <div class="card kpi-card bg-white p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <span class="text-muted small fw-semibold text-uppercase">Total Recaudado</span>
                <h3 class="fw-bold text-success mb-0 mt-1">$<?= number_format($totalRecaudado, 2) ?></h3>
                <small class="text-muted">Fondo acumulado</small>
              </div>
              <div class="rounded-circle bg-success-subtle p-3 text-success fs-4 d-flex align-items-center justify-content-center" style="width:52px; height:52px;">
                <i class="bi bi-cash-coin"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-4">
          <div class="card kpi-card bg-white p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <span class="text-muted small fw-semibold text-uppercase">Total Donantes</span>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= $totalDonantes ?></h3>
                <small class="text-muted">Personas e instituciones</small>
              </div>
              <div class="rounded-circle bg-primary-subtle p-3 text-primary fs-4 d-flex align-items-center justify-content-center" style="width:52px; height:52px;">
                <i class="bi bi-people-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-4">
          <div class="card kpi-card bg-white p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <span class="text-muted small fw-semibold text-uppercase">Aportaciones</span>
                <h3 class="fw-bold text-purple mb-0 mt-1" style="color:#721896;"><?= $totalDonativos ?></h3>
                <small class="text-muted">Donativos registrados</small>
              </div>
              <div class="rounded-circle p-3 fs-4 d-flex align-items-center justify-content-center" style="background:#f3e8ff; color:#721896; width:52px; height:52px;">
                <i class="bi bi-piggy-bank-fill"></i>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Fila principal de 3 columnas estilo Salesforce -->
      <div class="row g-4">
        
        <!-- Columna 1: Donantes Recientes y Donaciones Clave -->
        <div class="col-lg-4">
          
          <!-- Tarjeta Donantes Recientes -->
          <div class="card-sf">
            <div class="card-sf-header">
              <span><i class="bi bi-person-lines-fill me-2 text-primary"></i> Donantes Recientes</span>
              <a href="donantes.php" class="btn btn-sm btn-link text-decoration-none p-0">Ver todos</a>
            </div>
            <div class="card-body p-0">
              <ul class="list-group list-group-flush">
                <?php if (empty($donantesRecientes)): ?>
                  <li class="list-group-item text-center py-4 text-muted">No hay donantes registrados aún.</li>
                <?php else: ?>
                  <?php foreach ($donantesRecientes as $donante): ?>
                    <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                      <div class="d-flex align-items-center gap-3">
                        <div class="avatar-circle" style="background:#8e3ec0;">
                          <?= strtoupper(substr($donante['NombreCompleto'] ?? 'D', 0, 1)) ?>
                        </div>
                        <div>
                          <div class="fw-semibold text-dark"><?= htmlspecialchars($donante['NombreCompleto']) ?></div>
                          <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($donante['Email'] ?: 'Sin correo') ?></small>
                        </div>
                      </div>
                      <button class="btn btn-sm btn-outline-secondary" onclick="prepararCorreo('<?= htmlspecialchars($donante['NombreCompleto']) ?>', '<?= htmlspecialchars($donante['Email']) ?>')">
                        <i class="bi bi-send-fill"></i>
                      </button>
                    </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            </div>
          </div>

          <!-- Banner Informativo -->
          <div class="card border-0 shadow-sm p-4 text-white" style="background: linear-gradient(135deg, #9d4edd 100%); border-radius: 12px;">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; min-width: 50px;">
                <i class="bi bi-shield-check text-white" style="font-size: 1.6rem; line-height: 1; display: flex; align-items: center; justify-content: center;"></i>
              </div>
              <div>
                <h5 class="fw-bold mb-0">Atención Personalizada</h5>
                <small class="opacity-75">Fidelización de Donantes</small>
              </div>
            </div>
            <p class="small mb-3">Agradece cada donativo en menos de 24 horas y envía tarjetas de cumpleaños automáticas para fortalecer el vínculo con GesMujer.</p>
            <button class="btn btn-light btn-sm text-purple fw-semibold" data-bs-toggle="modal" data-bs-target="#modalEnviarCorreo">
              <i class="bi bi-envelope-heart-fill me-1"></i> Enviar Agradecimiento
            </button>
          </div>

        </div>

        <!-- Columna 2: Eventos / Cumpleaños y Donaciones Recientes -->
        <div class="col-lg-5">
          
          <!-- Tarjeta de Cumpleaños (Estilo Salesforce) -->
          <div class="card-sf">
            <div class="card-sf-header">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calendar-heart-fill text-danger" style="font-size: 1.1rem; line-height: 1; display: inline-flex; align-items: center; justify-content: center;"></i>
                <span class="fw-bold">Cumpleaños de Donantes</span>
              </div>
            </div>
            <div class="card-body p-3">
              
              <!-- Alerta de cumpleaños de hoy -->
              <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 mb-3 border-0 shadow-sm" style="border-radius:10px; background:#fff8e6;">
                <div class="d-flex align-items-center gap-3">
                  <div class="d-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle" style="width: 44px; height: 44px; min-width: 44px;">
                    <i class="bi bi-gift-fill text-danger" style="font-size: 1.4rem; line-height: 1; display: flex; align-items: center; justify-content: center;"></i>
                  </div>
                  <div>
                    <strong class="d-block text-dark">¡Hoy es cumpleaños de María González!</strong>
                    <small class="text-muted">Donante recurrente desde 2024</small>
                  </div>
                </div>
                <button class="btn btn-sm btn-danger fw-semibold px-3" onclick="enviarTarjetaCumple('María González', 'maria@ejemplo.com')">
                  <i class="bi bi-gift-fill me-1"></i> Felicitar
                </button>
              </div>

              <!-- Lista de próximos cumpleaños -->
              <h6 class="text-muted small fw-bold text-uppercase mt-3 mb-2">Próximos en los siguientes 15 días</h6>
              <div class="list-group list-group-flush">
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                  <div>
                    <span class="fw-semibold">Lic. Carlos Morales</span><br>
                    <small class="text-muted">Cumple el 18 de Septiembre (en 4 días)</small>
                  </div>
                  <button class="btn btn-sm btn-outline-purple" onclick="enviarTarjetaCumple('Carlos Morales', 'carlos@ejemplo.com')">
                    <i class="bi bi-envelope-paper-heart-fill me-1"></i> Tarjeta
                  </button>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                  <div>
                    <span class="fw-semibold">Dra. Patricia Reyes</span><br>
                    <small class="text-muted">Cumple el 24 de Septiembre (en 10 días)</small>
                  </div>
                  <button class="btn btn-sm btn-outline-purple" onclick="enviarTarjetaCumple('Patricia Reyes', 'patricia@ejemplo.com')">
                    <i class="bi bi-envelope-paper-heart-fill me-1"></i> Tarjeta
                  </button>
                </div>
              </div>

            </div>
          </div>

          <!-- Historial de Donaciones Recientes -->
          <div class="card-sf">
            <div class="card-sf-header">
              <span><i class="bi bi-clock-history me-2 text-purple"></i> Últimos Donativos Ingresados</span>
              <a href="donativos.php" class="btn btn-sm btn-link text-decoration-none p-0">Ver listado</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light small">
                    <tr>
                      <th>Donante</th>
                      <th>Monto</th>
                      <th>Método</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($donacionesRecientes)): ?>
                      <tr>
                        <td colspan="3" class="text-center py-4 text-muted">No hay donaciones recientes.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($donacionesRecientes as $donacion): ?>
                        <tr>
                          <td>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($donacion['Donante']) ?></span>
                          </td>
                          <td>
                            <span class="badge bg-success-subtle text-success fs-6 fw-bold">
                              $<?= number_format((float)$donacion['MontoDonacion'], 2) ?>
                            </span>
                          </td>
                          <td>
                            <span class="badge bg-light text-secondary border">
                              <i class="bi bi-credit-card-2-front me-1"></i><?= htmlspecialchars($donacion['TipoDonacion']) ?>
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>

        <!-- Columna 3: Accesos Rápidos y Vista Previa de Tarjeta -->
        <div class="col-lg-3">
          
          <!-- Acciones Rápidas -->
          <div class="card-sf">
            <div class="card-sf-header">
              <span></i> Accesos Directos</span>
            </div>
            <div class="card-body d-flex flex-column gap-2">
              <a href="donativo-nuevo.php" class="btn btn-outline-primary text-start d-flex align-items-center gap-2 py-2">
                <i class="bi bi-plus-circle-fill text-primary"></i> Registrar Nueva Donación
              </a>
              <a href="donante-nuevo.php" class="btn btn-outline-purple text-start d-flex align-items-center gap-2 py-2">
                <i class="bi bi-person-plus-fill"></i> Registrar Nuevo Donante
              </a>
              <a href="donativos.php" class="btn btn-outline-success text-start d-flex align-items-center gap-2 py-2">
                <i class="bi bi-file-earmark-spreadsheet-fill text-success"></i> Reporte de Donaciones
              </a>
              <button class="btn btn-outline-danger text-start d-flex align-items-center gap-2 py-2" data-bs-toggle="modal" data-bs-target="#modalEnviarCorreo">
                <i class="bi bi-envelope-fill text-danger"></i> Enviar Mensaje a Donante
              </button>
            </div>
          </div>

          <!-- Vista Previa Tarjeta de Cumpleaños -->
          <div class="card-sf text-center p-3">
            <span class="text-muted small fw-bold text-uppercase d-block mb-2">Diseño de Tarjeta GesMujer</span>
            <div class="p-3 rounded-3 text-white mb-2 shadow-sm" style="background: linear-gradient(135deg, #721896 0%);">
              <div class="text-warning mb-3" style="font-size: 2rem; line-height: 1;">
                <i class="bi bi-stars" style="width: auto; height: auto; font-size: 2rem;"></i>
                <i class="bi bi-gift-fill text-white" style="width: auto; height: auto; font-size: 2rem; margin: 0 4px;"></i>
                <i class="bi bi-stars" style="width: auto; height: auto; font-size: 2rem;"></i>
              </div>
              <h5 class="fw-bold mb-1">¡Feliz Cumpleaños!</h5>
              <p style="font-size: 0.78rem;" class="mb-2 opacity-90">GesMujer te desea un día lleno de dicha y te agradece de corazón tu apoyo solidario.</p>
              <span class="badge bg-white text-dark small px-3 py-1">
                <i class="bi bi-heart-fill text-danger me-1"></i> Familia GesMujer Oaxaca
              </span>
            </div>
          </div>

        </div>

      </div>

    </main>
  </div>
</div>

    <!-- Modal: Enviar Correo Directo -->
    <div class="modal fade" id="modalEnviarCorreo" tabindex="-1" aria-labelledby="modalEnviarCorreoLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header text-white" style="background:#721896;">
            <h5 class="modal-title" id="modalEnviarCorreoLabel"><i class="bi bi-envelope-paper-heart me-2"></i> Enviar Correo a Donante</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="acciones/enviar-correo.php" method="POST">
            <div class="modal-body">
              
              <div class="mb-3">
                <label class="form-label fw-semibold">Seleccionar Plantilla Rápida</label>
                <select class="form-select" id="plantillaSelect" onchange="cambiarPlantilla()">
                  <option value="agradecimiento">Carta de Agradecimiento por Donativo</option>
                  <option value="cumpleanos">Tarjeta de Feliz Cumpleaños</option>
                  <option value="informativo">Informe de Impacto y Actividades</option>
                  <option value="personalizado">Mensaje Personalizado</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Nombre del Donante</label>
                <input type="text" class="form-control" name="nombre" id="correo_nombre" placeholder="Nombre completo" required>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Correo Electrónico</label>
                <input type="email" class="form-control" name="email" id="correo_email" placeholder="correo@ejemplo.com" required>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Mensaje</label>
                <textarea class="form-control" name="mensaje" id="correo_mensaje" rows="5" required></textarea>
              </div>

            </div>
            <div class="modal-footer bg-light">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-purple"><i class="bi bi-send-fill me-1"></i> Enviar Correo Ahora</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
      function prepararCorreo(nombre, email) {
        document.getElementById('correo_nombre').value = nombre;
        document.getElementById('correo_email').value = email;
        cambiarPlantilla();
        new bootstrap.Modal(document.getElementById('modalEnviarCorreo')).show();
      }

      function enviarTarjetaCumple(nombre, email) {
        document.getElementById('correo_nombre').value = nombre;
        document.getElementById('correo_email').value = email;
        document.getElementById('plantillaSelect').value = 'cumpleanos';
        cambiarPlantilla();
        new bootstrap.Modal(document.getElementById('modalEnviarCorreo')).show();
      }

      function cambiarPlantilla() {
        const sel = document.getElementById('plantillaSelect').value;
        const txt = document.getElementById('correo_mensaje');
        const nom = document.getElementById('correo_nombre').value || '[Nombre del Donante]';

        if (sel === 'agradecimiento') {
          txt.value = `Estimado(a) ${nom},\n\nEn nombre de todo el equipo de GesMujer Oaxaca, queremos expresarle nuestro más sincero agradecimiento por su valiosa donación. Su generosidad nos permite continuar brindando atención integral y esperanza a más mujeres en nuestro estado.\n\n¡Gracias por ser parte de este cambio!`;
        } else if (sel === 'cumpleanos') {
          txt.value = `¡Feliz Cumpleaños, ${nom}!\n\nTodo el equipo de GesMujer Oaxaca le desea un día maravilloso lleno de salud, alegría y bendiciones. Agradecemos enormemente contar con su apoyo y solidaridad constante.\n\n¡Un fuerte abrazo de parte de la familia GesMujer!`;
        } else if (sel === 'informativo') {
          txt.value = `Estimado(a) ${nom},\n\nLe compartimos nuestro informe mensual de actividades. Gracias a su apoyo hemos logrado avanzar en nuestros programas de empoderamiento y acompañamiento.\n\nQuedamos a su disposición para cualquier duda.`;
        } else if (sel === 'personalizado') {
          txt.value = '';
        }
      }

      document.addEventListener('DOMContentLoaded', () => {
        cambiarPlantilla();
      });
    </script>
  </body>
</html>

