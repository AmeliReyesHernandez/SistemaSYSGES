<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../db/config.php';

try {
    $search  = isset($_GET['search'])  ? trim($_GET['search'])  : '';
    $estatus = isset($_GET['estatus']) ? trim($_GET['estatus']) : '';
    $pagina  = isset($_GET['pagina'])  && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina < 1) $pagina = 1;
    $registrosPorPagina = 10;
    $offset = ($pagina - 1) * $registrosPorPagina;

    $whereConditions = [];
    $params = [];

    if ($search !== '') {
        $whereConditions[] = "(d.Nombre LIKE :s OR d.ApellidoPaterno LIKE :s OR d.ApellidoMaterno LIKE :s OR d.Email LIKE :s OR d.Telefono LIKE :s OR d.RFC LIKE :s OR d.Ocupacion LIKE :s)";
        $params[':s'] = "%$search%";
    }
    if ($estatus !== '') {
        $whereConditions[] = "d.Estatus = :estatus";
        $params[':estatus'] = $estatus;
    }

    $whereSQL = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    // Conteo
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM donantes d" . $whereSQL);
    foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
    $stmtCount->execute();
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = max(1, (int)ceil($totalRegistros / $registrosPorPagina));

    // Datos
    $sql = "
        SELECT d.ID_Donante, d.Nombre, d.ApellidoPaterno, d.ApellidoMaterno, d.Email, d.Telefono,
               d.FechaNacimiento, d.Ocupacion, d.RFC,
               COALESCE(d.Estatus, 'Activo') AS Estatus,
               COALESCE(SUM(dn.MontoDonacion), 0) AS TotalAportado,
               COUNT(dn.ID_Donativo) AS NumeroDonaciones,
               MAX(dn.FechaDonacion) AS UltimaDonacion
        FROM donantes d
        LEFT JOIN donativos dn ON d.ID_Donante = dn.ID_Donante
        $whereSQL
        GROUP BY d.ID_Donante
        ORDER BY d.ID_Donante DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $donantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'         => true,
        'donantes'        => $donantes,
        'totalRegistros'  => $totalRegistros,
        'totalPaginas'    => $totalPaginas,
        'pagina'          => $pagina,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
