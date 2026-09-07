<?php
require_once __DIR__ . '/../pages/seccion.php';

require_once __DIR__ . '/../db/config.php'; // Ajusta la ruta si es diferente

// Verificar que venga el ID del donativo
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de donativo inválido.");
}

$idDonativo = (int) $_GET['id'];

try {
    // Conexión PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Antes de eliminar, verificar si el donativo existe
    $check = $conn->prepare("SELECT * FROM donativos WHERE ID_Donativo = :id");
    $check->bindParam(':id', $idDonativo, PDO::PARAM_INT);
    $check->execute();

    if ($check->rowCount() === 0) {
        die("El donativo no existe.");
    }

    // Intentar eliminar (puede fallar si hay FK relacionadas)
    $stmt = $conn->prepare("DELETE FROM donativos WHERE ID_Donativo = :id");
    $stmt->bindParam(':id', $idDonativo, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Redirigir con mensaje de éxito
      
        header("Location: ./ver-donaciones.php?msg=success");
        exit();
    } else {
        
        header("Location: ./ver-donaciones.php?msg=error");
        exit();
    }

} catch (PDOException $e) {
    echo  $e;
}
