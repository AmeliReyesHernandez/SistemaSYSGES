<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';
require_once __DIR__ . '/auditoria-servicio.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../donativos.php?status=error&msg=" . urlencode("ID de donación no válido."));
    exit();
}

$idDonativo = (int)$_GET['id'];

try {
    // Obtener datos antes de eliminar para el registro de auditoría
    $stmtPrev = $conn->prepare("SELECT MontoDonacion, TipoDonacion, ID_Donante FROM donativos WHERE ID_Donativo = :id");
    $stmtPrev->execute([':id' => $idDonativo]);
    $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("DELETE FROM donativos WHERE ID_Donativo = :id");
    $stmt->bindParam(':id', $idDonativo, PDO::PARAM_INT);
    $stmt->execute();

    if ($prev) {
        $montoVal = '$' . number_format((float)$prev['MontoDonacion'], 2) . " MXN ({$prev['TipoDonacion']})";
        registrarCambioAuditoria(
            $conn,
            'Donativo',
            $idDonativo,
            'Eliminación',
            'Registro de Donativo Eliminado',
            $montoVal,
            'Eliminado permanentemente',
            "Eliminación de la aportación folio #$idDonativo correspondiente a donante #{$prev['ID_Donante']}"
        );
    }

    header("Location: ../donativos.php?status=deleted");
    exit();

} catch (PDOException $e) {
    header("Location: ../donativos.php?status=error&msg=" . urlencode("No se pudo eliminar el donativo: " . $e->getMessage()));
    exit();
}

