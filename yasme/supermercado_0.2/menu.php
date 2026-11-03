
<?php

// carga el archivo que hace la conexion con la base de datos
include("conexion.php");

// activar los errores de mysql
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// comprobar la conexion
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}

// si no hay una conexion valida detiene la pagina
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Error al conectar con la base de datos.");
}

// define la codificacion de los caracteres de la base de datos
$conn->set_charset("utf8mb4");

// obtener el usuario y el rol
$usuario = $_POST["usuario"] ?? $_GET["usuario"] ?? "";
$rol = (string) ($_POST["rol"] ?? $_GET["rol"] ?? "");

// comprobar los datos recibidos
if ($usuario === "" || ($rol !== "1" && $rol !== "2")) {
    // redirige a index.html
    header("Location: index.html");
    exit();
}

// identificar el cargo del usuario
$nombreCargo = ($rol === "1") ? "Administrador" : "Empleado";
// esta variable almacena la primera letra del usuario para el encabezado
$inicial = strtoupper(substr(trim($usuario), 0, 1));
// esta variable almacena el mensaje que se muestra al usuario
$mensaje = "";

// inicializar los contadores
$totalProductos = 0;
$totalClientes = 0;
$totalEntradas = 0;
$totalSalidas = 0;
$totalProveedores = 0;
$totalUsuarios = 0;
$totalMovimientos = 0;

// consultar los productos registrados
try {
    // ejecuta la consulta sql
    $resultado = $conn->query("SELECT COUNT(*) AS total FROM producto");
    // obtiene la fila del resultado
    $fila = $resultado->fetch_assoc();
    // esta variable almacena el total de registros obtenido
    $totalProductos = (int) $fila["total"];
} catch (mysqli_sql_exception $e) {
    // si falla la base de datos guarda el mensaje de error
    error_log("Error al contar productos: " . $e->getMessage());
}

// consultar los clientes registrados
try {
    // ejecuta la consulta sql
    $resultado = $conn->query("SELECT COUNT(*) AS total FROM cliente");
    // obtiene la fila del resultado
    $fila = $resultado->fetch_assoc();
    // esta variable almacena el total de registros obtenido
    $totalClientes = (int) $fila["total"];
} catch (mysqli_sql_exception $e) {
    // si falla la base de datos guarda el mensaje de error
    error_log("Error al contar clientes: " . $e->getMessage());
}

// consultar las entradas registradas
try {
    // ejecuta la consulta sql
    $resultado = $conn->query("SELECT COUNT(*) AS total FROM mov_entrada");
    // obtiene la fila del resultado
    $fila = $resultado->fetch_assoc();
    // esta variable almacena el total de registros obtenido
    $totalEntradas = (int) $fila["total"];
} catch (mysqli_sql_exception $e) {
    // si falla la base de datos guarda el mensaje de error
    error_log("Error al contar entradas: " . $e->getMessage());
}

// cuenta las salidas (cada factura es una salida)
try {
    // ejecuta la consulta sql
    $resultado = $conn->query("SELECT COUNT(*) AS total FROM factura_salida");
    // obtiene la fila del resultado
    $fila = $resultado->fetch_assoc();
    // esta variable almacena el total de registros obtenido
    $totalSalidas = (int) $fila["total"];
} catch (mysqli_sql_exception $e) {
    // si falla la base de datos guarda el mensaje de error
    error_log("Error al contar salidas: " . $e->getMessage());
}

// consultar los proveedores registrados
try {
    // ejecuta la consulta sql
    $resultado = $conn->query("SELECT COUNT(*) AS total FROM proveedor");
    // obtiene la fila del resultado
    $fila = $resultado->fetch_assoc();
    // esta variable almacena el total de registros obtenido
    $totalProveedores = (int) $fila["total"];
} catch (mysqli_sql_exception $e) {
    // si falla la base de datos guarda el mensaje de error
    error_log("Error al contar proveedores: " . $e->getMessage());
}

// consultar los usuarios registrados
try {
    // ejecuta la consulta sql
    $resultado = $conn->query("SELECT COUNT(*) AS total FROM usuario");
    // obtiene la fila del resultado
    $fila = $resultado->fetch_assoc();
    // esta variable almacena el total de registros obtenido
    $totalUsuarios = (int) $fila["total"];
} catch (mysqli_sql_exception $e) {
    // si falla la base de datos guarda el mensaje de error
    error_log("Error al contar usuarios: " . $e->getMessage());
}

// sumar las entradas y las salidas
$totalMovimientos = $totalEntradas + $totalSalidas;

// procesar los botones del menu
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // regresar al inicio
    if (isset($_POST["salir"])) {
        // redirige a index.html
        header("Location: index.html");
        exit();
    }

    // abrir el modulo de clientes
    if (isset($_POST["cli"])) {
        // redirige a cliente.php
        header("Location: cliente.php?usuario=" . urlencode($usuario) . "&rol=" . urlencode($rol));
        exit();
    }

    // abrir el modulo de salidas
    if (isset($_POST["mov_salida"])) {
        // redirige a mov_salida.php
        header("Location: mov_salida.php?usuario=" . urlencode($usuario) . "&rol=" . urlencode($rol));
        exit();
    }

    // abrir productos si es administrador
    if (isset($_POST["pro"])) {
        if ($rol === "1") {
            // redirige a producto.php
            header("Location: producto.php?usuario=" . urlencode($usuario) . "&rol=1");
            exit();
        }
        $mensaje = "No tienes permiso para acceder a este módulo.";
    }

    // abrir entradas si es administrador
    if (isset($_POST["mov_entrada"])) {
        if ($rol === "1") {
            // redirige a mov_entrada.php
            header("Location: mov_entrada.php?usuario=" . urlencode($usuario) . "&rol=1");
            exit();
        }
        $mensaje = "No tienes permiso para acceder a este módulo.";
    }

    // abrir proveedores si es administrador
    if (isset($_POST["prov"])) {
        if ($rol === "1") {
            // redirige a proveedor.php
            header("Location: proveedor.php?usuario=" . urlencode($usuario) . "&rol=1");
            exit();
        }
        $mensaje = "No tienes permiso para acceder a este módulo.";
    }

    // abrir inventario si es administrador
    if (isset($_POST["inv"])) {
        if ($rol === "1") {
            // redirige a inventario.php
            header("Location: inventario.php?usuario=" . urlencode($usuario) . "&rol=1");
            exit();
        }
        $mensaje = "No tienes permiso para acceder a este módulo.";
    }

    // abrir reportes si es administrador
    if (isset($_POST["reportes"])) {
        if ($rol === "1") {
            // redirige a reportes.php
            header("Location: reportes.php?usuario=" . urlencode($usuario) . "&rol=1");
            exit();
        }
        $mensaje = "No tienes permiso para acceder a este módulo.";
    }

    // abrir la administracion de usuarios
    if (isset($_POST["editar"])) {
        if ($rol === "1") {
            // redirige a edita_usuario.php
            header("Location: edita_usuario.php?usuario=" . urlencode($usuario) . "&rol=1");
            exit();
        }
        $mensaje = "No tienes permiso para acceder a este módulo.";
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
    <link rel="stylesheet" href="css/menu.css">
</head>

<!-- cuerpo de la pagina -->
<body class="pagina-menu">

    <!-- encabezado principal -->
    <header class="encabezado">

        <button type="button" class="boton-menu" id="abrirMenu"
            aria-label="Abrir menú lateral" aria-controls="menuLateral"
            aria-expanded="false">
            <img src="img/menu.png" alt="" class="icono">
        </button>

        <div class="encabezado-logo">
            <img src="img/logo.png" alt="Logo de La Granja">
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
                <img src="img/logo.png" alt="Logo de La Granja">
                <div>
                    <h2>Supermercado</h2>
                    <p>La Granja</p>
                </div>
            </div>

            <button type="button" class="boton-cerrar" id="cerrarMenu"
                aria-label="Cerrar menú lateral">
                <img src="img/cerrar.png" alt="" class="icono">
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
                <input type="hidden" name="rol" value="<?= htmlspecialchars($rol) ?>">
                <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

                <button type="button" class="enlace-menu activo">
                    <span class="caja-icono icono-inicio">
                        <img src="img/inicio.png" alt="" class="icono">
                    </span>
                    <span>Inicio</span>
                </button>

                <button type="submit" name="cli" class="enlace-menu">
                    <span class="caja-icono">
                        <img src="img/cliente.png" alt="" class="icono">
                    </span>
                    <span>Cliente</span>
                </button>

                <button type="submit" name="mov_salida" class="enlace-menu">
                    <span class="caja-icono">
                        <img src="img/salida.png" alt="" class="icono">
                    </span>
                    <span>Movimiento de salida</span>
                </button>

                <?php if ($rol === "1"): ?>

                    <button type="submit" name="pro" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="img/producto.png" alt="" class="icono">
                        </span>
                        <span>Productos</span>
                    </button>

                    <button type="submit" name="mov_entrada" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="img/entrada.png" alt="" class="icono">
                        </span>
                        <span>Movimiento de entrada</span>
                    </button>

                    <button type="submit" name="prov" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="img/proveedor.png" alt="" class="icono">
                        </span>
                        <span>Proveedor</span>
                    </button>

                    <button type="submit" name="inv" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="img/inventario.png" alt="" class="icono">
                        </span>
                        <span>Inventario</span>
                    </button>

                    <button type="submit" name="reportes" class="enlace-menu">
                        <span class="caja-icono">
                            <img src="img/reportes.png" alt="" class="icono">
                        </span>
                        <span>Reportes</span>
                    </button>

                    <button type="submit" name="editar" class="enlace-menu">
                        <span class="caja-icono icono-modificar">
                            <img src="img/usuario.png" alt="" class="icono">
                        </span>
                        <span>Modificar usuario</span>
                    </button>

                <?php endif; ?>
            </form>
        </nav>

        <!-- formulario para cerrar sesion -->
        <form action="menu.php" method="POST" class="form-salir">
            <input type="hidden" name="rol" value="<?= htmlspecialchars($rol) ?>">
            <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

            <button type="submit" name="salir" class="boton-salir">
                <span class="caja-icono caja-icono-roja">
                    <img src="img/salir.png" alt="" class="icono">
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
                <input type="hidden" name="rol" value="<?= htmlspecialchars($rol) ?>">
                <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

                <button type="submit" name="cli" class="tarjeta tarjeta-turquesa">
                    <span class="tarjeta-icono">
                        <img src="img/cliente.png" alt="" class="icono">
                    </span>
                    <h3>Clientes</h3>
                    <span class="tarjeta-numero"><?= $totalClientes ?></span>
                    <p>Clientes registrados</p>
                    <span class="tarjeta-enlace">Ingresar →</span>
                </button>
            </form>

            <!-- formulario -->
            <form action="menu.php" method="POST" class="form-tarjeta">
                <input type="hidden" name="rol" value="<?= htmlspecialchars($rol) ?>">
                <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

                <button type="submit" name="mov_salida" class="tarjeta tarjeta-roja">
                    <span class="tarjeta-icono">
                        <img src="img/salida.png" alt="" class="icono">
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
                    <input type="hidden" name="rol" value="1">
                    <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

                    <button type="submit" name="pro" class="tarjeta tarjeta-verde">
                        <span class="tarjeta-icono">
                            <img src="img/producto.png" alt="" class="icono">
                        </span>
                        <h3>Productos</h3>
                        <span class="tarjeta-numero"><?= $totalProductos ?></span>
                        <p>Productos registrados</p>
                        <span class="tarjeta-enlace">Ver productos →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">
                    <input type="hidden" name="rol" value="1">
                    <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

                    <button type="submit" name="mov_entrada" class="tarjeta tarjeta-naranja">
                        <span class="tarjeta-icono">
                            <img src="img/entrada.png" alt="" class="icono">
                        </span>
                        <h3>Movimiento de entrada</h3>
                        <span class="tarjeta-numero"><?= $totalEntradas ?></span>
                        <p>Entradas registradas</p>
                        <span class="tarjeta-enlace">Ingresar →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">
                    <input type="hidden" name="rol" value="1">
                    <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

                    <button type="submit" name="prov" class="tarjeta tarjeta-morada">
                        <span class="tarjeta-icono">
                            <img src="img/proveedor.png" alt="" class="icono">
                        </span>
                        <h3>Proveedores</h3>
                        <span class="tarjeta-numero"><?= $totalProveedores ?></span>
                        <p>Proveedores registrados</p>
                        <span class="tarjeta-enlace">Ingresar →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">
                    <input type="hidden" name="rol" value="1">
                    <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

                    <button type="submit" name="inv" class="tarjeta tarjeta-turquesa">
                        <span class="tarjeta-icono">
                            <img src="img/inventario.png" alt="" class="icono">
                        </span>
                        <h3>Inventario</h3>
                        <span class="tarjeta-numero"><?= $totalProductos ?></span>
                        <p>Productos registrados</p>
                        <span class="tarjeta-enlace">Ingresar →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">
                    <input type="hidden" name="rol" value="1">
                    <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

                    <button type="submit" name="reportes" class="tarjeta tarjeta-rosa">
                        <span class="tarjeta-icono">
                            <img src="img/reportes.png" alt="" class="icono">
                        </span>
                        <h3>Reportes</h3>
                        <span class="tarjeta-numero"><?= $totalMovimientos ?></span>
                        <p>Movimientos registrados</p>
                        <span class="tarjeta-enlace">Ingresar →</span>
                    </button>
                </form>

                <!-- formulario -->
                <form action="menu.php" method="POST" class="form-tarjeta">
                    <input type="hidden" name="rol" value="1">
                    <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">

                    <button type="submit" name="editar" class="tarjeta tarjeta-morada">
                        <span class="tarjeta-icono tarjeta-icono-claro">
                            <img src="img/usuario.png" alt="" class="icono">
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

                <a href="ayuda.php?usuario=<?= urlencode($usuario) ?>&rol=<?= urlencode($rol) ?>" class="footer-enlace">
                    <span class="footer-icono">ⓘ</span>
                    Centro de ayuda
                </a>

                <a href="ayuda.php?usuario=<?= urlencode($usuario) ?>&rol=<?= urlencode($rol) ?>#privacidad" class="footer-enlace">
                    <span class="footer-icono">♧</span>
                    Privacidad y seguridad
                </a>

                <a href="ayuda.php?usuario=<?= urlencode($usuario) ?>&rol=<?= urlencode($rol) ?>#uso" class="footer-enlace">
                    <span class="footer-icono">▤</span>
                    Manual de uso del sistema
                </a>

                <a href="ayuda.php?usuario=<?= urlencode($usuario) ?>&rol=<?= urlencode($rol) ?>#soporte" class="footer-enlace">
                    <span class="footer-icono">?</span>
                    Asistencia técnica
                </a>

                <a href="ayuda.php?usuario=<?= urlencode($usuario) ?>&rol=<?= urlencode($rol) ?>#inventario" class="footer-enlace">
                    <span class="footer-icono">▣</span>
                    Gestión y control de inventario
                </a>

                <a href="ayuda.php?usuario=<?= urlencode($usuario) ?>&rol=<?= urlencode($rol) ?>#movimientos" class="footer-enlace">
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

    <?php $conn->close(); ?>

    <!-- abrir y cerrar el menu lateral -->
    <script>
        // elementos de la pagina que se usan en el codigo
        const abrirMenu = document.getElementById("abrirMenu");
        const cerrarMenu = document.getElementById("cerrarMenu");
        const fondoMenu = document.getElementById("fondoMenu");

        // escucha el clic del usuario
        abrirMenu.addEventListener("click", () => {
            document.body.classList.add("menu-abierto");
            abrirMenu.setAttribute("aria-expanded", "true");
        });

        // esta funcion cierra el menu lateral
        function cerrarMenuLateral() {
            document.body.classList.remove("menu-abierto");
            abrirMenu.setAttribute("aria-expanded", "false");
        }

        // escucha el clic del usuario
        cerrarMenu.addEventListener("click", cerrarMenuLateral);
        // escucha el clic del usuario
        fondoMenu.addEventListener("click", cerrarMenuLateral);

        // escucha las teclas que presiona el usuario
        document.addEventListener("keydown", (evento) => {
            if (evento.key === "Escape") {
                cerrarMenuLateral();
            }
        });
    </script>

</body>
</html>
