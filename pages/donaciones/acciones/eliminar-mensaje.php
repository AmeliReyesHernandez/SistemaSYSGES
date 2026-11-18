<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$id = isset($_POST['id_mensaje']) && is_numeric($_POST['id_mensaje']) ? (int)$_POST['id_mensaje'] : 0;

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID de mensaje inválido']);
    exit();
}

try {
    // Solo se permite eliminar borradores o fallidos, nunca enviados
    $stmt = $conn->prepare("SELECT Estado FROM donaciones_mensajes WHERE ID_Mensaje = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Mensaje no encontrado']);
        exit();
    }

    if ($row['Estado'] === 'Enviado') {
        echo json_encode(['success' => false, 'error' => 'No se puede eliminar un mensaje ya enviado']);
        exit();
    }

    $stmtDel = $conn->prepare("DELETE FROM donaciones_mensajes WHERE ID_Mensaje = :id AND Estado != 'Enviado'");
    $stmtDel->bindParam(':id', $id, PDO::PARAM_INT);
    $stmtDel->execute();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'mensaje' => 'Mensaje eliminado correctamente']);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error en base de datos: ' . $e->getMessage()]);
}
