<?php
    include("database/session.php"); //verifica si tienes o no una sesion iniciada
    include("database/publicaciones.php"); //muestra las publicaciones
    include("login.php"); //ventanas emergented de inicio de sesion y registro de usuario
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentNono | Inicio</title>
    <link rel="stylesheet" href="estilos/estilo.css">
    <link rel="stylesheet" href="estilos/publicaciones.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Poppins:wght@700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- BARRA DE NAVEGACION PRINCIPAL -->
    <header class="main-header">
        <div class="container header-content">
            <h1 class="site-logo"><a href="index.php">RentNono</a></h1>
            <nav class="main-nav">
                <ul>
                    <li><b href="#" class="btn-primary-small" href="index.php">Inicio</b></li>
                    <li><a href="explorador.php">Explorar</a></li>
                    <li><a href="nosotros.php">Nosotros</a></li>
                    
                    <!-- NOMBRE DE USUARIO O BOTON INICIAR SESION-->
                    <?php if(isset($_SESSION['nombre'])): ?>
                        <li>Bienvenido, <?php echo $_SESSION['nombre']; ?></li>
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
                <a href="#" class="btn-primary-large">Alquilar</a>
                <a href="#" class="btn-primary-large">Comprar</a>
                <a href="#" class="btn-primary-large">Vender</a>
                <section class="features-section container">
                    <div class="features-grid">
                        <div class="feature-item">
                            <p>Accede como usuario registrado y podras comentar</p>
                        </div>
                        <div class="feature-item">
                            <p>Contactate con el propietrario</p>
                        </div>
                        <div class="feature-item">
                            <p>Crea tu lista de favoritos</p>
                        </div>
                    </div>
                </section>            
            </div>
            <div class="search-box">
                <input list="opciones" type="text" id="buscar" placeholder="Escribe para buscar...">
                <datalist id="opciones">
                    <option value="Casa en alquiler">
                    <option value="Departamento en venta">
                    <option value="Terreno en Nonogasta">
                    <option value="Oficina comercial">
                    <option value="Cabaña turística">
                </datalist>
                <button type="button" class="icon" id="btnBuscar">🔍</button>
            </div>
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

<?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
<div id="toast" class="toast-success">
  <div class="icon-container">
    <i class="fa-solid fa-circle-check"></i>
    <span>Sesión cerrada correctamente</span>
  </div>
</div>

<script>
  // Mostrar el toast
  const toast = document.getElementById('toast');
  toast.style.display = 'flex';
  toast.classList.add('show');

  // Desvanecer y eliminar después de 3 segundos
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 600);
  }, 3000);
</script>

<style>
.toast-success {
  position: fixed;
  bottom: 30px;
  right: 30px;
  background-color: #82b16d; /* Verde principal RENTNONO */
  color: #fff;
  font-family: 'Poppins', sans-serif;
  padding: 14px 22px;
  border-radius: 12px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.15);
  display: none;
  align-items: center;
  gap: 10px;
  z-index: 9999;
  opacity: 0;
  transform: translateY(20px);
  transition: all 0.6s ease;
}

.toast-success.show {
  display: flex;
  opacity: 1;
  transform: translateY(0);
}

.icon-container {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 500;
}

.toast-success i {
  font-size: 1.3em;
  color: #fff;
  animation: spinCheck 1s ease-in-out;
}

/* ✨ Animación de giro del icono */
@keyframes spinCheck {
  0% { transform: rotate(0deg) scale(0.8); opacity: 0; }
  50% { transform: rotate(180deg) scale(1.2); opacity: 1; }
  100% { transform: rotate(360deg) scale(1); opacity: 1; }
}
</style>
<?php endif; ?>



</body>
</html>
