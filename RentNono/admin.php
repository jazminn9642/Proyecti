<?php
    include("database/session.php");
    include("login.php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentNono | Nosotros</title>
    <link rel="stylesheet" href="estilos/estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Poppins:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="main-header">
        <div class="container header-content">
            <h1 class="site-logo"><a href="index.php">RentNono</a></h1>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="explorador.php">Explorar</a></li>
                    <li><b href="#" class="btn-primary-small" href="nosotros.php">Nosotros</b></li>

                    <?php if(isset($_SESSION['nombre'])): ?>
                        <li>Bienvenido, <?php echo $_SESSION['nombre']; ?></li>
                        <li><a href="database/logout.php">Cerrar sesión</a></li>
                    <?php else: ?>
                        <a id="abrirLogin" class="btn-iniciar-sesion">Iniciar sesión</a>
                    <?php endif; ?>
            </nav>
        </div>
    </header>

    <section class="features-section container">
        <h3>Publicaciones mas visitadas</h3>
        <div class="features-grid">
            <div class="feature-item">

            </div>
            <p>No hay publicaciones disponibles.</p>
        </div>
    </section>

    <main>
        
    </main>

    <footer class="main-footer">
        <div class="container footer-content">
            <p>&copy; 2025 Rentnono. Todos los derechos reservados.</p>
            <ul class="footer-links">
                <li><a href="#">Términos y Condiciones</a></li>
                <li><a href="#">Política de Privacidad</a></li>
            </ul>
        </div>
    </footer>
</body>
</html>