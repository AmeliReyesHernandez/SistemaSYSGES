<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../db/config.php';

try {
    $search  = isset($_GET['search']) ? trim($_GET['search']) : '';
    $entidad = isset($_GET['entidad']) ? trim($_GET['entidad']) : '';
    $accion  = isset($_GET['accion']) ? trim($_GET['accion']) : '';
    $pagina  = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina < 1) $pagina = 1;
    $registrosPorPagina = 10;
    $offset = ($pagina - 1) * $registrosPorPagina;

    $whereConditions = [];
    $params = [];

    if ($search !== '') {
        $whereConditions[] = "(
            h.ID_Historial LIKE :s
            OR h.ID_Registro LIKE :s
            OR h.Campo LIKE :s 
            OR h.ValorAnterior LIKE :s 
            OR h.ValorNuevo LIKE :s 
            OR h.NombreUsuario LIKE :s 
            OR p.Nombre LIKE :s
            OR h.Detalles LIKE :s
            OR h.Entidad LIKE :s
        )";
        $params[':s'] = "%$search%";
    }

    if ($entidad !== '') {
        $whereConditions[] = "h.Entidad = :entidad";
        $params[':entidad'] = $entidad;
    }

    if ($accion !== '') {
        $whereConditions[] = "h.Accion = :accion";
        $params[':accion'] = $accion;
    }

    $whereSQL = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    // Conteo total
    $sqlCount = "
        SELECT COUNT(*) 
        FROM donaciones_historial_cambios h 
        LEFT JOIN personal p ON h.ID_Usuario = p.ID_Personal
        $whereSQL
    ";
    $stmtCount = $conn->prepare($sqlCount);
    foreach ($params as $k => $v) {
        $stmtCount->bindValue($k, $v);
    }
    $stmtCount->execute();
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = max(1, (int)ceil($totalRegistros / $registrosPorPagina));

    // Datos paginados
    $sqlList = "
        SELECT h.ID_Historial, h.FechaHora, h.ID_Usuario, 
               COALESCE(NULLIF(p.Nombre, ''), NULLIF(h.NombreUsuario, ''), 'SOLEDAD') AS NombreUsuario,
               h.Entidad, h.ID_Registro, h.Accion, h.Campo, h.ValorAnterior, h.ValorNuevo, 
               h.CodigoRastreo, h.Detalles
        FROM donaciones_historial_cambios h
        LEFT JOIN personal p ON h.ID_Usuario = p.ID_Personal
        $whereSQL
        ORDER BY h.ID_Historial DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmtList = $conn->prepare($sqlList);
    foreach ($params as $k => $v) {
        $stmtList->bindValue($k, $v);
    }
    $stmtList->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtList->execute();
    $historial = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'historial'      => $historial,
        'totalRegistros' => $totalRegistros,
        'totalPaginas'   => $totalPaginas,
        'pagina'         => $pagina,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
