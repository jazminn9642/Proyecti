<?php
session_start();
include __DIR__ . '/../database/conexion.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$id = $_SESSION['admin_id'];
$nombre = $_POST['nombre'];
$correo = $_POST['correo'];

// Subida de foto
$foto_perfil = $_SESSION['admin_foto'] ?? null;
if (!empty($_FILES['foto_perfil']['name'])) {
    $nombreArchivo = uniqid() . "_" . basename($_FILES['foto_perfil']['name']);
    $rutaDestino = __DIR__ . '/../uploads/' . $nombreArchivo;

    if (!is_dir(__DIR__ . '/../uploads')) mkdir(__DIR__ . '/../uploads', 0777, true);
    move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $rutaDestino);
    $foto_perfil = $nombreArchivo;
}

$stmt = $conn->prepare("UPDATE usuario_admin SET nombre = :nombre, correo = :correo, foto_perfil = :foto WHERE id = :id");
$stmt->execute([':nombre' => $nombre, ':correo' => $correo, ':foto' => $foto_perfil, ':id' => $id]);

// Actualizar sesión
$_SESSION['admin_nombre'] = $nombre;
$_SESSION['admin_correo'] = $correo;
$_SESSION['admin_foto'] = $foto_perfil;

header("Location: users.php");
exit;
?>
