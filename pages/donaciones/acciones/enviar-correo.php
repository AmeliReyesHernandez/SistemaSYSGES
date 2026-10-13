<?php
require_once __DIR__ . '/../seccion.php';
require_once __DIR__ . '/servicio-correo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');
$asunto = trim($_POST['asunto'] ?? 'Mensaje de la Familia GesMujer Oaxaca');
$accion = trim($_POST['accion'] ?? 'enviar');
$idDonante = isset($_POST['id_donante']) && is_numeric($_POST['id_donante']) ? (int)$_POST['id_donante'] : null;
$returnUrl = trim($_POST['return_url'] ?? '../mensajes.php');

if (!$nombre || !$email || !$mensaje) {
    header("Location: $returnUrl?status=error&msg=" . urlencode("Por favor completa todos los campos obligatorios del correo."));
    exit();
}

$rutaAdjunto = null;
$nombreAdjunto = null;

// Manejo de archivo adjunto
if (isset($_FILES['adjunto']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['adjunto']['tmp_name'];
    $fileOrigName = basename($_FILES['adjunto']['name']);
    $fileSize = $_FILES['adjunto']['size'];
    $fileExt = strtolower(pathinfo($fileOrigName, PATHINFO_EXTENSION));

    $extPermitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp', 'xls', 'xlsx'];
    $maxBytes = 15 * 1024 * 1024; // 15MB

    if (in_array($fileExt, $extPermitidas) && $fileSize <= $maxBytes) {
        $uploadDir = __DIR__ . '/../../../uploads/donaciones/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $nuevoNombre = 'doc_' . time() . '_' . uniqid() . '.' . $fileExt;
        $destino = $uploadDir . $nuevoNombre;

        if (move_uploaded_file($fileTmp, $destino)) {
            $rutaAdjunto = $destino;
            $nombreAdjunto = $fileOrigName;
        }
    }
}

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

if ($accion === 'borrador') {
    registrarMensajeEnHistorial($conn, $idDonante, $nombre, $email, $asunto, $mensaje, $tipoDetectado, 'Borrador', $nombreAdjunto);
    header("Location: ../mensajes.php?status=draft_saved");
    exit();
} else {
    $resultado = enviarCorreoInstitucional($email, $nombre, $asunto, $mensaje, $tipoDetectado, $idDonante, $rutaAdjunto, $nombreAdjunto);

    if ($resultado['success']) {
        header("Location: $returnUrl?status=sent");
        exit();
    } else {
        header("Location: $returnUrl?status=error&msg=" . urlencode("No se pudo enviar el correo: " . $resultado['error']));
        exit();
    }
}
