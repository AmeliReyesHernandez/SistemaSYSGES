<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../donativos.php?status=error&msg=" . urlencode("ID de donación no válido."));
    exit();
}

$idDonativo = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("DELETE FROM donativos WHERE ID_Donativo = :id");
    $stmt->bindParam(':id', $idDonativo, PDO::PARAM_INT);
    $stmt->execute();

    header("Location: ../donativos.php?status=deleted");
    exit();

} catch (PDOException $e) {
    header("Location: ../donativos.php?status=error&msg=" . urlencode("No se pudo eliminar el donativo: " . $e->getMessage()));
    exit();
}

