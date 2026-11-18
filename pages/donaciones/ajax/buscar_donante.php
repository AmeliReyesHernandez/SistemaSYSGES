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
        SELECT ID_Donante, 
               Nombre,
               TRIM(CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno, ''))) AS NombreCompleto, 
               Email,
               RFC,
               COALESCE(TipoPersonaFiscal, 'Fisica') AS TipoPersonaFiscal,
               COALESCE(RazonSocialFiscal, '') AS RazonSocialFiscal,
               COALESCE(CodigoPostalFiscal, '') AS CodigoPostalFiscal,
               COALESCE(RegimenFiscal, '') AS RegimenFiscal,
               COALESCE(UsoCFDI, 'D04 - Donativos') AS UsoCFDI,
               COALESCE(EmailFiscal, '') AS EmailFiscal
        FROM donantes
        WHERE Nombre LIKE :q OR ApellidoPaterno LIKE :q OR ApellidoMaterno LIKE :q OR Email LIKE :q OR RFC LIKE :q
        LIMIT 10
    ");
    $stmt->execute(['q' => "%$q%"]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

