<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/../../../db/config.php';

header('Content-Type: application/json; charset=utf-8');

$idDonante = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

if (!$idDonante) {
    echo json_encode(['success' => false, 'error' => 'ID de donante no válido.']);
    exit();
}

try {
    // 1. Datos completos del donante
    $stmtDonante = $conn->prepare("
        SELECT d.*, 
               CONCAT(d.Nombre, ' ', d.ApellidoPaterno, ' ', COALESCE(d.ApellidoMaterno, '')) AS NombreCompleto,
               COALESCE(SUM(dn.MontoDonacion), 0) AS TotalAportado,
               COUNT(dn.ID_Donativo) AS NumeroDonaciones,
               COALESCE(AVG(dn.MontoDonacion), 0) AS PromedioDonacion,
               MAX(dn.FechaDonacion) AS UltimaDonacion
        FROM donantes d
        LEFT JOIN donativos dn ON d.ID_Donante = dn.ID_Donante
        WHERE d.ID_Donante = :id
        GROUP BY d.ID_Donante
    ");
    $stmtDonante->execute([':id' => $idDonante]);
    $donante = $stmtDonante->fetch(PDO::FETCH_ASSOC);

    if (!$donante) {
        echo json_encode(['success' => false, 'error' => 'Donante no encontrado.']);
        exit();
    }

    // 2. Historial completo de donativos del donante
    $stmtDonativos = $conn->prepare("
        SELECT ID_Donativo, MontoDonacion, TipoDonacion, DescripcionEspecie, FechaDonacion
        FROM donativos
        WHERE ID_Donante = :id
        ORDER BY ID_Donativo DESC
    ");
    $stmtDonativos->execute([':id' => $idDonante]);
    $donativos = $stmtDonativos->fetchAll(PDO::FETCH_ASSOC);

    // Determinar si por tiempo (más de 12-18 meses sin donar) o estatus se considera inactivo
    $estatusActual = $donante['Estatus'] ?? 'Activo';
    $mesesSinDonar = null;
    if (!empty($donante['UltimaDonacion'])) {
        $fechaUltima = new DateTime($donante['UltimaDonacion']);
        $hoy = new DateTime();
        $intervalo = $hoy->diff($fechaUltima);
        $mesesSinDonar = ($intervalo->y * 12) + $intervalo->m;
    }

    echo json_encode([
        'success' => true,
        'donante' => $donante,
        'donativos' => $donativos,
        'mesesSinDonar' => $mesesSinDonar
    ]);
    exit();

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}
