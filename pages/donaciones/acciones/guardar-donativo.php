<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../donativos.php");
    exit();
}

$idDonativo = isset($_POST["id_donativo"]) && is_numeric($_POST["id_donativo"]) ? (int)$_POST["id_donativo"] : null;
$idDonante = isset($_POST["id_donante"]) && is_numeric($_POST["id_donante"]) ? (int)$_POST["id_donante"] : null;
$montoDonacion = isset($_POST["monto_donacion"]) ? (float)$_POST["monto_donacion"] : 0;
$tipoDonacion = trim($_POST["tipo_donacion"] ?? '');

if (!$idDonante || $montoDonacion <= 0 || $tipoDonacion === '') {
    $volver = $idDonativo ? "../donativo-editar.php?id=$idDonativo" : "../donativo-nuevo.php";
    header("Location: $volver&status=error&msg=" . urlencode("Por favor completa todos los campos requeridos con un monto válido."));
    exit();
}

try {
    if ($idDonativo) {
        $stmt = $conn->prepare("
            UPDATE donativos SET
                ID_Donante = :donante,
                MontoDonacion = :monto,
                TipoDonacion = :tipo
            WHERE ID_Donativo = :id
        ");
        $stmt->bindParam(':id', $idDonativo, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO donativos (ID_Donante, MontoDonacion, TipoDonacion)
            VALUES (:donante, :monto, :tipo)
        ");
    }

    $stmt->bindParam(':donante', $idDonante, PDO::PARAM_INT);
    $stmt->bindParam(':monto', $montoDonacion);
    $stmt->bindParam(':tipo', $tipoDonacion);

    $stmt->execute();

    header("Location: ../donativos.php?status=saved");
    exit();

} catch (PDOException $e) {
    header("Location: ../donativos.php?status=error&msg=" . urlencode("Error al procesar donativo: " . $e->getMessage()));
    exit();
}

