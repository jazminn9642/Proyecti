<?php
include("conexion.php");
include("session.php");

if (isset($_POST['iniciarSesion'])) {
    $correo = $_POST['correo'];
    $password = $_POST['password'];

    // 🔹 Primero buscamos en usuario_visitante (CON ESTADO)
    $stmt = $conn->prepare("SELECT id, nombre, correo, password, COALESCE(estado, 1) as estado FROM usuario_visitante
                            WHERE correo = :correo AND password = :password");
    $stmt->bindParam(':correo', $correo);
    $stmt->bindParam(':password', $password);
    $stmt->execute();

    if ($stmt->rowCount() === 1) {
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ✅ Verificar si el usuario está activo
        if ($usuario['estado'] == 0) {
            header("Location: ../index.php?error=inactivo");
            exit;
        }
        
        // ✅ Es un usuario visitante activo
        $_SESSION['id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['correo'] = $usuario['correo'];
        $_SESSION['rol'] = 'visitante';

        // 🧾 LOG: inicio de sesión visitante
        $log = $conn->prepare("
        INSERT INTO logs_actividad (usuario_id, usuario_nombre, rol, accion)
        VALUES (:id, :nombre, :rol, :accion)
        ");

        $log->execute([
        ':id' => $_SESSION['id'],
        ':nombre' => $_SESSION['nombre'],
        ':rol' => 'visitante',
        ':accion' => 'Inicio de sesión'
        ]);

        header("Location: ../usuario_visitante/ixusuario.php");
        exit;
    }

    // 🔹 Si no lo encontró, buscamos en usuario_propietario (CON ESTADO)
    $stmt2 = $conn->prepare("SELECT id, nombre, correo, password, COALESCE(estado, 1) as estado FROM usuario_propietario
                             WHERE correo = :correo AND password = :password");
    $stmt2->bindParam(':correo', $correo);
    $stmt2->bindParam(':password', $password);
    $stmt2->execute();

    if ($stmt2->rowCount() === 1) {
        $usuario = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        // ✅ Verificar si el usuario está activo
        if ($usuario['estado'] == 0) {
            header("Location: ../index.php?error=inactivo");
            exit;
        }
        
        // ✅ Es un propietario activo
        $_SESSION['id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['correo'] = $usuario['correo'];
        $_SESSION['rol'] = 'propietario';

        // 🧾 LOG: inicio de sesión propietario
        $log = $conn->prepare("
        INSERT INTO logs_actividad (usuario_id, usuario_nombre, rol, accion)
        VALUES (:id, :nombre, :rol, :accion)
        ");

        $log->execute([
        ':id' => $_SESSION['id'],
        ':nombre' => $_SESSION['nombre'],
        ':rol' => 'propietario',
        ':accion' => 'Inicio de sesión'
        ]);

        header("Location: ../usuario_propietario/index_propietario.php");
        exit;
    }

    // 🔹 Si no lo encontró, buscamos en usuario_admin (CON ESTADO)
    $stmt3 = $conn->prepare("SELECT id, nombre, correo, password_hash, COALESCE(estado, 1) as estado FROM usuario_admin
                             WHERE correo = :correo");
    $stmt3->bindParam(':correo', $correo);
    $stmt3->execute();

    if ($stmt3->rowCount() === 1) {
        $usuario = $stmt3->fetch(PDO::FETCH_ASSOC);
       
        // ⚙️ Verificar contraseña (si usás hash)
        if (password_verify($password, $usuario['password_hash'])) {
            
            // ✅ Verificar si el admin está activo
            if ($usuario['estado'] == 0) {
                header("Location: ../index.php?error=inactivo");
                exit;
            }
            
            $_SESSION['admin_id'] = $usuario['id'];
            $_SESSION['admin_nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = 'admin';

            // 🧾 LOG: inicio de sesión administrador
            $log = $conn->prepare("
            INSERT INTO logs_actividad (usuario_id, usuario_nombre, rol, accion)
            VALUES (:id, :nombre, :rol, :accion)
            ");

            $log->execute([
            ':id' => $_SESSION['admin_id'],
            ':nombre' => $_SESSION['admin_nombre'],
            ':rol' => 'admin',
            ':accion' => 'Inicio de sesión'
            ]);

            header("Location: ../admin/indexadmin.php");
            exit;
        }
    }

    // ❌ Si no se encontró en ninguna tabla
    header("Location: ../index.php?error=1");
    exit();
}
?>