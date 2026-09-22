<?php
ob_start();
require_once __DIR__ . '/../footer.php';
$html = ob_get_clean();

// Corregir enlaces del menú lateral para que naveguen a la raíz desde pages/donaciones/
$html = str_replace(
    [
        'href="../pages/',
        'href="../checkout/',
        'href="../sign-in/',
        'href="../justicia/',
        'href="../psicologia/',
        'href="../Soporte/',
        'src="../assets/'
    ],
    [
        'href="../../pages/',
        'href="../../checkout/',
        'href="../../sign-in/',
        'href="../../justicia/',
        'href="../../psicologia/',
        'href="../../Soporte/',
        'src="../../assets/'
    ],
    $html
);

echo $html;
?>
