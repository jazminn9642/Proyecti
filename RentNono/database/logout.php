<?php
// Este archivo cierra la sesion en caso de que este abierta
    include("session.php");
    session_destroy();
    header("Location: ../index.php");
    exit;
?>