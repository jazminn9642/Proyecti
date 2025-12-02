<?php
ob_start(); // inicia el buffer
include("database/session.php"); //verifica si tienes o no una sesion iniciada
include("database/publicaciones.php"); //muestra las publicaciones
include("login.php"); //ventanas emergented de inicio de sesion y registro de usuario

if (isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'visitante') {
        header("Location: usuario_visitante/ixusuario.php");
        exit;
    } elseif ($_SESSION['rol'] === 'propietario') {
        header("Location: usuario_propietario/index_propietario.php");
        exit;
    }
}
ob_end_flush(); // envía el buffer al navegador
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentNono | Inicio</title>
    <link rel="stylesheet" href="estilos/estilo.css">
    <link rel="stylesheet" href="estilos/publicaciones.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Poppins:wght@700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- BARRA DE NAVEGACION PRINCIPAL -->
    <header class="main-header">
        <div class="container header-content">
            <h1 class="site-logo">
                <?php if(isset($_SESSION['nombre'])): ?>
                    <a href="index.php">Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></a>
                <?php else: ?>
                    <a href="index.php">RentNono</a>
                <?php endif; ?>
            </h1>

            <nav class="main-nav">
                <ul>
                    <li><b href="#" class="btn-primary-small" href="index.php">Inicio</b></li>
                    <li><a href="explorador.php">Explorar Propiedades</a></li>
                    <li><a href="nosotros.php">Nosotros</a></li>
                    
                    <!-- NOMBRE DE USUARIO O BOTON INICIAR SESION-->
                    <?php if(isset($_SESSION['nombre'])): ?>
                       
                        <li><a href="database/logout.php">Cerrar sesión</a></li>
                    <?php else: ?>
                        <a id="abrirLogin" class="btn-iniciar-sesion">Iniciar sesión</a>
                    <?php endif; ?>
            </nav>
        </div>
    </header>
 
    <main>
        <!--SECCION DE PRESENTACION-->
        <section class="hero-section">
            <div class="hero-text-content">
                <h2>Encontrá tu hogar
                    en Nonogasta</h2>
                <p>Una plataforma simple e intuitiva para que alquiles y des en alquiler tus objetos y propiedades de 
                    forma segura y eficiente.</p>              
        
        <!-- 🔍 BUSCADOR POR PRECIO -->
<section class="buscador-precio container" style="margin-top:30px;">
    <h3>Filtrar por precio</h3>

    <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
        <div>
            <label>Precio mínimo</label>
            <input type="number" id="precio_min" placeholder="Ej: 100000" style="padding:8px;">
        </div>

        <div>
            <label>Precio máximo</label>
            <input type="number" id="precio_max" placeholder="Ej: 300000" style="padding:8px;">
        </div>

        <button id="btnFiltrar" style="padding:10px 20px; cursor:pointer; background:#2d6cdf; border:none; color:white; border-radius:5px;">
            Aplicar filtros
        </button>

        <button id="btnReset" style="padding:10px 20px; cursor:pointer; background:#777; border:none; color:white; border-radius:5px;">
            Reiniciar
        </button>
    </div>
</section>


<section class="features-section container" style="margin-top:20px;">
    <h3>Publicaciones</h3>

    <div class="features-grid" id="gridIndex"></div>

    <p id="mensajeVacio" style="display:none; text-align:center; padding:20px;">
        No existen publicaciones en ese rango de precio.
    </p>
</section>
</section>

        <!--SECCION DE PUBLICACIONES-->
        <section class="features-section container">
            <h3>Publicaciones mas visitadas</h3>
            <div class="features-grid">
                <?php if (count($publicaciones) > 0): ?>
                    <?php foreach ($publicaciones as $pub): ?>
                        <div class="feature-item">
                            <img src="media/publicaciones/<?php echo htmlspecialchars($pub['imagen']); ?>" alt="Imagen de <?php echo htmlspecialchars($pub['titulo']); ?>">
                            <h4><?php echo htmlspecialchars($pub['titulo']); ?></h4>
                            <p><?php echo htmlspecialchars($pub['descripcion']); ?></p>
                            <p><strong>Precio:</strong> $<?php echo number_format($pub['precio'], 2); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No hay publicaciones disponibles.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <!--PIE DE PAGINA-->
    <footer class="main-footer">
        <div class="container footer-content">
            <p>&copy; 2025 Rentnono. Todos los derechos reservados.</p>
            <ul class="footer-links">
                <li><a href="#">Términos y Condiciones</a></li>
                <li><a href="#">Política de Privacidad</a></li>
            </ul>
        </div>
    </footer>
    
    <!--HABILITA VENTANAS FLOTANTES DE LOGIN Y REGISTRO-->
    <script src="script/login.js"></script>
    <script src="script/infopub.js"></script>

    <!--HABILITA VENTANA FLOTANTE DE MENSAJE DE USUARIO CREADO-->
    <script>
        window.addEventListener("DOMContentLoaded", function() {
            const mensajeExito = document.getElementById("mensajeExito");

            <?php if (isset($_GET['registro']) && $_GET['registro'] === "ok"): ?>
                mensajeExito.style.display = "flex";

                // Ocultar después de 3 segundos
                setTimeout(() => {
                    mensajeExito.style.display = "none";
                }, 3000);
            <?php endif; ?>
        });
    </script>

    <script>
// Cambia esto según tu sistema de login
const usuarioLogueado = <?php echo isset($_SESSION['usuario']) ? 'true' : 'false'; ?>;

function toggleFavorito(idPublicacion) {
    if (!usuarioLogueado) {
        window.location.href = "login.php"; 
        return;
    }

    const btn = event.currentTarget;
    btn.classList.toggle("active");

    // Enviar a backend (si querés guardar favoritos de verdad)
    /*
    fetch("guardar_favorito.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "id=" + idPublicacion
    });
    */
}
</script>
<script>


// 🌟 Contenedores
const gridIndex = document.getElementById("gridIndex");
const mensajeVacio = document.getElementById("mensajeVacio");

// Inputs
const precioMin = document.getElementById("precio_min");
const precioMax = document.getElementById("precio_max");

const btnFiltrar = document.getElementById("btnFiltrar");
const btnReset = document.getElementById("btnReset");

// 🔄 Función para cargar publicaciones
function cargarPublicaciones() {

    let params = [];

    if (precioMin.value) params.push("precio_min=" + encodeURIComponent(precioMin.value));
    if (precioMax.value) params.push("precio_max=" + encodeURIComponent(precioMax.value));

    let url = "database/publicaciones.php?ajax=1&" + params.join("&");

    fetch(url)
        .then(res => res.text())
        .then(html => {
            gridIndex.innerHTML = html;

            if (html.trim() === "" || html.includes("No existen")) {
                mensajeVacio.style.display = "block";
            } else {
                mensajeVacio.style.display = "none";
            }
        });
}

// ▶️ Botón "Aplicar filtros"
btnFiltrar.addEventListener("click", cargarPublicaciones);

// 🔄 Botón "Reiniciar"
btnReset.addEventListener("click", () => {
    precioMin.value = "";
    precioMax.value = "";
    cargarPublicaciones();
});

// ▶️ Cargar al iniciar
document.addEventListener("DOMContentLoaded", cargarPublicaciones);
</script>

</body>
</html>
