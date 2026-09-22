<?php
ob_start();
require_once __DIR__ . '/../header.php';
$html = ob_get_clean();

// Corregir rutas relativas diseñadas para nivel 1 al nivel 2 de pages/donaciones/
$html = str_replace(
    [
        'href="./../pages/',
        'href="../pages/',
        'href="../checkout/',
        'href="../sign-in/',
        'href="./../',
        'src="../assets/',
        'src="../uploads/'
    ],
    [
        'href="../../pages/',
        'href="../../pages/',
        'href="../../checkout/',
        'href="../../sign-in/',
        'href="../../',
        'src="../../assets/',
        'src="../../uploads/'
    ],
    $html
);

echo $html;
?>
