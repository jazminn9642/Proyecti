-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 03-12-2025 a las 17:46:22
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `rentnono`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `favoritos`
--

CREATE TABLE `favoritos` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_propiedad` int(11) NOT NULL,
  `fecha_agregado` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_actividad`
--

CREATE TABLE `logs_actividad` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(150) DEFAULT NULL,
  `rol` enum('admin','propietario','visitante') NOT NULL DEFAULT 'visitante',
  `accion` varchar(255) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `logs_actividad`
--

INSERT INTO `logs_actividad` (`id`, `usuario_id`, `usuario_nombre`, `rol`, `accion`, `fecha`) VALUES
(1, 1, 'Administrador', 'admin', 'Inicio de sesión', '2025-11-27 09:34:51'),
(2, 1, 'Administrador', 'admin', 'Inicio de sesión', '2025-11-27 10:05:31'),
(3, 1, 'Administrador', 'admin', 'Inicio de sesión', '2025-11-27 10:19:02'),
(4, 1, 'Administrador', 'admin', 'Inicio de sesión', '2025-11-27 10:37:09'),
(5, 1, 'Administrador', 'admin', 'Inicio de sesión', '2025-11-27 11:04:48'),
(6, 1, 'Administrador', 'admin', 'Cierre de sesión', '2025-11-27 13:19:48'),
(7, 1, 'Administrador', 'admin', 'Inicio de sesión', '2025-11-27 13:20:03'),
(8, NULL, 'Administrador', 'admin', 'inhabilitó usuario ID 6 (propietario)', '2025-11-28 00:55:39'),
(9, NULL, 'Administrador', 'admin', 'activó usuario ID 6 (propietario)', '2025-11-28 00:55:41'),
(10, NULL, 'Administrador', 'admin', 'inhabilitó usuario ID 6 (propietario)', '2025-11-28 00:55:46'),
(11, NULL, 'Administrador', 'admin', 'activó usuario ID 6 (propietario)', '2025-11-28 00:55:52'),
(12, NULL, 'Administrador', 'admin', 'inhabilitó usuario ID 6 (propietario)', '2025-11-28 00:55:53'),
(13, NULL, 'Administrador', 'admin', 'activó usuario ID 6 (propietario)', '2025-11-28 00:55:54'),
(14, NULL, 'Administrador', 'admin', 'inhabilitó usuario ID 1 (admin)', '2025-11-28 19:42:52'),
(15, NULL, 'Administrador', 'admin', 'activó usuario ID 1 (admin)', '2025-11-28 19:42:54'),
(16, 1, 'Administrador', 'admin', 'Cierre de sesión', '2025-12-03 11:52:54'),
(17, 1, 'Administrador', 'admin', 'Inicio de sesión', '2025-12-03 11:53:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('reseña','solicitud','otro') DEFAULT 'otro',
  `leido` tinyint(1) DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `opiniones`
--

CREATE TABLE `opiniones` (
  `id` int(11) NOT NULL,
  `propiedad_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comentario` text DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `propiedades`
--

CREATE TABLE `propiedades` (
  `id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(12,2) NOT NULL,
  `tipo` enum('casa','departamento','local comercial','terreno o lote','galpon','camping') NOT NULL,
  `operacion` enum('alquiler','venta') NOT NULL,
  `superficie` int(11) DEFAULT NULL,
  `ambientes` int(11) DEFAULT NULL,
  `dormitorios` int(11) DEFAULT NULL,
  `sanitarios` int(11) DEFAULT NULL,
  `garaje` tinyint(1) DEFAULT 0,
  `estado` enum('a estrenar','usado','en construcción') NOT NULL DEFAULT 'a estrenar',
  `ubicacion` varchar(255) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `disponibilidad` enum('disponible','reservado') DEFAULT 'disponible',
  `imagen` varchar(255) DEFAULT NULL,
  `fecha_publicacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `propiedades`
--

INSERT INTO `propiedades` (`id`, `titulo`, `descripcion`, `precio`, `tipo`, `operacion`, `superficie`, `ambientes`, `dormitorios`, `sanitarios`, `garaje`, `estado`, `ubicacion`, `direccion`, `disponibilidad`, `imagen`, `fecha_publicacion`, `id_usuario`) VALUES
(7, 'Departamento céntrico', 'Departamento de 2 dormitorios en el centro, cerca de comercios y transporte público.', 45000.00, 'departamento', 'venta', 80, 4, 2, 1, 0, '', 'https://maps.google.com/?q=-29.163,-67.498', 'Av. San Martín 120, Nonogasta', 'disponible', 'departamento_centrico.jpg', '2025-07-15 21:19:36', 1),
(8, 'Casa familiar', 'Casa de 3 dormitorios con patio amplio y garaje, ideal para familias grandes.', 65000.00, 'casa', 'alquiler', 200, 5, 3, 2, 1, 'en construcción', 'https://maps.app.goo.gl/sdjT4VZfZbNdsgCr7', 'Calle Belgrano 450, Nonogasta', 'disponible', 'casa1.jpg', '2025-04-15 21:19:36', 2),
(9, 'Departamento moderno', 'Departamento moderno de 1 dormitorio con todas las comodidades y balcón.', 40000.00, 'departamento', 'alquiler', 60, 3, 1, 1, 0, 'usado', 'https://maps.google.com/?q=-29.165,-67.495', 'Calle Rivadavia 300, Chilecito', 'disponible', 'departamento_moderno.jpeg', '2025-03-15 21:19:36', 1),
(10, 'Casa con jardín', 'Hermosa casa de 3 dormitorios con amplio jardín y quincho para reuniones.', 75000.00, 'casa', 'venta', 250, 6, 3, 2, 1, 'a estrenar', 'https://maps.google.com/?q=-29.160,-67.497', 'Calle Rioja 210, Nonogasta', 'disponible', 'casa_jardin.jpeg', '2025-07-15 21:19:36', 4),
(11, 'Monoambiente amoblado', 'Monoambiente totalmente amoblado, ideal para estudiantes o personas solas.', 30000.00, 'departamento', 'alquiler', 35, 1, 0, 1, 0, 'a estrenar', 'https://maps.google.com/?q=-29.161,-67.492', 'Calle 9 de Julio 50, Nonogasta', 'disponible', 'monoambiente_amoblado.jpg', '2025-02-15 21:19:36', 2),
(12, 'Departamento con terraza', 'Departamento de 2 dormitorios con terraza y vista panorámica a los cerros.', 50000.00, 'departamento', 'alquiler', 90, 4, 2, 1, 0, 'usado', 'https://maps.google.com/?q=-29.166,-67.493', 'Calle Libertad 700, Chilecito', 'disponible', 'departamento_terraza.jpg', '2025-02-15 21:19:36', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `publicaciones`
--

CREATE TABLE `publicaciones` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `tipo` enum('alquiler','venta') NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `fecha_publicacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `publicaciones`
--

INSERT INTO `publicaciones` (`id`, `titulo`, `descripcion`, `tipo`, `precio`, `imagen`, `fecha_publicacion`) VALUES
(4, 'Departamento céntrico', 'Departamento de 2 dormitorios en el centro, cerca de transporte y comercios.', 'venta', 45000.00, 'departamento_centrico.jpg', '2025-08-15 21:19:36'),
(5, 'Casa familiar', 'Casa de 3 dormitorios con patio amplio y garaje, ideal para familias.', 'alquiler', 65000.00, 'casa1.jpg', '2025-08-15 21:19:36'),
(6, 'Departamento moderno', 'Departamento moderno de 1 dormitorio con todas las comodidades, cerca de zonas comerciales.', 'alquiler', 40000.00, 'departamento_moderno.jpeg', '2025-08-15 21:19:36'),
(7, 'Casa con jardín', 'Hermosa casa de 3 dormitorios con amplio jardín y garaje, ubicada en zona tranquila y segura.', 'venta', 75000.00, 'casa_jardin.jpeg', '2025-08-15 21:19:36'),
(8, 'Monoambiente amoblado', 'Monoambiente totalmente amoblado, ideal para estudiantes o profesionales.', 'alquiler', 30000.00, 'monoambiente_amoblado.jpg', '2025-08-15 21:19:36'),
(9, 'Departamento con terraza', 'Departamento de 2 dormitorios con terraza y vista panorámica, cerca de transporte público.', 'alquiler', 50000.00, 'departamento_terraza.jpg', '2025-08-15 21:19:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `nombre` varchar(30) NOT NULL,
  `sexo` varchar(10) NOT NULL,
  `dni` varchar(30) NOT NULL,
  `correo` varchar(20) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `password` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`nombre`, `sexo`, `dni`, `correo`, `telefono`, `password`) VALUES
('nery', 'femenino', 'ererer', 'ererer', 'erer', 'erer'),
('Lenis Riojs', 'masculino', '43344607', 'sdfsd', 'sdfsdf', 'sdfsdf'),
('Lenis Riojs', 'masculino', '43344607', 'sdfsd', 'sdfsdf', 'sdfsdf'),
('Lenis Riojs', 'masculino', '43344607', 'sdfsd', 'sdfsdf', 'sdfsdf'),
('dfgdgdfgdfgfd', 'femenino', 'dfgfdgdf', 'dfgfdg', 'gdfgdfg', 'gdfgdf'),
('Nery jair', 'masculino', '43344607', 'nrt', 'wr', 'wrew'),
('Lenis Riojs', 'masculino', 'dasasd', 'asdasd', 'asdasd', 'asdasd'),
('asdasdas', 'masculino', 'asdas', 'asdasd', 'adasd', 'asdasd'),
('asdas', 'masculino', 'asdasd', 'asdas', 'dasdasd', 'asdasd'),
('LENIS RIOS', 'femenino', '12357545', '5354545', 'ER45454', 'FDGFG');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_admin`
--

CREATE TABLE `usuario_admin` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `last_activity` datetime DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario_admin`
--

INSERT INTO `usuario_admin` (`id`, `nombre`, `correo`, `password_hash`, `telefono`, `foto_perfil`, `role`, `last_activity`, `creado_en`, `estado`) VALUES
(1, 'Administrador', 'admin@rentnono.com', '$2y$10$yDwzuj0IFWkJJSSdqAWlDOS5.Z/NpKH1Emaxz1PfTSHaIS4d9qvby', '3825612630', NULL, 'admin', '2025-11-27 13:34:51', '2025-10-21 09:44:43', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_propietario`
--

CREATE TABLE `usuario_propietario` (
  `id` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `sexo` varchar(10) NOT NULL,
  `dni` varchar(30) NOT NULL,
  `correo` varchar(30) NOT NULL,
  `telefono` varchar(30) NOT NULL,
  `password` varchar(30) NOT NULL,
  `rol` varchar(30) NOT NULL DEFAULT 'propietario',
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario_propietario`
--

INSERT INTO `usuario_propietario` (`id`, `nombre`, `sexo`, `dni`, `correo`, `telefono`, `password`, `rol`, `estado`) VALUES
(6, 'Nery Jair Reinoso', 'masculino', '43344607', 'nery.reinoso.7@gmail.com', '3825456521', '0000', 'propietario', 1),
(7, 'JJ', 'femenino', '15768983', 'amelia@gmail.com', '380467892', '1234', 'propietario', 1),
(8, 'Rios Lenis', 'femenino', '47462403', 'lenis@gmail.com', '3825278392', '0000', 'propietario', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_visitante`
--

CREATE TABLE `usuario_visitante` (
  `id` int(100) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `correo` varchar(30) NOT NULL,
  `password` varchar(30) NOT NULL,
  `rol` varchar(20) NOT NULL DEFAULT 'visitante',
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario_visitante`
--

INSERT INTO `usuario_visitante` (`id`, `nombre`, `correo`, `password`, `rol`, `estado`) VALUES
(1, 'Nery Jair Reinoso', 'nery.reinoso.7@gmail.com', '1234', 'visitante', 1),
(2, 'Lenis Samira Rios', 'lenis@gmail.com', '0000', 'visitante', 1),
(4, 'Florencia Rios', 'florenciarios@gmail.com', 'Silnemarei22', 'visitante', 1),
(5, 'Graciela Vega', 'gachy@gmail.com', 'gachy', 'visitante', 1),
(7, 'Lucas Ortiz', 'lucas@gmail.com', 'lucas', 'visitante', 1),
(8, 'Arturo Nievas', 'arturo@gmail.com', 'arturo', 'visitante', 1),
(9, 'Ruben Vazquez', 'ruben@gmail.com', 'ruebn', 'visitante', 1),
(10, 'Nuevo Usuario', 'usuario@gmail.com', 'usuario', 'visitante', 1),
(11, 'Root', 'root@gmail.com', 'root', 'visitante', 1),
(12, 'nuevo', 'nuevo@nuevo.com', 'nuevo', 'visitante', 1),
(13, 'user', 'user@gmail.com', 'user', 'visitante', 1),
(14, 'Graciela Mercedes Vega', 'graciela@vega.com', 'graciela', 'visitante', 1),
(15, 'lenis rios', 'lenis@gmail.com', '1234', 'visitante', 1),
(16, 'Mercedes', 'mecha@gmail.com', '01234', 'visitante', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `favoritos`
--
ALTER TABLE `favoritos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_propiedad` (`id_propiedad`);

--
-- Indices de la tabla `logs_actividad`
--
ALTER TABLE `logs_actividad`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_id` (`usuario_id`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `opiniones`
--
ALTER TABLE `opiniones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `propiedades`
--
ALTER TABLE `propiedades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuario_propietario`
--
ALTER TABLE `usuario_propietario`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuario_visitante`
--
ALTER TABLE `usuario_visitante`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `logs_actividad`
--
ALTER TABLE `logs_actividad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `opiniones`
--
ALTER TABLE `opiniones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `propiedades`
--
ALTER TABLE `propiedades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `usuario_propietario`
--
ALTER TABLE `usuario_propietario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `usuario_visitante`
--
ALTER TABLE `usuario_visitante`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `favoritos`
--
ALTER TABLE `favoritos`
  ADD CONSTRAINT `favoritos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario_visitante` (`id`),
  ADD CONSTRAINT `favoritos_ibfk_2` FOREIGN KEY (`id_propiedad`) REFERENCES `propiedades` (`id`);

--
-- Filtros para la tabla `propiedades`
--
ALTER TABLE `propiedades`
  ADD CONSTRAINT `propiedades_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario_visitante` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
