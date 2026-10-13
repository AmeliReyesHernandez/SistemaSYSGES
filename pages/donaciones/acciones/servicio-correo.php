<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../db/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Registra un mensaje en el historial donaciones_mensajes
 */
/**
 * Registra un mensaje en el historial donaciones_mensajes
 */
function registrarMensajeEnHistorial($conn, $idDonante, $nombreDestino, $emailDestino, $asunto, $mensajeTexto, $tipoMensaje = 'Manual', $estado = 'Enviado', $archivoAdjunto = null) {
    if (!$conn) return false;
    try {
        $stmt = $conn->prepare("
            INSERT INTO donaciones_mensajes (ID_Donante, NombreDestino, EmailDestino, Asunto, Mensaje, ArchivoAdjunto, TipoMensaje, Estado, FechaRegistro)
            VALUES (:donante, :nombre, :email, :asunto, :mensaje, :adjunto, :tipo, :estado, NOW())
        ");
        $stmt->execute([
            ':donante' => $idDonante ? (int)$idDonante : null,
            ':nombre' => $nombreDestino,
            ':email' => $emailDestino,
            ':asunto' => $asunto,
            ':mensaje' => $mensajeTexto,
            ':adjunto' => $archivoAdjunto,
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
function enviarCorreoInstitucional($emailDestino, $nombreDestino, $asunto, $mensajeTexto, $tipoMensaje = 'Manual', $idDonante = null, $rutaAdjunto = null, $nombreAdjunto = null) {
    global $conn;

    if (empty($emailDestino) || !filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
        if ($conn) {
            registrarMensajeEnHistorial($conn, $idDonante, $nombreDestino, $emailDestino, $asunto, $mensajeTexto, $tipoMensaje, 'Fallido', $nombreAdjunto);
        }
        return ['success' => false, 'error' => 'Correo de destino no válido.'];
    }

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'Administrador@gesmujer.org';
        $mail->Password   = 'nznc jsjr toqn gqis';
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

        // Adjuntar archivo si existe (solo el archivo real del usuario)
        if (!empty($rutaAdjunto) && file_exists($rutaAdjunto)) {
            $mail->addAttachment($rutaAdjunto, $nombreAdjunto ?: basename($rutaAdjunto));
        }

        // Obtener las imágenes del membrete y de cumpleaños
        $headerImgPath = realpath(__DIR__ . '/../../../assets/img/membrete_header.jpg');
        $footerImgPath = realpath(__DIR__ . '/../../../assets/img/membrete_footer.jpg');
        $cumpleImgPath = realpath(__DIR__ . '/../../../assets/img/cumpleanos_banner.png');

        $hasHeaderImg = ($headerImgPath && file_exists($headerImgPath));
        $hasFooterImg = ($footerImgPath && file_exists($footerImgPath));
        $isCumple = ($tipoMensaje === 'Cumpleaños' || stripos($asunto, 'cumpleañ') !== false || stripos($mensajeTexto, 'cumpleaños') !== false);
        $hasCumpleImg = $isCumple && ($cumpleImgPath && file_exists($cumpleImgPath));

        if ($hasHeaderImg) {
            $mail->addEmbeddedImage($headerImgPath, 'membrete_header', 'membrete_header.jpg', 'base64', 'image/jpeg', 'inline');
        }
        if ($hasFooterImg) {
            $mail->addEmbeddedImage($footerImgPath, 'membrete_footer', 'membrete_footer.jpg', 'base64', 'image/jpeg', 'inline');
        }
        if ($hasCumpleImg) {
            $mail->addEmbeddedImage($cumpleImgPath, 'cumpleanos_banner', 'cumpleanos_banner.png', 'base64', 'image/png', 'inline');
        }

        $mail->isHTML(true);
        $mail->Subject = $asunto;

        // Encabezado exacto con la imagen oficial de GesMujer
        $headerHtml = $hasHeaderImg 
            ? "<div style='width: 100%; text-align: center; background-color: #ffffff;'><img src='cid:membrete_header' alt='GesMujer Oaxaca' style='width: 100%; max-width: 650px; height: auto; display: block; margin: 0 auto; border: 0;'></div>"
            : "<div style='text-align: center; padding: 20px; background-color: #721896; color: #ffffff;'><h2 style='margin:0;'>GesMujer Oaxaca</h2></div>";

        // Pie de página exacto con la imagen oficial de GesMujer
        $footerHtml = $hasFooterImg
            ? "<div style='width: 100%; text-align: center; background-color: #721896;'><img src='cid:membrete_footer' alt='Contacto GesMujer Oaxaca' style='width: 100%; max-width: 650px; height: auto; display: block; margin: 0 auto; border: 0;'></div>"
            : "<div style='text-align: center; padding: 15px; color: #ffffff; background-color: #721896;'>GesMujer Oaxaca | www.gesmujer.org</div>";

        // Si es felicitación de cumpleaños, integrar la ilustración oficial y subtítulo
        $cumpleBannerHtml = '';
        $textoFinal = $mensajeTexto;
        if ($hasCumpleImg) {
            $cumpleBannerHtml = "
                <div style='text-align: center; margin: 10px auto 12px auto;'>
                    <img src='cid:cumpleanos_banner' alt='¡Feliz Cumpleaños!' style='width: 100%; max-width: 520px; height: auto; display: inline-block; border-radius: 8px; box-shadow: 0 2px 12px rgba(114, 24, 150, 0.12);'>
                </div>
                <div style='text-align: center; color: #8e1f7c; font-size: 16px; font-weight: bold; font-style: italic; margin-bottom: 22px;'>
                    ¡Que tengas el mejor de los días!
                </div>
            ";
            // Evitar duplicar el subtítulo si ya venía en el texto plano
            $textoFinal = str_ireplace("¡Que tengas el mejor de los días!\r\n\r\n", "", $textoFinal);
            $textoFinal = str_ireplace("¡Que tengas el mejor de los días!\n\n", "", $textoFinal);
            $textoFinal = str_ireplace("¡Que tengas el mejor de los días!", "", $textoFinal);
            $textoFinal = trim($textoFinal);
        }

        // Reemplazo dinámico de cualquier comodín de nombre restante por el nombre real
        $saludoPila = !empty($nombreDestino) ? explode(' ', trim($nombreDestino))[0] : $nombreDestino;
        $textoFinal = str_replace(
            ['[Nombre del Donante]', '[Nombre]', '[nombre]', '{Nombre}'],
            $saludoPila ?: $nombreDestino,
            $textoFinal
        );

        $mail->Body = "
            <div style='background-color: #f4f6f9; padding: 20px 10px; font-family: Arial, Helvetica, sans-serif;'>
                <div style='max-width: 650px; margin: 0 auto; background-color: #ffffff; border: 1px solid #dcdfe6; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.06);'>
                    
                    <!-- ENCABEZADO EXACTO DE LA HOJA MEMBRETADA -->
                    {$headerHtml}
                    
                    <!-- CUERPO DEL MENSAJE / CARTA -->
                    <div style='padding: 24px 34px 28px 34px; color: #222222; line-height: 1.7; background-color: #ffffff; font-family: Arial, Helvetica, sans-serif;'>
                        {$cumpleBannerHtml}
                        <p style='font-size: 14.5px; color: #222222; margin: 0; white-space: pre-line; line-height: 1.75; font-family: Arial, sans-serif; text-align: justify;'>" . htmlspecialchars($textoFinal) . "</p>
                    </div>
                    
                    <!-- PIE DE PÁGINA EXACTO DE LA HOJA MEMBRETADA -->
                    {$footerHtml}

                </div>
            </div>
        ";

        $mail->send();

        // Registrar envío exitoso en el historial
        if ($conn) {
            registrarMensajeEnHistorial($conn, $idDonante, $nombreDestino, $emailDestino, $asunto, $mensajeTexto, $tipoMensaje, 'Enviado', $nombreAdjunto);
        }

        return ['success' => true];

    } catch (Exception $e) {
        $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
        if ($conn) {
            registrarMensajeEnHistorial($conn, $idDonante, $nombreDestino, $emailDestino, $asunto, $mensajeTexto, $tipoMensaje, 'Fallido', $nombreAdjunto);
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
            SELECT ID_Donante, Nombre, ApellidoPaterno, ApellidoMaterno,
                   CONCAT(Nombre, ' ', ApellidoPaterno, ' ', COALESCE(ApellidoMaterno, '')) AS NombreCompleto,
                   Email, FechaNacimiento
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
            $nombrePila = trim($donante['Nombre'] ?? '');
            $nombreCompleto = trim($donante['NombreCompleto'] ?? '');
            $saludoNombre = !empty($nombrePila) ? $nombrePila : (!empty($nombreCompleto) ? $nombreCompleto : 'Amiga/Amigo');
            $email = $donante['Email'];
            $idDonante = $donante['ID_Donante'];
            $asunto = "En GESMujer Te deseamos... ¡Feliz Cumpleaños!";
            
            $mensaje = "¡Que tengas el mejor de los días!\n\n"
                     . "{$saludoNombre}, en el GESMujer no solo celebramos tu cumpleaños, ¡También el tiempo de amistad y cariño! Gracias por ser parte de nuestra historia, tu apoyo siempre constante nos llena de alegría la vida. Deseamos con cariño que la sigas pasando... ¡Estupendo! Disfruta tu día en compañía de quienes más quieres, dedícate tiempo y déjate querer mucho.\n\n"
                     . "Recibe los mejores deseos y muchos abrazos con cariño.\n\n"
                     . "¡ Felicidades !\n"
                     . "Atentamente.\n"
                     . "Grupo de Estudios Sobre la Mujer \"Rosario Castellanos\" A.C.";

            $res = enviarCorreoInstitucional($email, $nombreCompleto ?: $saludoNombre, $asunto, $mensaje, 'Cumpleaños', $idDonante);
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
