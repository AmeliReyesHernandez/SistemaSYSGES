<?php
require_once __DIR__ . '/../../../db/config.php';

/**
 * Registra un evento en la bitácora de auditoría / historial de cambios estilo Salesforce
 *
 * @param PDO    $conn
 * @param string $entidad       'Donante' | 'Donativo' | 'Mensaje' | 'Configuracion'
 * @param int    $idRegistro    ID del registro afectado
 * @param string $accion        'Registro' | 'Modificación' | 'Cambio de Estatus' | 'Eliminación' | 'Envío'
 * @param string $campo         Nombre del campo o descripción del cambio
 * @param string|null $valorAnterior
 * @param string|null $valorNuevo
 * @param string|null $detalles
 * @return string Código de rastreo generado
 */
function registrarCambioAuditoria($conn, $entidad, $idRegistro, $accion, $campo, $valorAnterior = null, $valorNuevo = null, $detalles = null) {
    if (!$conn) return false;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Determinar usuario autenticado actual de SYSGES
    $idUsuario = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
    $nombreUsuario = !empty($_SESSION['user_name']) ? trim($_SESSION['user_name']) : '';

    if (empty($nombreUsuario) && $idUsuario) {
        try {
            $stmtUser = $conn->prepare("SELECT Nombre FROM personal WHERE ID_Personal = :id LIMIT 1");
            $stmtUser->execute([':id' => $idUsuario]);
            $nom = trim($stmtUser->fetchColumn() ?: '');
            if (!empty($nom)) {
                $nombreUsuario = $nom;
                $_SESSION['user_name'] = $nom;
            }
        } catch (Exception $e) {
            // fallback
        }
    }

    if (empty($nombreUsuario)) {
        $nombreUsuario = 'SOLEDAD';
    }

    // Generar código UUID de rastreo estilo Salesforce (ej: c31ada53-a2c5-47ce-b2b0-02186b883d3d)
    $codigoRastreo = sprintf(
        '%08s-%04s-%04s-%04s-%12s',
        bin2hex(random_bytes(4)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(6))
    );

    try {
        $stmt = $conn->prepare("
            INSERT INTO donaciones_historial_cambios 
            (FechaHora, ID_Usuario, NombreUsuario, Entidad, ID_Registro, Accion, Campo, ValorAnterior, ValorNuevo, CodigoRastreo, Detalles)
            VALUES (NOW(), :idUser, :nombreUser, :entidad, :idReg, :accion, :campo, :valAnt, :valNue, :trace, :detalles)
        ");
        $stmt->execute([
            ':idUser'     => $idUsuario,
            ':nombreUser' => $nombreUsuario,
            ':entidad'    => $entidad,
            ':idReg'      => $idRegistro ? (int)$idRegistro : null,
            ':accion'     => $accion,
            ':campo'      => $campo,
            ':valAnt'     => $valorAnterior,
            ':valNue'     => $valorNuevo,
            ':trace'      => $codigoRastreo,
            ':detalles'   => $detalles
        ]);
        return $codigoRastreo;
    } catch (Exception $e) {
        return false;
    }
}
