<?php
//ESTE ARCHIVO VERIFICA SI HAY UNA SESION ABIERTA
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
?>