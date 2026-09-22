<?php
require_once __DIR__ . '/../../seccion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../db/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');

if (!$nombre || !$email || !$mensaje) {
    header("Location: ../index.php?status=error&msg=" . urlencode("Por favor completa todos los campos del correo."));
    exit();
}

try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'mail.gesmujer.org';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'soporte@gesmujer.org';
    $mail->Password   = 'G3smujer2025';
    $mail->SMTPSecure = 'ssl';
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('soporte@gesmujer.org', 'GesMujer Oaxaca');
    $mail->addAddress($email, $nombre);
    $mail->addReplyTo('soporte@gesmujer.org', 'GesMujer Oaxaca');

    $mail->isHTML(true);
    $mail->Subject = 'Mensaje de la Familia GesMujer Oaxaca';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #721896; margin: 0;'>GesMujer Oaxaca</h2>
                <p style='color: #666; font-size: 14px;'>Grupo de Estudios sobre la Mujer Rosario Castellanos</p>
            </div>
            <div style='background: #fdf8ff; padding: 15px; border-left: 4px solid #721896; margin-bottom: 20px; border-radius: 4px;'>
                <p style='font-size: 15px; line-height: 1.6; color: #333; margin: 0; white-space: pre-line;'>" . htmlspecialchars($mensaje) . "</p>
            </div>
            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #999; text-align: center;'>Este es un mensaje institucional emitido desde el Sistema SYSGES de GesMujer Oaxaca.</p>
        </div>
    ";

    $mail->send();
    header("Location: ../index.php?status=sent");
    exit();

} catch (Exception $e) {
    header("Location: ../index.php?status=error&msg=" . urlencode("No se pudo enviar el correo: " . $mail->ErrorInfo));
    exit();
}

