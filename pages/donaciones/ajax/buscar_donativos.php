<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../db/config.php';

try {
    $search  = isset($_GET['search']) ? trim($_GET['search']) : '';
    $metodo  = isset($_GET['metodo']) ? trim($_GET['metodo']) : '';
    $pagina  = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina < 1) $pagina = 1;
    $registrosPorPagina = 10;
    $offset = ($pagina - 1) * $registrosPorPagina;

    $whereConditions = [];
    $params = [];

    if ($search !== '') {
        $whereConditions[] = "(dn.Nombre LIKE :s OR dn.ApellidoPaterno LIKE :s OR dn.ApellidoMaterno LIKE :s OR dn.Email LIKE :s)";
        $params[':s'] = "%$search%";
    }
    if ($metodo !== '') {
        $whereConditions[] = "d.TipoDonacion = :metodo";
        $params[':metodo'] = $metodo;
    }

    $whereSQL = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    // Conteo
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM donativos d LEFT JOIN donantes dn ON d.ID_Donante = dn.ID_Donante" . $whereSQL);
    foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
    $stmtCount->execute();
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = max(1, (int)ceil($totalRegistros / $registrosPorPagina));

    // Datos
    $sql = "
        SELECT d.ID_Donativo, d.MontoDonacion, d.TipoDonacion, d.DescripcionEspecie, d.FechaDonacion,
               CONCAT(dn.Nombre, ' ', dn.ApellidoPaterno, ' ', COALESCE(dn.ApellidoMaterno,'')) AS Donante,
               dn.Email
        FROM donativos d
        LEFT JOIN donantes dn ON d.ID_Donante = dn.ID_Donante
        $whereSQL
        ORDER BY d.ID_Donativo DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $donativos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'donativos'      => $donativos,
        'totalRegistros' => $totalRegistros,
        'totalPaginas'   => $totalPaginas,
        'pagina'         => $pagina,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
