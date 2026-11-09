<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';
require_once __DIR__ . '/auditoria-servicio.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../donantes.php?status=error&msg=" . urlencode("ID de donante no válido."));
    exit();
}

$idDonante = (int)$_GET['id'];

try {
    // Obtener datos antes de eliminar para auditoría
    $stmtPrev = $conn->prepare("SELECT CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno,'')) AS NombreCompleto, Email FROM donantes WHERE ID_Donante = :id");
    $stmtPrev->execute([':id' => $idDonante]);
    $prevDonante = $stmtPrev->fetch(PDO::FETCH_ASSOC);

    // Verificar donativos asociados
    $checkDonativos = $conn->prepare("SELECT COUNT(*) FROM donativos WHERE ID_Donante = :id");
    $checkDonativos->bindParam(':id', $idDonante, PDO::PARAM_INT);
    $checkDonativos->execute();
    $tieneDonativos = (int)$checkDonativos->fetchColumn();

    if ($tieneDonativos > 0) {
        $delDonativos = $conn->prepare("DELETE FROM donativos WHERE ID_Donante = :id");
        $delDonativos->bindParam(':id', $idDonante, PDO::PARAM_INT);
        $delDonativos->execute();
    }

    $stmt = $conn->prepare("DELETE FROM donantes WHERE ID_Donante = :id");
    $stmt->bindParam(':id', $idDonante, PDO::PARAM_INT);
    $stmt->execute();

    if ($prevDonante) {
        registrarCambioAuditoria(
            $conn,
            'Donante',
            $idDonante,
            'Eliminación',
            'Expediente de Donante Eliminado',
            $prevDonante['NombreCompleto'] . " ({$prevDonante['Email']})",
            'Eliminado permanentemente',
            "Eliminación del donante #$idDonante junto con $tieneDonativos donaciones asociadas"
        );
    }

    header("Location: ../donantes.php?status=deleted");
    exit();

} catch (PDOException $e) {
    header("Location: ../donantes.php?status=error&msg=" . urlencode("No se pudo eliminar: " . $e->getMessage()));
    exit();
}

