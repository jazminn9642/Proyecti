<?php
    //ESTE ARCHIVO ESTABLECE LA CONEXION CON LA BASE DE DATOS con PDO
    //Valores de XAMPP predeterminados son:
    //host=localhost
    //base de datos= uno la selecciona
    //usuario = root
    //contraseña = (vacio)
    try{
        //new PDO("mysql:host=LOCALHOST;dbname=NOMBREDEBASEDEDATOS;charset=utf8", "usuario","contraseña")
        $conn = new PDO("mysql:host=localhost;dbname=rentnono;charset=utf8", "root","");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }catch (PDOException $e){
        die('conexion de error:' . $e->getMessage());
    }
?>