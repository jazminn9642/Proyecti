<?php
    include("database/registro.php");
    include("database/inicio_sesion.php");

    $error = isset($_GET['error']) ? $_GET['error'] : "";
?>

<link rel="stylesheet" href="estilos/login.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />


<!-- LOGIN -->
<div id="modalFondoLogin" class="modal-fondo">
    <div class="modal-contenido">
        <span id="cerrarLogin" class="cerrar">&times;</span>
        <h2>BIENVENIDO</h2>

        <?php if (isset($_GET['error'])): ?>
            <div class="error-login">
                Correo o contraseña incorrectos
            </div>
            <script>
                // Forzar que la ventana flotante se abra al cargar
                window.addEventListener("DOMContentLoaded", function() {
                    document.getElementById("modalFondoLogin").style.display = "flex";
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="database/inicio_sesion.php">
            <label for="usuarioLogin">Correo electrónico:</label>
            <input type="email" id="usuarioLogin" name="correo" required>

            <label for="passwordLogin">Contraseña:</label>
            <input type="password" id="passwordLogin" name="password" required>

            <button type="submit" name="iniciarSesion" class="btn-registrar">Entrar</button>

            <p>¿Aún no tienes cuenta?</p>
            <button type="button" id="abrirRegistroPropietario" class="btn-registrar">Registrarme como Propietario</button>
            <button type="button" id="abrirRegistroVisitante" class="btn-registrar">Registrarme como Visitante</button>
        </form>
    </div>
</div>

<!-- === REGISTRO VISITANTE === -->
<div id="modalFondoRegistroVisitante" class="modal-fondo">
    <div class="modal-contenido">
        <span id="cerrarRegistroVisitante" class="cerrar">&times;</span>
        <form method="POST" autocomplete="off" id="formRegistro">
            <h2>REGISTRO</h2>
            <div class="input-group">
                <div class="input-container">
                    <input type="text" name="nombre" placeholder="Nombre Completo" required>
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="input-container">
                    <input type="email" name="correo" placeholder="Correo Electrónico" required>
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div class="input-container">
                    <input type="password" name="password" placeholder="Nueva Contraseña" required>
                    <i class="fa-solid fa-lock"></i>
                </div>
            </div>
            <input type="submit" name="enviarRegistroVisitante" value="Registrar" class="btn-registrar">
        </form>
    </div>
</div>

<!-- === REGISTRO PROPIETARIO === -->
<div id="modalFondoRegistroPropietario" class="modal-fondo">
    <div class="modal-contenido">
        <span id="cerrarRegistroPropietario" class="cerrar">&times;</span>
        <form method="POST" autocomplete="off" id="formRegistro">
            <h2>REGISTRO</h2>
            <div class="input-group">
                <div class="input-container">
                    <input type="text" name="nombre" placeholder="Nombre Completo" required>
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="input-container">
                    <select name="sexo" required>
                        <option value="sxtext" disabled selected>Selecciona tu sexo</option>
                        <option value="masculino">Masculino</option>
                        <option value="femenino">Femenino</option>
                    </select>
                    <i class="fa-solid fa-venus-mars"></i>
                </div>
                <div class="input-container">
                    <input type="text" name="dni" placeholder="DNI" required>
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <div class="input-container">
                    <input type="email" name="correo" placeholder="Correo Electrónico" required>
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div class="input-container">
                    <input type="tel" name="phone" placeholder="Teléfono" required>
                    <i class="fa-solid fa-phone"></i>
                </div>
                <div class="input-container">
                    <input type="password" name="password" placeholder="Nueva Contraseña" required>
                    <i class="fa-solid fa-lock"></i>
                </div>
            </div>
            <input type="submit" name="enviarRegistroPropietario" value="Registrar" class="btn-registrar">
        </form>
    </div>
</div>

<!-- === MENSAJE DE ÉXITO === -->
<div id="mensajeExito" class="mensaje-exito">
    ¡Usuario creado exitosamente!
</div>
