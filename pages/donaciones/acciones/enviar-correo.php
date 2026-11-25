<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/servicio-correo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

$nombre    = trim($_POST['nombre']    ?? '');
$email     = trim($_POST['email']     ?? '');
$mensaje   = trim($_POST['mensaje']   ?? '');
$asunto    = trim($_POST['asunto']    ?? 'Mensaje de la Familia GESMujer Oaxaca');
$accion    = trim($_POST['accion']    ?? 'enviar');
$idDonante = isset($_POST['id_donante']) && is_numeric($_POST['id_donante']) ? (int)$_POST['id_donante'] : null;
$idMensaje = isset($_POST['id_mensaje']) && is_numeric($_POST['id_mensaje']) ? (int)$_POST['id_mensaje'] : null;
$returnUrl = trim($_POST['return_url'] ?? '../mensajes.php');

if (!$nombre || !$email || !$mensaje) {
    header("Location: $returnUrl?status=error&msg=" . urlencode("Por favor completa todos los campos obligatorios del correo."));
    exit();
}

$rutaAdjunto  = null;
$nombreAdjunto = null;

// Manejo de archivo adjunto
if (isset($_FILES['adjunto']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
    $fileTmp      = $_FILES['adjunto']['tmp_name'];
    $fileOrigName = basename($_FILES['adjunto']['name']);
    $fileSize     = $_FILES['adjunto']['size'];
    $fileExt      = strtolower(pathinfo($fileOrigName, PATHINFO_EXTENSION));

    $extPermitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp', 'xls', 'xlsx'];
    $maxBytes = 15 * 1024 * 1024; // 15MB

    if (in_array($fileExt, $extPermitidas) && $fileSize <= $maxBytes) {
        $uploadDir = __DIR__ . '/../../../uploads/donaciones/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $nuevoNombre = 'doc_' . time() . '_' . uniqid() . '.' . $fileExt;
        $destino     = $uploadDir . $nuevoNombre;

        if (move_uploaded_file($fileTmp, $destino)) {
            $rutaAdjunto  = $destino;
            $nombreAdjunto = $fileOrigName;
        }
    }
}

// Detectar tipo de mensaje
$tipoDetectado = 'Manual';
if (stripos($asunto, 'cumpleañ') !== false || stripos($mensaje, 'cumpleaños') !== false) {
    $tipoDetectado = 'Cumpleaños';
} elseif (stripos($asunto, 'agradecimiento') !== false || stripos($mensaje, 'agradecimiento') !== false || stripos($mensaje, 'generoso donativo') !== false) {
    $tipoDetectado = 'Agradecimiento';
} elseif (stripos($asunto, 'recibo') !== false || stripos($mensaje, 'recibo') !== false) {
    $tipoDetectado = 'Recibo';
} elseif (stripos($asunto, 'informe') !== false || stripos($mensaje, 'informe') !== false) {
    $tipoDetectado = 'Informativo';
}

// ─── GUARDAR BORRADOR ────────────────────────────────────────────────────────
if ($accion === 'borrador') {
    if ($idMensaje) {
        // Actualizar borrador existente
        try {
            $stmt = $conn->prepare("
                UPDATE donaciones_mensajes
                SET NombreDestino = :nombre,
                    EmailDestino  = :email,
                    Asunto        = :asunto,
                    Mensaje       = :mensaje,
                    TipoMensaje   = :tipo,
                    ID_Donante    = :id_donante
                WHERE ID_Mensaje  = :id AND Estado = 'Borrador'
            ");
            $stmt->bindParam(':nombre',     $nombre);
            $stmt->bindParam(':email',      $email);
            $stmt->bindParam(':asunto',     $asunto);
            $stmt->bindParam(':mensaje',    $mensaje);
            $stmt->bindParam(':tipo',       $tipoDetectado);
            $stmt->bindParam(':id_donante', $idDonante);
            $stmt->bindParam(':id',         $idMensaje, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            header("Location: $returnUrl?status=error&msg=" . urlencode("Error al actualizar borrador."));
            exit();
        }
    } else {
        // Crear borrador nuevo
        registrarMensajeEnHistorial($conn, $idDonante, $nombre, $email, $asunto, $mensaje, $tipoDetectado, 'Borrador', $nombreAdjunto);
    }
    header("Location: ../mensajes.php?status=draft_saved");
    exit();
}

// ─── ENVIAR ──────────────────────────────────────────────────────────────────
$adjuntosExtra = [];
$mensajeEnvio  = $mensaje;
$nombreAdjuntoEnvio = $nombreAdjunto;

if ($tipoDetectado === 'Agradecimiento') {
    require_once __DIR__ . '/carta-agradecimiento-pdf.php';
    $saludoNombre = explode(' ', trim($nombre))[0] ?: $nombre;
    $pdfBinario = generarCartaAgradecimientoPDF([
        'saludoNombre'       => $saludoNombre,
        'textoPersonalizado' => $mensaje
    ], 'S');
    $nombrePdf = 'Carta_Agradecimiento_' . preg_replace('/[^A-Za-z0-9_-]/', '', $saludoNombre) . '.pdf';
    $adjuntosExtra[] = ['contenido' => $pdfBinario, 'nombre' => $nombrePdf];
    $nombreAdjuntoEnvio = $nombreAdjunto ? "$nombreAdjunto, $nombrePdf" : $nombrePdf;

    $mensajeEnvio = "Estimado(a) {$saludoNombre}:\n\n"
                  . "Te compartimos adjunta tu carta de agradecimiento en formato PDF, como muestra de nuestro reconocimiento y gratitud por tu valiosa donación y por contribuir a nuestra labor.\n\n"
                  . "Recibe nuestro más sincero agradecimiento.\n\n"
                  . "Atentamente,\n"
                  . "Grupo de Estudios sobre la Mujer Rosario Castellanos (GESMujer)";
} elseif ($tipoDetectado === 'Cumpleaños') {
    require_once __DIR__ . '/tarjeta-cumpleanos-pdf.php';
    $saludoNombre = explode(' ', trim($nombre))[0] ?: $nombre;
    $pdfBinario = generarTarjetaCumpleanosPDF([
        'saludoNombre'       => $saludoNombre,
        'textoPersonalizado' => $mensaje
    ], 'S');
    $nombrePdf = 'Tarjeta_Cumpleanos_' . preg_replace('/[^A-Za-z0-9_-]/', '', $saludoNombre) . '.pdf';
    $adjuntosExtra[] = ['contenido' => $pdfBinario, 'nombre' => $nombrePdf];
    $nombreAdjuntoEnvio = $nombreAdjunto ? "$nombreAdjunto, $nombrePdf" : $nombrePdf;

    $mensajeEnvio = "Estimado(a) {$saludoNombre}:\n\n"
                  . "¡En GESMujer celebramos tu vida y te deseamos un muy feliz cumpleaños! Te compartimos adjunta tu tarjeta de felicitación en formato PDF.\n\n"
                  . "Recibe un afectuoso abrazo.\n\n"
                  . "Atentamente,\n"
                  . "Grupo de Estudios sobre la Mujer Rosario Castellanos (GESMujer)";
}

if ($idMensaje) {
    // Enviar un borrador existente: actualizarlo y enviarlo
    $resultado = enviarCorreoInstitucional($email, $nombre, $asunto, $mensajeEnvio, $tipoDetectado, $idDonante, $rutaAdjunto, $nombreAdjuntoEnvio, $adjuntosExtra);

    if ($resultado['success']) {
        try {
            $stmt = $conn->prepare("
                UPDATE donaciones_mensajes
                SET NombreDestino = :nombre,
                    EmailDestino  = :email,
                    Asunto        = :asunto,
                    Mensaje       = :mensaje,
                    ArchivoAdjunto = :adjunto,
                    TipoMensaje   = :tipo,
                    Estado        = 'Enviado',
                    ID_Donante    = :id_donante
                WHERE ID_Mensaje  = :id
            ");
            $stmt->bindParam(':nombre',     $nombre);
            $stmt->bindParam(':email',      $email);
            $stmt->bindParam(':asunto',     $asunto);
            $stmt->bindParam(':mensaje',    $mensajeEnvio);
            $stmt->bindParam(':adjunto',    $nombreAdjuntoEnvio);
            $stmt->bindParam(':tipo',       $tipoDetectado);
            $stmt->bindParam(':id_donante', $idDonante);
            $stmt->bindParam(':id',         $idMensaje, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            // El correo se envió pero no se pudo actualizar la BD — registrar como nuevo enviado
            registrarMensajeEnHistorial($conn, $idDonante, $nombre, $email, $asunto, $mensajeEnvio, $tipoDetectado, 'Enviado', $nombreAdjuntoEnvio);
        }
        header("Location: $returnUrl?status=sent");
        exit();
    } else {
        header("Location: $returnUrl?status=error&msg=" . urlencode("No se pudo enviar el correo: " . $resultado['error']));
        exit();
    }
} else {
    // Envío nuevo normal
    $resultado = enviarCorreoInstitucional($email, $nombre, $asunto, $mensajeEnvio, $tipoDetectado, $idDonante, $rutaAdjunto, $nombreAdjuntoEnvio, $adjuntosExtra);

    if ($resultado['success']) {
        header("Location: $returnUrl?status=sent");
        exit();
    } else {
        header("Location: $returnUrl?status=error&msg=" . urlencode("No se pudo enviar el correo: " . $resultado['error']));
        exit();
    }
}
