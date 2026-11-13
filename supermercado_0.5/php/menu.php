<?php
session_start();

// si no ha iniciado sesion, volver al ingreso
if (!isset($_SESSION["usuario"])) {
    header("Location: ../index.php");
    exit();
}

// datos del usuario que inicio sesion
$usuario = $_SESSION["usuario"];
$rol = (string) $_SESSION["rol"]; // 1 administrador | 2 empleado
$esAdmin = ($rol === "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr($usuario, 0, 1));

// conexion a la base de datos
include("conexion.php");

// cuenta los registros de una tabla
function contar($conn, $tabla)
{
    try {
        $fila = $conn->query("SELECT COUNT(*) AS total FROM $tabla")->fetch_assoc();
        return (int) $fila["total"];
    } catch (mysqli_sql_exception $e) {
        error_log("Error al contar $tabla: " . $e->getMessage());
        return 0;
    }
}

$totalProductos = contar($conn, "producto");
$totalClientes = contar($conn, "cliente");
$totalEntradas = contar($conn, "mov_entrada");
$totalSalidas = contar($conn, "factura_salida"); // cada factura es una salida
$totalProveedores = contar($conn, "proveedor");
$totalUsuarios = contar($conn, "usuario");
$totalMovimientos = $totalEntradas + $totalSalidas;

// mensaje que se muestra al usuario
$mensaje = "";

// botones del menu: nombre del boton => [pagina, solo administrador]
$botones = [
    "cli"         => ["cliente.php", false],
    "mov_salida"  => ["mov_salida.php", false],
    "pro"         => ["producto.php", true],
    "mov_entrada" => ["mov_entrada.php", true],
    "prov"        => ["proveedor.php", true],
    "inv"         => ["inventario.php", true],
    "reportes"    => ["reportes.php", true],
    "editar"      => ["edita_usuario.php", true],
];

// abre la pagina del boton que se presiono
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($botones as $boton => [$pagina, $soloAdmin]) {
        if (isset($_POST[$boton])) {
            if ($soloAdmin && !$esAdmin) {
                $mensaje = "No tienes permiso para acceder a este módulo.";
            } else {
                header("Location: " . $pagina);
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú principal | La Granja</title>
    <!-- hoja de estilos de la pagina -->
    <link rel="stylesheet" href="../css/menu.css">
</head>

<!-- cuerpo de la pagina -->
<body class="pagina-menu">

    <!-- encabezado principal -->
    <header class="encabezado">

        <button type="button" class="boton-menu" id="abrirMenu"
            aria-label="Abrir menú lateral" aria-controls="menuLateral"
            aria-expanded="false">
            <img src="../img/menu.png" alt="" class="icono">
        </button>

        <div class="encabezado-logo">
            <img src="../img/logo.png" alt="Logo de La Granja">
        </div>

        <div class="encabezado-texto">
            <h1>Supermercado La Granja</h1>
            <p>Sistema de gestión de inventario</p>
        </div>

        <!-- datos del usuario que inicio sesion -->
        <div class="encabezado-usuario">
            <span class="usuario-inicial"><?= htmlspecialchars($inicial) ?></span>
            <div>
                <strong><?= htmlspecialchars($usuario) ?></strong>
                <p><?= htmlspecialchars($nombreCargo) ?></p>
            </div>
        </div>

    </header>

    <!-- fondo que oscurece la pagina cuando el menu esta abierto -->
    <div class="fondo-menu" id="fondoMenu"></div>

    <!-- menu lateral -->
    <aside class="menu-lateral" id="menuLateral" aria-label="Menú principal">

        <div class="menu-lateral-encabezado">
            <div class="menu-lateral-marca">
                <img src="../img/logo.png" alt="Logo de La Granja">
                <div>
                    <h2>Supermercado</h2>
                    <p>La Granja</p>
                </div>
            </div>

            <button type="button" class="boton-cerrar" id="cerrarMenu"
                aria-label="Cerrar menú lateral">
                <img src="../img/cerrar.png" alt="" class="icono">
            </button>
        </div>

        <!-- datos del usuario en el menu lateral -->
        <div class="menu-lateral-usuario">
            <div class="usuario-inicial usuario-grande">
                <?= htmlspecialchars($inicial) ?>
            </div>
            <div>
                <strong><?= htmlspecialchars($usuario) ?></strong>
                <p><?= htmlspecialchars($nombreCargo) ?></p>
            </div>
        </div>

        <!-- navegacion entre modulos -->
        <nav class="navegacion">
            <p class="titulo-navegacion">MENÚ PRINCIPAL</p>

            <!-- formulario -->
            <form action="menu.php" method="POST">

                <button type="button" class="enlace-menu activo">
                    <span class="caja-icono icono-inicio">
                        <img src="../img/inicio.png" alt="" class="icono">
                    </span>
                    <span>Inicio</span>
                </button>

                <button type="submit" name="cli" class="enlace-menu">
                    <span class="caja-icono">
                        <img src="../img/cliente.png" alt="" class="icono">
                    </span>
                    <span>Cliente</span>
                </button>

                <button type="submit" name="mov_salida" class="enlace-menu">
                    <span class="caja-icono">
                        <img src="../img/salida.png" alt="" class="icono">
                    </span>
                    <span>Movimiento de salida</span>
                </button>

                <?php if ($rol === "1"): ?>

                    <button type="submit" name="pro" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="../img/producto.png" alt="" class="icono">
                        </span>
                        <span>Productos</span>
                    </button>

                    <button type="submit" name="mov_entrada" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="../img/entrada.png" alt="" class="icono">
                        </span>
                        <span>Movimiento de entrada</span>
                    </button>

                    <button type="submit" name="prov" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="../img/proveedor.png" alt="" class="icono">
                        </span>
                        <span>Proveedor</span>
                    </button>

                    <button type="submit" name="inv" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="../img/inventario.png" alt="" class="icono">
                        </span>
                        <span>Inventario</span>
                    </button>

                    <button type="submit" name="reportes" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="../img/reportes.png" alt="" class="icono">
                        </span>
                        <span>Reportes</span>
                    </button>

                    <button type="submit" name="editar" class="enlace-menu">
                        <span class="caja-icono icono-modificar">
                            <img src="../img/usuario.png" alt="" class="icono">
                        </span>
                        <span>Modificar usuario</span>
                    </button>

                <?php endif; ?>
            </form>
        </nav>

        <!-- formulario para cerrar sesion -->
        <form action="cerrar_sesion.php" method="POST" class="form-salir">

            <button type="submit" name="salir" class="boton-salir">
                <span class="caja-icono caja-icono-roja">
                    <img src="../img/salir.png" alt="" class="icono">
                </span>
                <span>Cerrar sesión</span>
            </button>
        </form>

    </aside>

    <!-- contenido principal -->
    <main class="contenido-principal">

        <section class="bienvenida">
            <p class="texto-superior">PANEL PRINCIPAL</p>
            <h2>¡Bienvenido, <?= htmlspecialchars($usuario) ?>!</h2>

            <p class="texto-bienvenida">
                <?= htmlspecialchars($usuario) ?>, administra la información
                del supermercado desde este menú principal.
            </p>

            <!-- mensaje de aviso -->
            <?php if ($mensaje !== ""): ?>
                <p class="mensaje-error"><?= htmlspecialchars($mensaje) ?></p>
            <?php endif; ?>
        </section>

        <!-- tarjetas con las estadisticas -->
        <section class="tarjetas">

            <!-- formulario -->
            <form action="menu.php" method="POST" class="form-tarjeta">

                <button type="submit" name="cli" class="tarjeta tarjeta-turquesa">
                    <span class="tarjeta-icono">
                        <img src="../img/cliente.png" alt="" class="icono">
                    </span>
                    <h3>Clientes</h3>
                    <span class="tarjeta-numero"><?= $totalClientes ?></span>
                    <p>Clientes registrados</p>
                    <span class="tarjeta-enlace">Ingresar →</span>
                </button>
            </form>

            <!-- formulario -->
            <form action="menu.php" method="POST" class="form-tarjeta">

                <button type="submit" name="mov_salida" class="tarjeta tarjeta-roja">
                    <span class="tarjeta-icono">
                        <img src="../img/salida.png" alt="" class="icono">
                    </span>
                    <h3>Movimiento de salida</h3>
                    <span class="tarjeta-numero"><?= $totalSalidas ?></span>
                    <p>Salidas registradas</p>
                    <span class="tarjeta-enlace">Ingresar →</span>
                </button>
            </form>

            <?php if ($rol === "1"): ?>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">

                    <button type="submit" name="pro" class="tarjeta tarjeta-verde">
                        <span class="tarjeta-icono">
                            <img src="../img/producto.png" alt="" class="icono">
                        </span>
                        <h3>Productos</h3>
                        <span class="tarjeta-numero"><?= $totalProductos ?></span>
                        <p>Productos registrados</p>
                        <span class="tarjeta-enlace">Ver productos →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">

                    <button type="submit" name="mov_entrada" class="tarjeta tarjeta-naranja">
                        <span class="tarjeta-icono">
                            <img src="../img/entrada.png" alt="" class="icono">
                        </span>
                        <h3>Movimiento de entrada</h3>
                        <span class="tarjeta-numero"><?= $totalEntradas ?></span>
                        <p>Entradas registradas</p>
                        <span class="tarjeta-enlace">Ingresar →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">

                    <button type="submit" name="prov" class="tarjeta tarjeta-morada">
                        <span class="tarjeta-icono">
                            <img src="../img/proveedor.png" alt="" class="icono">
                        </span>
                        <h3>Proveedores</h3>
                        <span class="tarjeta-numero"><?= $totalProveedores ?></span>
                        <p>Proveedores registrados</p>
                        <span class="tarjeta-enlace">Ingresar →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">

                    <button type="submit" name="inv" class="tarjeta tarjeta-turquesa">
                        <span class="tarjeta-icono">
                            <img src="../img/inventario.png" alt="" class="icono">
                        </span>
                        <h3>Inventario</h3>
                        <span class="tarjeta-numero"><?= $totalProductos ?></span>
                        <p>Productos registrados</p>
                        <span class="tarjeta-enlace">Ingresar →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">

                    <button type="submit" name="reportes" class="tarjeta tarjeta-rosa">
                        <span class="tarjeta-icono">
                            <img src="../img/reportes.png" alt="" class="icono">
                        </span>
                        <h3>Reportes</h3>
                        <span class="tarjeta-numero"><?= $totalMovimientos ?></span>
                        <p>Movimientos registrados</p>
                        <span class="tarjeta-enlace">Ingresar →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">

                    <button type="submit" name="editar" class="tarjeta tarjeta-morada">
                        <span class="tarjeta-icono tarjeta-icono-claro">
                            <img src="../img/usuario.png" alt="" class="icono">
                        </span>
                        <h3>Modificar usuario</h3>
                        <span class="tarjeta-numero"><?= $totalUsuarios ?></span>
                        <p>Usuarios registrados</p>
                        <span class="tarjeta-enlace">Ingresar →</span>
                    </button>
                </form>

            <?php endif; ?>

        </section>
    </main>

    <!-- pie de pagina -->
    <footer class="pie-pagina">

        <div class="footer-contenido">

            <!-- enlaces de ayuda -->
            <section class="footer-ayuda">
                <h4>AYUDA Y SOPORTE</h4>

                <p class="footer-descripcion">
                    Encuentra orientación para utilizar correctamente
                    el sistema de gestión de inventario.
                </p>

                <a href="ayuda.php" class="footer-enlace">
                    <span class="footer-icono">ⓘ</span>
                    Centro de ayuda
                </a>

                <a href="ayuda.php#privacidad" class="footer-enlace">
                    <span class="footer-icono">♧</span>
                    Privacidad y seguridad
                </a>

                <a href="ayuda.php#uso" class="footer-enlace">
                    <span class="footer-icono">▤</span>
                    Manual de uso del sistema
                </a>

                <a href="ayuda.php#soporte" class="footer-enlace">
                    <span class="footer-icono">?</span>
                    Asistencia técnica
                </a>

                <a href="ayuda.php#inventario" class="footer-enlace">
                    <span class="footer-icono">▣</span>
                    Gestión y control de inventario
                </a>

                <a href="ayuda.php#movimientos" class="footer-enlace">
                    <span class="footer-icono">↔</span>
                    Movimientos de entrada y salida
                </a>
            </section>

            <!-- informacion del sistema -->
            <section class="footer-informacion">
                <h4>INFORMACIÓN DEL SISTEMA</h4>

                <p>
                    <strong>Plataforma administrativa</strong><br>
                    Herramienta para organizar y controlar
                    los productos del supermercado.
                </p>

                <p>
                    <strong>Gestión de productos</strong><br>
                    Registro, consulta y actualización
                    de información de los productos.
                </p>

                <p>
                    <strong>Control de movimientos</strong><br>
                    Seguimiento de las entradas y salidas de mercancía.
                </p>

                <div class="footer-nota">
                    <strong>Uso del sistema</strong>
                    <p>
                        Acceso destinado a empleados
                        y administradores autorizados.
                    </p>
                </div>
            </section>

        </div>

        <!-- parte inferior del pie de pagina -->
        <div class="footer-inferior">
            <p>
                &copy; <?= date("Y") ?> Supermercado La Granja.
                Todos los derechos reservados.
            </p>
            <p>Sistema de gestión de inventario</p>
        </div>

    </footer>

    
    <script src = "../js/menu.js"></script>

</body>
</html>
