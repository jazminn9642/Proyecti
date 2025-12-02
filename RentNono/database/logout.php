<?php
include __DIR__ . '/conexion.php';
session_start();

/* =========================
   REGISTRAR LOG DE CIERRE
========================= */
if (isset($_SESSION['rol'])) {

    $usuarioId = $_SESSION['id'] 
        ?? $_SESSION['admin_id'] 
        ?? null;

    $usuarioNombre = $_SESSION['nombre'] 
        ?? $_SESSION['admin_nombre'] 
        ?? 'Desconocido';

    $rol = $_SESSION['rol'];

    $log = $conn->prepare("
        INSERT INTO logs_actividad (usuario_id, usuario_nombre, rol, accion)
        VALUES (:id, :nombre, :rol, :accion)
    ");

    $log->execute([
        ':id' => $usuarioId,
        ':nombre' => $usuarioNombre,
        ':rol' => $rol,
        ':accion' => 'Cierre de sesión'
    ]);
}

/* =========================
   CERRAR SESIÓN
========================= */
$_SESSION = [];
session_destroy();

header("Location: ../index.php");
exit;
