-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-11-2026 a las 02:31:29
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
(12345, 'yakis', '1234661111', 'yakis@gmail.com', 'mz lote 515 manuela'),
(123456, 'yakis', '3239885116', '', ''),
(77777777, 'qqqq', '3234567890', '', ''),
(98765432, 'eee', '3230987644', '', '');

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
(4, '2026-11-11', '', 12345, '1', 200000.00),
(5, '2026-11-11', '', 12345, '1', 50000.00),
(6, '2026-11-12', '', 123456, '1', 7000.00),
(7, '2026-11-16', '', 98765432, '1', 20000.00),
(8, '2026-11-16', '', 98765432, '1', 60000.00),
(9, '2026-11-17', '', 123456, '1', 400000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `cod_inventario` int(11) NOT NULL,
  `cod_producto` int(11) NOT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `proveedor` varchar(150) NOT NULL,
  `entradas` int(11) NOT NULL DEFAULT 0,
  `salidas` int(11) NOT NULL DEFAULT 0,
  `stock_actual` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`cod_inventario`, `cod_producto`, `nombre_producto`, `precio_unitario`, `proveedor`, `entradas`, `salidas`, `stock_actual`) VALUES
(1, 11, 'arroz', 20000.00, '11 - yasme', 82, 32, 50),
(2, 12, 'higado', 40000.00, '11 - yasme', 0, 0, 0),
(3, 13, 'fresas', 50000.00, '11 - yasme', 0, 0, 0),
(4, 15, 'coca-cola', 3500.00, '15 - coca-cola', 90, 0, 90),
(5, 16, 'aceite', 4800.00, '11 - yasme', 160, 0, 160),
(6, 17, 'Aceite vegetal', 8500.00, '12 - Distribuidora La Canasta', 80, 0, 80),
(7, 18, 'Harina de trigo', 3200.00, '13 - Alimentos del Campo', 90, 1, 89),
(8, 19, 'Sal', 1800.00, '13 - Alimentos del Campo', 100, 0, 100),
(9, 20, 'Lentejas', 5000.00, '21 - Distribuidora de Granos', 60, 10, 50),
(10, 21, 'Frijoles', 6500.00, '21 - Distribuidora de Granos', 60, 0, 60),
(11, 22, 'Pasta', 2800.00, '12 - Distribuidora La Canasta', 100, 0, 100),
(12, 23, 'Cafe', 12000.00, '12 - Distribuidora La Canasta', 40, 0, 40),
(13, 24, 'Chocolate en polvo', 7500.00, '13 - Alimentos del Campo', 50, 0, 50),
(14, 25, 'Avena', 4000.00, '13 - Alimentos del Campo', 70, 0, 70),
(15, 26, 'Leche entera', 4200.00, '18 - Lacteos del Norte', 0, 0, 0),
(16, 27, 'Queso campesino', 14000.00, '18 - Lacteos del Norte', 30, 0, 30),
(17, 28, 'Yogur', 5500.00, '18 - Lacteos del Norte', 0, 0, 0),
(18, 29, 'Mantequilla', 7000.00, '18 - Lacteos del Norte', 30, 0, 30),
(19, 30, 'Huevos', 18000.00, '13 - Alimentos del Campo', 20, 0, 20),
(20, 31, 'Pollo', 12000.00, '16 - DistriCarnes', 40, 0, 40),
(21, 32, 'Carne de res', 26000.00, '16 - DistriCarnes', 30, 0, 30),
(22, 33, 'Carne de cerdo', 22000.00, '16 - DistriCarnes', 30, 0, 30),
(23, 34, 'Salchichas', 8500.00, '16 - DistriCarnes', 40, 0, 40),
(24, 35, 'Jamon', 9000.00, '16 - DistriCarnes', 30, 0, 30),
(25, 36, 'Manzanas', 6000.00, '17 - Frutas Frescas', 50, 0, 50),
(26, 37, 'Banano', 3000.00, '17 - Frutas Frescas', 80, 0, 80),
(27, 38, 'Naranjas', 4000.00, '17 - Frutas Frescas', 60, 0, 60),
(28, 39, 'Mangos', 5000.00, '17 - Frutas Frescas', 50, 0, 50),
(29, 40, 'Papa', 3500.00, '17 - Frutas Frescas', 100, 2, 98),
(30, 41, 'Tomate', 4500.00, '17 - Frutas Frescas', 70, 0, 70),
(31, 42, 'Cebolla', 3200.00, '17 - Frutas Frescas', 70, 0, 70),
(32, 43, 'Zanahoria', 2800.00, '17 - Frutas Frescas', 60, 0, 60),
(33, 44, 'Lechuga', 2500.00, '17 - Frutas Frescas', 40, 0, 40),
(34, 45, 'Aguacate', 5000.00, '17 - Frutas Frescas', 40, 0, 40),
(35, 46, 'Gaseosa', 4500.00, '14 - Postobon', 60, 0, 60),
(36, 47, 'Agua embotellada', 2000.00, '14 - Postobon', 100, 0, 100),
(37, 48, 'Jugo de naranja', 3800.00, '14 - Postobon', 50, 0, 50),
(38, 49, 'Bebida energetica', 5000.00, '14 - Postobon', 40, 0, 40),
(39, 50, 'Agua con gas', 2500.00, '14 - Postobon', 60, 0, 60),
(40, 51, 'Pan tajado', 5500.00, '19 - Panaderia El Trigal', 40, 0, 40),
(41, 52, 'Pan dulce', 1500.00, '19 - Panaderia El Trigal', 60, 0, 60),
(42, 53, 'Galletas', 3500.00, '19 - Panaderia El Trigal', 50, 0, 50),
(43, 54, 'Torta', 12000.00, '19 - Panaderia El Trigal', 20, 0, 20),
(44, 55, 'Arepas', 4500.00, '19 - Panaderia El Trigal', 50, 0, 50),
(45, 56, 'Detergente', 9000.00, '20 - Productos de Aseo Limpio', 40, 0, 40),
(46, 57, 'Jabon de baño', 3000.00, '20 - Productos de Aseo Limpio', 60, 0, 60),
(47, 58, 'Lavaloza', 4500.00, '20 - Productos de Aseo Limpio', 40, 0, 40),
(48, 59, 'Blanqueador', 4000.00, '20 - Productos de Aseo Limpio', 40, 0, 40),
(49, 60, 'Papel higienico', 8000.00, '20 - Productos de Aseo Limpio', 30, 0, 30),
(50, 61, 'Crema dental', 6500.00, '20 - Productos de Aseo Limpio', 30, 0, 30),
(51, 62, 'Shampoo', 11000.00, '20 - Productos de Aseo Limpio', 0, 0, 0),
(52, 63, 'Papas fritas', 2500.00, '22 - Dulces y Golosinas', 0, 0, 0),
(53, 64, 'Chocolatina', 1800.00, '22 - Dulces y Golosinas', 0, 0, 0),
(54, 65, 'Caramelos', 3000.00, '22 - Dulces y Golosinas', 0, 0, 0),
(55, 66, 'menudencia', 2000.00, '12 - Distribuidora La Canasta', 50, 40, 10);

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
(0, '2026-11-16', 2000.00, 50, '', 12, 66),
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
(4, 11, 20000.00, 10, 'arroz', '2026-11-11', 12345),
(5, 20, 5000.00, 10, 'Lentejas', '2026-11-11', 12345),
(6, 40, 3500.00, 2, 'Papa', '2026-11-12', 123456),
(7, 66, 2000.00, 10, 'menudencia', '2026-11-16', 98765432),
(8, 66, 2000.00, 30, 'menudencia', '2026-11-16', 98765432),
(9, 11, 20000.00, 20, 'arroz', '2026-11-17', 123456);

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
(65, 'Caramelos', 3000.00, 'Dulces', 'Bolsa de caramelos', 22),
(66, 'menudencia', 2000.00, 'pollo', '', 12);

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
  `contra` varchar(255) NOT NULL,
  `id_tipo_usuario` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`cedula`, `nombre`, `apellido`, `telefono`, `direccion`, `email`, `usuario`, `contra`, `id_tipo_usuario`) VALUES
(0, NULL, NULL, NULL, NULL, NULL, 'yakis', '$2y$10$Ct97zgOi0GIkH.LHz/e9VuKSW6K9eNoLnAgDuIPGQWMwjYp0bRSrq', 1),
(123, 'aaa', 'aaaa', '123777777', 'aaa', 'aaa@gmail.com', 'estiven', '123456789', 2),
(1234, 'andres', 'perez', '3123787015', 'mz lote 515 manuela', 'yakelinequintero2707@gmail.com', 'yasme', '0987654321', 2),
(12345, 'estiven', 'gomez', '32345678', 'patios', 'estiven@gmail.com', 'estiven', 'e123456789', 2),
(111111, 'aaa', 'aaaa', '', '', 'aaa@gmail.com', 'aaa', '123456789', 1),
(2707988, 'yeison', 'aa', '', '', 'yakis@gmail.com', 'yeison', '1234567890', 2),
(88027144, 'alonso', 'becerra', '', '', 'alonso@gmail.com', 'alonso', 'a123456789', 2),
(1030049617, 'yeison', 'aa', '', '', 'yakis@gmail.com', 'yeison', 'y123456789', 2),
(1090524096, 'yasmeira', 'acosta', '', '', 'yasme@gmail.com', 'yasmeira', 'yasme12345', 2),
(1093907912, 'ludys', 'quintero', '3118551660', 'mz lote 515 manuela', 'ludys@gmail.com', 'ludys', '$2y$10$RCVLFw1AIZhpVwf1/WgyJuRO/bTUSI504Toz/o5jAqbavBak0G4V6', 2),
(1094049616, 'carmen', 'acosta', '', '', 'yasme@gmail.com', 'carmen', 'carmen12345', 2),
(1233434444, 'aaa', 'aaa', '11111111', 'aaaa', 'aaaa@gmail.com', 'aaa', 'aaa1111111111', 2),
(1234567890, 'aa', 'aa', '', '', 'yakis@gmail.com', 'yake', '123456789', 2);

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
  ADD UNIQUE KEY `uk_cod_producto` (`cod_producto`);

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
  MODIFY `cod_movimiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `cod_inventario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

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
  ADD CONSTRAINT `fk_inventario_producto` FOREIGN KEY (`cod_producto`) REFERENCES `producto` (`cod_producto`);

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
