<?php

include("conexion.php");

/*REGISTRO USUARIO PROPIETARIO*/
if(isset($_POST['enviarRegistroPropietario'])) {
    $nombre = $_POST['nombre'];
    $sexo = $_POST['sexo'];
    $dni = $_POST['dni'];
    $correo = $_POST['correo'];
    $telefono = $_POST['phone'];
    $pwd = $_POST['password'];

    $consultaSQL = $conn->query("INSERT INTO usuario_propietario(nombre, sexo, dni, correo, telefono, password) VALUES ('$nombre','$sexo','$dni','$correo','$telefono','$pwd')");
    
    header("Location: index.php?registro=ok");
    exit;
}

/*REGISTRO USUARIO VISITANTE*/
if(isset($_POST['enviarRegistroVisitante'])) {
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $pwd = $_POST['password'];

    $consultaSQL = $conn->query("INSERT INTO usuario_visitante(nombre, correo, password) VALUES ('$nombre','$correo','$pwd')");
    
    // Después de insertar en la base de datos
    header("Location: index.php?registro=ok");
    exit();
}

?>
