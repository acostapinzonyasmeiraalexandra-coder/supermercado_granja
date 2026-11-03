<?php
include("conexion.php");

// conexion
// revisa que haya conexion a la base de datos
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("No se encontró una conexión válida en conexion.php.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// usuario y cargo
// cerrar sesion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["salir"])) {
    header("Location: index.html");
    exit();
}

// usuario y rol que llegan del menu
$usuario = trim((string)($_POST["usuario"] ?? $_GET["usuario"] ?? ""));
$rol = (string)($_POST["rol"] ?? $_GET["rol"] ?? "");

if ($usuario === "" || ($rol !== "1" && $rol !== "2")) {
    header("Location: index.html");
    exit();
}

$esAdmin = ($rol === "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr(trim($usuario), 0, 1));
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($rol);

// los reportes son solo para el admin
if (!$esAdmin) {
    header("Location: menu.php?" . $parametros);
    exit();
}

// paginas del menu (admin true = solo administrador)
$paginas = [
    "inicio"      => ["archivo" => "menu.php",         "texto" => "Inicio",                "icono" => "inicio.png",     "admin" => false],
    "cliente"     => ["archivo" => "cliente.php",      "texto" => "Cliente",               "icono" => "cliente.png",    "admin" => false],
    "mov_salida"  => ["archivo" => "mov_salida.php",   "texto" => "Movimiento de salida",  "icono" => "salida.png",     "admin" => false],
    "producto"    => ["archivo" => "producto.php",     "texto" => "Productos",             "icono" => "producto.png",   "admin" => true],
    "mov_entrada" => ["archivo" => "mov_entrada.php",  "texto" => "Movimiento de entrada", "icono" => "entrada.png",    "admin" => true],
    "proveedor"   => ["archivo" => "proveedor.php",    "texto" => "Proveedor",             "icono" => "proveedor.png",  "admin" => true],
    "inventario"  => ["archivo" => "inventario.php",   "texto" => "Inventario",            "icono" => "inventario.png", "admin" => true],
    "reportes"    => ["archivo" => "reportes.php",     "texto" => "Reportes",              "icono" => "reportes.png",   "admin" => true],
    "usuario"     => ["archivo" => "edita_usuario.php","texto" => "Modificar usuario",     "icono" => "usuario.png",    "admin" => true],
];

// evita que el texto rompa el html
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

// fechas del reporte (por defecto los ultimos 30 dias)
$hasta = date("Y-m-d");
$desde = date("Y-m-d", strtotime("-29 days"));

if (isset($_GET["desde"]) && strtotime($_GET["desde"])) {
    $desde = date("Y-m-d", strtotime($_GET["desde"]));
}
if (isset($_GET["hasta"]) && strtotime($_GET["hasta"])) {
    $hasta = date("Y-m-d", strtotime($_GET["hasta"]));
}

// si las fechas estan al reves las cambia
if ($desde > $hasta) {
    $temp = $desde;
    $desde = $hasta;
    $hasta = $temp;
}

// entradas y salidas de cada producto
$sql = "SELECT p.cod_producto, p.nombre, p.categoria,
        (SELECT COALESCE(SUM(cantidad), 0) FROM mov_entrada
         WHERE cod_producto = p.cod_producto) AS entradas,
        (SELECT COALESCE(SUM(d.cantidad), 0) FROM detalle_salida d
         INNER JOIN factura_salida f ON f.cod_movimiento = d.cod_movimiento
         WHERE d.cod_producto = p.cod_producto) AS salidas
        FROM producto p
        ORDER BY p.nombre";
$res = $conn->query($sql);

$productos = [];
$total_stock = 0;
$agotados = 0;
$bajos = 0;
$categorias = [];

// calcula el stock (entradas - salidas) de cada producto
while ($fila = $res->fetch_assoc()) {
    $stock = $fila["entradas"] - $fila["salidas"];
    if ($stock < 0) {
        $stock = 0;
    }
    $fila["stock"] = $stock;
    $productos[] = $fila;

    $total_stock = $total_stock + $stock;
    if ($stock == 0) {
        $agotados++;
    }
    if ($stock <= 5) {
        $bajos++;
    }

    // suma productos y unidades por categoria
    $categoria = $fila["categoria"];
    if ($categoria == "") {
        $categoria = "Sin categoría";
    }
    if (!isset($categorias[$categoria])) {
        $categorias[$categoria] = ["productos" => 0, "stock" => 0];
    }
    $categorias[$categoria]["productos"]++;
    $categorias[$categoria]["stock"] = $categorias[$categoria]["stock"] + $stock;
}

// los 5 productos con mas stock
$top5 = $productos;
usort($top5, function ($a, $b) {
    return $b["stock"] - $a["stock"];
});
$top5 = array_slice($top5, 0, 5);

$total_productos = count($productos);

// total de proveedores
$res = $conn->query("SELECT COUNT(*) AS total FROM proveedor");
$total_proveedores = $res->fetch_assoc()["total"];

// entradas del periodo
$res = $conn->query("SELECT COALESCE(SUM(cantidad), 0) AS total, COUNT(*) AS registros
                     FROM mov_entrada WHERE fecha BETWEEN '$desde' AND '$hasta'");
$fila = $res->fetch_assoc();
$unid_entran = $fila["total"];
$reg_entradas = $fila["registros"];

// salidas del periodo
$res = $conn->query("SELECT COALESCE(SUM(d.cantidad), 0) AS total, COUNT(DISTINCT f.cod_movimiento) AS facturas
                     FROM factura_salida f
                     INNER JOIN detalle_salida d ON d.cod_movimiento = f.cod_movimiento
                     WHERE f.fecha BETWEEN '$desde' AND '$hasta'");
$fila = $res->fetch_assoc();
$unid_salen = $fila["total"];
$facturas = $fila["facturas"];

// ultimas 10 entradas
$lista_entradas = $conn->query("SELECT me.cod_movimiento, me.fecha, p.nombre, me.cantidad
                                FROM mov_entrada me
                                INNER JOIN producto p ON p.cod_producto = me.cod_producto
                                WHERE me.fecha BETWEEN '$desde' AND '$hasta'
                                ORDER BY me.fecha DESC
                                LIMIT 10");

// ultimas 10 salidas
$lista_salidas = $conn->query("SELECT f.cod_movimiento, f.fecha, f.cedula_cliente, SUM(d.cantidad) AS cantidad
                               FROM factura_salida f
                               INNER JOIN detalle_salida d ON d.cod_movimiento = f.cod_movimiento
                               WHERE f.fecha BETWEEN '$desde' AND '$hasta'
                               GROUP BY f.cod_movimiento, f.fecha, f.cedula_cliente
                               ORDER BY f.fecha DESC
                               LIMIT 10");

// entradas por dia
$ent_dia = [];
$res = $conn->query("SELECT fecha, SUM(cantidad) AS total FROM mov_entrada
                     WHERE fecha BETWEEN '$desde' AND '$hasta' GROUP BY fecha");
while ($fila = $res->fetch_assoc()) {
    $ent_dia[$fila["fecha"]] = $fila["total"];
}

// salidas por dia
$sal_dia = [];
$res = $conn->query("SELECT f.fecha, SUM(d.cantidad) AS total
                     FROM factura_salida f
                     INNER JOIN detalle_salida d ON d.cod_movimiento = f.cod_movimiento
                     WHERE f.fecha BETWEEN '$desde' AND '$hasta' GROUP BY f.fecha");
while ($fila = $res->fetch_assoc()) {
    $sal_dia[$fila["fecha"]] = $fila["total"];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes | La Granja</title>
    <link rel="stylesheet" href="css/reportes.css">
</head>
<body class="pagina-reportes">

<!-- encabezado -->
<header class="encabezado">
    <button type="button" class="boton-menu" id="abrirMenu" aria-label="Abrir menú lateral" aria-controls="menuLateral" aria-expanded="false">
        <img src="img/menu.png" alt="" class="icono">
    </button>
    <div class="encabezado-logo">
        <img src="img/logo.png" alt="Logo de La Granja">
    </div>
    <div class="encabezado-texto">
        <h1>Supermercado La Granja</h1>
        <p>Sistema de gestión de inventario</p>
    </div>
    <div class="encabezado-usuario">
        <span class="usuario-inicial"><?= e($inicial) ?></span>
        <div>
            <strong><?= e($usuario) ?></strong>
            <p><?= e($nombreCargo) ?></p>
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
        <button type="button" class="boton-cerrar" id="cerrarMenu" aria-label="Cerrar menú lateral">
            <img src="img/cerrar.png" alt="" class="icono">
        </button>
    </div>

    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= e($inicial) ?></div>
        <div>
            <strong><?= e($usuario) ?></strong>
            <p><?= e($nombreCargo) ?></p>
        </div>
    </div>

    <nav class="navegacion">
        <p class="titulo-navegacion">MENÚ PRINCIPAL</p>
        <div class="lista-navegacion">
            <?php foreach ($paginas as $clave => $pagina): ?>
                <?php if ($pagina["admin"] && !$esAdmin) { continue; } ?>
                <?php $esActual = ($clave === "reportes"); ?>
                <a href="<?= e($pagina["archivo"] . "?" . $parametros) ?>" class="enlace-menu<?= $esActual ? " activo" : "" ?>"<?= $esActual ? ' aria-current="page"' : "" ?>>
                    <span class="caja-icono"><img src="img/<?= $pagina["icono"] ?>" alt="" class="icono"></span>
                    <span><?= e($pagina["texto"]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <form action="menu.php" method="POST" class="form-salir">
        <input type="hidden" name="usuario" value="<?= e($usuario) ?>">
        <input type="hidden" name="rol" value="<?= e($rol) ?>">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja"><img src="img/salir.png" alt="" class="icono"></span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<!-- contenido principal -->
<main class="contenido">
    <section class="titulo-pagina">
        <span class="titulo-icono"><img src="img/reportes.png" alt="" class="icono"></span>
        <div>
            <h2>Reportes de inventario</h2>
            <p>Consulta las existencias, las entradas y las salidas del supermercado.</p>
        </div>
    </section>

    <!-- filtro de fechas -->
    <section class="panel sin-imprimir">
        <h3 class="panel-titulo">Periodo del reporte</h3>
        <form method="GET" action="reportes.php" class="filtro-fechas">
            <input type="hidden" name="usuario" value="<?= e($usuario) ?>">
            <input type="hidden" name="rol" value="<?= e($rol) ?>">
            <div class="campo">
                <label for="desde">Desde</label>
                <input type="date" id="desde" name="desde" value="<?= e($desde) ?>" required>
            </div>
            <div class="campo">
                <label for="hasta">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="<?= e($hasta) ?>" required>
            </div>
            <div class="botones">
                <button type="submit" class="boton boton-principal">Aplicar fechas</button>
                <a href="reportes.php?<?= e($parametros) ?>" class="boton boton-secundario">Últimos 30 días</a>
                <button type="button" class="boton boton-secundario" onclick="window.print()">Imprimir</button>
            </div>
        </form>
    </section>

    <!-- resumen general -->
    <section class="panel">
        <h3 class="panel-titulo">Resumen general</h3>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr><th>Indicador</th><th class="derecha">Valor</th></tr>
                </thead>
                <tbody>
                    <tr><td>Total de productos</td><td class="derecha"><?= e($total_productos) ?></td></tr>
                    <tr><td>Stock total</td><td class="derecha"><?= e($total_stock) ?></td></tr>
                    <tr><td>Proveedores</td><td class="derecha"><?= e($total_proveedores) ?></td></tr>
                    <tr><td>Productos agotados</td><td class="derecha"><?= e($agotados) ?></td></tr>
                    <tr><td>Stock bajo (5 o menos)</td><td class="derecha"><?= e($bajos) ?></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- productos y unidades por categoria -->
    <section class="panel">
        <h3 class="panel-titulo">Productos y unidades por categoría</h3>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr><th>Categoría</th><th class="derecha">Productos</th><th class="derecha">Unidades</th></tr>
                </thead>
                <tbody>
                    <?php if (count($categorias) > 0): ?>
                        <?php foreach ($categorias as $nombre => $info): ?>
                            <tr>
                                <td><?= e(ucfirst($nombre)) ?></td>
                                <td class="derecha"><?= e($info["productos"]) ?></td>
                                <td class="derecha"><?= e($info["stock"]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="tabla-vacia">No hay productos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- top 5 con mas stock -->
    <section class="panel">
        <h3 class="panel-titulo">Top 5 productos con más stock</h3>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr><th>Puesto</th><th>Producto</th><th>Categoría</th><th class="derecha">Stock</th></tr>
                </thead>
                <tbody>
                    <?php if (count($top5) > 0): ?>
                        <?php $puesto = 1; ?>
                        <?php foreach ($top5 as $p): ?>
                            <tr>
                                <td><?= $puesto ?></td>
                                <td><?= e($p["nombre"]) ?></td>
                                <td><?= e(ucfirst((string)$p["categoria"])) ?></td>
                                <td class="derecha"><?= e($p["stock"]) ?></td>
                            </tr>
                            <?php $puesto++; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="tabla-vacia">No hay productos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- existencias por producto -->
    <section class="panel">
        <h3 class="panel-titulo">Existencias por producto</h3>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th class="derecha">Entradas</th>
                        <th class="derecha">Salidas</th>
                        <th class="derecha">Stock</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($productos) > 0): ?>
                        <?php foreach ($productos as $p): ?>
                            <tr>
                                <td><?= e($p["cod_producto"]) ?></td>
                                <td><?= e($p["nombre"]) ?></td>
                                <td><?= e(ucfirst((string)$p["categoria"])) ?></td>
                                <td class="derecha"><?= e($p["entradas"]) ?></td>
                                <td class="derecha"><?= e($p["salidas"]) ?></td>
                                <td class="derecha"><?= e($p["stock"]) ?></td>
                                <td>
                                    <?php if ($p["stock"] == 0): ?>
                                        <span class="estado estado-agotado">Agotado</span>
                                    <?php elseif ($p["stock"] <= 5): ?>
                                        <span class="estado estado-bajo">Stock bajo</span>
                                    <?php else: ?>
                                        <span class="estado estado-ok">Disponible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="tabla-vacia">No hay productos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ultimas entradas -->
    <section class="panel">
        <h3 class="panel-titulo">Últimas entradas</h3>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr><th>Código</th><th>Fecha</th><th>Producto</th><th class="derecha">Cantidad</th></tr>
                </thead>
                <tbody>
                    <?php if ($lista_entradas->num_rows > 0): ?>
                        <?php while ($fila = $lista_entradas->fetch_assoc()): ?>
                            <tr>
                                <td><?= e($fila["cod_movimiento"]) ?></td>
                                <td><?= e($fila["fecha"]) ?></td>
                                <td><?= e($fila["nombre"]) ?></td>
                                <td class="derecha"><?= e($fila["cantidad"]) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="tabla-vacia">No hay entradas en este periodo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ultimas salidas -->
    <section class="panel">
        <h3 class="panel-titulo">Últimas salidas</h3>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr><th>Código</th><th>Fecha</th><th>Cliente</th><th class="derecha">Cantidad</th></tr>
                </thead>
                <tbody>
                    <?php if ($lista_salidas->num_rows > 0): ?>
                        <?php while ($fila = $lista_salidas->fetch_assoc()): ?>
                            <tr>
                                <td><?= e($fila["cod_movimiento"]) ?></td>
                                <td><?= e($fila["fecha"]) ?></td>
                                <td><?= e($fila["cedula_cliente"]) ?></td>
                                <td class="derecha"><?= e($fila["cantidad"]) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="tabla-vacia">No hay salidas en este periodo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- entradas y salidas por dia -->
    <section class="panel">
        <h3 class="panel-titulo">Entradas y salidas por día</h3>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr><th>Fecha</th><th class="derecha">Entradas</th><th class="derecha">Salidas</th><th class="derecha">Balance</th></tr>
                </thead>
                <tbody>
                    <?php
                    // recorre todos los dias del periodo
                    $dia = $desde;
                    while ($dia <= $hasta) {
                        $ent = 0;
                        $sal = 0;
                        if (isset($ent_dia[$dia])) {
                            $ent = $ent_dia[$dia];
                        }
                        if (isset($sal_dia[$dia])) {
                            $sal = $sal_dia[$dia];
                        }
                    ?>
                        <tr>
                            <td><?= date("d/m/Y", strtotime($dia)) ?></td>
                            <td class="derecha"><?= e($ent) ?></td>
                            <td class="derecha"><?= e($sal) ?></td>
                            <td class="derecha"><?= e($ent - $sal) ?></td>
                        </tr>
                    <?php
                        $dia = date("Y-m-d", strtotime($dia . " +1 day"));
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- resumen del periodo -->
    <section class="panel">
        <h3 class="panel-titulo">Resumen del periodo</h3>
        <div class="tabla-contenedor">
            <table class="tabla">
                <tbody>
                    <tr><th>Unidades que entraron</th><td class="derecha"><?= e($unid_entran) ?></td></tr>
                    <tr><th>Unidades que salieron</th><td class="derecha"><?= e($unid_salen) ?></td></tr>
                    <tr><th>Balance de unidades</th><td class="derecha"><?= e($unid_entran - $unid_salen) ?></td></tr>
                    <tr><th>Registros de entrada</th><td class="derecha"><?= e($reg_entradas) ?></td></tr>
                    <tr><th>Facturas de salida</th><td class="derecha"><?= e($facturas) ?></td></tr>
                </tbody>
            </table>
        </div>

        <!-- alerta si salen mas unidades de las que entran -->
        <?php if ($unid_salen > $unid_entran): ?>
            <div class="mensaje mensaje-error alerta-periodo" role="status">Alerta: las salidas superan las entradas del periodo.</div>
        <?php else: ?>
            <div class="mensaje mensaje-ok alerta-periodo" role="status">Las entradas son iguales o superiores a las salidas.</div>
        <?php endif; ?>
    </section>
</main>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.</p>
            <a href="ayuda.php?<?= e($parametros) ?>#uso" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
            <a href="ayuda.php?<?= e($parametros) ?>#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
            <a href="ayuda.php?<?= e($parametros) ?>#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
            <a href="ayuda.php?<?= e($parametros) ?>#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
            <a href="ayuda.php?<?= e($parametros) ?>#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
            <a href="ayuda.php?<?= e($parametros) ?>#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
        </section>
        <section class="footer-informacion">
            <h4>INFORMACIÓN DEL SISTEMA</h4>
            <p><strong>Plataforma administrativa</strong><br>Herramienta para organizar y controlar los productos del supermercado.</p>
            <p><strong>Reportes de inventario</strong><br>Consulta de existencias, entradas y salidas por periodo.</p>
            <p><strong>Control de movimientos</strong><br>Seguimiento de las entradas y salidas de mercancía.</p>
            <div class="footer-nota">
                <strong>Uso del sistema</strong>
                <p>Acceso destinado a empleados y administradores autorizados.</p>
            </div>
        </section>
    </div>
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<script>
// menu lateral
const abrir = document.getElementById("abrirMenu");
const cerrar = document.getElementById("cerrarMenu");
const fondo = document.getElementById("fondoMenu");

function mostrarMenu() {
    document.body.classList.add("menu-abierto");
    abrir.setAttribute("aria-expanded", "true");
}

function ocultarMenu() {
    document.body.classList.remove("menu-abierto");
    abrir.setAttribute("aria-expanded", "false");
}

abrir.addEventListener("click", mostrarMenu);
cerrar.addEventListener("click", ocultarMenu);
fondo.addEventListener("click", ocultarMenu);

document.addEventListener("keydown", function(evento) {
    if (evento.key === "Escape") {
        ocultarMenu();
    }
});
</script>

</body>
</html>
<?php
$conn->close();
?>
