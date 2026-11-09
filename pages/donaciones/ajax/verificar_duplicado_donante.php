<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../db/config.php';

try {
    $idDonante       = isset($_POST['id_donante']) && is_numeric($_POST['id_donante']) ? (int)$_POST['id_donante'] : 0;
    $nombre          = trim($_POST['nombre'] ?? '');
    $apellidoPaterno = trim($_POST['apellido_paterno'] ?? '');
    $apellidoMaterno = trim($_POST['apellido_materno'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $rfc             = strtoupper(trim($_POST['rfc'] ?? ''));

    // Limpiar dígitos de teléfono para comparación flexible
    $telDigitos = preg_replace('/[^0-9]/', '', $telefono);

    $coincidencias = [];

    // Solo consultar si hay al menos un dato relevante capturado
    if ($email !== '' || ($nombre !== '' && $apellidoPaterno !== '') || (strlen($telDigitos) >= 7) || ($rfc !== '')) {
        
        $sql = "
            SELECT ID_Donante, Nombre, ApellidoPaterno, ApellidoMaterno,
                   CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno, '')) AS NombreCompleto,
                   Email, Telefono, RFC, Estatus, Ciudad
            FROM donantes
            WHERE (:id_excluir = 0 OR ID_Donante != :id_excluir)
              AND (
                  (:email != '' AND LOWER(TRIM(Email)) = LOWER(:email))
                  OR (:nom != '' AND :ap != '' AND LOWER(TRIM(Nombre)) = LOWER(:nom) AND LOWER(TRIM(ApellidoPaterno)) = LOWER(:ap))
                  OR (:rfc != '' AND UPPER(TRIM(RFC)) = :rfc)
                  OR (:telClean != '' AND REPLACE(REPLACE(REPLACE(REPLACE(Telefono, ' ', ''), '-', ''), '(', ''), ')', '') = :telClean)
              )
            LIMIT 5
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_excluir', $idDonante, PDO::PARAM_INT);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':nom', $nombre);
        $stmt->bindValue(':ap', $apellidoPaterno);
        $stmt->bindValue(':rfc', $rfc);
        $stmt->bindValue(':telClean', strlen($telDigitos) >= 7 ? $telDigitos : '');
        $stmt->execute();
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($registros as $reg) {
            $motivos = [];

            // Evaluar coincidencia de correo
            if ($email !== '' && strcasecmp(trim($reg['Email']), $email) === 0) {
                $motivos[] = "Mismo correo electrónico ({$reg['Email']})";
            }

            // Evaluar coincidencia de nombre y apellido
            if ($nombre !== '' && $apellidoPaterno !== '') {
                $nomMatch = strcasecmp(trim($reg['Nombre']), $nombre) === 0;
                $apMatch  = strcasecmp(trim($reg['ApellidoPaterno']), $apellidoPaterno) === 0;
                $amMatch  = ($apellidoMaterno !== '' && !empty($reg['ApellidoMaterno'])) 
                            ? strcasecmp(trim($reg['ApellidoMaterno']), $apellidoMaterno) === 0 
                            : true;

                if ($nomMatch && $apMatch) {
                    if ($amMatch && $apellidoMaterno !== '') {
                        $motivos[] = "Nombre completo idéntico ({$reg['NombreCompleto']})";
                    } else {
                        $motivos[] = "Mismo nombre y apellido paterno ({$reg['Nombre']} {$reg['ApellidoPaterno']})";
                    }
                }
            }

            // Evaluar coincidencia de teléfono
            $regTelDigitos = preg_replace('/[^0-9]/', '', $reg['Telefono'] ?? '');
            if (strlen($telDigitos) >= 7 && $regTelDigitos === $telDigitos) {
                $motivos[] = "Mismo número de teléfono ({$reg['Telefono']})";
            }

            // Evaluar coincidencia de RFC
            if ($rfc !== '' && !empty($reg['RFC']) && strcasecmp(trim($reg['RFC']), $rfc) === 0) {
                $motivos[] = "Mismo RFC ({$reg['RFC']})";
            }

            if (!empty($motivos)) {
                $coincidencias[] = [
                    'id'              => (int)$reg['ID_Donante'],
                    'nombre_completo' => trim($reg['NombreCompleto']),
                    'email'           => $reg['Email'],
                    'telefono'        => $reg['Telefono'],
                    'estatus'         => $reg['Estatus'],
                    'rfc'             => $reg['RFC'],
                    'ciudad'          => $reg['Ciudad'],
                    'motivos'         => $motivos
                ];
            }
        }
    }

    echo json_encode([
        'success'       => true,
        'hayDuplicados' => !empty($coincidencias),
        'total'         => count($coincidencias),
        'coincidencias' => $coincidencias
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
