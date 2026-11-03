<?php
// mostrar los errores de mysql
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// recibir el usuario y el cargo
$usuario = trim((string) ($_GET["usuario"] ?? ""));
$cargo = (string) ($_GET["rol"] ?? "");

// verificar los datos de acceso
if ($usuario === "" || ($cargo !== "1" && $cargo !== "2")) {
    header("Location: index.html");
    exit();
}

// conservar los datos en los enlaces
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($cargo);
$parametrosHtml = htmlspecialchars($parametros, ENT_QUOTES, "UTF-8");

$esAdmin = ($cargo === "1");
$inicial = strtoupper(substr(trim($usuario), 0, 1));
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";

// proteger los textos que se muestran
function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// mostrar los iconos de la carpeta img
function icono($nombre) {
    return '<img src="img/' . escapar($nombre) . '.png" alt="" class="icono">';
}

// conectar con la base de datos
require_once "conexion.php";

// aceptar los dos nombres de conexion
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}

// comprobar la conexion
if (!isset($conn) || !($conn instanceof mysqli)) {
    exit("No se encontró una conexión válida en conexion.php.");
}

$conn->set_charset("utf8mb4");

// recibir el texto de busqueda
$buscar = trim((string) ($_GET["buscar_texto"] ?? ""));
$productos = [];
$agotados = [];
$mensajeError = "";

// consultar los productos y sus movimientos
try {
    $sqlBase = "SELECT
                    p.cod_producto,
                    p.nombre,
                    p.precio,
                    pr.cod_proveedor,
                    pr.nombre AS proveedor,
                    COALESCE(ent.entradas, 0) AS entradas,
                    COALESCE(sal.salidas, 0) AS salidas,
                    GREATEST(
                        0,
                        COALESCE(ent.entradas, 0) - COALESCE(sal.salidas, 0)
                    ) AS stock
                FROM producto p
                LEFT JOIN proveedor pr
                    ON p.cod_proveedor = pr.cod_proveedor
                LEFT JOIN (
                    SELECT cod_producto, SUM(cantidad) AS entradas
                    FROM mov_entrada
                    GROUP BY cod_producto
                ) ent
                    ON p.cod_producto = ent.cod_producto
                LEFT JOIN (
                    SELECT d.cod_producto, SUM(d.cantidad) AS salidas
                    FROM detalle_salida d
                    INNER JOIN factura_salida f
                        ON d.cod_movimiento = f.cod_movimiento
                    GROUP BY d.cod_producto
                ) sal
                    ON p.cod_producto = sal.cod_producto";

    // buscar por codigo exacto, nombre o proveedor
    if ($buscar !== "") {
        $sql = $sqlBase . " WHERE
                            CAST(p.cod_producto AS CHAR) = ?
                            OR p.nombre LIKE ?
                            OR CAST(pr.cod_proveedor AS CHAR) = ?
                            OR pr.nombre LIKE ?
                            ORDER BY p.cod_producto ASC";

        // preparar los datos de busqueda
        $filtro = "%" . $buscar . "%";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssss",
            $buscar,
            $filtro,
            $buscar,
            $filtro
        );
    } else {
        // mostrar todos los productos
        $stmt = $conn->prepare(
            $sqlBase . " ORDER BY p.cod_producto ASC"
        );
    }

    // ejecutar la consulta
    $stmt->execute();
    $resultado = $stmt->get_result();

    // guardar los productos encontrados
    while ($fila = $resultado->fetch_assoc()) {
        $productos[] = $fila;
    }

    $stmt->close();

    // consultar los productos agotados
    $stmt = $conn->prepare(
        $sqlBase . " HAVING stock = 0 ORDER BY p.nombre ASC"
    );

    $stmt->execute();
    $resultado = $stmt->get_result();

    // guardar los productos agotados
    while ($fila = $resultado->fetch_assoc()) {
        $agotados[] = $fila;
    }

    $stmt->close();

} catch (mysqli_sql_exception $ex) {
    // guardar el error para revisarlo
    error_log("Error en inventario.php: " . $ex->getMessage());
    $mensajeError = "No fue posible consultar el inventario. Verifica la base de datos y vuelve a intentarlo.";
}

// calcular los totales
$cantidadProductos = count($productos);
$cantidadAgotados = count($agotados);
$stockTotal = 0;

foreach ($productos as $producto) {
    $stockTotal += (int) $producto["stock"];
}

// organizar los enlaces de ayuda
$ayuda = [
    ["ayuda.php?" . $parametrosHtml, "ⓘ", "Centro de ayuda"],
    ["ayuda.php?" . $parametrosHtml . "#privacidad", "♧", "Privacidad y seguridad"],
    ["ayuda.php?" . $parametrosHtml . "#uso", "▤", "Manual de uso del sistema"],
    ["ayuda.php?" . $parametrosHtml . "#soporte", "?", "Asistencia técnica"],
    ["ayuda.php?" . $parametrosHtml . "#inventario", "▣", "Gestión y control de inventario"],
    ["ayuda.php?" . $parametrosHtml . "#movimientos", "↔", "Movimientos de entrada y salida"]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario | Supermercado La Granja</title>
    <link rel="stylesheet" href="css/inventario.css">
</head>
<body class="pagina-inventario">

<!-- encabezado superior -->
<header class="encabezado">
    <button type="button" class="boton-menu" id="abrirMenu"
            aria-label="Abrir menú lateral" aria-expanded="false">
        <?= icono("menu") ?>
    </button>

    <div class="encabezado-logo">
        <img src="img/logo.png" alt="Logo de La Granja">
    </div>

    <div class="encabezado-texto">
        <h1>Supermercado La Granja</h1>
        <p>Sistema de gestión de inventario</p>
    </div>

    <div class="encabezado-usuario">
        <span class="usuario-inicial"><?= escapar($inicial) ?></span>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>
</header>

<div class="fondo-menu" id="fondoMenu"></div>

<!-- menu lateral -->
<aside class="menu-lateral" id="menuLateral" aria-label="Menú principal">

    <div class="menu-lateral-encabezado">
        <div class="menu-lateral-marca">
            <img src="img/logo.png" alt="Logo de Supermercado La Granja">
            <div>
                <h2>Supermercado</h2>
                <p>La Granja</p>
            </div>
        </div>

        <button type="button" class="boton-cerrar" id="cerrarMenu"
                aria-label="Cerrar menú lateral">
            <?= icono("cerrar") ?>
        </button>
    </div>

    <!-- datos del usuario -->
    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande">
            <?= escapar($inicial) ?>
        </div>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>

    <!-- enlaces del menu -->
    <nav class="navegacion">
        <p class="titulo-navegacion">MENÚ PRINCIPAL</p>

        <div class="lista-navegacion">
            <a href="menu.php?<?= $parametrosHtml ?>" class="enlace-menu">
                <span class="caja-icono"><?= icono("inicio") ?></span>
                <span>Inicio</span>
            </a>

            <a href="cliente.php?<?= $parametrosHtml ?>" class="enlace-menu">
                <span class="caja-icono"><?= icono("cliente") ?></span>
                <span>Cliente</span>
            </a>

            <a href="mov_salida.php?<?= $parametrosHtml ?>" class="enlace-menu">
                <span class="caja-icono"><?= icono("salida") ?></span>
                <span>Movimiento de salida</span>
            </a>

            <?php if ($esAdmin): ?>
                <a href="producto.php?<?= $parametrosHtml ?>" class="enlace-menu">
                    <span class="caja-icono"><?= icono("producto") ?></span>
                    <span>Productos</span>
                </a>

                <a href="mov_entrada.php?<?= $parametrosHtml ?>" class="enlace-menu">
                    <span class="caja-icono"><?= icono("entrada") ?></span>
                    <span>Movimiento de entrada</span>
                </a>

                <a href="proveedor.php?<?= $parametrosHtml ?>" class="enlace-menu">
                    <span class="caja-icono"><?= icono("proveedor") ?></span>
                    <span>Proveedor</span>
                </a>

                <!-- marcar el inventario como activo -->
                <a href="inventario.php?<?= $parametrosHtml ?>"
                   class="enlace-menu activo" aria-current="page">
                    <span class="caja-icono"><?= icono("inventario") ?></span>
                    <span>Inventario</span>
                </a>

                <a href="reportes.php?<?= $parametrosHtml ?>" class="enlace-menu">
                    <span class="caja-icono"><?= icono("reportes") ?></span>
                    <span>Reportes</span>
                </a>

                <a href="edita_usuario.php?<?= $parametrosHtml ?>" class="enlace-menu">
                    <span class="caja-icono"><?= icono("usuario") ?></span>
                    <span>Modificar usuario</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- cerrar la sesion -->
    <form action="menu.php?<?= $parametrosHtml ?>" method="POST" class="form-salir">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja">
                <?= icono("salir") ?>
            </span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<!-- contenido principal -->
<main class="contenido">

    <!-- titulo del inventario -->
    <section class="titulo-pagina">
        <div class="titulo-icono"><?= icono("inventario") ?></div>
        <div>
            <h2>Inventario de productos</h2>
            <p>Consulta las existencias y controla los productos del supermercado.</p>
        </div>
    </section>

    <!-- mostrar errores de consulta -->
    <?php if ($mensajeError !== ""): ?>
        <div class="mensaje mensaje-error">
            <strong>Atención:</strong> <?= escapar($mensajeError) ?>
        </div>
    <?php endif; ?>

    <!-- resumen del inventario -->
    <section class="resumen-inventario">
        <article class="tarjeta-resumen tarjeta-azul">
            <div class="resumen-icono">&#9638;</div>
            <div>
                <p>Productos encontrados</p>
                <h3><?= $cantidadProductos ?></h3>
            </div>
        </article>

        <article class="tarjeta-resumen tarjeta-verde">
            <div class="resumen-icono">&#10003;</div>
            <div>
                <p>Unidades disponibles</p>
                <h3><?= number_format($stockTotal, 0, ",", ".") ?></h3>
            </div>
        </article>

        <article class="tarjeta-resumen tarjeta-roja">
            <div class="resumen-icono">&#9888;</div>
            <div>
                <p>Productos agotados</p>
                <h3><?= $cantidadAgotados ?></h3>
            </div>
        </article>
    </section>

    <!-- buscador de productos -->
    <section class="panel-inventario">
        <div class="panel-encabezado">
            <div>
                <h3>Buscar productos</h3>
                <p>Busca por código de producto, nombre o proveedor.</p>
            </div>
        </div>

        <form action="inventario.php" method="get" class="form-busqueda">

            <!-- conservar los datos del usuario -->
            <input type="hidden" name="usuario" value="<?= escapar($usuario) ?>">
            <input type="hidden" name="rol" value="<?= escapar($cargo) ?>">

            <!-- escribir el producto que se busca -->
            <div class="campo-busqueda">
                <label for="buscar_texto">Producto o proveedor</label>
                <input type="text" id="buscar_texto" name="buscar_texto"
                       value="<?= escapar($buscar) ?>"
                       placeholder="Escribe el código, nombre o proveedor...">
            </div>

            <!-- realizar la busqueda -->
            <button type="submit" class="boton boton-buscar">Buscar</button>

            <!-- mostrar el boton solo si hay una busqueda -->
            <?php if ($buscar !== ""): ?>
                <a href="inventario.php?<?= $parametrosHtml ?>"
                   class="boton boton-limpiar">
                    Mostrar todos
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- tabla de existencias -->
    <section class="panel-inventario">
        <div class="panel-encabezado">
            <div>
                <h3>Existencias actuales</h3>
                <p>El stock se calcula restando las salidas de las entradas registradas.</p>
            </div>
            <span class="contador"><?= $cantidadProductos ?> producto(s)</span>
        </div>

        <div class="tabla-contenedor">
            <table class="tabla-inventario">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Precio unitario</th>
                        <th>Proveedor</th>
                        <th>Entradas</th>
                        <th>Salidas</th>
                        <th>Stock actual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($cantidadProductos > 0): ?>
                        <?php foreach ($productos as $fila): ?>
                            <tr>
                                <td>
                                    <span class="codigo-producto">
                                        <?= escapar($fila["cod_producto"]) ?>
                                    </span>
                                </td>
                                <td class="nombre-producto">
                                    <?= escapar($fila["nombre"]) ?>
                                </td>
                                <td class="precio-producto">
                                    $<?= number_format((float) $fila["precio"], 0, ",", ".") ?>
                                </td>
                                <td>
                                    <?php if ($fila["cod_proveedor"] !== null): ?>
                                        <?= escapar($fila["cod_proveedor"]) ?> -
                                        <?= escapar($fila["proveedor"]) ?>
                                    <?php else: ?>
                                        <span class="sin-proveedor">Sin proveedor</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cantidad cantidad-entrada">
                                        <?= escapar($fila["entradas"]) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cantidad cantidad-salida">
                                        <?= escapar($fila["salidas"]) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int) $fila["stock"] === 0): ?>
                                        <span class="estado estado-agotado">Agotado</span>
                                    <?php else: ?>
                                        <span class="estado estado-disponible">
                                            <?= escapar($fila["stock"]) ?> unidades
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- mostrar un aviso si no hay resultados -->
                        <tr>
                            <td colspan="7" class="tabla-vacia">
                                <?= $buscar !== ""
                                    ? "No se encontraron productos que coincidan con la búsqueda."
                                    : "No hay productos registrados en el inventario." ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- alertas de productos agotados -->
    <section class="panel-inventario panel-alertas">
        <div class="panel-encabezado">
            <div>
                <h3>Alertas de inventario</h3>
                <p>Productos que necesitan reposición.</p>
            </div>
            <span class="contador contador-alertas">
                <?= $cantidadAgotados ?> agotado(s)
            </span>
        </div>

        <?php if ($cantidadAgotados > 0): ?>
            <div class="lista-alertas">
                <?php foreach ($agotados as $fila): ?>
                    <article class="alerta-producto">
                        <div class="alerta-icono">&#9888;</div>

                        <div class="alerta-detalle">
                            <h4><?= escapar($fila["nombre"]) ?></h4>
                            <p>Código: <strong><?= escapar($fila["cod_producto"]) ?></strong></p>
                            <p>
                                Proveedor:
                                <strong>
                                    <?php if ($fila["cod_proveedor"] !== null): ?>
                                        <?= escapar($fila["cod_proveedor"]) ?> -
                                        <?= escapar($fila["proveedor"]) ?>
                                    <?php else: ?>
                                        Sin proveedor asignado
                                    <?php endif; ?>
                                </strong>
                            </p>
                            <p class="texto-agotado">
                                Este producto no tiene unidades disponibles.
                            </p>
                        </div>

                        <span class="estado estado-agotado">Agotado</span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- mostrar un aviso si no hay productos agotados -->
            <div class="sin-alertas">
                <span class="sin-alertas-icono">&#10003;</span>
                <div>
                    <h4>¡Todo en orden!</h4>
                    <p>No hay productos agotados en este momento.</p>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">
                Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.
            </p>

            <!-- mostrar los enlaces de ayuda -->
            <?php foreach ($ayuda as [$enlace, $simbolo, $texto]): ?>
                <a href="<?= $enlace ?>" class="footer-enlace">
                    <span class="footer-icono"><?= $simbolo ?></span>
                    <?= escapar($texto) ?>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="footer-informacion">
            <h4>INFORMACIÓN DEL SISTEMA</h4>
            <p><strong>Plataforma administrativa</strong><br>
                Herramienta para organizar y controlar los productos del supermercado.</p>
            <p><strong>Gestión de productos</strong><br>
                Registro, consulta y actualización de información de los productos.</p>
            <p><strong>Control de movimientos</strong><br>
                Seguimiento de las entradas y salidas de mercancía.</p>

            <div class="footer-nota">
                <strong>Uso del sistema</strong>
                <p>Acceso destinado a empleados y administradores autorizados.</p>
            </div>
        </section>
    </div>

    <!-- informacion final -->
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<script>
    // obtener los elementos del menu
    const abrirMenu = document.getElementById("abrirMenu");
    const cerrarMenu = document.getElementById("cerrarMenu");
    const fondoMenu = document.getElementById("fondoMenu");

    // cambiar el estado del menu
    function cambiarMenu(abierto) {
        document.body.classList.toggle("menu-abierto", abierto);
        abrirMenu.setAttribute("aria-expanded", abierto);
    }

    // abrir y cerrar el menu
    abrirMenu.addEventListener("click", function () {
        cambiarMenu(true);
    });

    cerrarMenu.addEventListener("click", function () {
        cambiarMenu(false);
    });

    fondoMenu.addEventListener("click", function () {
        cambiarMenu(false);
    });

    // cerrar el menu con escape
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            cambiarMenu(false);
        }
    });
</script>
</body>
</html>
<?php
// cerrar la conexion
$conn->close();
?>

