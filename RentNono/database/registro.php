<?php
include("conexion.php");

/* ===============================
   REGISTRO DE USUARIO PROPIETARIO
================================ */
if (isset($_POST['registrar_propietario'])) {

    $nombre     = $_POST['nombre'];
    $sexo       = $_POST['sexo'];
    $dni        = $_POST['dni'];
    $fecha_nac  = $_POST['fecha_nac'];
    $correo     = $_POST['correo'];
    $telefono   = $_POST['telefono'];
    $password   = $_POST['password'];

    try {
        $sql = "INSERT INTO usuario_propietario 
                (nombre, sexo, dni, fecha_nac, correo, telefono, password)
                VALUES 
                (:nombre, :sexo, :dni, :fecha_nac, :correo, :telefono, :password)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':nombre'     => $nombre,
            ':sexo'       => $sexo,
            ':dni'        => $dni,
            ':fecha_nac'  => $fecha_nac,
            ':correo'     => $correo,
            ':telefono'   => $telefono,
            ':password'   => $password
        ]);

        header("Location: login.php?registro=ok");
        exit;

    } catch (PDOException $e) {
        echo "Error al registrar propietario: " . $e->getMessage();
    }
}


/* ===============================
   REGISTRO DE USUARIO VISITANTE
================================ */
if (isset($_POST['registrar_visitante'])) {

    $nombre   = $_POST['nombre'];
    $correo   = $_POST['correo'];
    $password = $_POST['password'];

    try {
        $sql = "INSERT INTO usuario_visitante (nombre, correo, password)
                VALUES (:nombre, :correo, :password)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':nombre'   => $nombre,
            ':correo'   => $correo,
            ':password' => $password
        ]);

        header("Location: login.php?registro=ok");
        exit;

    } catch (PDOException $e) {
        echo "Error al registrar visitante: " . $e->getMessage();
    }
}
?>
