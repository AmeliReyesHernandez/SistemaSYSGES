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

    // Si es un donativo nuevo, enviar correo automático de agradecimiento al donante
    if ($esNuevo) {
        $stmtDonante = $conn->prepare("
            SELECT CONCAT(Nombre, ' ', ApellidoPaterno) AS NombreCompleto, Email
            FROM donantes
            WHERE ID_Donante = :id
        ");
        $stmtDonante->execute([':id' => $idDonante]);
        $donante = $stmtDonante->fetch(PDO::FETCH_ASSOC);

        if ($donante && !empty($donante['Email'])) {
            $nombre = $donante['NombreCompleto'];
            $email = $donante['Email'];
            $asunto = "¡Gracias por su valioso donativo - GesMujer Oaxaca!";

            if ($esEspecie) {
                $detalleText = "en especie ({$tipoDonacion}: {$descripcionEspecie})";
            } else {
                $montoFormateado = number_format($montoDonacion, 2);
                $detalleText = "de \${$montoFormateado} MXN ({$tipoDonacion})";
            }

            $mensaje = "Estimado(a) {$nombre},\n\nEn nombre de todo el equipo de GesMujer Oaxaca, queremos expresarle nuestro más sincero agradecimiento por su reciente donativo {$detalleText}.\n\nSu generosidad nos permite continuar brindando atención integral y esperanza a más mujeres en nuestro estado.\n\n¡Gracias por ser parte de este cambio!";

            enviarCorreoInstitucional($email, $nombre, $asunto, $mensaje, 'Agradecimiento', $idDonante);
        }
    }

    header("Location: ../donativos.php?status=saved");
    exit();

} catch (PDOException $e) {
    header("Location: ../donativos.php?status=error&msg=" . urlencode("Error al procesar donativo: " . $e->getMessage()));
    exit();
}
