<?php
require_once __DIR__ . '/../pages/seccion.php';
require_once __DIR__ . '/../db/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_usuario = $_POST["id_usuario"];
    $id_proyecto = $_POST["id_proyecto"];

    try {
        $fecha_actual = date("Y-m-d");

        $sql = "INSERT INTO asignaciones (ID_Usuario, ID_Proyecto, FechaRegistro)
                VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id_usuario, $id_proyecto, $fecha_actual]);

        header("Location: ../pages/ver-proyectos-asignados.php?statuss=success");
        exit;
    } catch (PDOException $e) {
        header("Location: ../pages/ver-proyectos-asignados.php?statuss=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}
?>
