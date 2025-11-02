<?php
// Este archivo hace las peticiones a la base de datos y las devuelve a la pagina para que
// puedan ser visualizados
    include ("conexion.php");

    try {

        //Consulta las últimas 3 publicaciones
        $stmtUltimasPublicaciones = $conn->prepare("SELECT * FROM propiedades ORDER BY fecha_publicacion DESC LIMIT 3");
        $stmtUltimasPublicaciones->execute();

        // Obtener resultados como array asociativo
        $ultimasPublicaciones = $stmtUltimasPublicaciones->fetchAll(PDO::FETCH_ASSOC);


        

        //FILTRO DE BUSQUEDA
        $sql = "SELECT * FROM propiedades WHERE 1=1";
        $params = [];

        // Operación
        if (!empty($_GET['operacion'])) {
            $sql .= " AND operacion = :operacion";
            $params[':operacion'] = $_GET['operacion'];
        }

        // Tipo
        if (!empty($_GET['tipo'])) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = $_GET['tipo'];
        }

        // Precio máximo
        if (!empty($_GET['precio_max'])) {
            $sql .= " AND precio <= :precio_max";
            $params[':precio_max'] = $_GET['precio_max'];
        }

        // Ambientes
        if (!empty($_GET['ambientes'])) {
            $sql .= " AND ambientes = :ambientes";
            $params[':ambientes'] = $_GET['ambientes'];
        }

        // Dormitorios
        if (!empty($_GET['dormitorios'])) {
            $sql .= " AND dormitorios = :dormitorios";
            $params[':dormitorios'] = $_GET['dormitorios'];
        }

        // Sanitarios
        if (!empty($_GET['sanitarios'])) {
            $sql .= " AND sanitarios = :sanitarios";
            $params[':sanitarios'] = $_GET['sanitarios'];
        }

        // Garaje
        $garaje = $_GET['garaje'] ?? '';
        if ($garaje !== '') {
            $filtros[] = "garaje = :garaje";
            $params[':garaje'] = $garaje;
        }

        // Estado
        if (!empty($_GET['estado'])) {
            $sql .= " AND estado = :estado";
            $params[':estado'] = $_GET['estado'];
        }

        // Ejecutar consulta
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error al obtener las peticiones de propiedades: " . $e->getMessage());
    }

?>
