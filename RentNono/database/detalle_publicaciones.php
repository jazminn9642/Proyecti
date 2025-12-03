<?php
// -----------------------------------------
// 📌 1. Conexión a la base de datos (PDO)
// -----------------------------------------
require_once '../database/conexion.php'; // ajusta la ruta según tu estructura

// -----------------------------------------
// 📌 2. Validar el parámetro ID
// -----------------------------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<h2>ID de publicación no válido.</h2>');
}

$id = intval($_GET['id']);

// -----------------------------------------
// 📌 3. Consultar la publicación
// -----------------------------------------
$stmt = $conn->prepare("SELECT * FROM propiedades WHERE id = :id");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$pub = $stmt->fetch(PDO::FETCH_ASSOC);

// -----------------------------------------
// 📌 4. Verificar si existe
// -----------------------------------------
if (!$pub) {
    die('<h2>Publicación no encontrada.</h2>');
}

// -----------------------------------------
// 📌 5. Imagen por defecto
// -----------------------------------------
$imagen = !empty($pub['imagen'])
    ? '/RentNono/media/publicaciones/' . htmlspecialchars($pub['imagen'])
    : '/RentNono/media/publicaciones/noimage.png';

include("conexion.php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../estilos/estilo.css">
    <title><?= htmlspecialchars($pub['titulo']) ?> | Detalle</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            margin: 0;
            padding: 20px;
        }
        .volver {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #007bff;
        }
        .volver:hover {
            text-decoration: underline;
        }
        .detalle-container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .detalle-container img {
            width: 100%;
            height: 420px;
            object-fit: cover;
        }
        .detalle-body {
            padding: 25px;
        }
        h1 {
            margin-top: 0;
            font-size: 26px;
            color: #333;
        }
        .detalle-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
            background: #fafafa;
            padding: 15px;
            border-radius: 8px;
        }
        .detalle-info p {
            margin: 5px 0;
            font-size: 15px;
            color: #444;
        }
        .precio {
            font-size: 22px;
            color: #2b9348;
            font-weight: bold;
        }
        .descripcion {
            margin-top: 25px;
            line-height: 1.6;
            color: #555;
        }
        .fecha {
            margin-top: 20px;
            font-size: 13px;
            color: #888;
        }
        .mapa {
            margin-top: 30px;
        }
        .mapa h3 {
            margin-bottom: 10px;
            color: #333;
        }
    </style>
</head>

<body>
    <header class="main-header">
    <div class="container header-content">
        <h1 class="site-logo">
                <a href="javascript:history.back()" class="volver">← Volver</a>
        </h1>

        <nav class="main-nav">
            <ul>
                <li><a href="ixusuario.php">Inicio</a></li>
                <li><b class="btn-primary-small" href="erusuario.php">Explorar</b></li>
                <li><a href="nsusuarios.php">Nosotros</a></li>
                <li><a href="../database/logout.php">Cerrar sesión</a></li>
            </ul>
        </nav>
    </div>
</header>

    <div class="detalle-container">
        <img src="<?= $imagen ?>" alt="Imagen de la propiedad">

        <div class="detalle-body">
            <h1><?= htmlspecialchars($pub['titulo']) ?></h1>
            <p class="precio">$<?= number_format($pub['precio'], 2, ',', '.') ?></p>

            <div class="detalle-info">
                <p><strong>Tipo:</strong> <?= htmlspecialchars($pub['tipo']) ?></p>
                <p><strong>Operación:</strong> <?= htmlspecialchars($pub['operacion']) ?></p>
                <p><strong>Estado:</strong> <?= htmlspecialchars($pub['estado']) ?></p>
                <p><strong>Superficie:</strong> <?= htmlspecialchars($pub['superficie']) ?> m²</p>
                <p><strong>Ambientes:</strong> <?= htmlspecialchars($pub['ambientes']) ?></p>
                <p><strong>Dormitorios:</strong> <?= htmlspecialchars($pub['dormitorios']) ?></p>
                <p><strong>Sanitarios:</strong> <?= htmlspecialchars($pub['sanitarios']) ?></p>
                <p><strong>Garaje:</strong> <?= htmlspecialchars($pub['garaje']) ?></p>
                <p><strong>Disponibilidad:</strong> <?= htmlspecialchars($pub['disponibilidad']) ?></p>
                <div class="mapa">
                    <h3>Ubicación</h3>
                    <iframe
                        width="100%"
                        height="350"
                        style="border:0; border-radius:8px;"
                        loading="lazy"
                        allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://www.google.com/maps?q=<?= urlencode($pub['direccion'] ?: $pub['ubicacion']) ?>&output=embed">
                    </iframe>
                </div>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($pub['direccion']) ?></p>
            </div>


</head>
<title>Calificar propiedad</title>

<div class="rating-container">
  <h2> Califica esta propiedad </h2>

  <form method="POST" action="guardar_opinion.php">
    <div class="rating" id="rating">
      <input type="radio" id="star5" name="rating" value="5"><label for="star5" title="5 estrellas"></label>
      <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 estrellas"></label>
      <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 estrellas"></label>
      <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 estrellas"></label>
      <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 estrella"></label>
    </div>

    <div class="output" id="output">Selecciona una puntuación</div>

    <textarea name="comentario" placeholder="Escribe tu opinión..."></textarea>
    <button type="submit">Enviar reseña</button>
  </form>
</div>


            <div class="descripcion">
                <h3>Descripción completa</h3>
                <p><?= nl2br(htmlspecialchars($pub['descripcion'])) ?></p>
            </div>

            <p class="fecha">📅 Publicado el: <?= date('d/m/Y', strtotime($pub['fecha_publicacion'])) ?></p>
        </div>
    </div>

</body>
</html>

