<?php
session_start();

if (!isset($_SESSION["usuario"]) || !isset($_SESSION["cargo"])) {
    header("Location: index.html");
    exit();
}

if ($_SESSION["cargo"] != 1 && $_SESSION["cargo"] != 2) {
    header("Location: menu.php");
    exit();
}

include("conexion.php");

if (!isset($conn)) {
    $conn = $conexion;
}

function e($texto) {
    return htmlspecialchars($texto);
}

$hasta = date("Y-m-d");
$desde = date("Y-m-d", strtotime("-29 days"));

if (isset($_GET["desde"]) && strtotime($_GET["desde"])) {
    $desde = date("Y-m-d", strtotime($_GET["desde"]));
}
if (isset($_GET["hasta"]) && strtotime($_GET["hasta"])) {
    $hasta = date("Y-m-d", strtotime($_GET["hasta"]));
}
if ($desde > $hasta) {
    $temp = $desde;
    $desde = $hasta;
    $hasta = $temp;
}

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

$top5 = $productos;
usort($top5, function ($a, $b) {
    return $b["stock"] - $a["stock"];
});
$top5 = array_slice($top5, 0, 5);

$total_productos = count($productos);

$res = $conn->query("SELECT COUNT(*) AS total FROM proveedor");
$total_proveedores = $res->fetch_assoc()["total"];

$res = $conn->query("SELECT COALESCE(SUM(cantidad), 0) AS total, COUNT(*) AS registros
                     FROM mov_entrada WHERE fecha BETWEEN '$desde' AND '$hasta'");
$fila = $res->fetch_assoc();
$unid_entran = $fila["total"];
$reg_entradas = $fila["registros"];

$res = $conn->query("SELECT COALESCE(SUM(d.cantidad), 0) AS total, COUNT(DISTINCT f.cod_movimiento) AS facturas
                     FROM factura_salida f
                     INNER JOIN detalle_salida d ON d.cod_movimiento = f.cod_movimiento
                     WHERE f.fecha BETWEEN '$desde' AND '$hasta'");
$fila = $res->fetch_assoc();
$unid_salen = $fila["total"];
$facturas = $fila["facturas"];

$lista_entradas = $conn->query("SELECT me.cod_movimiento, me.fecha, p.nombre, me.cantidad
                                FROM mov_entrada me
                                INNER JOIN producto p ON p.cod_producto = me.cod_producto
                                WHERE me.fecha BETWEEN '$desde' AND '$hasta'
                                ORDER BY me.fecha DESC
                                LIMIT 10");

$lista_salidas = $conn->query("SELECT f.cod_movimiento, f.fecha, f.cedula_cliente, SUM(d.cantidad) AS cantidad
                               FROM factura_salida f
                               INNER JOIN detalle_salida d ON d.cod_movimiento = f.cod_movimiento
                               WHERE f.fecha BETWEEN '$desde' AND '$hasta'
                               GROUP BY f.cod_movimiento, f.fecha, f.cedula_cliente
                               ORDER BY f.fecha DESC
                               LIMIT 10");

$ent_dia = [];
$res = $conn->query("SELECT fecha, SUM(cantidad) AS total FROM mov_entrada
                     WHERE fecha BETWEEN '$desde' AND '$hasta' GROUP BY fecha");
while ($fila = $res->fetch_assoc()) {
    $ent_dia[$fila["fecha"]] = $fila["total"];
}

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
<title>Reportes - Supermercado La Granja</title>
<style>
    body { font-family: Arial, sans-serif; font-size: 14px; margin: 15px; }
    table { border-collapse: collapse; margin-bottom: 15px; width: auto; }
    th, td { border: 1px solid #999; padding: 2px 6px; text-align: left; font-size: 13px; }
    th { background: #e5e5e5; }
    .verde { color: green; font-weight: bold; }
    .rojo { color: red; font-weight: bold; }
</style>
</head>
<body>

<h1>Reportes de inventario</h1>
  <form action="menu.php" method="GET">
        <button type="submit">Menú principal</button>
    </form><br><br>
<button onclick="window.print()">Imprimir</button>

<form method="GET" action="reportes.php">
    Desde: <input type="date" name="desde" value="<?php echo $desde; ?>" required>
    Hasta: <input type="date" name="hasta" value="<?php echo $hasta; ?>" required>
    <button type="submit">Aplicar fechas</button>
    <a href="reportes.php">Últimos 30 días</a>
</form>

<h2>Resumen general</h2>
<table>
    <tr><th>Indicador</th><th>Valor</th></tr>
    <tr><td>Total de productos</td><td><?php echo $total_productos; ?></td></tr>
    <tr><td>Stock total</td><td><?php echo $total_stock; ?></td></tr>
    <tr><td>Proveedores</td><td><?php echo $total_proveedores; ?></td></tr>
    <tr><td>Productos agotados</td><td><?php echo $agotados; ?></td></tr>
    <tr><td>Stock bajo (5 o menos)</td><td><?php echo $bajos; ?></td></tr>
</table>

<h2>Productos y unidades por categoría</h2>
<table>
    <tr><th>Categoría</th><th>Productos</th><th>Unidades</th></tr>
    <?php foreach ($categorias as $nombre => $info) { ?>
    <tr>
        <td><?php echo e($nombre); ?></td>
        <td><?php echo $info["productos"]; ?></td>
        <td><?php echo $info["stock"]; ?></td>
    </tr>
    <?php } ?>
</table>

<h2>Top 5 productos con más stock</h2>
<table>
    <tr><th>Puesto</th><th>Producto</th><th>Categoría</th><th>Stock</th></tr>
    <?php $puesto = 1; foreach ($top5 as $p) { ?>
    <tr>
        <td><?php echo $puesto; ?></td>
        <td><?php echo e($p["nombre"]); ?></td>
        <td><?php echo e($p["categoria"]); ?></td>
        <td><?php echo $p["stock"]; ?></td>
    </tr>
    <?php $puesto++; } ?>
</table>

<h2>Existencias por producto</h2>
<table>
    <tr>
        <th>Código</th><th>Producto</th><th>Categoría</th>
        <th>Entradas</th><th>Salidas</th><th>Stock</th><th>Estado</th>
    </tr>
    <?php foreach ($productos as $p) { ?>
    <tr>
        <td><?php echo $p["cod_producto"]; ?></td>
        <td><?php echo e($p["nombre"]); ?></td>
        <td><?php echo e($p["categoria"]); ?></td>
        <td><?php echo $p["entradas"]; ?></td>
        <td><?php echo $p["salidas"]; ?></td>
        <td><?php echo $p["stock"]; ?></td>
        <td>
            <?php if ($p["stock"] == 0) { ?>
                <span class="rojo">Agotado</span>
            <?php } elseif ($p["stock"] <= 5) { ?>
                <span class="rojo">Stock bajo</span>
            <?php } else { ?>
                <span class="verde">Disponible</span>
            <?php } ?>
        </td>
    </tr>
    <?php } ?>
</table>

<h2>Últimas entradas</h2>
<table>
    <tr><th>Código</th><th>Fecha</th><th>Producto</th><th>Cantidad</th></tr>
    <?php while ($fila = $lista_entradas->fetch_assoc()) { ?>
    <tr>
        <td><?php echo $fila["cod_movimiento"]; ?></td>
        <td><?php echo $fila["fecha"]; ?></td>
        <td><?php echo e($fila["nombre"]); ?></td>
        <td><?php echo $fila["cantidad"]; ?></td>
    </tr>
    <?php } ?>
</table>

<h2>Últimas salidas</h2>
<table>
    <tr><th>Código</th><th>Fecha</th><th>Cliente</th><th>Cantidad</th></tr>
    <?php while ($fila = $lista_salidas->fetch_assoc()) { ?>
    <tr>
        <td><?php echo $fila["cod_movimiento"]; ?></td>
        <td><?php echo $fila["fecha"]; ?></td>
        <td><?php echo e($fila["cedula_cliente"]); ?></td>
        <td><?php echo $fila["cantidad"]; ?></td>
    </tr>
    <?php } ?>
</table>

<h2>Entradas y salidas por día</h2>
<table>
    <tr><th>Fecha</th><th>Entradas</th><th>Salidas</th><th>Balance</th></tr>
    <?php
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
        <td><?php echo date("d/m/Y", strtotime($dia)); ?></td>
        <td><?php echo $ent; ?></td>
        <td><?php echo $sal; ?></td>
        <td><?php echo $ent - $sal; ?></td>
    </tr>
    <?php
        $dia = date("Y-m-d", strtotime($dia . " +1 day"));
    }
    ?>
</table>

<h2>Resumen del periodo</h2>
<table>
    <tr><th>Unidades que entraron</th><td><?php echo $unid_entran; ?></td></tr>
    <tr><th>Unidades que salieron</th><td><?php echo $unid_salen; ?></td></tr>
    <tr><th>Balance de unidades</th><td><?php echo $unid_entran - $unid_salen; ?></td></tr>
    <tr><th>Registros de entrada</th><td><?php echo $reg_entradas; ?></td></tr>
    <tr><th>Facturas de salida</th><td><?php echo $facturas; ?></td></tr>
</table>

<?php if ($unid_salen > $unid_entran) { ?>
    <p class="rojo">Alerta: las salidas superan las entradas del periodo.</p>
<?php } else { ?>
    <p class="verde">Las entradas son iguales o superiores a las salidas.</p>
<?php } ?>

</body>
</html>
<?php
$conn->close();
?>