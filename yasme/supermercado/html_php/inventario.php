<?php
session_start();

// Si no hay sesión, regresar al inicio
if (!isset($_SESSION["usuario"])) {
    header("Location: index.html");
    exit();
}

include("conexion.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mensaje = "";
$buscar = "";
$resultado = false;


/* BUSCAR */

if (isset($_POST["buscar"])) {
    $buscar = trim($_POST["buscar_texto"]);
}


/* Consultar Inventario */
/* Entradas = tabla mov_entrada. Salidas = tabla detalle_salida (la que usa mov_salida.php) */

$sql = "SELECT p.cod_producto, p.nombre, pr.cod_proveedor, pr.nombre AS proveedor,
               IFNULL(e.entradas, 0) AS entradas,
               IFNULL(s.salidas, 0) AS salidas,
               IFNULL(e.entradas, 0) - IFNULL(s.salidas, 0) AS stock
        FROM producto p
        LEFT JOIN proveedor pr
            ON p.cod_proveedor = pr.cod_proveedor

        LEFT JOIN (
            SELECT cod_producto, SUM(cantidad) AS entradas
            FROM mov_entrada
            GROUP BY cod_producto
        ) e
            ON p.cod_producto = e.cod_producto

        LEFT JOIN (
            SELECT d.cod_producto, SUM(d.cantidad) AS salidas
            FROM detalle_salida d
            INNER JOIN factura_salida f
                ON f.cod_movimiento = d.cod_movimiento
            GROUP BY d.cod_producto
        ) s
            ON p.cod_producto = s.cod_producto";

if ($buscar !== "") {
    $sql .= " WHERE p.cod_producto LIKE ?
                 OR p.nombre LIKE ?
                 OR pr.cod_proveedor LIKE ?
                 OR pr.nombre LIKE ?";
}

$sql .= " ORDER BY p.cod_producto";

try {

    $stmt = $conn->prepare($sql);

    if ($buscar !== "") {
        $texto = "%" . $buscar . "%";
        $stmt->bind_param("ssss", $texto, $texto, $texto, $texto);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();

} catch (mysqli_sql_exception $e) {

    $mensaje = "Error en la base de datos: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Inventario</title>
</head>

<body>

    <h2>INVENTARIO DE PRODUCTOS</h2>

    <?php
    if ($mensaje != "") {
        echo "<p style='color:red;'>" . htmlspecialchars($mensaje) . "</p>";
    }
    ?>

    <form action="menu.php">
        <button type="submit">Menu Principal</button>
    </form>

    <h3>Buscar producto</h3>

    <form method="POST">
        <input type="text" name="buscar_texto" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Buscar producto">
        <input type="submit" name="buscar" value="Buscar">
        <a href="inventario.php">Mostrar todos</a>
    </form>

    <br>

    <table border="1" cellpadding="5" cellspacing="0">

        <tr>
            <th>Codigo Producto</th>
            <th>Nombre Producto</th>
            <th>Proveedor</th>
            <th>Entradas</th>
            <th>Salidas</th>
            <th>Stock</th>
        </tr>

        <?php if ($resultado && $resultado->num_rows > 0): ?>

            <?php while ($fila = $resultado->fetch_assoc()): ?>

                <tr>
                    <td><?php echo htmlspecialchars($fila["cod_producto"]); ?></td>
                    <td><?php echo htmlspecialchars($fila["nombre"]); ?></td>
                    <td><?php echo htmlspecialchars($fila["cod_proveedor"] . " - " . $fila["proveedor"]); ?></td>
                    <td><?php echo $fila["entradas"]; ?></td>
                    <td><?php echo $fila["salidas"]; ?></td>
                    <td><?php echo $fila["stock"]; ?></td>
                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="6">No se encontraron productos.</td>
            </tr>

        <?php endif; ?>

    </table>

</body>

</html>

<?php
$conn->close();
?>
