<?php
include 'conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';

    // Buscar al usuario en la tabla de administradores
    $stmt = $conn->prepare("SELECT * FROM usuario_admin WHERE correo = :correo");
    $stmt->execute([':correo' => $correo]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario['password_hash'])) {
        // ✅ Guardar datos en la sesión
        $_SESSION['admin_id'] = $usuario['id'];
        $_SESSION['admin_nombre'] = $usuario['nombre']; // ← guarda el nombre real
        $_SESSION['admin_correo'] = $usuario['correo'];

        header("Location: ../admin/users.php");
        exit;
    } else {
        header("Location: ../login.php?error=1");
        exit;
    }
}
?>
