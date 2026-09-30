<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../db/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Registra un mensaje en el historial donaciones_mensajes
 */
function registrarMensajeEnHistorial($conn, $idDonante, $nombreDestino, $emailDestino, $asunto, $mensajeTexto, $tipoMensaje = 'Manual', $estado = 'Enviado') {
    if (!$conn) return false;
    try {
        $stmt = $conn->prepare("
            INSERT INTO donaciones_mensajes (ID_Donante, NombreDestino, EmailDestino, Asunto, Mensaje, TipoMensaje, Estado, FechaRegistro)
            VALUES (:donante, :nombre, :email, :asunto, :mensaje, :tipo, :estado, NOW())
        ");
        $stmt->execute([
            ':donante' => $idDonante ? (int)$idDonante : null,
            ':nombre' => $nombreDestino,
            ':email' => $emailDestino,
            ':asunto' => $asunto,
            ':mensaje' => $mensajeTexto,
            ':tipo' => $tipoMensaje,
            ':estado' => $estado
        ]);
        return $conn->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Función centralizada para enviar correos electrónicos institucionales vía SMTP
 */
function enviarCorreoInstitucional($emailDestino, $nombreDestino, $asunto, $mensajeTexto, $tipoMensaje = 'Manual', $idDonante = null) {
    global $conn;

    if (empty($emailDestino) || !filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
        if ($conn) {
            registrarMensajeEnHistorial($conn, $idDonante, $nombreDestino, $emailDestino, $asunto, $mensajeTexto, $tipoMensaje, 'Fallido');
        }
        return ['success' => false, 'error' => 'Correo de destino no válido.'];
    }

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'amelireyes963@gmail.com';
        $mail->Password   = 'kpuu epyu yekj bcvm';
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // Opciones SSL para entornos locales XAMPP
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom($mail->Username, 'GesMujer Oaxaca');
        $mail->addAddress($emailDestino, $nombreDestino);
        $mail->addReplyTo($mail->Username, 'GesMujer Oaxaca');

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #721896; margin: 0; font-size: 24px;'>GesMujer Oaxaca</h2>
                    <p style='color: #666; font-size: 13px; margin-top: 4px;'>Grupo de Estudios sobre la Mujer Rosario Castellanos A.C.</p>
                </div>
                <div style='background: #fdf8ff; padding: 18px; border-left: 5px solid #721896; margin-bottom: 20px; border-radius: 6px;'>
                    <p style='font-size: 15px; line-height: 1.6; color: #333; margin: 0; white-space: pre-line;'>" . htmlspecialchars($mensajeTexto) . "</p>
                </div>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            </div>
        ";

        $mail->send();

        // Registrar envío exitoso en el historial
        if ($conn) {
            registrarMensajeEnHistorial($conn, $idDonante, $nombreDestino, $emailDestino, $asunto, $mensajeTexto, $tipoMensaje, 'Enviado');
        }

        return ['success' => true];

    } catch (Exception $e) {
        $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
        if ($conn) {
            registrarMensajeEnHistorial($conn, $idDonante, $nombreDestino, $emailDestino, $asunto, $mensajeTexto, $tipoMensaje, 'Fallido');
        }
        return ['success' => false, 'error' => $errorMsg];
    }
}

/**
 * Función para verificar y enviar automáticamente las felicitaciones de cumpleaños del día
 */
function procesarCumpleanosAutomaticos($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT ID_Donante, CONCAT(Nombre, ' ', ApellidoPaterno) AS NombreCompleto, Email, FechaNacimiento
            FROM donantes
            WHERE Email IS NOT NULL 
              AND Email != ''
              AND FechaNacimiento IS NOT NULL
              AND DATE_FORMAT(FechaNacimiento, '%m-%d') = DATE_FORMAT(CURRENT_DATE(), '%m-%d')
              AND (UltimoCumpleEnviado IS NULL OR YEAR(UltimoCumpleEnviado) < YEAR(CURRENT_DATE()))
        ");
        $stmt->execute();
        $cumpleaneros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $enviados = 0;
        foreach ($cumpleaneros as $donante) {
            $nombre = $donante['NombreCompleto'];
            $email = $donante['Email'];
            $idDonante = $donante['ID_Donante'];
            $asunto = "¡Feliz Cumpleaños le desea GesMujer Oaxaca!";
            $mensaje = "¡Feliz Cumpleaños, {$nombre}!\n\nTodo el equipo de GesMujer Oaxaca le desea un día maravilloso lleno de salud, alegría y bendiciones. Agradecemos enormemente contar con su apoyo y solidaridad constante.\n\n¡Un fuerte abrazo de parte de la familia GesMujer!";

            $res = enviarCorreoInstitucional($email, $nombre, $asunto, $mensaje, 'Cumpleaños', $idDonante);
            if ($res['success']) {
                $update = $conn->prepare("UPDATE donantes SET UltimoCumpleEnviado = CURRENT_DATE() WHERE ID_Donante = :id");
                $update->execute([':id' => $idDonante]);
                $enviados++;
            }
        }
        return $enviados;
    } catch (Exception $e) {
        return 0;
    }
}
