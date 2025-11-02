<?php
session_start();
include __DIR__ . '/../database/conexion.php';

// Verificar sesión del admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);

    if ($id > 0 && !empty($nombre) && !empty($correo)) {
        $stmt = $conn->prepare("UPDATE usuario_admin 
                                SET nombre = :nombre, correo = :correo, telefono = :telefono 
                                WHERE id = :id");
        $stmt->execute([
            ':nombre' => $nombre,
            ':correo' => $correo,
            ':telefono' => $telefono,
            ':id' => $id
        ]);

        // Redirigir con mensaje
        header("Location: users.php?msg=actualizado");
        exit;
    } else {
        header("Location: users.php?msg=error");
        exit;
    }
} else {
    header("Location: users.php");
    exit;
}
?>
