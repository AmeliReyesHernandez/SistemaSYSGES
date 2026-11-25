<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!class_exists('TarjetaCumpleanosPDF')) {
    class TarjetaCumpleanosPDF extends TCPDF {
        public function Header() {
            $headerImg = realpath(__DIR__ . '/../../../assets/img/membrete_header.jpg');
            if ($headerImg && file_exists($headerImg)) {
                $this->Image($headerImg, 12, 8, 186, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            } else {
                $this->SetFont('helvetica', 'B', 15);
                $this->SetTextColor(114, 24, 150);
                $this->Cell(0, 10, 'GESMujer Oaxaca', 0, 1, 'C');
            }
        }

        public function Footer() {
            $autoPageBreak = $this->AutoPageBreak;
            $bMargin = $this->bMargin;
            $this->SetAutoPageBreak(false, 0);

            $footerImg = realpath(__DIR__ . '/../../../assets/img/membrete_footer.jpg');
            if ($footerImg && file_exists($footerImg)) {
                $this->Image($footerImg, 0, 277.3, 210, 0, 'JPG', '', '', false, 300, '', false, false, 0, false, false, false);
            }

            $this->SetAutoPageBreak($autoPageBreak, $bMargin);
        }
    }
}

/**
 * Construye el PDF oficial de la Tarjeta de Cumpleaños.
 *
 * @param array $opciones [
 *   'saludoNombre'       => string,
 *   'textoPersonalizado' => string|null
 * ]
 * @param string $dest 'I' (inline), 'S' (string binario), 'F' (archivo en $rutaSalida)
 * @param string $rutaSalida
 * @return string|null
 */
function generarTarjetaCumpleanosPDF(array $opciones, string $dest = 'S', string $rutaSalida = 'Tarjeta_Cumpleanos.pdf') {
    $saludoNombre       = trim($opciones['saludoNombre'] ?? 'Amiga/Amigo');
    $textoPersonalizado = $opciones['textoPersonalizado'] ?? null;

    $pdf = new TarjetaCumpleanosPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('SYSGES - GESMujer');
    $pdf->SetAuthor('GESMujer Oaxaca');
    $pdf->SetTitle('Tarjeta de Cumpleaños - GESMujer');
    $pdf->SetSubject('Felicitación de Cumpleaños');

    $pdf->SetMargins(22, 42, 22);
    $pdf->SetHeaderMargin(8);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 26);
    $pdf->AddPage();

    // Ilustración oficial de cumpleaños centrada (JPG sin canal alfa para compatibilidad sin GD/Imagick)
    $cumpleImg = realpath(__DIR__ . '/../../../assets/img/cumpleanos_banner.jpg');
    if ($cumpleImg && file_exists($cumpleImg)) {
        // Centrada en A4 (210mm de ancho): x = (210 - 132) / 2 = 39mm
        $pdf->Image($cumpleImg, 39, 44, 132, 0, 'JPG', '', 'N', false, 300, '', false, false, 0, false, false, false);
        $pdf->Ln(4);
    }

    if ($textoPersonalizado !== null && trim($textoPersonalizado) !== '') {
        $saludoPila = !empty($saludoNombre) ? explode(' ', $saludoNombre)[0] : $saludoNombre;
        $textoLimpiado = str_replace(
            ['[Nombre del Donante]', '[Nombre]', '[nombre]', '{Nombre}'],
            $saludoPila ?: $saludoNombre,
            trim($textoPersonalizado)
        );
        $textoLimpiado = str_ireplace("¡Que tengas el mejor de los días!\r\n\r\n", '', $textoLimpiado);
        $textoLimpiado = str_ireplace("¡Que tengas el mejor de los días!\n\n", '', $textoLimpiado);
        $textoLimpiado = str_ireplace("¡Que tengas el mejor de los días!", '', $textoLimpiado);
        $textoLimpiado = trim($textoLimpiado);

        $parrafos = preg_split("/(\r\n|\n|\r){2,}/", $textoLimpiado);
        $bloquesHtml = '';
        foreach ($parrafos as $p) {
            $pTrim = trim($p);
            if ($pTrim === '') continue;
            $pHtml = nl2br(htmlspecialchars($pTrim));
            if (stripos($pTrim, '¡ Felicidades') !== false || stripos($pTrim, '¡Felicidades') !== false) {
                $bloquesHtml .= '<p style="text-align: center; font-size: 13pt; color: #721896; font-weight: bold; margin: 14px 0;">' . $pHtml . '</p>';
            } elseif (stripos($pTrim, 'Atentamente') === 0 || stripos($pTrim, 'Grupo de Estudios') !== false) {
                $bloquesHtml .= '<p style="margin: 10px 0 0 0; line-height: 1.4;">' . $pHtml . '</p>';
            } else {
                $bloquesHtml .= '<p style="text-align: justify; margin-bottom: 12px;">' . $pHtml . '</p>';
            }
        }
    } else {
        $bloquesHtml = '
        <p style="text-align: justify; margin-bottom: 12px;">
            <strong>' . htmlspecialchars($saludoNombre) . '</strong>, en el GESMujer no solo celebramos tu cumpleaños, ¡también el tiempo de amistad y cariño! Gracias por ser parte de nuestra historia, tu apoyo siempre constante nos llena de alegría la vida. Deseamos con cariño que la sigas pasando... ¡Estupendo! Disfruta tu día en compañía de quienes más quieres, dedícate tiempo y déjate querer mucho.
        </p>
        <p style="text-align: justify; margin-bottom: 14px;">
            Recibe los mejores deseos y muchos abrazos con cariño.
        </p>
        <p style="text-align: center; font-size: 13pt; color: #721896; font-weight: bold; margin: 14px 0;">
            ¡ Felicidades !
        </p>
        <p style="margin: 12px 0 0 0; line-height: 1.4;">
            Atentamente.<br>
            <strong>Grupo de Estudios Sobre la Mujer "Rosario Castellanos" A.C.</strong>
        </p>';
    }

    $html = '
    <div style="font-family: helvetica, Arial, sans-serif; font-size: 10.5pt; color: #222222; line-height: 1.55;">
        <div style="text-align: center; color: #8e1f7c; font-size: 13pt; font-weight: bold; font-style: italic; margin-bottom: 14px;">
            ¡Que tengas el mejor de los días!
        </div>
        ' . $bloquesHtml . '
    </div>';

    $pdf->writeHTML($html, true, false, true, false, '');

    return $pdf->Output($rutaSalida, $dest);
}
