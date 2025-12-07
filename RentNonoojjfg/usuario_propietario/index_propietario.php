<?php
include("../database/session.php");
include("../database/conexion.php");

if (isset($_POST['agregarPropiedad'])) {
    $titulo = mysqli_real_escape_string($conn, $_POST['titulo']);
    $precio = floatval($_POST['precio']);
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $operacion = mysqli_real_escape_string($conn, $_POST['operacion']);
    $superficie = intval($_POST['superficie']);
    $ambientes = intval($_POST['ambientes']);
    $dormitorios = intval($_POST['dormitorios']);
    $sanitarios = intval($_POST['sanitarios']);
    $garaje = intval($_POST['garaje']);
    $estado = mysqli_real_escape_string($conn, $_POST['estado']);
    $ubicacion = mysqli_real_escape_string($conn, $_POST['ubicacion']);
    $direccion = mysqli_real_escape_string($conn, $_POST['direccion']);
    $disponibilidad = mysqli_real_escape_string($conn, $_POST['disponibilidad']);
    $id_usuario = $_SESSION['id_usuario'] ?? null;

    if (!$id_usuario) {
        echo "<script>alert('Debes iniciar sesión para agregar una propiedad.');</script>";
    } else {
        $imagenesGuardadas = [];
        $totalArchivos = count($_FILES['imagenes']['name']);
        $maxArchivos = min($totalArchivos, 5);

        if (!file_exists("uploads")) mkdir("uploads", 0777, true);

        for ($i = 0; $i < $maxArchivos; $i++) {
            $nombreArchivo = $_FILES['imagenes']['name'][$i];
            $tmp = $_FILES['imagenes']['tmp_name'][$i];
            $rutaDestino = "uploads/" . uniqid() . "_" . basename($nombreArchivo);

            if (move_uploaded_file($tmp, $rutaDestino)) {
                $imagenesGuardadas[] = $rutaDestino;
            }
        }

        $imagenPrincipal = $imagenesGuardadas[0] ?? null;

        $sql = "INSERT INTO propiedades 
                (titulo, precio, tipo, operacion, superficie, ambientes, dormitorios, sanitarios, garaje, estado, ubicacion, direccion, disponibilidad, imagen, id_usuario)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sdsssiiiiissssi", 
            $titulo, $precio, $tipo, $operacion, $superficie, $ambientes, $dormitorios, 
            $sanitarios, $garaje, $estado, $ubicacion, $direccion, $disponibilidad, $imagenPrincipal, $id_usuario);

        if (mysqli_stmt_execute($stmt)) {
            echo "<script>alert('✅ Propiedad publicada con éxito.');</script>";
        } else {
            echo "<script>alert('❌ Error al publicar la propiedad.');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel del Propietario | RentNono</title>
    <link rel="stylesheet" href="../estilos/estilo.css">
        <link rel="stylesheet" href="estilos/publicaciones.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@700&display=swap">
<script src="https://kit.fontawesome.com/a2b0b5b2b0.js" crossorigin="anonymous"></script>
<link rel="stylesheet" href="estilo_index_propietario.css">
</head>
<body>

  <!-- BARRA DE NAVEGACION PRINCIPAL -->
    <header class="main-header">
        <div class="container header-content">
            <h1 class="site-logo">
                <?php if(isset($_SESSION['nombre'])): ?>
                    <a> Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></a>
                <?php else: ?>
                    <a href="index_propietario.php">RentNono</a>
                <?php endif; ?>
            </h1>

            <nav class="main-nav">
                <ul>
                    <li><b href="#" class="btn-primary-small" href="index_propietario.php">Inicio</b></li>
                    <li><a href="../usuario_visitante/erusuario.php">Explorar Propiedades</a></li>

                    <!-- NOMBRE DE USUARIO O BOTON INICIAR SESION-->
                    <?php if(isset($_SESSION['nombre'])): ?>
                       
                        <li><a href="../database/logout.php">Cerrar sesión</a></li>
                    <?php else: ?>
                        <a id="abrirLogin" class="btn-iniciar-sesion">Iniciar sesión</a>
                    <?php endif; ?>
            </nav>
        </div>
    </header>

<main class="main-content">
  <div class="bienvenida">
    <h2>¡Hola <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Propietario'); ?>! 👋</h2>
    <p>Bienvenido a tu panel de gestión. Aquí podés administrar tus propiedades.</p>
  </div>

  <div class="botones-panel">
    <div class="card" id="abrirModalPropiedad">
      <i class="fas fa-plus-circle"></i>
      <h3>Agregar Nueva Propiedad</h3>
      <p>Publicá una nueva casa o departamento.</p>
    </div>
    <div class="card">
      <i class="fas fa-home"></i>
      <h3>Mis Propiedades</h3>
      <p>Visualizá, editá o eliminá tus publicaciones.</p>
    </div>
    <div class="card">
      <i class="fas fa-chart-line"></i>
      <h3>Estadísticas</h3>
      <p>Consultá el rendimiento de tus propiedades.</p>
    </div>
    <div class="card">
      <i class="fas fa-comments"></i>
      <h3>Comentarios</h3>
      <p>Leé las opiniones de los interesados.</p>
    </div>
  </div>
</main>

<footer><p>&copy; 2025 RentNono. Todos los derechos reservados.</p></footer>

<!-- MODAL -->
<div id="modalAgregarPropiedad" class="modal-fondo">
  <div class="modal-contenido">
    <span id="cerrarModalPropiedad" class="cerrar">&times;</span>
    <h2>Agregar Nueva Propiedad</h2>

    <form method="POST" enctype="multipart/form-data" class="form-propiedad">
      <div class="columna">
        <label>Título</label>
        <input type="text" name="titulo" required>

        <label>Precio ($)</label>
        <input type="number" name="precio" required>

        <label>Tipo</label>
        <select name="tipo" required>
          <option value="">Seleccionar tipo...</option>
          <option value="Casa">Casa</option>
          <option value="Departamento">Departamento</option>
          <option value="Local comercial">Local comercial</option>
          <option value="Terreno">Terreno</option>
        </select>

        <label>Operación</label>
        <select name="operacion" required>
          <option value="">Seleccionar operación...</option>
          <option value="Venta">Venta</option>
          <option value="Alquiler">Alquiler</option>
        </select>
      </div>

      <div class="columna">
        <label>Superficie (m²)</label>
        <input type="number" name="superficie" required>

        <label>Ambientes</label>
        <input type="number" name="ambientes" required>

        <label>Dormitorios</label>
        <input type="number" name="dormitorios" required>

        <label>Baños</label>
        <input type="number" name="sanitarios" required>

        <label>Garaje</label>
        <select name="garaje" required>
          <option value="1">Sí</option>
          <option value="0">No</option>
        </select>
      </div>

      <div class="columna">
        <label>Estado</label>
        <select name="estado">
          <option value="A estrenar">A estrenar</option>
          <option value="Usado">Usado</option>
          <option value="En construcción">En construcción</option>
        </select>

        <label>Ubicación (Google Maps)</label>
        <input type="url" name="ubicacion" placeholder="https://maps.google.com/?q=-29.1800,-67.5000" required>

        <label>Dirección</label>
        <input type="text" name="direccion" required>

        <label>Disponibilidad</label>
        <select name="disponibilidad">
          <option value="Disponible">Disponible</option>
          <option value="Reservado">Reservado</option>
        </select>

        <label>Imágenes (máx. 5)</label>
        <input type="file" name="imagenes[]" accept="image/*" multiple required>
      </div>

      <div class="acciones">
        <button type="submit" name="agregarPropiedad" class="btn-enviar">Publicar Propiedad</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('modalAgregarPropiedad');
const abrir = document.getElementById('abrirModalPropiedad');
const cerrar = document.getElementById('cerrarModalPropiedad');
abrir.onclick = () => modal.style.display = 'flex';
cerrar.onclick = () => modal.style.display = 'none';
window.onclick = e => { if (e.target == modal) modal.style.display = 'none'; }
</script>

</body>
</html>