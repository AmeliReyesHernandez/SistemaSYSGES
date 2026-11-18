<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';
require_once __DIR__ . '/servicio-correo.php';
require_once __DIR__ . '/auditoria-servicio.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../donantes.php");
    exit();
}

$idDonante = isset($_POST["id_donante"]) && is_numeric($_POST["id_donante"]) ? (int)$_POST["id_donante"] : null;
$nombre = trim($_POST["nombre"] ?? '');
$apellidoPaterno = trim($_POST["apellido_paterno"] ?? '');
$apellidoMaterno = trim($_POST["apellido_materno"] ?? '');
$email = trim($_POST["email"] ?? '');
$telefono = trim($_POST["telefono"] ?? '');
$fechaNacimiento = !empty($_POST["fecha_nacimiento"]) ? trim($_POST["fecha_nacimiento"]) : null;
$ocupacion = trim($_POST["ocupacion"] ?? '');
$domicilio = trim($_POST["domicilio"] ?? '');
$ciudad = trim($_POST["ciudad"] ?? '');
$estado = trim($_POST["estado"] ?? '');
$rfc = strtoupper(trim($_POST["rfc"] ?? ''));
$tipoPersonaFiscal = trim($_POST["tipo_persona_fiscal"] ?? 'Fisica');
if ($tipoPersonaFiscal !== 'Moral') {
    $tipoPersonaFiscal = 'Fisica';
}
$razonSocialFiscal = trim($_POST["razon_social_fiscal"] ?? '');
$codigoPostalFiscal = trim($_POST["codigo_postal_fiscal"] ?? '');
$regimenFiscal = trim($_POST["regimen_fiscal"] ?? '');
$usoCFDI = trim($_POST["uso_cfdi"] ?? 'D04 - Donativos');
$emailFiscal = trim($_POST["email_fiscal"] ?? '');
$estatus = trim($_POST["estatus"] ?? 'Activo');
if ($estatus !== 'Activo' && $estatus !== 'Inactivo') {
    $estatus = 'Activo';
}

// Datos de la aportación inicial (para nuevos donantes)
$tipoDonacion = trim($_POST["tipo_donacion"] ?? 'Efectivo');
$montoDonacion = isset($_POST["monto_donacion"]) ? (float)$_POST["monto_donacion"] : 0;
$descripcionEspecie = trim($_POST["descripcion_especie"] ?? '');
$esEspecie = ($tipoDonacion === 'En Especie' || $tipoDonacion === 'Bienes / Mobiliario');

if ($nombre === '' || $apellidoPaterno === '' || $email === '') {
    $volver = $idDonante ? "../donante-editar.php?id=$idDonante" : "../donante-nuevo.php";
    header("Location: $volver?status=error&msg=" . urlencode("Por favor completa los campos obligatorios del donante."));
    exit();
}

// Validación de la aportación inicial si es un nuevo registro
if (!$idDonante) {
    if ($tipoDonacion === '') {
        header("Location: ../donante-nuevo.php?status=error&msg=" . urlencode("Por favor selecciona el método o tipo de la aportación inicial."));
        exit();
    }
    if ($esEspecie) {
        $montoDonacion = 0;
        if ($descripcionEspecie === '') {
            header("Location: ../donante-nuevo.php?status=error&msg=" . urlencode("Por favor ingresa la descripción de los bienes o especie donados."));
            exit();
        }
    } else {
        $descripcionEspecie = null;
        if ($montoDonacion <= 0) {
            header("Location: ../donante-nuevo.php?status=error&msg=" . urlencode("Por favor ingresa un monto válido mayor a $0.00 para la aportación inicial."));
            exit();
        }
    }
}

// Protección contra registros duplicados
$permitirDuplicado = isset($_POST['permitir_duplicado']) && $_POST['permitir_duplicado'] === '1';

if (!$idDonante && !$permitirDuplicado) {
    try {
        $stmtDup = $conn->prepare("
            SELECT ID_Donante, CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno,'')) AS NombreCompleto, Email, Telefono
            FROM donantes
            WHERE LOWER(TRIM(Email)) = LOWER(:email)
               OR (LOWER(TRIM(Nombre)) = LOWER(:nom) AND LOWER(TRIM(ApellidoPaterno)) = LOWER(:ap))
            LIMIT 1
        ");
        $stmtDup->execute([
            ':email' => $email,
            ':nom'   => $nombre,
            ':ap'    => $apellidoPaterno
        ]);
        $dupFound = $stmtDup->fetch(PDO::FETCH_ASSOC);

        if ($dupFound) {
            $msg = "Atención: Ya existe un donante registrado con información similar (" . trim($dupFound['NombreCompleto']) . " - " . $dupFound['Email'] . "). Si estás segura de que es una persona diferente, confirma para guardar.";
            header("Location: ../donante-nuevo.php?status=duplicado&msg=" . urlencode($msg) . 
                   "&nombre=" . urlencode($nombre) . 
                   "&apellido_paterno=" . urlencode($apellidoPaterno) . 
                   "&apellido_materno=" . urlencode($apellidoMaterno) . 
                   "&email=" . urlencode($email) . 
                   "&telefono=" . urlencode($telefono) . 
                   "&rfc=" . urlencode($rfc) . 
                   "&id_duplicado=" . (int)$dupFound['ID_Donante']);
            exit();
        }
    } catch (Exception $e) {
        // En caso de error en la verificación, continuar
    }
}

try {
    $conn->beginTransaction();

    if ($idDonante) {
        // Actualizar donante existente
        $stmt = $conn->prepare("
            UPDATE donantes SET
                Nombre = :nombre,
                ApellidoPaterno = :ap,
                ApellidoMaterno = :am,
                Email = :email,
                Telefono = :tel,
                FechaNacimiento = :fn,
                Ocupacion = :ocup,
                Domicilio = :dom,
                Ciudad = :ciudad,
                Estado = :edo,
                RFC = :rfc,
                TipoPersonaFiscal = :tpf,
                RazonSocialFiscal = :rsf,
                CodigoPostalFiscal = :cpf,
                RegimenFiscal = :rf,
                UsoCFDI = :cfdi,
                EmailFiscal = :ef,
                Estatus = :estatus
            WHERE ID_Donante = :id
        ");
        $stmt->bindParam(':id', $idDonante, PDO::PARAM_INT);
    } else {
        // Crear nuevo donante
        $stmt = $conn->prepare("
            INSERT INTO donantes (Nombre, ApellidoPaterno, ApellidoMaterno, Email, Telefono, FechaNacimiento, Ocupacion, Domicilio, Ciudad, Estado, RFC, TipoPersonaFiscal, RazonSocialFiscal, CodigoPostalFiscal, RegimenFiscal, UsoCFDI, EmailFiscal, Estatus)
            VALUES (:nombre, :ap, :am, :email, :tel, :fn, :ocup, :dom, :ciudad, :edo, :rfc, :tpf, :rsf, :cpf, :rf, :cfdi, :ef, :estatus)
        ");
    }

    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':ap', $apellidoPaterno);
    $stmt->bindParam(':am', $apellidoMaterno);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':tel', $telefono);
    $stmt->bindParam(':fn', $fechaNacimiento);
    $stmt->bindParam(':ocup', $ocupacion);
    $stmt->bindParam(':dom', $domicilio);
    $stmt->bindParam(':ciudad', $ciudad);
    $stmt->bindParam(':edo', $estado);
    $stmt->bindParam(':rfc', $rfc);
    $stmt->bindParam(':tpf', $tipoPersonaFiscal);
    $stmt->bindParam(':rsf', $razonSocialFiscal);
    $stmt->bindParam(':cpf', $codigoPostalFiscal);
    $stmt->bindParam(':rf', $regimenFiscal);
    $stmt->bindParam(':cfdi', $usoCFDI);
    $stmt->bindParam(':ef', $emailFiscal);
    $stmt->bindParam(':estatus', $estatus);

    $stmt->execute();

    $nombreComp = trim("$nombre $apellidoPaterno $apellidoMaterno");

    if ($idDonante) {
        // Auditoría de actualización
        registrarCambioAuditoria(
            $conn, 
            'Donante', 
            $idDonante, 
            'Modificación', 
            'Actualización de Expediente', 
            'Datos anteriores', 
            $nombreComp, 
            "Actualización: Correo: $email, Tel: $telefono, Estatus: $estatus"
        );
    } else {
        $nuevoId = (int)$conn->lastInsertId();

        // Registrar aportación inicial en la tabla donativos
        $requiereRecibo = (!empty($rfc) || !empty($razonSocialFiscal)) ? 1 : 0;
        $emailFinalFiscal = !empty($emailFiscal) ? $emailFiscal : $email;
        $montoFinal = $esEspecie ? 0 : $montoDonacion;
        $descFinal = $esEspecie ? $descripcionEspecie : null;

        $stmtDonativo = $conn->prepare("
            INSERT INTO donativos (
                ID_Donante, MontoDonacion, TipoDonacion, DescripcionEspecie, FechaDonacion,
                RequiereRecibo, FacturacionDiferente, TipoPersonaFiscal, RFC_Fiscal, RazonSocial_Fiscal,
                CP_Fiscal, Regimen_Fiscal, UsoCFDI_Fiscal, Email_Fiscal
            )
            VALUES (
                :donante, :monto, :tipo, :descripcion, NOW(),
                :requiere_recibo, 0, :tipo_persona_fiscal, :rfc_fiscal, :razon_social_fiscal,
                :cp_fiscal, :regimen_fiscal, :uso_cfdi_fiscal, :email_fiscal
            )
        ");
        $stmtDonativo->bindParam(':donante', $nuevoId, PDO::PARAM_INT);
        $stmtDonativo->bindParam(':monto', $montoFinal);
        $stmtDonativo->bindParam(':tipo', $tipoDonacion);
        $stmtDonativo->bindParam(':descripcion', $descFinal);
        $stmtDonativo->bindParam(':requiere_recibo', $requiereRecibo, PDO::PARAM_INT);
        $stmtDonativo->bindParam(':tipo_persona_fiscal', $tipoPersonaFiscal);
        $stmtDonativo->bindParam(':rfc_fiscal', $rfc);
        $stmtDonativo->bindParam(':razon_social_fiscal', $razonSocialFiscal);
        $stmtDonativo->bindParam(':cp_fiscal', $codigoPostalFiscal);
        $stmtDonativo->bindParam(':regimen_fiscal', $regimenFiscal);
        $stmtDonativo->bindParam(':uso_cfdi_fiscal', $usoCFDI);
        $stmtDonativo->bindParam(':email_fiscal', $emailFinalFiscal);
        $stmtDonativo->execute();

        $nuevoIdDonativo = (int)$conn->lastInsertId();

        // Auditoría de registro de donante
        registrarCambioAuditoria(
            $conn, 
            'Donante', 
            $nuevoId, 
            'Registro', 
            'Alta de Donante', 
            '', 
            $nombreComp, 
            "Registro inicial de donante: Correo: $email, RFC: $rfc, Estatus: $estatus"
        );

        // Auditoría de registro de donativo inicial
        $montoTxt = $esEspecie ? "En Especie: $descripcionEspecie" : '$' . number_format((float)$montoDonacion, 2) . " MXN ($tipoDonacion)";
        $reciboTxt = $requiereRecibo ? " | Recibo Deducible: Sí (RFC: $rfc)" : " | Recibo Deducible: No";
        registrarCambioAuditoria(
            $conn,
            'Donativo',
            $nuevoIdDonativo,
            'Registro',
            'Aportación Recibida',
            '',
            $montoTxt,
            "Aportación inicial registrada al dar de alta al Donante #$nuevoId{$reciboTxt}"
        );

        // Envío de correo automático de agradecimiento institucional
        if (!empty($email)) {
            $saludoNombre = !empty($nombre) ? $nombre : $nombreComp;
            $asunto = "¡Gracias por sumarte a transformar vidas en Oaxaca! - GesMujer";

            $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $dia = date('j');
            $mes = $meses[(int)date('n')];
            $ano = date('Y');
            $fechaCarta = "Oaxaca de Juárez, Oaxaca a {$dia} de {$mes} de {$ano}";

            if ($esEspecie) {
                $descEsp = trim($descripcionEspecie) ?: 'alimentos e insumos';
                $detalleDonativo = " (En Especie: {$descEsp})";
            } else {
                $montoFormateado = number_format($montoDonacion, 2);
                $tipoMetodo = $tipoDonacion ?: 'Efectivo';
                $detalleDonativo = " de \${$montoFormateado} MXN ({$tipoMetodo})";
            }

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

            enviarCorreoInstitucional($email, $nombreComp ?: $saludoNombre, $asunto, $mensaje, 'Agradecimiento', $nuevoId);
        }
    }

    $conn->commit();

    header("Location: ../donantes.php?status=saved");
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $volver = $idDonante ? "../donante-editar.php?id=$idDonante" : "../donante-nuevo.php";
    header("Location: $volver?status=error&msg=" . urlencode("Error al procesar: " . $e->getMessage()));
    exit();
}


