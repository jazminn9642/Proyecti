<?php
    include("database/session.php");
    include("database/publicaciones.php");
    include("login.php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentNono | Explorador</title>
    <link rel="stylesheet" href="estilos/estilo.css">
    <link rel="stylesheet" href="estilos/publicaciones.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Poppins:wght@700&display=swap" rel="stylesheet">
</head>
<body>

    <header class="main-header">
        <div class="container header-content">
            <h1 class="site-logo"><a href="index.php">RentNono</a></h1>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><b href="#" class="btn-primary-small" href="explorador.php">Explorar</b></li>
                    <li><a href="nosotros.php">Nosotros</a></li>

                    <?php if(isset($_SESSION['nombre'])): ?>
                        <li>Bienvenido, <?php echo $_SESSION['nombre']; ?></li>
                        <li><a href="database/logout.php">Cerrar sesión</a></li>
                    <?php else: ?>
                        <a id="abrirLogin" class="btn-iniciar-sesion">Iniciar sesión</a>
                    <?php endif; ?>
            </nav>
        </div>
    </header>
    
    <!-- BARRA DE FILTRO -->
    <section class="filtros container">
        <form method="GET" action="explorador.php" class="filtros-form">
            <div class="fila-filtros">
                <select name="operacion">
                    <option value="" disabled selected>Operación</option>
                    <option value="alquiler">Alquiler</option>
                    <option value="venta">Venta</option>
                </select>

                <select name="tipo">
                    <option value="" disabled selected>Tipo de Propiedad</option>
                    <option value="casa">Casa</option>
                    <option value="departamento">Departamento</option>
                    <option value="local comercial">Local Comercial</option>
                    <option value="terreno o lote">Terreno o Lote</option>
                    <option value="galpon">Galpón</option>
                    <option value="camping">Camping</option>
                </select>

                <select name="estado">
                    <option value="" disabled selected>Estado</option>
                    <option value="usado">Usado</option>
                    <option value="a estrenar">A Estrenar</option>
                    <option value="en construccion">En Construcción</option>
                </select>

                <select name="garaje">
                    <option value=""disabled selected>Garaje</option>
                    <option value="1">Sí</option>
                    <option value="0">No</option>
                </select>
                
            </div>

            <div class="fila-filtros">
                <select name="precio_max">
                    <option value="" disabled selected>Precio Máximo</option>
                    <option value="1000000">$100.000</option>
                    <option value="2000000">$200.000</option>
                    <option value="3000000">$300.000</option>
                    <option value="4000000">$400.000</option>
                    <option value="5000000">$500.000</option>
                </select>

                <select name="ambientes">
                    <option value="" disabled selected>Cantidad de Ambientes</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="100">Mas de 5</option>
                </select>
                
                <select name="dormitorios">
                    <option value="" disabled selected>Cantidad de Dormitorios</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="100">Mas de 5</option>
                </select>

                <select name="sanitarios">
                    <option value="" disabled selected>Cantidad de Baños</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="10">Mas de 3</option>
                </select>

                <button type="submit">Filtrar</button>
            </div>

            
        </form>
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Buscar publicaciones...">
        </div>
    </section>
    
    <!-- PUBLICACIONES FILTRADAS -->
    <section class="features-section container">
        <div class="features-grid" id="featuresGrid">
            <?php if (count($publicaciones) > 0): ?>
                <?php foreach ($publicaciones as $pub): ?>
                    <div class="feature-item" data-title="<?php echo htmlspecialchars($pub['titulo']); ?>" data-description="<?php echo htmlspecialchars($pub['descripcion']); ?>">
                        <img src="media/publicaciones/<?php echo htmlspecialchars($pub['imagen']); ?>" alt="Imagen">
                        <h4><?php echo htmlspecialchars($pub['titulo']); ?></h4>
                        <p><?php echo htmlspecialchars($pub['descripcion']); ?></p>
                        <p><strong>Precio:</strong> $<?php echo number_format($pub['precio']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Mensaje de no resultados FILTROS-->
                <p >No existen publicaciones que coincidan con tu búsqueda</p>
            <?php endif; ?>
            <!-- Mensaje de no resultados BARRA DE BUSQUEDA -->
            <p id="noResultsMessage" style="display: none;">No existen publicaciones que coincidan con tu búsqueda</p>
        </div>
    </section>
    
    <!-- ULTIMAS PUBLICACIONES -->
    <section class="features-section container">
        <h3>Últimas publicaciones</h3>
        <div class="features-grid" id="featuresGrid">
            <?php if (count($ultimasPublicaciones) > 0): ?>
                <?php foreach ($ultimasPublicaciones as $lastPub): ?>
                    <div class="feature-item">
                        <img src="media/publicaciones/<?php echo htmlspecialchars($lastPub['imagen']); ?>" alt="Imagen de <?php echo htmlspecialchars($lastPub['titulo']); ?>">
                        <h4><?php echo htmlspecialchars($lastPub['titulo']); ?></h4>
                        <p><?php echo htmlspecialchars($lastPub['descripcion']); ?></p>
                        <p><strong>Precio:</strong> $<?php echo number_format($lastPub['precio'], 2); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No hay publicaciones recientes.</p>
            <?php endif; ?>
        </div>
    </section>

    <footer class="main-footer">
        <div class="container footer-content">
            <p>&copy; 2025 Rentnono. Todos los derechos reservados.</p>
            <ul class="footer-links">
                <li><a href="#">Términos y Condiciones</a></li>
                <li><a href="#">Política de Privacidad</a></li>
            </ul>
        </div>
    </footer>
    
    <!--HABILITALA BUSQUEDA ENTIEMPO REAL Y EL MENSAJE-->
    <script>
        const searchInput = document.getElementById('searchInput');
        const featuresGrid = document.getElementById('featuresGrid');
        const featureItems = featuresGrid.getElementsByClassName('feature-item');
        const noResultsMessage = document.getElementById('noResultsMessage');

        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            let hasVisible = false;

            Array.from(featureItems).forEach(item => {
                const title = item.getAttribute('data-title').toLowerCase();
                const description = item.getAttribute('data-description').toLowerCase();
                if(title.includes(filter) || description.includes(filter)) {
                    item.style.display = '';
                    hasVisible = true;
                } else {
                    item.style.display = 'none';
                }
            });

            // Mostrar mensaje si no hay coincidencias
            if(hasVisible) {
                noResultsMessage.style.display = 'none';
            } else {
                noResultsMessage.style.display = '';
            }
        });

    </script>

    <!--HABILITA VENTANAS FLOTANTES DE LOGIN Y REGISTRO-->
    <script src="script/login.js"></script>

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
</body>
</html>