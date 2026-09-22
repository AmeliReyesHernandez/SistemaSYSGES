<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';

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

if ($nombre === '' || $apellidoPaterno === '' || $email === '') {
    $volver = $idDonante ? "../donante-editar.php?id=$idDonante" : "../donante-nuevo.php";
    header("Location: $volver&status=error&msg=" . urlencode("Por favor completa los campos obligatorios."));
    exit();
}

try {
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
                RFC = :rfc
            WHERE ID_Donante = :id
        ");
        $stmt->bindParam(':id', $idDonante, PDO::PARAM_INT);
    } else {
        // Crear nuevo donante
        $stmt = $conn->prepare("
            INSERT INTO donantes (Nombre, ApellidoPaterno, ApellidoMaterno, Email, Telefono, FechaNacimiento, Ocupacion, Domicilio, Ciudad, Estado, RFC)
            VALUES (:nombre, :ap, :am, :email, :tel, :fn, :ocup, :dom, :ciudad, :edo, :rfc)
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

    $stmt->execute();

    header("Location: ../donantes.php?status=saved");
    exit();

} catch (PDOException $e) {
    header("Location: ../donantes.php?status=error&msg=" . urlencode("Error al procesar: " . $e->getMessage()));
    exit();
}

