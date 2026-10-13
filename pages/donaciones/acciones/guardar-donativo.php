<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';
require_once __DIR__ . '/servicio-correo.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../donativos.php");
    exit();
}

$idDonativo = isset($_POST["id_donativo"]) && is_numeric($_POST["id_donativo"]) ? (int)$_POST["id_donativo"] : null;
$idDonante = isset($_POST["id_donante"]) && is_numeric($_POST["id_donante"]) ? (int)$_POST["id_donante"] : null;
$tipoDonacion = trim($_POST["tipo_donacion"] ?? '');
$montoDonacion = isset($_POST["monto_donacion"]) ? (float)$_POST["monto_donacion"] : 0;
$descripcionEspecie = trim($_POST["descripcion_especie"] ?? '');

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

    if ($idDonativo) {
        $stmt = $conn->prepare("
            UPDATE donativos SET
                ID_Donante = :donante,
                MontoDonacion = :monto,
                TipoDonacion = :tipo,
                DescripcionEspecie = :descripcion
            WHERE ID_Donativo = :id
        ");
        $stmt->bindParam(':id', $idDonativo, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO donativos (ID_Donante, MontoDonacion, TipoDonacion, DescripcionEspecie, FechaDonacion)
            VALUES (:donante, :monto, :tipo, :descripcion, NOW())
        ");
    }

    $stmt->bindParam(':donante', $idDonante, PDO::PARAM_INT);
    $stmt->bindParam(':monto', $montoDonacion);
    $stmt->bindParam(':tipo', $tipoDonacion);
    $stmt->bindParam(':descripcion', $descripcionEspecie);

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
                     . "A nombre de todas las personas que formamos parte del Grupo de Estudios sobre la Mujer Rosario Castellanos, GesMujer, quiero expresarte nuestro más sincero agradecimiento por tu generoso donativo{$detalleDonativo}.\n\n"
                     . "Acciones como la tuya hacen posible que sigamos caminando junto a mujeres, jóvenes y niñas de diversas comunidades indígenas y rurales del estado de Oaxaca. Gracias a tu valioso respaldo, podemos fortalecer nuestras acciones de acompañamiento, formación, defensa de sus derechos e impulso a su autonomía, brindándoles herramientas para construir proyectos de vida con dignidad, justicia y libres de violencia.\n\n"
                     . "En GesMujer estamos convencidas de que la transformación comunitaria se logra uniendo voluntades. Tu contribución no solo representa un apoyo económico indispensable, sino también un mensaje directo de esperanza y solidaridad para quienes más lo necesitan.\n\n"
                     . "Nos comprometemos firmemente a administrar tu aportación con total transparencia y con la dedicación que nos ha caracterizado por más de cuatro décadas de trabajo en favor de las mujeres oaxaqueñas.\n\n"
                     . "Te reiteramos nuestra gratitud y dejamos nuestras puertas abiertas para mantenerte informado sobre el impacto y los logros que tu donativo ayuda a construir día con día.\n\n"
                     . "Con aprecio y respeto,\n\n"
                     . "María del Rosario Martínez Miguel\n"
                     . "Grupo de Estudios sobre la Mujer Rosario Castellanos (GesMujer)";

            enviarCorreoInstitucional($email, $nombreCompleto ?: $saludoNombre, $asunto, $mensaje, 'Agradecimiento', $idDonante);
        }
    }

    header("Location: ../donativos.php?status=saved");
    exit();

} catch (PDOException $e) {
    header("Location: ../donativos.php?status=error&msg=" . urlencode("Error al procesar donativo: " . $e->getMessage()));
    exit();
}
