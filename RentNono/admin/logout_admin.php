<?php
session_start();

// Destruir todas las variables de sesión
$_SESSION = [];

// Destruir la sesión completamente
session_destroy();

// Evitar volver con las flechas del navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirigir al inicio de la página principal con un parámetro
header("Location: ../index.php?logout=success");
exit;
?>

