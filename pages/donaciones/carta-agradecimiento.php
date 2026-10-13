<?php
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/seccion.php';
require_once __DIR__ . '/../../db/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Validar parámetro de donativo
$idDonativo = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$idDonativo) {
    die("Error: No se proporcionó un ID de donativo válido.");
}

try {
    $stmt = $conn->prepare("
        SELECT d.ID_Donativo, d.MontoDonacion, d.TipoDonacion, d.DescripcionEspecie, d.FechaDonacion,
               dn.ID_Donante, dn.Nombre, dn.ApellidoPaterno, dn.ApellidoMaterno, dn.Email, dn.Telefono
        FROM donativos d
        LEFT JOIN donantes dn ON d.ID_Donante = dn.ID_Donante
        WHERE d.ID_Donativo = :id
    ");
    $stmt->execute([':id' => $idDonativo]);
    $donativo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donativo) {
        die("Error: El donativo solicitado no existe.");
    }

    // Procesar nombre del donante
    $nombrePila = trim($donativo['Nombre'] ?? '');
    $apellidoP = trim($donativo['ApellidoPaterno'] ?? '');
    $apellidoM = trim($donativo['ApellidoMaterno'] ?? '');
    $nombreCompleto = trim("$nombrePila $apellidoP $apellidoM");

    $saludoNombre = !empty($nombrePila) ? $nombrePila : (!empty($nombreCompleto) ? $nombreCompleto : 'Donante');

    // Procesar fecha en formato formal en español
    $fechaObj = !empty($donativo['FechaDonacion']) ? new DateTime($donativo['FechaDonacion']) : new DateTime();
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dia = $fechaObj->format('j');
    $mes = $meses[(int)$fechaObj->format('n')];
    $ano = $fechaObj->format('Y');
    $fechaCarta = "Oaxaca de Juárez, Oaxaca a {$dia} de {$mes} de {$ano}";

    // Procesar detalle del donativo (especie o monetario)
    $tipo = trim($donativo['TipoDonacion'] ?? '');
    $esEspecie = ($tipo === 'En Especie' || $tipo === 'Bienes / Mobiliario' || (float)$donativo['MontoDonacion'] <= 0 || !empty($donativo['DescripcionEspecie']));

    if ($esEspecie) {
        $descEspecie = trim($donativo['DescripcionEspecie'] ?? '') ?: 'alimentos';
        $detalleDonativo = " (En Especie: {$descEspecie})";
    } else {
        $montoFormateado = number_format((float)$donativo['MontoDonacion'], 2);
        $tipoMetodo = $tipo ?: 'Efectivo';
        $detalleDonativo = " de \${$montoFormateado} MXN ({$tipoMetodo})";
    }

} catch (PDOException $e) {
    die("Error al consultar el donativo: " . $e->getMessage());
}

// Configuración de TCPDF con membrete institucional
class CartaAgradecimientoPDF extends TCPDF {
    public function Header() {
        $headerImg = realpath(__DIR__ . '/../../assets/img/membrete_header.jpg');
        if ($headerImg && file_exists($headerImg)) {
            $this->Image($headerImg, 12, 8, 186, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        } else {
            $this->SetFont('helvetica', 'B', 15);
            $this->SetTextColor(114, 24, 150);
            $this->Cell(0, 10, 'GesMujer Oaxaca', 0, 1, 'C');
        }
    }

    public function Footer() {
        $footerImg = realpath(__DIR__ . '/../../assets/img/membrete_footer.jpg');
        if ($footerImg && file_exists($footerImg)) {
            $this->Image($footerImg, 12, 272, 186, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
    }
}

$pdf = new CartaAgradecimientoPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Metadatos
$pdf->SetCreator('SYSGES - GesMujer');
$pdf->SetAuthor('GesMujer Oaxaca');
$pdf->SetTitle('Carta de Agradecimiento - Folio #' . $idDonativo);
$pdf->SetSubject('Agradecimiento por Donativo');

// Márgenes: Superior 44mm para el membrete, Inferior 26mm para el pie de página
$pdf->SetMargins(20, 44, 20);
$pdf->SetHeaderMargin(8);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 26);
$pdf->AddPage();

// Contenido HTML de la carta
$htmlCarta = '
<div style="font-family: helvetica, Arial, sans-serif; font-size: 10.5pt; color: #222222; line-height: 1.55;">

    <!-- Fecha alineada a la derecha -->
    <div style="text-align: right; margin-bottom: 22px; color: #333333; font-size: 10pt;">
        ' . htmlspecialchars($fechaCarta) . '
    </div>

    <!-- Saludo personalizado -->
    <div style="margin-bottom: 18px; font-size: 11pt; color: #111111;">
        <strong>Querido(a) ' . htmlspecialchars($saludoNombre) . ', ¡gracias por sumarte a transformar vidas en Oaxaca!</strong>
    </div>

    <!-- Párrafo 1 con detalle del donativo -->
    <p style="text-align: justify; margin-bottom: 14px; text-indent: 0;">
        A nombre de todas las personas que formamos parte del Grupo de Estudios sobre la Mujer Rosario Castellanos, GesMujer, quiero expresarte nuestro más sincero agradecimiento por tu generoso donativo' . htmlspecialchars($detalleDonativo) . '.
    </p>

    <!-- Párrafo 2 -->
    <p style="text-align: justify; margin-bottom: 14px; text-indent: 0;">
        Acciones como la tuya hacen posible que sigamos caminando junto a mujeres, jóvenes y niñas de diversas comunidades indígenas y rurales del estado de Oaxaca. Gracias a tu valioso respaldo, podemos fortalecer nuestras acciones de acompañamiento, formación, defensa de sus derechos e impulso a su autonomía, brindándoles herramientas para construir proyectos de vida con dignidad, justicia y libres de violencia.
    </p>

    <!-- Párrafo 3 -->
    <p style="text-align: justify; margin-bottom: 14px; text-indent: 0;">
        En GesMujer estamos convencidas de que la transformación comunitaria se logra uniendo voluntades. Tu contribución no solo representa un apoyo económico indispensable, sino también un mensaje directo de esperanza y solidaridad para quienes más lo necesitan.
    </p>

    <!-- Párrafo 4 -->
    <p style="text-align: justify; margin-bottom: 14px; text-indent: 0;">
        Nos comprometemos firmemente a administrar tu aportación con total transparencia y con la dedicación que nos ha caracterizado por más de cuatro décadas de trabajo en favor de las mujeres oaxaqueñas.
    </p>

    <!-- Párrafo 5 -->
    <p style="text-align: justify; margin-bottom: 26px; text-indent: 0;">
        Te reiteramos nuestra gratitud y dejamos nuestras puertas abiertas para mantenerte informado sobre el impacto y los logros que tu donativo ayuda a construir día con día.
    </p>

    <!-- Cierre y Despedida (Sin firma manuscrita) -->
    <div style="margin-top: 15px;">
        <p style="margin: 0 0 16px 0;">Con aprecio y respeto,</p>
        <p style="margin: 0; line-height: 1.3;">
            <strong>María del Rosario Martínez Miguel</strong><br>
            <span style="color: #444444; font-size: 9.5pt;">Grupo de Estudios sobre la Mujer Rosario Castellanos (GesMujer)</span>
        </p>
    </div>

</div>
';

$pdf->writeHTML($htmlCarta, true, false, true, false, '');

// Salida del PDF
$nombreArchivo = 'Carta_Agradecimiento_Folio_' . $idDonativo . '_' . preg_replace('/[^A-Za-z0-9_-]/', '', $saludoNombre) . '.pdf';
$pdf->Output($nombreArchivo, 'I');
exit();
