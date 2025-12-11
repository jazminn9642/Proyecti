<?php
include("database/publicaciones.php");
include("database/session.php");
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
    <script src="https://kit.fontawesome.com/a2d9a66f09.js" crossorigin="anonymous"></script>
</head>
<body>

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
                    <li><a href="index.php">Inicio</a></li>
                    <li><b href="#" class="btn-primary-small" href="explorador.php">Explorar Propiedades</b></li>
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


<!-- 🏡 FILTROS -->
<section class="filtros container">
  <h3 class="titulo-filtros">Filtrar propiedades</h3>
  <form id="filtrosForm" class="filtros-form">
    <div class="fila-filtros">
      <div class="campo-filtro">
        <label><i class="fa-solid fa-handshake"></i> Operación</label>
        <select name="operacion" id="operacion">
          <option value="">Todos</option>
          <option value="alquiler">Alquiler</option>
          <option value="venta">Venta</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-house"></i> Tipo de Propiedad</label>
        <select name="tipo" id="tipo">
          <option value="">Todos</option>
          <option value="casa">Casa</option>
          <option value="departamento">Departamento</option>
          <option value="terreno o lote">Terreno o Lote</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-building"></i> Estado</label>
        <select name="estado" id="estado">
          <option value="">Todos</option>
          <option value="usado">Usado</option>
          <option value="a estrenar">A Estrenar</option>
          <option value="en construccion">En Construcción</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-car"></i> Garaje</label>
        <select name="garaje" id="garaje">
          <option value="">Todos</option>
          <option value="1">Sí</option>
          <option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="fila-filtros">
      <div class="campo-filtro">
        <label><i class="fa-solid fa-dollar-sign"></i> Precio Máximo</label>
        <select name="precio_max" id="precio_max">
          <option value="">Todos</option>
          <option value="100000">$100.000</option>
          <option value="200000">$200.000</option>
          <option value="300000">$300.000</option>
          <option value="400000">$400.000</option>
          <option value="500000">$500.000</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-door-open"></i> Ambientes</label>
        <select name="ambientes" id="ambientes">
          <option value="">Todos</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">Más de 5</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-bed"></i> Dormitorios</label>
        <select name="dormitorios" id="dormitorios">
          <option value="">Todos</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">Más de 5</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-bath"></i> Baños</label>
        <select name="sanitarios" id="sanitarios">
          <option value="">Todos</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">Más de 3</option>
        </select>
      </div>
    </div>

    <div class="botones-filtros">
      <button type="button" class="btn-reiniciar" id="reiniciarFiltros">
        <i class="fa-solid fa-rotate-right"></i> Reiniciar filtros
      </button>
    </div>
  </form>
</section>

<!-- 🏠 PUBLICACIONES FILTRADAS -->
<section class="features-section container">
  <div class="features-grid" id="featuresGrid">
    <!-- Se llenará dinámicamente con fetch -->
  </div>
  <p id="noResultsMessage" style="display:none;text-align:center;padding:20px;">No existen publicaciones que coincidan con tu búsqueda</p>
</section>

<footer class="main-footer">
  <div class="container footer-content">
    <p>&copy; 2025 RentNono. Todos los derechos reservados.</p>
    <ul class="footer-links">
      <li><a href="#">Términos y Condiciones</a></li>
      <li><a href="#">Política de Privacidad</a></li>
    </ul>
  </div>
</footer>

<!-- ⚙️ Script de filtros, búsqueda y reinicio -->
<script>
const filtros = ['operacion','tipo','estado','garaje','precio_max','ambientes','dormitorios','sanitarios'];
const featuresGrid = document.getElementById('featuresGrid');
const reiniciarBtn = document.getElementById('reiniciarFiltros');
const noResultsMessage = document.getElementById('noResultsMessage');

// 🎯 Cargar publicaciones filtradas
function cargarPublicaciones() {
    let params = filtros.map(f => {
        const val = document.getElementById(f).value;
        return val ? `${f}=${encodeURIComponent(val)}` : '';
    }).filter(p => p !== '').join('&');

    fetch('database/publicaciones.php?ajax=1&' + params)
        .then(res => res.text())
        .then(html => {
            featuresGrid.innerHTML = html;

            featuresGrid.style.opacity = 0;
            setTimeout(() => {
                featuresGrid.style.opacity = 1;
                featuresGrid.style.transition = 'opacity 0.4s ease';
            }, 50);

            if(html.trim() === '' || html.includes('No se encontraron resultados')) {
                noResultsMessage.style.display = 'block';
            } else {
                noResultsMessage.style.display = 'none';
            }
        })
        .catch(err => console.error('Error al cargar publicaciones:', err));
}

// Cambios en los filtros
filtros.forEach(f => {
    const el = document.getElementById(f);
    if(el) el.addEventListener('change', cargarPublicaciones);
});

// Botón reiniciar
reiniciarBtn.addEventListener('click', () => {
    filtros.forEach(f => document.getElementById(f).value = '');
    cargarPublicaciones();
});

// Carga inicial
document.addEventListener('DOMContentLoaded', cargarPublicaciones);
</script>


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

</body>
</html>
