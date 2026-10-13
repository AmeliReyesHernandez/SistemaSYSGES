<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';

header('Content-Type: application/json; charset=utf-8');

$idDonante = isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ? (int)$_REQUEST['id'] : null;
$nuevoEstatus = isset($_REQUEST['estatus']) ? trim($_REQUEST['estatus']) : '';

if (!$idDonante) {
    echo json_encode(['success' => false, 'error' => 'ID de donante no proporcionado.']);
    exit();
}

try {
    // Si no se especifica el nuevo estatus, alternar el actual
    if ($nuevoEstatus !== 'Activo' && $nuevoEstatus !== 'Inactivo') {
        $stmtCur = $conn->prepare("SELECT Estatus FROM donantes WHERE ID_Donante = :id");
        $stmtCur->execute([':id' => $idDonante]);
        $current = $stmtCur->fetchColumn() ?: 'Activo';
        $nuevoEstatus = ($current === 'Activo') ? 'Inactivo' : 'Activo';
    }

    $stmt = $conn->prepare("UPDATE donantes SET Estatus = :estatus WHERE ID_Donante = :id");
    $stmt->execute([
        ':estatus' => $nuevoEstatus,
        ':id' => $idDonante
    ]);

    // Si es petición GET estándar, redirigir
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['ajax'])) {
        header("Location: ../donantes.php?status=status_updated");
        exit();
    }

    echo json_encode(['success' => true, 'nuevoEstatus' => $nuevoEstatus, 'id' => $idDonante]);
    exit();

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}
