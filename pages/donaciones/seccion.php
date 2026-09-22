<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Verificar si el usuario ha iniciado sesión y tiene el rol adecuado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../../sign-in/index.php");
    exit();
}
?>
