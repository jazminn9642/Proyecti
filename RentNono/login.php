<?php
    include("database/registro.php");
    include("database/inicio_sesion.php");

    $error = isset($_GET['error']) ? $_GET['error'] : "";
    
?>

<link rel="stylesheet" href="estilos/login.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Ventana Login -->
<div id="modalFondoLogin" class="modal-fondo">
    <div class="modal-contenido">
        <span id="cerrarLogin" class="cerrar">&times;</span>
        <h2>BIENVENIDO</h2>

        <?php if (isset($_GET['error'])): ?>
            <div class="error-login">
                Correo o contraseña incorrectos
            </div>
            <script>
                window.addEventListener("DOMContentLoaded", function() {
                    document.getElementById("modalFondoLogin").style.display = "flex";
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="database/inicio_sesion.php" id="formLogin">
            <label for="usuarioLogin">Correo electrónico:</label>
            <input type="email" id="usuarioLogin" name="correo" required>
            <small id="errorLoginCorreo" style="color:red;display:none;">Ingrese un correo electrónico válido.</small>

            <label for="passwordLogin">Contraseña:</label>
            <input type="password" id="passwordLogin" name="password" required>

            <button type="submit" name="iniciarSesion">Entrar</button>

            <p>¿Aún no tienes cuenta?</p>
            <button type="button" id="abrirRegistroPropietario" class="btn-registrar">Registrarme como Propietario</button>
            <button type="button" id="abrirRegistroVisitante" class="btn-registrar">Registrarme como Visitante</button>
        </form>
    </div>
</div>

<!-- Ventana Registro Visitante-->
<div id="modalFondoRegistroVisitante" class="modal-fondo">
    <div class="modal-contenido">
        <span id="cerrarRegistroVisitante" class="cerrar">&times;</span>
        <form method="POST" autocomplete="off" id="formRegistroVisitante">
            <h2>REGISTRO</h2>
            <div class="input-group">

                <div class="input-container">
                    <input type="text" name="nombre" placeholder="Nombre Completo" required>
                    <i class="fa-solid fa-user"></i>
                </div>

                <div class="input-container">
                    <input type="email" id="correoVisitante" name="correo" placeholder="Correo Electrónico" required>
                    <i class="fa-solid fa-envelope"></i>
                    <small id="errorCorreoVisitante" style="color:red;display:none;">Debe ingresar un correo válido con @.</small>
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

<!-- Ventana Registro Propietario-->
<div id="modalFondoRegistroPropietario" class="modal-fondo">
    <div class="modal-contenido">
        <span id="cerrarRegistroPropietario" class="cerrar">&times;</span>
        <form method="POST" autocomplete="off" id="formRegistroPropietario">
            <h2>REGISTRO</h2>
            <div class="input-group">

                <div class="input-container">
                    <input type="text" name="nombre" placeholder="Nombre Completo" required>
                    <i class="fa-solid fa-user"></i>
                </div>

                <div class="input-container">
                    <select name="sexo" required>
                        <option value="" disabled selected>Selecciona tu sexo</option>
                        <option value="masculino">Masculino</option>
                        <option value="femenino">Femenino</option>
                    </select>
                </div>

                <div class="input-container">
                    <input type="text" id="dniPropietario" name="dni" placeholder="DNI" required maxlength="8" pattern="[0-9]{7,8}" inputmode="numeric">
                    <i class="fa-solid fa-id-card"></i>
                    <small id="errorDniPropietario" style="color:red;display:none;">El DNI debe tener entre 7 y 8 números.</small>
                </div>

                <div class="input-container">
                    <input type="email" id="correoPropietario" name="correo" placeholder="Correo Electrónico" required>
                    <i class="fa-solid fa-envelope"></i>
                    <small id="errorCorreoPropietario" style="color:red;display:none;">Debe ingresar un correo válido con @.</small>
                </div>

                <div class="input-container">
                    <input type="tel" id="telefonoPropietario" name="telefono" placeholder="Teléfono" required maxlength="13" inputmode="numeric">
                    <i class="fa-solid fa-phone"></i>
                    <small id="errorTelPropietario" style="color:red;display:none;">Ingrese un teléfono válido (ej: 3825 40-7398).</small>
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

<!-- Mensaje Exitoso -->
<div id="mensajeExito" class="mensaje-exito">
    ¡Usuario creado exitosamente!
</div>

<script>
function validarCorreo(campoCorreo, errorLabel) {
    const correo = campoCorreo.value.trim();
    const regexCorreo = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
    if (correo === "" || !regexCorreo.test(correo)) {
        errorLabel.style.display = "block";
        return false;
    } else {
        errorLabel.style.display = "none";
        return true;
    }
}

function validarDNI(input, errorLabel) {
    const valor = input.value.trim();
    if (!/^\d{7,8}$/.test(valor)){
        errorLabel.style.display = "block";
        return false;
    } else {
        errorLabel.style.display = "none";
        return true;
    }
}

function validarTelefono(input, errorLabel) {
    const valor = input.value.trim();
    if (!/^\d{4}\s\d{2}-\d{4}$/.test(valor)) {
        errorLabel.style.display = "block";
        return false;
    } else {
        errorLabel.style.display = "none";
        return true;
    }
}

// Aplicar formato al teléfono mientras se escribe
function formatearTelefono(input) {
    let valor = input.value.replace(/\D/g, ""); // quitar no numéricos
    if (valor.length > 4 && valor.length <= 6)
        valor = valor.replace(/(\d{4})(\d+)/, "$1 $2");
    else if (valor.length > 6)
        valor = valor.replace(/(\d{4})(\d{2})(\d{0,4})/, "$1 $2-$3");
    input.value = valor.trim();
}

// Quitar flechitas ↑↓ de los inputs numéricos
document.querySelectorAll('input[type="number"], input[type="text"][inputmode="numeric"], input[type="tel"]').forEach(input => {
    input.addEventListener("keydown", e => {
        if (e.key === "ArrowUp" || e.key === "ArrowDown") e.preventDefault();
    });
});

// Eventos de formato en teléfono
["telefonoVisitante", "telefonoPropietario"].forEach(id => {
    const telInput = document.getElementById(id);
    telInput.addEventListener("input", function() {
        formatearTelefono(this);
    });
});

// Validar correos al salir del campo
document.getElementById("correoVisitante").addEventListener("blur", function() {
    validarCorreo(this, document.getElementById("errorCorreoVisitante"));
});
document.getElementById("correoPropietario").addEventListener("blur", function() {
    validarCorreo(this, document.getElementById("errorCorreoPropietario"));
});
document.getElementById("usuarioLogin").addEventListener("blur", function() {
    validarCorreo(this, document.getElementById("errorLoginCorreo"));
});

// Validar DNI y Teléfonos al salir del campo
["dniVisitante", "dniPropietario"].forEach(id => {
    const dniInput = document.getElementById(id);
    dniInput.addEventListener("blur", function() {
        validarDNI(this, document.getElementById("error" + id.charAt(0).toUpperCase() + id.slice(1)));
    });
});

["telefonoVisitante", "telefonoPropietario"].forEach(id => {
    const telInput = document.getElementById(id);
    telInput.addEventListener("blur", function() {
        validarTelefono(this, document.getElementById("error" + id.charAt(0).toUpperCase() + id.slice(1)));
    });
});

// Validación final antes de enviar formularios
["formLogin", "formRegistroVisitante", "formRegistroPropietario"].forEach(formId => {
    document.getElementById(formId).addEventListener("submit", function(e) {
        let valido = true;
        const correoInput = this.querySelector('input[type="email"]');
        const errorCorreo = this.querySelector('small[id^="errorCorreo"]');
        if (!validarCorreo(correoInput, errorCorreo)) valido = false;

        const dniInput = this.querySelector('input[name="dni"]');
        const telInput = this.querySelector('input[name="telefono"]');
        const errorDni = this.querySelector('small[id^="errorDni"]');
        const errorTel = this.querySelector('small[id^="errorTel"]');

        if (dniInput && !validarDNI(dniInput, errorDni)) valido = false;
        if (telInput && !validarTelefono(telInput, errorTel)) valido = false;

        if (!valido) e.preventDefault();
    });
});
// ABRIR MODAL DESDE EL CORAZÓN
document.addEventListener("click", function (e) {

    // Si el elemento clickeado tiene la clase btn-fav
    if (e.target.classList.contains("btn-fav")) {
        const modal = document.getElementById("modalLogin");
        modal.style.display = "flex";  // Mostrar ventana emergente
    }
});

</script>

