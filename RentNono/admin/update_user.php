<?php
session_start();
include __DIR__ . '/../database/conexion.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $tabla = $_POST['tabla'];
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);

    if ($id > 0 && !empty($nombre) && !empty($correo)) {
        $stmt = $conn->prepare("UPDATE $tabla SET nombre=:nombre, correo=:correo, telefono=:telefono WHERE id=:id");
        $stmt->execute([':nombre'=>$nombre, ':correo'=>$correo, ':telefono'=>$telefono, ':id'=>$id]);
        header("Location: users.php?msg=editado");
        exit;
    } else {
        header("Location: users.php?msg=error");
        exit;
    }
}
?>
