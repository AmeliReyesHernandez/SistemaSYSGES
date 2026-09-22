<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../db/config.php';

try {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if ($q === '') {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT ID_Donante, CONCAT(Nombre, ' ', ApellidoPaterno, ' ', ApellidoMaterno) AS NombreCompleto, Email
        FROM donantes
        WHERE Nombre LIKE :q OR ApellidoPaterno LIKE :q OR ApellidoMaterno LIKE :q OR Email LIKE :q
        LIMIT 10
    ");
    $stmt->execute(['q' => "%$q%"]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

