<?php
    include("../database/session.php"); //verifica si tienes o no una sesion iniciada
    include("../database/publicaciones.php"); //muestra las publicaciones
    include("../database/session.php");


    //include("../login.php"); //ventanas emergented de inicio de sesion y registro de usuario
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentNono | Inicio</title>
    <link rel="stylesheet" href="../estilos/estilo.css">
    <link rel="stylesheet" href="../estilos/publicaciones.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Poppins:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

    <!-- BARRA DE NAVEGACION PRINCIPAL -->
    <header class="main-header">
        <div class="container header-content">
            <h1 class="site-logo">
                <?php if(isset($_SESSION['nombre'])): ?>
                    <a href="ixusuario.php">Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></a>
                <?php else: ?>
                    <a href="ixusuario.php">RentNono</a>
                <?php endif; ?>
            </h1>

            <nav class="main-nav">
                <ul>
                    <li><b href="#" class="btn-primary-small" href="ixusuario.php">Inicio</b></li>
                    <li><a href="erusuario.php">Explorar Propiedades</a></li>
                    
                    <!-- NOMBRE DE USUARIO O BOTON INICIAR SESION-->
                    <?php if(isset($_SESSION['nombre'])): ?>
                       
                        <li><a href="../database/logout.php">Cerrar sesión</a></li>
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
                    en NonogastaKJDDNFBDKNCNDAVCDNLVNDVC</h2>
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
                            <img src="../media/publicaciones/<?php echo htmlspecialchars($pub['imagen']); ?>" alt="Imagen de <?php echo htmlspecialchars($pub['titulo']); ?>">
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
    <script src="../script/login.js"></script>
    <script src="../script/infopub.js"></script>

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
document.querySelectorAll('.fav-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.id;
    btn.classList.toggle('active');
    btn.querySelector('i').classList.toggle('fas');
    btn.querySelector('i').classList.toggle('far');

    const formData = new FormData();
    formData.append('id_propiedad', id);

    const res = await fetch('/RentNono/favorito_toggle.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    console.log(data);
  });
});
</script>
 
</body>
</html>
