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

if (!$nombre || !$email || !$mensaje) {
    header("Location: ../index.php?status=error&msg=" . urlencode("Por favor completa todos los campos del correo."));
    exit();
}

if ($accion === 'borrador') {
    registrarMensajeEnHistorial($conn, null, $nombre, $email, $asunto, $mensaje, 'Manual', 'Borrador');
    header("Location: ../mensajes.php?status=draft_saved");
    exit();
} else {
    $resultado = enviarCorreoInstitucional($email, $nombre, $asunto, $mensaje, 'Manual');

    if ($resultado['success']) {
        header("Location: ../index.php?status=sent");
        exit();
    } else {
        header("Location: ../index.php?status=error&msg=" . urlencode("No se pudo enviar el correo: " . $resultado['error']));
        exit();
    }
}
