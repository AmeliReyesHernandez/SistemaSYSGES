<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';
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
$estatus = trim($_POST["estatus"] ?? 'Activo');
if ($estatus !== 'Activo' && $estatus !== 'Inactivo') {
    $estatus = 'Activo';
}

if ($nombre === '' || $apellidoPaterno === '' || $email === '') {
    $volver = $idDonante ? "../donante-editar.php?id=$idDonante" : "../donante-nuevo.php";
    header("Location: $volver?status=error&msg=" . urlencode("Por favor completa los campos obligatorios."));
    exit();
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
                Estatus = :estatus
            WHERE ID_Donante = :id
        ");
        $stmt->bindParam(':id', $idDonante, PDO::PARAM_INT);
    } else {
        // Crear nuevo donante
        $stmt = $conn->prepare("
            INSERT INTO donantes (Nombre, ApellidoPaterno, ApellidoMaterno, Email, Telefono, FechaNacimiento, Ocupacion, Domicilio, Ciudad, Estado, RFC, Estatus)
            VALUES (:nombre, :ap, :am, :email, :tel, :fn, :ocup, :dom, :ciudad, :edo, :rfc, :estatus)
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
    $stmt->bindParam(':estatus', $estatus);

    $stmt->execute();

    // Registrar en auditoría
    $nombreComp = trim("$nombre $apellidoPaterno $apellidoMaterno");
    if ($idDonante) {
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
        registrarCambioAuditoria(
            $conn, 
            'Donante', 
            $nuevoId, 
            'Registro', 
            'Alta de Donante', 
            'Sin registrar', 
            $nombreComp, 
            "Registro inicial de donante: Correo: $email, RFC: $rfc, Estatus: $estatus"
        );
    }

    header("Location: ../donantes.php?status=saved");
    exit();

} catch (PDOException $e) {
    header("Location: ../donantes.php?status=error&msg=" . urlencode("Error al procesar: " . $e->getMessage()));
    exit();
}

