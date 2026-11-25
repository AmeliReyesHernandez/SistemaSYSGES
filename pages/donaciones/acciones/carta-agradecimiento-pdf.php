<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!class_exists('CartaAgradecimientoPDF')) {
    class CartaAgradecimientoPDF extends TCPDF {
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
 * Construye el PDF oficial de la Carta de Agradecimiento.
 *
 * @param array $opciones [
 *   'saludoNombre'    => string,
 *   'fechaCarta'      => string,
 *   'detalleDonativo' => string (opcional, ej. " de $500.00 MXN (Efectivo)"),
 *   'idDonativo'      => int|null,
 *   'textoPersonalizado' => string|null (opcional, si viene de redactar correo manual)
 * ]
 * @param string $dest 'I' (inline), 'S' (string binario), 'F' (archivo en $rutaSalida)
 * @param string $rutaSalida Nombre o ruta del archivo
 * @return string|null
 */
function generarCartaAgradecimientoPDF(array $opciones, string $dest = 'S', string $rutaSalida = 'Carta_Agradecimiento.pdf') {
    $saludoNombre       = trim($opciones['saludoNombre'] ?? 'Donante');
    $fechaCarta         = trim($opciones['fechaCarta'] ?? '');
    $detalleDonativo    = $opciones['detalleDonativo'] ?? '';
    $idDonativo         = $opciones['idDonativo'] ?? null;
    $textoPersonalizado = $opciones['textoPersonalizado'] ?? null;

    if ($fechaCarta === '') {
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $fechaCarta = 'Oaxaca de Juárez, Oaxaca a ' . date('j') . ' de ' . $meses[(int)date('n')] . ' de ' . date('Y');
    }

    $pdf = new CartaAgradecimientoPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('SYSGES - GESMujer');
    $pdf->SetAuthor('GESMujer Oaxaca');
    $pdf->SetTitle('Carta de Agradecimiento' . ($idDonativo ? ' - Folio #' . $idDonativo : ''));
    $pdf->SetSubject('Agradecimiento por Donativo');

    $pdf->SetMargins(20, 44, 20);
    $pdf->SetHeaderMargin(8);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 26);
    $pdf->AddPage();

    if ($textoPersonalizado !== null && trim($textoPersonalizado) !== '') {
        // Reemplazar comodines por el nombre real
        $saludoPila = !empty($saludoNombre) ? explode(' ', $saludoNombre)[0] : $saludoNombre;
        $textoLimpiado = str_replace(
            ['[Nombre del Donante]', '[Nombre]', '[nombre]', '{Nombre}'],
            $saludoPila ?: $saludoNombre,
            trim($textoPersonalizado)
        );
        $lineas = preg_split("/\r\n|\n|\r/", $textoLimpiado);
        $bloquesHtml = '';
        $parrafos = preg_split("/(\r\n|\n|\r){2,}/", $textoLimpiado);

        foreach ($parrafos as $idx => $p) {
            $pTrim = trim($p);
            if ($pTrim === '') continue;
            $pHtml = nl2br(htmlspecialchars($pTrim));
            if ($idx === 0 && stripos($pTrim, 'Oaxaca de') === 0) {
                $bloquesHtml .= '<div style="text-align: right; margin-bottom: 22px; color: #333333; font-size: 10pt;">' . $pHtml . '</div>';
            } elseif (stripos($pTrim, 'Querido(a)') === 0 || stripos($pTrim, 'Estimado(a)') === 0) {
                $bloquesHtml .= '<div style="margin-bottom: 18px; font-size: 11pt; color: #111111;"><strong>' . $pHtml . '</strong></div>';
            } elseif (stripos($pTrim, 'Con aprecio') === 0 || stripos($pTrim, 'Atentamente') === 0 || stripos($pTrim, 'María del Rosario') !== false) {
                $bloquesHtml .= '<p style="margin: 0 0 12px 0; line-height: 1.4;">' . $pHtml . '</p>';
            } else {
                $bloquesHtml .= '<p style="text-align: justify; margin-bottom: 14px; text-indent: 0;">' . $pHtml . '</p>';
            }
        }

        $htmlCarta = '<div style="font-family: helvetica, Arial, sans-serif; font-size: 10.5pt; color: #222222; line-height: 1.55;">' . $bloquesHtml . '</div>';
    } else {
        $htmlCarta = '
        <div style="font-family: helvetica, Arial, sans-serif; font-size: 10.5pt; color: #222222; line-height: 1.55;">
            <div style="text-align: right; margin-bottom: 22px; color: #333333; font-size: 10pt;">
                ' . htmlspecialchars($fechaCarta) . '
            </div>
            <div style="margin-bottom: 18px; font-size: 11pt; color: #111111;">
                <strong>Querido(a) ' . htmlspecialchars($saludoNombre) . ', ¡gracias por sumarte a transformar vidas en Oaxaca!</strong>
            </div>
            <p style="text-align: justify; margin-bottom: 14px; text-indent: 0;">
                A nombre de todas las personas que formamos parte del Grupo de Estudios sobre la Mujer Rosario Castellanos, GESMujer, quiero expresarte nuestro más sincero agradecimiento por tu generoso donativo' . htmlspecialchars($detalleDonativo) . '.
            </p>
            <p style="text-align: justify; margin-bottom: 14px; text-indent: 0;">
                Acciones como la tuya hacen posible que sigamos caminando junto a mujeres, jóvenes y niñas de diversas comunidades indígenas y rurales del estado de Oaxaca. Gracias a tu valioso respaldo, podemos fortalecer nuestras acciones de acompañamiento, formación, defensa de sus derechos e impulso a su autonomía, brindándoles herramientas para construir proyectos de vida con dignidad, justicia y libres de violencia.
            </p>
            <p style="text-align: justify; margin-bottom: 14px; text-indent: 0;">
                En GESMujer estamos convencidas de que la transformación comunitaria se logra uniendo voluntades. Tu contribución no solo representa un apoyo económico indispensable, sino también un mensaje directo de esperanza y solidaridad para quienes más lo necesitan.
            </p>
            <p style="text-align: justify; margin-bottom: 14px; text-indent: 0;">
                Nos comprometemos firmemente a administrar tu aportación con total transparencia y con la dedicación que nos ha caracterizado por más de cuatro décadas de trabajo en favor de las mujeres oaxaqueñas.
            </p>
            <p style="text-align: justify; margin-bottom: 26px; text-indent: 0;">
                Te reiteramos nuestra gratitud y dejamos nuestras puertas abiertas para mantenerte informado sobre el impacto y los logros que tu donativo ayuda a construir día con día.
            </p>
            <div style="margin-top: 15px;">
                <p style="margin: 0 0 16px 0;">Con aprecio y respeto,</p>
                <p style="margin: 0; line-height: 1.3;">
                    <strong>María del Rosario Martínez Miguel</strong><br>
                    <span style="color: #444444; font-size: 9.5pt;">Grupo de Estudios sobre la Mujer Rosario Castellanos (GESMujer)</span>
                </p>
            </div>
        </div>';
    }

    $pdf->writeHTML($htmlCarta, true, false, true, false, '');

    return $pdf->Output($rutaSalida, $dest);
}
