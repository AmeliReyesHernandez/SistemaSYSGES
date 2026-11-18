<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';
require_once __DIR__ . '/servicio-correo.php';
require_once __DIR__ . '/auditoria-servicio.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../donativos.php");
    exit();
}

$idDonativo = isset($_POST["id_donativo"]) && is_numeric($_POST["id_donativo"]) ? (int)$_POST["id_donativo"] : null;
$idDonante = isset($_POST["id_donante"]) && is_numeric($_POST["id_donante"]) ? (int)$_POST["id_donante"] : null;
$tipoDonacion = trim($_POST["tipo_donacion"] ?? '');
$montoDonacion = isset($_POST["monto_donacion"]) ? (float)$_POST["monto_donacion"] : 0;
$descripcionEspecie = trim($_POST["descripcion_especie"] ?? '');

// Datos fiscales / Recibo deducible
$requiereRecibo = isset($_POST['requiere_recibo']) && $_POST['requiere_recibo'] == '1' ? 1 : 0;
$facturacionModo = trim($_POST['facturacion_modo'] ?? 'donante');

$facturacionDiferente = 0;
$tipoPersonaFiscal = null;
$rfcFiscal = null;
$razonSocialFiscal = null;
$cpFiscal = null;
$regimenFiscal = null;
$usoCfdiFiscal = null;
$emailFiscal = null;

$esEspecie = ($tipoDonacion === 'En Especie' || $tipoDonacion === 'Bienes / Mobiliario');

if (!$idDonante || $tipoDonacion === '') {
    $volver = $idDonativo ? "../donativo-editar.php?id=$idDonativo" : "../donativo-nuevo.php";
    header("Location: $volver&status=error&msg=" . urlencode("Por favor selecciona un donante y un tipo de donativo."));
    exit();
}

if ($esEspecie) {
    $montoDonacion = 0;
    if ($descripcionEspecie === '') {
        $volver = $idDonativo ? "../donativo-editar.php?id=$idDonativo" : "../donativo-nuevo.php";
        header("Location: $volver&status=error&msg=" . urlencode("Por favor ingresa la descripción o detalle del donativo en especie."));
        exit();
    }
} else {
    $descripcionEspecie = null;
    if ($montoDonacion <= 0) {
        $volver = $idDonativo ? "../donativo-editar.php?id=$idDonativo" : "../donativo-nuevo.php";
        header("Location: $volver&status=error&msg=" . urlencode("Por favor ingresa un monto mayor a cero para donativos monetarios."));
        exit();
    }
}

try {
    $esNuevo = !$idDonativo;

    // Procesar datos fiscales si requiere recibo deducible
    if ($requiereRecibo === 1) {
        if ($facturacionModo === 'diferente') {
            $facturacionDiferente = 1;
            $tipoPersonaFiscal = trim($_POST['fact_tipo_persona'] ?? 'Moral');
            $rfcFiscal = strtoupper(trim($_POST['fact_rfc'] ?? ''));
            $razonSocialFiscal = trim($_POST['fact_razon_social'] ?? '');
            $cpFiscal = trim($_POST['fact_cp'] ?? '');
            $regimenFiscal = trim($_POST['fact_regimen'] ?? '');
            $usoCfdiFiscal = trim($_POST['fact_uso_cfdi'] ?? 'D04 - Donativos');
            $emailFiscal = trim($_POST['fact_email'] ?? '');
        } else {
            // Utilizar los datos fiscales guardados en el perfil del donante
            $facturacionDiferente = 0;
            $stmtD = $conn->prepare("
                SELECT TipoPersonaFiscal, RFC, RazonSocialFiscal, CodigoPostalFiscal, RegimenFiscal, UsoCFDI, EmailFiscal,
                       CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno, '')) AS NombreCompleto, Email
                FROM donantes
                WHERE ID_Donante = :id
            ");
            $stmtD->execute([':id' => $idDonante]);
            $donanteFiscal = $stmtD->fetch(PDO::FETCH_ASSOC);

            if ($donanteFiscal) {
                $tipoPersonaFiscal = $donanteFiscal['TipoPersonaFiscal'] ?: 'Fisica';
                $rfcFiscal = strtoupper(trim($donanteFiscal['RFC'] ?? ''));
                $razonSocialFiscal = trim($donanteFiscal['RazonSocialFiscal'] ?? '') ?: trim($donanteFiscal['NombreCompleto'] ?? '');
                $cpFiscal = trim($donanteFiscal['CodigoPostalFiscal'] ?? '');
                $regimenFiscal = trim($donanteFiscal['RegimenFiscal'] ?? '');
                $usoCfdiFiscal = trim($donanteFiscal['UsoCFDI'] ?? 'D04 - Donativos');
                $emailFiscal = trim($donanteFiscal['EmailFiscal'] ?? '') ?: trim($donanteFiscal['Email'] ?? '');
            }
        }
    }

    if ($idDonativo) {
        $stmt = $conn->prepare("
            UPDATE donativos SET
                ID_Donante = :donante,
                MontoDonacion = :monto,
                TipoDonacion = :tipo,
                DescripcionEspecie = :descripcion,
                RequiereRecibo = :requiere_recibo,
                FacturacionDiferente = :facturacion_diferente,
                TipoPersonaFiscal = :tipo_persona_fiscal,
                RFC_Fiscal = :rfc_fiscal,
                RazonSocial_Fiscal = :razon_social_fiscal,
                CP_Fiscal = :cp_fiscal,
                Regimen_Fiscal = :regimen_fiscal,
                UsoCFDI_Fiscal = :uso_cfdi_fiscal,
                Email_Fiscal = :email_fiscal
            WHERE ID_Donativo = :id
        ");
        $stmt->bindParam(':id', $idDonativo, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO donativos (
                ID_Donante, MontoDonacion, TipoDonacion, DescripcionEspecie, FechaDonacion,
                RequiereRecibo, FacturacionDiferente, TipoPersonaFiscal, RFC_Fiscal, RazonSocial_Fiscal,
                CP_Fiscal, Regimen_Fiscal, UsoCFDI_Fiscal, Email_Fiscal
            )
            VALUES (
                :donante, :monto, :tipo, :descripcion, NOW(),
                :requiere_recibo, :facturacion_diferente, :tipo_persona_fiscal, :rfc_fiscal, :razon_social_fiscal,
                :cp_fiscal, :regimen_fiscal, :uso_cfdi_fiscal, :email_fiscal
            )
        ");
    }

    $stmt->bindParam(':donante', $idDonante, PDO::PARAM_INT);
    $stmt->bindParam(':monto', $montoDonacion);
    $stmt->bindParam(':tipo', $tipoDonacion);
    $stmt->bindParam(':descripcion', $descripcionEspecie);
    $stmt->bindParam(':requiere_recibo', $requiereRecibo, PDO::PARAM_INT);
    $stmt->bindParam(':facturacion_diferente', $facturacionDiferente, PDO::PARAM_INT);
    $stmt->bindParam(':tipo_persona_fiscal', $tipoPersonaFiscal);
    $stmt->bindParam(':rfc_fiscal', $rfcFiscal);
    $stmt->bindParam(':razon_social_fiscal', $razonSocialFiscal);
    $stmt->bindParam(':cp_fiscal', $cpFiscal);
    $stmt->bindParam(':regimen_fiscal', $regimenFiscal);
    $stmt->bindParam(':uso_cfdi_fiscal', $usoCfdiFiscal);
    $stmt->bindParam(':email_fiscal', $emailFiscal);

    $stmt->execute();

    // Reactivar automáticamente el estatus del donante a 'Activo'
    $stmtActivar = $conn->prepare("UPDATE donantes SET Estatus = 'Activo' WHERE ID_Donante = :donante");
    $stmtActivar->execute([':donante' => $idDonante]);

    // Si es un donativo nuevo, enviar correo automático de agradecimiento al donante
    if ($esNuevo) {
        $stmtDonante = $conn->prepare("
            SELECT Nombre, ApellidoPaterno, ApellidoMaterno,
                   CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno, '')) AS NombreCompleto, Email
            FROM donantes
            WHERE ID_Donante = :id
        ");
        $stmtDonante->execute([':id' => $idDonante]);
        $donante = $stmtDonante->fetch(PDO::FETCH_ASSOC);

        if ($donante && !empty($donante['Email'])) {
            $nombrePila = trim($donante['Nombre'] ?? '');
            $nombreCompleto = trim($donante['NombreCompleto'] ?? '');
            $saludoNombre = !empty($nombrePila) ? $nombrePila : (!empty($nombreCompleto) ? $nombreCompleto : 'Donante');
            $email = $donante['Email'];
            $asunto = "¡Gracias por sumarte a transformar vidas en Oaxaca! - GesMujer";

            // Fecha en formato formal
            $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $dia = date('j');
            $mes = $meses[(int)date('n')];
            $ano = date('Y');
            $fechaCarta = "Oaxaca de Juárez, Oaxaca a {$dia} de {$mes} de {$ano}";

            // Detalle dinámico del donativo
            if ($esEspecie) {
                $descEspecie = trim($descripcionEspecie) ?: 'alimentos';
                $detalleDonativo = " (En Especie: {$descEspecie})";
            } else {
                $montoFormateado = number_format($montoDonacion, 2);
                $tipoMetodo = $tipoDonacion ?: 'Efectivo';
                $detalleDonativo = " de \${$montoFormateado} MXN ({$tipoMetodo})";
            }

            // Cuerpo exacto de la carta oficial
            $mensaje = "{$fechaCarta}\n\n"
                     . "Querido(a) {$saludoNombre}, ¡gracias por sumarte a transformar vidas en Oaxaca!\n\n"
                     . "A nombre de todas las personas que formamos parte del Grupo de Estudios sobre la Mujer Rosario Castellanos, GESMujer, quiero expresarte nuestro más sincero agradecimiento por tu generoso donativo{$detalleDonativo}.\n\n"
                     . "Acciones como la tuya hacen posible que sigamos caminando junto a mujeres, jóvenes y niñas de diversas comunidades indígenas y rurales del estado de Oaxaca. Gracias a tu valioso respaldo, podemos fortalecer nuestras acciones de acompañamiento, formación, defensa de sus derechos e impulso a su autonomía, brindándoles herramientas para construir proyectos de vida con dignidad, justicia y libres de violencia.\n\n"
                     . "En GesMujer estamos convencidas de que la transformación comunitaria se logra uniendo voluntades. Tu contribución no solo representa un apoyo económico indispensable, sino también un mensaje directo de esperanza y solidaridad para quienes más lo necesitan.\n\n"
                     . "Nos comprometemos firmemente a administrar tu aportación con total transparencia y con la dedicación que nos ha caracterizado por más de cuatro décadas de trabajo en favor de las mujeres oaxaqueñas.\n\n"
                     . "Te reiteramos nuestra gratitud y dejamos nuestras puertas abiertas para mantenerte informado sobre el impacto y los logros que tu donativo ayuda a construir día con día.\n\n"
                     . "Con aprecio y respeto,\n\n"
                     . "María del Rosario Martínez Miguel\n"
                     . "Grupo de Estudios sobre la Mujer Rosario Castellanos (GESMujer)";

            enviarCorreoInstitucional($email, $nombreCompleto ?: $saludoNombre, $asunto, $mensaje, 'Agradecimiento', $idDonante);
        }
    }

    // Registrar en auditoría
    $montoTxt = $esEspecie ? "En Especie: $descripcionEspecie" : '$' . number_format((float)$montoDonacion, 2) . " MXN ($tipoDonacion)";
    $reciboTxt = $requiereRecibo ? " | Recibo Deducible: Sí (RFC: $rfcFiscal" . ($facturacionDiferente ? ", Facturado a: $razonSocialFiscal" : "") . ")" : " | Recibo Deducible: No";
    if ($esNuevo) {
        $nuevoIdDonativo = (int)$conn->lastInsertId();
        registrarCambioAuditoria(
            $conn,
            'Donativo',
            $nuevoIdDonativo,
            'Registro',
            'Aportación Recibida',
            '$0.00 MXN',
            $montoTxt,
            "Registro de donativo asignado al Donante #$idDonante{$reciboTxt}"
        );
    } else {
        registrarCambioAuditoria(
            $conn,
            'Donativo',
            $idDonativo,
            'Modificación',
            'Modificación de Aportación',
            'Datos previos',
            $montoTxt,
            "Actualización del donativo #$idDonativo para Donante #$idDonante{$reciboTxt}"
        );
    }

    header("Location: ../donativos.php?status=saved");
    exit();

} catch (PDOException $e) {
    header("Location: ../donativos.php?status=error&msg=" . urlencode("Error al procesar donativo: " . $e->getMessage()));
    exit();
}
