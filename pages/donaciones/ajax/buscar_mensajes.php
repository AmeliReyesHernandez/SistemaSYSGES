<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../db/config.php';

try {
    $search  = isset($_GET['search']) ? trim($_GET['search']) : '';
    $estado  = isset($_GET['estado']) ? trim($_GET['estado']) : '';
    $tipo    = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
    $pagina  = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina < 1) $pagina = 1;
    $registrosPorPagina = 10;
    $offset = ($pagina - 1) * $registrosPorPagina;

    $whereConditions = [];
    $params = [];

    if ($search !== '') {
        $whereConditions[] = "(
            m.NombreDestino LIKE :s 
            OR m.EmailDestino LIKE :s 
            OR m.Asunto LIKE :s 
            OR m.Mensaje LIKE :s
            OR dn.Nombre LIKE :s
            OR dn.ApellidoPaterno LIKE :s
            OR dn.ApellidoMaterno LIKE :s
            OR dn_name.Nombre LIKE :s
            OR dn_name.ApellidoPaterno LIKE :s
            OR dn_name.ApellidoMaterno LIKE :s
        )";
        $params[':s'] = "%$search%";
    }

    if ($estado !== '') {
        $whereConditions[] = "m.Estado = :estado";
        $params[':estado'] = $estado;
    }

    if ($tipo !== '') {
        $whereConditions[] = "m.TipoMensaje = :tipo";
        $params[':tipo'] = $tipo;
    }

    $whereSQL = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    $fromJoins = "
        FROM donaciones_mensajes m
        LEFT JOIN donantes dn ON m.ID_Donante = dn.ID_Donante
        LEFT JOIN (
            SELECT ID_Donante, Nombre, ApellidoPaterno, ApellidoMaterno, Email
            FROM donantes
            GROUP BY ID_Donante
        ) dn_name ON (m.ID_Donante IS NULL AND (
            m.NombreDestino = CONCAT(dn_name.Nombre, ' ', dn_name.ApellidoPaterno, ' ', COALESCE(dn_name.ApellidoMaterno,''))
            OR m.NombreDestino = CONCAT(dn_name.Nombre, ' ', dn_name.ApellidoPaterno)
            OR m.NombreDestino = dn_name.Nombre
        ))
    ";

    // Conteo total
    $sqlCount = "SELECT COUNT(DISTINCT m.ID_Mensaje) $fromJoins $whereSQL";
    $stmtCount = $conn->prepare($sqlCount);
    foreach ($params as $k => $v) {
        $stmtCount->bindValue($k, $v);
    }
    $stmtCount->execute();
    $totalRegistros = (int)$stmtCount->fetchColumn();
    $totalPaginas = max(1, (int)ceil($totalRegistros / $registrosPorPagina));

    // Datos paginados
    $sqlList = "
        SELECT m.ID_Mensaje, m.ID_Donante, m.NombreDestino, m.EmailDestino, m.Asunto, m.Mensaje,
               m.ArchivoAdjunto, m.TipoMensaje, m.Estado, m.FechaRegistro,
               COALESCE(
                   NULLIF(TRIM(CONCAT(dn.Nombre, ' ', dn.ApellidoPaterno, ' ', COALESCE(dn.ApellidoMaterno,''))), ''),
                   NULLIF(TRIM(CONCAT(dn_name.Nombre, ' ', dn_name.ApellidoPaterno, ' ', COALESCE(dn_name.ApellidoMaterno,''))), ''),
                   m.NombreDestino
               ) AS DestinatarioCompleto
        $fromJoins
        $whereSQL
        GROUP BY m.ID_Mensaje
        ORDER BY m.ID_Mensaje DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmtList = $conn->prepare($sqlList);
    foreach ($params as $k => $v) {
        $stmtList->bindValue($k, $v);
    }
    $stmtList->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtList->execute();
    $mensajes = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'mensajes'       => $mensajes,
        'totalRegistros' => $totalRegistros,
        'totalPaginas'   => $totalPaginas,
        'pagina'         => $pagina,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
