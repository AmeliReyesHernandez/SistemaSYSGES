<?php
// conexion.php
$host = 'localhost';
$db = 'gesmujer_oaxaca';
$user = 'gesmujer_sigdadmin';
$pass = 'uU362-&Hfd';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error en la conexión: " . $e->getMessage());
}
?>
