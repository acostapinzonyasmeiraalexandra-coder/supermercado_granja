-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-10-2026 a las 00:38:54
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
(12345, 'yakis', '3434555', 'yakelinequintero2707@gmail.com', 'ttttt'),
(131465, 'Carlos Andres Gomez', '3001234567', 'carlos.gomez@gmail.com', 'Calle 10 # 15-20'),
(309499, 'andres', '2333333', 'andres@gmail.com', 'mz23patios'),
(354544, 'Maria Fernanda Lopez', '3012345678', 'maria.lopez@gmail.com', 'Carrera 12 # 20-30'),
(1002345678, 'Juan David Rodriguez', '3023456789', 'juan.rodriguez@gmail.com', 'Calle 25 # 10-15'),
(1003456789, 'Laura Camila Martinez', '3034567890', 'laura.martinez@gmail.com', 'Carrera 8 # 12-40'),
(1004567890, 'Andres Felipe Torres', '3045678901', 'andres.torres@gmail.com', 'Calle 30 # 18-25'),
(1005678901, 'Daniela Sofia Ramirez', '3056789012', 'daniela.ramirez@gmail.com', 'Carrera 15 # 22-10'),
(1006789012, 'Sebastian Herrera', '3101234567', 'sebastian.herrera@gmail.com', 'Calle 40 # 16-35'),
(1007890123, 'Valentina Castro', '3112345678', 'valentina.castro@gmail.com', 'Carrera 20 # 30-12'),
(1008901234, 'Mateo Jimenez', '3123456789', 'mateo.jimenez@gmail.com', 'Calle 18 # 14-22'),
(1009012345, 'Isabella Moreno', '3134567890', 'isabella.moreno@gmail.com', 'Carrera 7 # 25-18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_salida`
--

CREATE TABLE `detalle_salida` (
  `id_detalle` int(11) NOT NULL,
  `cod_movimiento` int(10) UNSIGNED NOT NULL,
  `cod_producto` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `nombre_producto` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_salida`
--

INSERT INTO `detalle_salida` (`id_detalle`, `cod_movimiento`, `cod_producto`, `precio`, `cantidad`, `nombre_producto`) VALUES
(104, 23, 16, 4500.00, 5, 'Azucar'),
(105, 25, 18, 3200.00, 6, 'Harina de trigo'),
(106, 27, 20, 5000.00, 8, 'Lentejas'),
(107, 29, 22, 2800.00, 12, 'Pasta'),
(108, 31, 24, 7500.00, 6, 'Chocolate en polvo'),
(109, 33, 26, 4200.00, 10, 'Leche entera'),
(110, 35, 28, 5500.00, 7, 'Yogur'),
(111, 37, 30, 18000.00, 4, 'Huevos'),
(112, 39, 32, 26000.00, 5, 'Carne de res'),
(113, 41, 34, 8500.00, 6, 'Salchichas'),
(114, 43, 36, 6000.00, 10, 'Manzanas'),
(115, 45, 38, 4000.00, 12, 'Naranjas'),
(116, 47, 40, 3500.00, 20, 'Papa'),
(117, 49, 42, 3200.00, 15, 'Cebolla'),
(118, 51, 44, 2500.00, 8, 'Lechuga'),
(119, 53, 46, 4500.00, 12, 'Gaseosa'),
(120, 55, 48, 3800.00, 10, 'Jugo de naranja'),
(121, 57, 50, 2500.00, 12, 'Agua con gas'),
(122, 59, 52, 1500.00, 15, 'Pan dulce'),
(123, 61, 54, 12000.00, 4, 'Torta'),
(124, 63, 56, 9000.00, 8, 'Detergente'),
(125, 65, 58, 4500.00, 10, 'Lavaloza'),
(126, 67, 60, 8000.00, 6, 'Papel higienico'),
(127, 69, 62, 11000.00, 5, 'Shampoo'),
(128, 71, 64, 1800.00, 20, 'Chocolatina'),
(129, 24, 17, 8500.00, 4, 'Aceite vegetal'),
(130, 26, 19, 1800.00, 10, 'Sal'),
(131, 28, 21, 6500.00, 5, 'Frijoles'),
(132, 30, 23, 12000.00, 3, 'Cafe'),
(133, 32, 25, 4000.00, 9, 'Avena'),
(134, 34, 27, 14000.00, 4, 'Queso campesino'),
(135, 36, 29, 7000.00, 5, 'Mantequilla'),
(136, 38, 31, 12000.00, 8, 'Pollo'),
(137, 40, 33, 22000.00, 4, 'Carne de cerdo'),
(138, 42, 35, 9000.00, 5, 'Jamon'),
(139, 44, 37, 3000.00, 15, 'Banano'),
(140, 46, 39, 5000.00, 8, 'Mangos'),
(141, 48, 41, 4500.00, 10, 'Tomate'),
(142, 50, 43, 2800.00, 10, 'Zanahoria'),
(143, 52, 45, 5000.00, 7, 'Aguacate'),
(144, 54, 47, 2000.00, 20, 'Agua embotellada'),
(145, 56, 49, 5000.00, 8, 'Bebida energetica'),
(146, 58, 51, 5500.00, 8, 'Pan tajado'),
(147, 60, 53, 3500.00, 10, 'Galletas'),
(148, 62, 55, 4500.00, 10, 'Arepas'),
(149, 64, 57, 3000.00, 12, 'Jabon de baño'),
(150, 66, 59, 4000.00, 8, 'Blanqueador'),
(151, 68, 61, 6500.00, 7, 'Crema dental'),
(152, 70, 63, 2500.00, 15, 'Papas fritas'),
(167, 72, 65, 3000.00, 10, 'Caramelos'),
(168, 72, 24, 7500.00, 3, 'Chocolate en polvo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura_salida`
--

CREATE TABLE `factura_salida` (
  `cod_movimiento` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL DEFAULT curdate(),
  `nota` varchar(255) DEFAULT NULL,
  `cedula_cliente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `factura_salida`
--

INSERT INTO `factura_salida` (`cod_movimiento`, `fecha`, `nota`, `cedula_cliente`) VALUES
(23, '2026-10-26', 'Venta de azucar', 12345),
(24, '2026-10-26', 'Venta de aceite vegetal', 309499),
(25, '2026-10-26', 'Venta de harina de trigo', 12345),
(26, '2026-10-26', 'Venta de sal', 309499),
(27, '2026-10-26', 'Venta de lentejas', 12345),
(28, '2026-10-26', 'Venta de frijoles', 309499),
(29, '2026-10-26', 'Venta de pasta', 12345),
(30, '2026-10-26', 'Venta de cafe', 309499),
(31, '2026-10-26', 'Venta de chocolate en polvo', 12345),
(32, '2026-10-26', 'Venta de avena', 309499),
(33, '2026-10-26', 'Venta de leche entera', 12345),
(34, '2026-10-26', 'Venta de queso campesino', 309499),
(35, '2026-10-26', 'Venta de yogur', 12345),
(36, '2026-10-26', 'Venta de mantequilla', 309499),
(37, '2026-10-26', 'Venta de huevos', 12345),
(38, '2026-10-26', 'Venta de pollo', 309499),
(39, '2026-10-26', 'Venta de carne de res', 12345),
(40, '2026-10-26', 'Venta de carne de cerdo', 309499),
(41, '2026-10-26', 'Venta de salchichas', 12345),
(42, '2026-10-26', 'Venta de jamon', 309499),
(43, '2026-10-26', 'Venta de manzanas', 12345),
(44, '2026-10-26', 'Venta de banano', 309499),
(45, '2026-10-26', 'Venta de naranjas', 12345),
(46, '2026-10-26', 'Venta de mangos', 309499),
(47, '2026-10-26', 'Venta de papa', 12345),
(48, '2026-10-26', 'Venta de tomate', 309499),
(49, '2026-10-26', 'Venta de cebolla', 12345),
(50, '2026-10-26', 'Venta de zanahoria', 309499),
(51, '2026-10-26', 'Venta de lechuga', 12345),
(52, '2026-10-26', 'Venta de aguacate', 309499),
(53, '2026-10-26', 'Venta de gaseosa', 12345),
(54, '2026-10-26', 'Venta de agua embotellada', 309499),
(55, '2026-10-26', 'Venta de jugo de naranja', 12345),
(56, '2026-10-26', 'Venta de bebida energetica', 309499),
(57, '2026-10-26', 'Venta de agua con gas', 12345),
(58, '2026-10-26', 'Venta de pan tajado', 309499),
(59, '2026-10-26', 'Venta de pan dulce', 12345),
(60, '2026-10-26', 'Venta de galletas', 309499),
(61, '2026-10-26', 'Venta de torta', 12345),
(62, '2026-10-26', 'Venta de arepas', 309499),
(63, '2026-10-26', 'Venta de detergente', 12345),
(64, '2026-10-26', 'Venta de jabon de baño', 309499),
(65, '2026-10-26', 'Venta de lavaloza', 12345),
(66, '2026-10-26', 'Venta de blanqueador', 309499),
(67, '2026-10-26', 'Venta de papel higienico', 12345),
(68, '2026-10-26', 'Venta de crema dental', 309499),
(69, '2026-10-26', 'Venta de shampoo', 12345),
(70, '2026-10-26', 'Venta de papas fritas', 309499),
(71, '2026-10-26', 'Venta de chocolatina', 12345),
(72, '2026-10-26', '', 309499);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `cod_inventario` int(11) NOT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `cod_producto` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(12, '2026-10-26', 3500.00, 80, 'Entrada de leche', 18, 26),
(13, '2026-10-26', 12000.00, 30, 'Entrada de queso', 18, 27),
(14, '2026-10-26', 4500.00, 40, 'Entrada de yogur', 18, 28),
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
(48, '2026-10-26', 5000.00, 30, 'Entrada de crema dental', 20, 61),
(49, '2026-10-26', 9000.00, 25, 'Entrada de shampoo', 20, 62),
(50, '2026-10-26', 1800.00, 60, 'Entrada de papas fritas', 22, 63),
(51, '2026-10-26', 1200.00, 80, 'Entrada de chocolatinas', 22, 64),
(52, '2026-10-26', 2000.00, 50, 'Entrada de caramelos', 22, 65);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mov_salida`
--

CREATE TABLE `mov_salida` (
  `cod_movimiento` int(11) NOT NULL,
  `fecha` date DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `nota` varchar(100) DEFAULT NULL,
  `cod_producto` int(11) DEFAULT NULL,
  `cedula_cliente` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mov_salida`
--

INSERT INTO `mov_salida` (`cod_movimiento`, `fecha`, `precio`, `cantidad`, `nota`, `cod_producto`, `cedula_cliente`) VALUES
(3, '2026-10-26', 8500.00, 1, 'Venta de aceite', 17, 309499),
(4, '2026-10-26', 3200.00, 3, 'Venta de harina', 18, 12345),
(5, '2026-10-26', 1800.00, 2, 'Venta de sal', 19, 309499),
(6, '2026-10-26', 5000.00, 1, 'Venta de lentejas', 20, 12345),
(7, '2026-10-26', 6500.00, 2, 'Venta de frijoles', 21, 309499),
(8, '2026-10-26', 2800.00, 4, 'Venta de pasta', 22, 12345),
(9, '2026-10-26', 12000.00, 1, 'Venta de cafe', 23, 309499),
(10, '2026-10-26', 7500.00, 2, 'Venta de chocolate', 24, 12345),
(11, '2026-10-26', 4000.00, 3, 'Venta de avena', 25, 309499),
(12, '2026-10-26', 4200.00, 2, 'Venta de leche', 26, 12345),
(13, '2026-10-26', 14000.00, 1, 'Venta de queso', 27, 309499),
(14, '2026-10-26', 5500.00, 2, 'Venta de yogur', 28, 12345),
(16, '2026-10-26', 7000.00, 1, 'Venta de mantequilla', 29, 309499),
(17, '2026-10-26', 18000.00, 1, 'Venta de huevos', 30, 12345),
(18, '2026-10-26', 12000.00, 2, 'Venta de pollo', 31, 309499),
(19, '2026-10-26', 26000.00, 1, 'Venta de carne de res', 32, 12345),
(20, '2026-10-26', 3000.00, 3, 'Venta de bananos', 37, 309499),
(21, '2026-10-26', 4500.00, 2, 'Venta de gaseosa', 46, 12345),
(22, '2026-10-26', 8000.00, 1, 'Venta de papel higienico', 60, 309499),
(23, '2026-10-26', 4500.00, 5, 'Venta de azucar', 16, 12345),
(24, '2026-10-26', 8500.00, 4, 'Venta de aceite vegetal', 17, 309499),
(25, '2026-10-26', 3200.00, 6, 'Venta de harina de trigo', 18, 12345),
(26, '2026-10-26', 1800.00, 10, 'Venta de sal', 19, 309499),
(27, '2026-10-26', 5000.00, 8, 'Venta de lentejas', 20, 12345),
(28, '2026-10-26', 6500.00, 5, 'Venta de frijoles', 21, 309499),
(29, '2026-10-26', 2800.00, 12, 'Venta de pasta', 22, 12345),
(30, '2026-10-26', 12000.00, 3, 'Venta de cafe', 23, 309499),
(31, '2026-10-26', 7500.00, 6, 'Venta de chocolate en polvo', 24, 12345),
(32, '2026-10-26', 4000.00, 9, 'Venta de avena', 25, 309499),
(33, '2026-10-26', 4200.00, 10, 'Venta de leche entera', 26, 12345),
(34, '2026-10-26', 14000.00, 4, 'Venta de queso campesino', 27, 309499),
(35, '2026-10-26', 5500.00, 7, 'Venta de yogur', 28, 12345),
(36, '2026-10-26', 7000.00, 5, 'Venta de mantequilla', 29, 309499),
(37, '2026-10-26', 18000.00, 4, 'Venta de huevos', 30, 12345),
(38, '2026-10-26', 12000.00, 8, 'Venta de pollo', 31, 309499),
(39, '2026-10-26', 26000.00, 5, 'Venta de carne de res', 32, 12345),
(40, '2026-10-26', 22000.00, 4, 'Venta de carne de cerdo', 33, 309499),
(41, '2026-10-26', 8500.00, 6, 'Venta de salchichas', 34, 12345),
(42, '2026-10-26', 9000.00, 5, 'Venta de jamon', 35, 309499),
(43, '2026-10-26', 6000.00, 10, 'Venta de manzanas', 36, 12345),
(44, '2026-10-26', 3000.00, 15, 'Venta de banano', 37, 309499),
(45, '2026-10-26', 4000.00, 12, 'Venta de naranjas', 38, 12345),
(46, '2026-10-26', 5000.00, 8, 'Venta de mangos', 39, 309499),
(47, '2026-10-26', 3500.00, 20, 'Venta de papa', 40, 12345),
(48, '2026-10-26', 4500.00, 10, 'Venta de tomate', 41, 309499),
(49, '2026-10-26', 3200.00, 15, 'Venta de cebolla', 42, 12345),
(50, '2026-10-26', 2800.00, 10, 'Venta de zanahoria', 43, 309499),
(51, '2026-10-26', 2500.00, 8, 'Venta de lechuga', 44, 12345),
(52, '2026-10-26', 5000.00, 7, 'Venta de aguacate', 45, 309499),
(53, '2026-10-26', 4500.00, 12, 'Venta de gaseosa', 46, 12345),
(54, '2026-10-26', 2000.00, 20, 'Venta de agua embotellada', 47, 309499),
(55, '2026-10-26', 3800.00, 10, 'Venta de jugo de naranja', 48, 12345),
(56, '2026-10-26', 5000.00, 8, 'Venta de bebida energetica', 49, 309499),
(57, '2026-10-26', 2500.00, 12, 'Venta de agua con gas', 50, 12345),
(58, '2026-10-26', 5500.00, 8, 'Venta de pan tajado', 51, 309499),
(59, '2026-10-26', 1500.00, 15, 'Venta de pan dulce', 52, 12345),
(60, '2026-10-26', 3500.00, 10, 'Venta de galletas', 53, 309499),
(61, '2026-10-26', 12000.00, 4, 'Venta de torta', 54, 12345),
(62, '2026-10-26', 4500.00, 10, 'Venta de arepas', 55, 309499),
(63, '2026-10-26', 9000.00, 8, 'Venta de detergente', 56, 12345),
(64, '2026-10-26', 3000.00, 12, 'Venta de jabon de baño', 57, 309499),
(65, '2026-10-26', 4500.00, 10, 'Venta de lavaloza', 58, 12345),
(66, '2026-10-26', 4000.00, 8, 'Venta de blanqueador', 59, 309499),
(67, '2026-10-26', 8000.00, 6, 'Venta de papel higienico', 60, 12345),
(68, '2026-10-26', 6500.00, 7, 'Venta de crema dental', 61, 309499),
(69, '2026-10-26', 11000.00, 5, 'Venta de shampoo', 62, 12345),
(70, '2026-10-26', 2500.00, 15, 'Venta de papas fritas', 63, 309499),
(71, '2026-10-26', 1800.00, 20, 'Venta de chocolatina', 64, 12345),
(72, '2026-10-26', 3000.00, 10, 'Venta de caramelos', 65, 309499);

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
(16, 'Azucar', 4500.00, 'Abarrotes', 'Azucar blanca 1 kg', 12),
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
  `contra` varchar(50) DEFAULT NULL,
  `id_tipo_usuario` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`cedula`, `nombre`, `apellido`, `telefono`, `direccion`, `email`, `usuario`, `contra`, `id_tipo_usuario`) VALUES
(0, 'yakeline', 'becerra', '21321554', 'el rosario', 'yakis@gmail.com', 'yakis', 'y12345', 1),
(1094049616, 'yasmeira', 'acosta', '12345', 'mz4lote1', 'yasme@gmail.com', 'yasmeira', 'y1234', 2);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`cedula_cliente`);

--
-- Indices de la tabla `detalle_salida`
--
ALTER TABLE `detalle_salida`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `fk_detsal_producto` (`cod_producto`),
  ADD KEY `fk_detsal_factura` (`cod_movimiento`);

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
  ADD PRIMARY KEY (`cod_movimiento`),
  ADD KEY `cod_producto` (`cod_producto`),
  ADD KEY `fk_cliente_mov_salida` (`cedula_cliente`);

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
-- AUTO_INCREMENT de la tabla `detalle_salida`
--
ALTER TABLE `detalle_salida`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- AUTO_INCREMENT de la tabla `factura_salida`
--
ALTER TABLE `factura_salida`
  MODIFY `cod_movimiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_salida`
--
ALTER TABLE `detalle_salida`
  ADD CONSTRAINT `fk_detsal_factura` FOREIGN KEY (`cod_movimiento`) REFERENCES `factura_salida` (`cod_movimiento`),
  ADD CONSTRAINT `fk_detsal_producto` FOREIGN KEY (`cod_producto`) REFERENCES `producto` (`cod_producto`);

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
  ADD CONSTRAINT `fk_cliente_mov_salida` FOREIGN KEY (`cedula_cliente`) REFERENCES `cliente` (`cedula_cliente`),
  ADD CONSTRAINT `mov_salida_ibfk_2` FOREIGN KEY (`cod_producto`) REFERENCES `producto` (`cod_producto`);

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
