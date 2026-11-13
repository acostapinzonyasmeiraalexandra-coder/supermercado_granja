-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-11-2026 a las 08:13:05
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
-- Base de datos: `supermercado`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--

CREATE TABLE `cliente` (
  `cedula_cliente` int(11) NOT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `direccion` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cliente`
--

INSERT INTO `cliente` (`cedula_cliente`, `nombre`, `telefono`, `email`, `direccion`) VALUES
(12345, 'yakis', '123466', 'yakis@gmail.com', 'mz lote 515 manuela'),
(123456, 'yakis', '3239885115', '', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura_salida`
--

CREATE TABLE `factura_salida` (
  `cod_movimiento` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL DEFAULT curdate(),
  `nota` varchar(255) DEFAULT NULL,
  `cedula_cliente` int(11) NOT NULL,
  `productos` varchar(255) DEFAULT NULL,
  `total_venta` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `factura_salida`
--

INSERT INTO `factura_salida` (`cod_movimiento`, `fecha`, `nota`, `cedula_cliente`, `productos`, `total_venta`) VALUES
(1, '2026-10-28', 'Venta de arroz', 12345, '1', 30000.00),
(2, '2026-11-10', 'Venta de arroz, harina de trigo', 12345, '2', 23200.00),
(3, '2026-11-10', '', 12345, '2', 114400.00),
(4, '2026-11-11', '', 12345, '1', 200000.00),
(5, '2026-11-11', '', 12345, '1', 50000.00),
(6, '2026-11-12', '', 123456, '1', 7000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `cod_inventario` int(11) NOT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `cod_producto` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`cod_inventario`, `cantidad`, `cod_producto`) VALUES
(0, 98, 11);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mov_entrada`
--

CREATE TABLE `mov_entrada` (
  `cod_movimiento` int(11) NOT NULL,
  `fecha` date DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `nota` varchar(100) DEFAULT NULL,
  `cod_proveedor` int(11) DEFAULT NULL,
  `cod_producto` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mov_entrada`
--

INSERT INTO `mov_entrada` (`cod_movimiento`, `fecha`, `precio`, `cantidad`, `nota`, `cod_proveedor`, `cod_producto`) VALUES
(1, '2026-10-09', 20000.00, 82, 'ee', 11, 11),
(2, '2026-10-26', 3500.00, 100, 'Entrada de azucar', 12, 16),
(3, '2026-10-26', 7000.00, 80, 'Entrada de aceite', 12, 17),
(4, '2026-10-26', 2500.00, 90, 'Entrada de harina', 13, 18),
(5, '2026-10-26', 1200.00, 100, 'Entrada de sal', 13, 19),
(6, '2026-10-26', 4000.00, 60, 'Entrada de lentejas', 21, 20),
(7, '2026-10-26', 5000.00, 60, 'Entrada de frijoles', 21, 21),
(8, '2026-10-26', 2000.00, 100, 'Entrada de pasta', 12, 22),
(9, '2026-10-26', 10000.00, 40, 'Entrada de cafe', 12, 23),
(10, '2026-10-26', 6000.00, 50, 'Entrada de chocolate', 13, 24),
(11, '2026-10-26', 3000.00, 70, 'Entrada de avena', 13, 25),
(12, '2026-10-26', 3000.00, 60, '', 11, 16),
(13, '2026-10-26', 12000.00, 30, 'Entrada de queso', 18, 27),
(14, '2026-10-26', 5000.00, 90, '', 15, 15),
(16, '2026-10-26', 6000.00, 30, 'Entrada de mantequilla', 18, 29),
(17, '2026-10-26', 15000.00, 20, 'Entrada de huevos', 13, 30),
(18, '2026-10-26', 10000.00, 40, 'Entrada de pollo', 16, 31),
(19, '2026-10-26', 22000.00, 30, 'Entrada de carne de res', 16, 32),
(20, '2026-10-26', 18000.00, 30, 'Entrada de carne de cerdo', 16, 33),
(21, '2026-10-26', 7000.00, 40, 'Entrada de salchichas', 16, 34),
(22, '2026-10-26', 7500.00, 30, 'Entrada de jamon', 16, 35),
(23, '2026-10-26', 4500.00, 50, 'Entrada de manzanas', 17, 36),
(24, '2026-10-26', 2000.00, 80, 'Entrada de bananos', 17, 37),
(25, '2026-10-26', 3000.00, 60, 'Entrada de naranjas', 17, 38),
(26, '2026-10-26', 4000.00, 50, 'Entrada de mangos', 17, 39),
(27, '2026-10-26', 2500.00, 100, 'Entrada de papa', 17, 40),
(28, '2026-10-26', 3500.00, 70, 'Entrada de tomate', 17, 41),
(29, '2026-10-26', 2500.00, 70, 'Entrada de cebolla', 17, 42),
(30, '2026-10-26', 2000.00, 60, 'Entrada de zanahoria', 17, 43),
(31, '2026-10-26', 1800.00, 40, 'Entrada de lechuga', 17, 44),
(32, '2026-10-26', 4000.00, 40, 'Entrada de aguacate', 17, 45),
(33, '2026-10-26', 3500.00, 60, 'Entrada de gaseosa', 14, 46),
(34, '2026-10-26', 1200.00, 100, 'Entrada de agua', 14, 47),
(35, '2026-10-26', 3000.00, 50, 'Entrada de jugo', 14, 48),
(36, '2026-10-26', 4000.00, 40, 'Entrada de bebida energetica', 14, 49),
(37, '2026-10-26', 1800.00, 60, 'Entrada de agua con gas', 14, 50),
(38, '2026-10-26', 4500.00, 40, 'Entrada de pan tajado', 19, 51),
(39, '2026-10-26', 1000.00, 60, 'Entrada de pan dulce', 19, 52),
(40, '2026-10-26', 2500.00, 50, 'Entrada de galletas', 19, 53),
(41, '2026-10-26', 10000.00, 20, 'Entrada de tortas', 19, 54),
(42, '2026-10-26', 3500.00, 50, 'Entrada de arepas', 19, 55),
(43, '2026-10-26', 7500.00, 40, 'Entrada de detergente', 20, 56),
(44, '2026-10-26', 2200.00, 60, 'Entrada de jabon', 20, 57),
(45, '2026-10-26', 3500.00, 40, 'Entrada de lavaloza', 20, 58),
(46, '2026-10-26', 3000.00, 40, 'Entrada de blanqueador', 20, 59),
(47, '2026-10-26', 6500.00, 30, 'Entrada de papel higienico', 20, 60),
(48, '2026-10-26', 5000.00, 30, 'Entrada de crema dental', 20, 61);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mov_salida`
--

CREATE TABLE `mov_salida` (
  `cod_movimiento` int(10) UNSIGNED NOT NULL,
  `cod_producto` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `nombre_producto` varchar(100) NOT NULL,
  `fecha` date DEFAULT NULL,
  `cedula_cliente` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mov_salida`
--

INSERT INTO `mov_salida` (`cod_movimiento`, `cod_producto`, `precio`, `cantidad`, `nombre_producto`, `fecha`, `cedula_cliente`) VALUES
(1, 11, 30000.00, 1, 'arroz', '2026-10-28', 12345),
(2, 11, 20000.00, 1, 'arroz', '2026-11-10', 12345),
(2, 18, 3200.00, 1, 'Harina de trigo', '2026-11-10', 12345),
(3, 11, 20000.00, 5, 'arroz', '2026-11-10', 12345),
(3, 16, 4800.00, 3, 'aceite', '2026-11-10', 12345),
(4, 11, 20000.00, 10, 'arroz', '2026-11-11', 12345),
(5, 20, 5000.00, 10, 'Lentejas', '2026-11-11', 12345),
(6, 40, 3500.00, 2, 'Papa', '2026-11-12', 123456);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `cod_producto` int(11) NOT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `categoria` varchar(500) DEFAULT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  `cod_proveedor` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto`
--

INSERT INTO `producto` (`cod_producto`, `nombre`, `precio`, `categoria`, `descripcion`, `cod_proveedor`) VALUES
(11, 'arroz', 20000.00, 'carnes', 'en buen estado', 11),
(12, 'higado', 40000.00, 'carnes', 'en buen estado', 11),
(13, 'fresas', 50000.00, 'frutas', 'en buen estado', 11),
(15, 'coca-cola', 3500.00, 'carnes', 'coacola', 15),
(16, 'aceite', 4800.00, 'abarrotes', 'aaa', 11),
(17, 'Aceite vegetal', 8500.00, 'Abarrotes', 'Aceite 1 litro', 12),
(18, 'Harina de trigo', 3200.00, 'Abarrotes', 'Harina 1 kg', 13),
(19, 'Sal', 1800.00, 'Abarrotes', 'Sal refinada 1 kg', 13),
(20, 'Lentejas', 5000.00, 'Granos', 'Lentejas 1 kg', 21),
(21, 'Frijoles', 6500.00, 'Granos', 'Frijoles rojos 1 kg', 21),
(22, 'Pasta', 2800.00, 'Abarrotes', 'Pasta espagueti 500 g', 12),
(23, 'Cafe', 12000.00, 'Bebidas', 'Cafe molido 500 g', 12),
(24, 'Chocolate en polvo', 7500.00, 'Bebidas', 'Chocolate 500 g', 13),
(25, 'Avena', 4000.00, 'Cereales', 'Avena en hojuelas 500 g', 13),
(26, 'Leche entera', 4200.00, 'Lacteos', 'Leche en bolsa 1 litro', 18),
(27, 'Queso campesino', 14000.00, 'Lacteos', 'Queso fresco 500 g', 18),
(28, 'Yogur', 5500.00, 'Lacteos', 'Yogur de fresa 1 litro', 18),
(29, 'Mantequilla', 7000.00, 'Lacteos', 'Mantequilla 250 g', 18),
(30, 'Huevos', 18000.00, 'Huevos', 'Cubeta de 30 huevos', 13),
(31, 'Pollo', 12000.00, 'Carnes', 'Pollo por kilogramo', 16),
(32, 'Carne de res', 26000.00, 'Carnes', 'Carne de res por kilogramo', 16),
(33, 'Carne de cerdo', 22000.00, 'Carnes', 'Carne de cerdo por kilogramo', 16),
(34, 'Salchichas', 8500.00, 'Carnes', 'Paquete de salchichas', 16),
(35, 'Jamon', 9000.00, 'Carnes', 'Jamon tajado 250 g', 16),
(36, 'Manzanas', 6000.00, 'Frutas', 'Manzanas por kilogramo', 17),
(37, 'Banano', 3000.00, 'Frutas', 'Banano por kilogramo', 17),
(38, 'Naranjas', 4000.00, 'Frutas', 'Naranjas por kilogramo', 17),
(39, 'Mangos', 5000.00, 'Frutas', 'Mangos por kilogramo', 17),
(40, 'Papa', 3500.00, 'Verduras', 'Papa por kilogramo', 17),
(41, 'Tomate', 4500.00, 'Verduras', 'Tomate por kilogramo', 17),
(42, 'Cebolla', 3200.00, 'Verduras', 'Cebolla por kilogramo', 17),
(43, 'Zanahoria', 2800.00, 'Verduras', 'Zanahoria por kilogramo', 17),
(44, 'Lechuga', 2500.00, 'Verduras', 'Lechuga por unidad', 17),
(45, 'Aguacate', 5000.00, 'Frutas', 'Aguacate por unidad', 17),
(46, 'Gaseosa', 4500.00, 'Bebidas', 'Gaseosa 1.5 litros', 14),
(47, 'Agua embotellada', 2000.00, 'Bebidas', 'Agua 600 ml', 14),
(48, 'Jugo de naranja', 3800.00, 'Bebidas', 'Jugo en botella 1 litro', 14),
(49, 'Bebida energetica', 5000.00, 'Bebidas', 'Bebida energetica 500 ml', 14),
(50, 'Agua con gas', 2500.00, 'Bebidas', 'Agua con gas 600 ml', 14),
(51, 'Pan tajado', 5500.00, 'Panaderia', 'Pan tajado 500 g', 19),
(52, 'Pan dulce', 1500.00, 'Panaderia', 'Pan dulce por unidad', 19),
(53, 'Galletas', 3500.00, 'Galletas', 'Paquete de galletas', 19),
(54, 'Torta', 12000.00, 'Panaderia', 'Torta pequena', 19),
(55, 'Arepas', 4500.00, 'Panaderia', 'Paquete de arepas', 19),
(56, 'Detergente', 9000.00, 'Aseo', 'Detergente en polvo 1 kg', 20),
(57, 'Jabon de baño', 3000.00, 'Aseo', 'Jabon en barra', 20),
(58, 'Lavaloza', 4500.00, 'Aseo', 'Lavaloza liquido 500 ml', 20),
(59, 'Blanqueador', 4000.00, 'Aseo', 'Blanqueador 1 litro', 20),
(60, 'Papel higienico', 8000.00, 'Aseo', 'Paquete de 4 rollos', 20),
(61, 'Crema dental', 6500.00, 'Aseo personal', 'Crema dental 100 ml', 20),
(62, 'Shampoo', 11000.00, 'Aseo personal', 'Shampoo 400 ml', 20),
(63, 'Papas fritas', 2500.00, 'Mecato', 'Paquete de papas fritas', 22),
(64, 'Chocolatina', 1800.00, 'Dulces', 'Chocolatina individual', 22),
(65, 'Caramelos', 3000.00, 'Dulces', 'Bolsa de caramelos', 22);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedor`
--

CREATE TABLE `proveedor` (
  `cod_proveedor` int(11) NOT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `direccion` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedor`
--

INSERT INTO `proveedor` (`cod_proveedor`, `nombre`, `telefono`, `email`, `direccion`) VALUES
(11, 'yasme', '3239887653', 'yasme@gmail.com', 'mz lote 34 san juanito'),
(12, 'Distribuidora La Canasta', '3104567891', 'canasta@gmail.com', 'Bogota Centro'),
(13, 'Alimentos del Campo', '3115678902', 'campo@gmail.com', 'Calle 10 Bogota'),
(14, 'Postobon', '3126789013', 'postobon@gmail.com', 'Zona Industrial Bogota'),
(15, 'coca-cola', '3118776225', 'cocacola@gmail.com', 'patios'),
(16, 'DistriCarnes', '3137890124', 'districarnes@gmail.com', 'Calle 20 Los Patios'),
(17, 'Frutas Frescas', '3148901235', 'frutas@gmail.com', 'Central de Abastos'),
(18, 'Lacteos del Norte', '3159012346', 'lacteos@gmail.com', 'Avenida Principal'),
(19, 'Panaderia El Trigal', '3160123457', 'trigal@gmail.com', 'Barrio Centro'),
(20, 'Productos de Aseo Limpio', '3171234568', 'aseo@gmail.com', 'Zona Comercial'),
(21, 'Distribuidora de Granos', '3182345679', 'granos@gmail.com', 'Calle 15 Bogota'),
(22, 'Dulces y Golosinas', '3193456780', 'dulces@gmail.com', 'Barrio San Juanito');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_usuario`
--

CREATE TABLE `tipo_usuario` (
  `id_tipo_usuario` int(11) NOT NULL,
  `tipo_usu` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipo_usuario`
--

INSERT INTO `tipo_usuario` (`id_tipo_usuario`, `tipo_usu`) VALUES
(1, 'administrador'),
(2, 'empleado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `cedula` int(11) NOT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `apellido` varchar(50) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `contra` varchar(255) DEFAULT NULL,
  `id_tipo_usuario` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`cedula`, `nombre`, `apellido`, `telefono`, `direccion`, `email`, `usuario`, `contra`, `id_tipo_usuario`) VALUES
(1234, 'yakis', 'quintero', '36413156', 'mazjhcd', 'yakis@gmail.com', 'yakis', '$2y$10$4AATke7kmlTE.IfRuGLLsuckqhLvK/CBi1C5rzabS2r2Abn9q.52O', 1),
(12345, 'Yasmeira', 'pinzón', '3155421545', 'mz4 lote 13 atalaya', 'yasmeira@gmail.com', 'yasmeira', '$2y$10$c99Uk16SesEFa.WCI8ZQM.6RchmWHh3azH9n47YnzajuHRJmWf/B6', 2);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`cedula_cliente`);

--
-- Indices de la tabla `factura_salida`
--
ALTER TABLE `factura_salida`
  ADD PRIMARY KEY (`cod_movimiento`),
  ADD KEY `cedula_cliente` (`cedula_cliente`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`cod_inventario`),
  ADD KEY `cod_producto` (`cod_producto`);

--
-- Indices de la tabla `mov_entrada`
--
ALTER TABLE `mov_entrada`
  ADD PRIMARY KEY (`cod_movimiento`),
  ADD KEY `cod_proveedor` (`cod_proveedor`),
  ADD KEY `cod_producto` (`cod_producto`);

--
-- Indices de la tabla `mov_salida`
--
ALTER TABLE `mov_salida`
  ADD PRIMARY KEY (`cod_movimiento`,`cod_producto`),
  ADD KEY `fk_detsal_producto` (`cod_producto`),
  ADD KEY `idx_movsal_cliente` (`cedula_cliente`);

--
-- Indices de la tabla `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`cod_producto`),
  ADD KEY `fk_proveedor_producto` (`cod_proveedor`);

--
-- Indices de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  ADD PRIMARY KEY (`cod_proveedor`);

--
-- Indices de la tabla `tipo_usuario`
--
ALTER TABLE `tipo_usuario`
  ADD PRIMARY KEY (`id_tipo_usuario`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`cedula`),
  ADD KEY `id_tipo_usuario` (`id_tipo_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `factura_salida`
--
ALTER TABLE `factura_salida`
  MODIFY `cod_movimiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `factura_salida`
--
ALTER TABLE `factura_salida`
  ADD CONSTRAINT `factura_salida_ibfk_1` FOREIGN KEY (`cedula_cliente`) REFERENCES `cliente` (`cedula_cliente`);

--
-- Filtros para la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `inventario_ibfk_1` FOREIGN KEY (`cod_producto`) REFERENCES `producto` (`cod_producto`);

--
-- Filtros para la tabla `mov_entrada`
--
ALTER TABLE `mov_entrada`
  ADD CONSTRAINT `mov_entrada_ibfk_1` FOREIGN KEY (`cod_proveedor`) REFERENCES `proveedor` (`cod_proveedor`),
  ADD CONSTRAINT `mov_entrada_ibfk_2` FOREIGN KEY (`cod_producto`) REFERENCES `producto` (`cod_producto`);

--
-- Filtros para la tabla `mov_salida`
--
ALTER TABLE `mov_salida`
  ADD CONSTRAINT `fk_detsal_producto` FOREIGN KEY (`cod_producto`) REFERENCES `producto` (`cod_producto`),
  ADD CONSTRAINT `fk_movsal_cliente` FOREIGN KEY (`cedula_cliente`) REFERENCES `cliente` (`cedula_cliente`),
  ADD CONSTRAINT `fk_movsal_factura` FOREIGN KEY (`cod_movimiento`) REFERENCES `factura_salida` (`cod_movimiento`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `producto`
--
ALTER TABLE `producto`
  ADD CONSTRAINT `fk_proveedor_producto` FOREIGN KEY (`cod_proveedor`) REFERENCES `proveedor` (`cod_proveedor`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_tipo_usuario`) REFERENCES `tipo_usuario` (`id_tipo_usuario`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
