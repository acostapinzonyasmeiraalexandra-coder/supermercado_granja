<?php
// inicia sesion
include_once(__DIR__ . "/sesion.php");

// revisa sesion y rol
if (
    !isset($_SESSION["usuario"], $_SESSION["rol"]) ||
    trim((string)$_SESSION["usuario"]) === "" ||
    !in_array((string)$_SESSION["rol"], ["1", "2"], true)
) {
    header("Location: ../index.php");
    exit();
}

// sin cache
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

date_default_timezone_set("America/Bogota");

// datos del usuario (1 admin, 2 empleado)
$usuario = $_SESSION["usuario"];
$esAdmin = ((string)$_SESSION["rol"] === "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr($usuario, 0, 1));

// conexion
include("conexion.php");


// funciones

// texto seguro para html
function escapar($dato)
{
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

// actualiza el inventario
function actualizarInventario($conn, $codigos)
{
    foreach (array_unique($codigos) as $cod) {

        // crea la fila si no existe
        $stmt = $conn->prepare(
            "INSERT INTO inventario (cod_producto, nombre_producto, precio_unitario, proveedor, entradas, salidas, stock_actual)
             SELECT cod_producto, nombre, precio, '', 0, 0, 0 FROM producto
             WHERE cod_producto = ? AND NOT EXISTS (SELECT 1 FROM inventario WHERE cod_producto = ?)"
        );
        $stmt->bind_param("ii", $cod, $cod);
        $stmt->execute();
        $stmt->close();

        // entradas - salidas = stock
        $stmt = $conn->prepare(
            "UPDATE inventario SET
                entradas = (SELECT COALESCE(SUM(cantidad), 0) FROM mov_entrada WHERE cod_producto = ?),
                salidas = (SELECT COALESCE(SUM(cantidad), 0) FROM mov_salida WHERE cod_producto = ?),
                stock_actual = GREATEST(0, entradas - salidas)
             WHERE cod_producto = ?"
        );
        $stmt->bind_param("iii", $cod, $cod, $cod);
        $stmt->execute();
        $stmt->close();
    }
}


// notificaciones del encabezado

// productos con poco stock
$totalStockBajo = 0;
try {
    $consultaStock = $conn->query("SELECT COUNT(*) AS total FROM producto WHERE stock <= stock_minimo");
    $totalStockBajo = (int)($consultaStock->fetch_assoc()["total"] ?? 0);
} catch (Throwable $error) {
    $totalStockBajo = 0;
}


// buscador de clientes y productos

if (isset($_GET["buscar_cliente"]) || isset($_GET["buscar_producto"])) {
    header("Content-Type: application/json; charset=utf-8");

    $esCliente = isset($_GET["buscar_cliente"]);
    $texto = trim((string)($esCliente ? $_GET["buscar_cliente"] : $_GET["buscar_producto"]));
    $lista = [];

    if ($texto !== "") {
        $filtro = "%" . $texto . "%";
        $filtroCodigo = "%" . trim(explode(" - ", $texto)[0]) . "%";

        // cliente o producto
        if ($esCliente) {
            $sql = "SELECT cedula_cliente AS cedula, nombre FROM cliente
                    WHERE CAST(cedula_cliente AS CHAR) LIKE ? OR nombre LIKE ? ORDER BY nombre LIMIT 10";
        } else {
            $sql = "SELECT cod_producto AS codigo, nombre, precio FROM producto
                    WHERE CAST(cod_producto AS CHAR) LIKE ? OR nombre LIKE ? ORDER BY nombre LIMIT 10";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $filtroCodigo, $filtro);
        $stmt->execute();
        $lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    echo json_encode($lista, JSON_UNESCAPED_UNICODE);
    exit();
}


// variables del html

$mensaje = "";
$tipoMensaje = "";
$factura_editar = null;
$detalles_editar = [];
$cedulaSeleccionada = "";
$clienteSeleccionadoTexto = "";
$resultadoResumen = null;
$resultadoFacturas = null;


// ver factura

$verFactura = isset($_GET["factura"]);
$facturaVer = null;
$detallesFactura = [];
$totalFactura = 0;

if ($verFactura) {
    $codigoFactura = (int)$_GET["factura"];

    // factura y cliente
    $stmt = $conn->prepare(
        "SELECT f.cod_movimiento, f.fecha, f.nota, f.cedula_cliente, c.nombre, c.telefono, c.email, c.direccion
         FROM factura_salida f
         LEFT JOIN cliente c ON f.cedula_cliente = c.cedula_cliente
         WHERE f.cod_movimiento = ?"
    );
    $stmt->bind_param("i", $codigoFactura);
    $stmt->execute();
    $facturaVer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // productos
    $stmt = $conn->prepare("SELECT cod_producto, nombre_producto, precio, cantidad FROM mov_salida WHERE cod_movimiento = ? ORDER BY cod_producto");
    $stmt->bind_param("i", $codigoFactura);
    $stmt->execute();
    $detallesFactura = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // total
    foreach ($detallesFactura as $detalle) {
        $totalFactura += $detalle["precio"] * $detalle["cantidad"];
    }
}


// eliminar movimiento

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_movimiento"])) {
    try {
        if (!$esAdmin) {
            throw new Exception("No tienes permiso para eliminar movimientos.");
        }

        $codigo = (int)($_POST["cod_movimiento"] ?? 0);
        $conn->begin_transaction();

        // guarda los productos
        $stmt = $conn->prepare("SELECT cod_producto FROM mov_salida WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $productos = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "cod_producto");
        $stmt->close();

        // borra los productos
        $stmt = $conn->prepare("DELETE FROM mov_salida WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $stmt->close();

        // borra la factura
        $stmt = $conn->prepare("DELETE FROM factura_salida WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $eliminados = $stmt->affected_rows;
        $stmt->close();

        if ($eliminados < 1) {
            throw new Exception("No se encontró el movimiento que deseas eliminar.");
        }

        actualizarInventario($conn, $productos);
        $conn->commit();

        header("Location: mov_salida.php?mensaje=eliminado");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    }
}


// guardar o actualizar salida

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["eliminar_movimiento"])) {
    $accion = $_POST["accion"] ?? "";
    $codigo = (int)($_POST["cod_movimiento"] ?? 0);
    $cedula = trim((string)($_POST["cedula_cliente"] ?? ""));
    $nota = trim((string)($_POST["nota"] ?? ""));
    $codigos = $_POST["cod_producto"] ?? [];
    $cantidades = $_POST["cantidad"] ?? [];

    try {
        // validaciones
        if ($accion === "actualizar" && !$esAdmin) {
            throw new Exception("No tienes permiso para actualizar movimientos.");
        }
        if ($accion === "guardar" && $esAdmin) {
            throw new Exception("El administrador no registra nuevas salidas. Inicia sesión como empleado.");
        }
        if (!ctype_digit($cedula)) {
            throw new Exception("Selecciona un cliente válido de la lista.");
        }
        if (!is_array($codigos) || count($codigos) === 0 || count($codigos) !== count($cantidades)) {
            throw new Exception("Agrega al menos un producto.");
        }

        // junta productos repetidos
        $pedido = [];
        for ($i = 0; $i < count($codigos); $i++) {
            if ((int)$codigos[$i] <= 0 || (int)$cantidades[$i] <= 0) {
                throw new Exception("Revisa los productos: elige un producto válido y una cantidad mayor a 0.");
            }
            $pedido[(int)$codigos[$i]] = ($pedido[(int)$codigos[$i]] ?? 0) + (int)$cantidades[$i];
        }

        $conn->begin_transaction();

        // el cliente existe
        $cedula = (int)$cedula;
        $stmt = $conn->prepare("SELECT cedula_cliente FROM cliente WHERE cedula_cliente = ?");
        $stmt->bind_param("i", $cedula);
        $stmt->execute();
        $existeCliente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existeCliente) {
            throw new Exception("El cliente seleccionado no está registrado.");
        }

        $preciosOriginales = [];

        if ($accion === "actualizar") {

            // la factura existe
            $stmt = $conn->prepare("SELECT fecha FROM factura_salida WHERE cod_movimiento = ? FOR UPDATE");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            $factura = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$factura) {
                throw new Exception("El movimiento que intentas actualizar no existe.");
            }
            $fecha = $factura["fecha"];

            // guarda los precios viejos
            $stmt = $conn->prepare("SELECT cod_producto, precio FROM mov_salida WHERE cod_movimiento = ?");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fila) {
                $preciosOriginales[(int)$fila["cod_producto"]] = (float)$fila["precio"];
            }
            $stmt->close();

            // actualiza factura
            $stmt = $conn->prepare("UPDATE factura_salida SET cedula_cliente = ?, nota = ? WHERE cod_movimiento = ?");
            $stmt->bind_param("isi", $cedula, $nota, $codigo);
            $stmt->execute();
            $stmt->close();

            // borra productos viejos
            $stmt = $conn->prepare("DELETE FROM mov_salida WHERE cod_movimiento = ?");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            $stmt->close();
        } else {

            // codigo nuevo
            $codigo = (int)$conn->query("SELECT COALESCE(MAX(cod_movimiento), 0) + 1 FROM factura_salida FOR UPDATE")->fetch_row()[0];
            $fecha = date("Y-m-d");

            $stmt = $conn->prepare("INSERT INTO factura_salida (cod_movimiento, fecha, nota, cedula_cliente) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $codigo, $fecha, $nota, $cedula);
            $stmt->execute();
            $stmt->close();
        }

        // guarda cada producto
        $totalVenta = 0;

        foreach ($pedido as $codProducto => $cantidad) {
            $stmt = $conn->prepare(
                "SELECT nombre, precio,
                    (SELECT COALESCE(SUM(cantidad), 0) FROM mov_entrada WHERE cod_producto = ?) -
                    (SELECT COALESCE(SUM(cantidad), 0) FROM mov_salida WHERE cod_producto = ?) AS disponible
                 FROM producto WHERE cod_producto = ? FOR UPDATE"
            );
            $stmt->bind_param("iii", $codProducto, $codProducto, $codProducto);
            $stmt->execute();
            $producto = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$producto) {
                throw new Exception("El producto con código " . $codProducto . " no existe.");
            }
            if ($cantidad > (int)$producto["disponible"]) {
                throw new Exception("Stock insuficiente para '" . $producto["nombre"] . "'. Disponibles: " . (int)$producto["disponible"] . ", solicitados: " . $cantidad . ".");
            }

            $nombre = $producto["nombre"];
            $precio = $preciosOriginales[$codProducto] ?? (float)$producto["precio"];

            $stmt = $conn->prepare(
                "INSERT INTO mov_salida (cod_movimiento, fecha, precio, cantidad, cod_producto, nombre_producto, cedula_cliente)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isdiisi", $codigo, $fecha, $precio, $cantidad, $codProducto, $nombre, $cedula);
            $stmt->execute();
            $stmt->close();

            $totalVenta += $precio * $cantidad;
        }

        // guarda cantidad y total
        $numeroProductos = count($pedido);
        $stmt = $conn->prepare("UPDATE factura_salida SET productos = ?, total_venta = ? WHERE cod_movimiento = ?");
        $stmt->bind_param("idi", $numeroProductos, $totalVenta, $codigo);
        $stmt->execute();
        $stmt->close();

        // actualiza inventario
        actualizarInventario($conn, array_merge(array_keys($pedido), array_keys($preciosOriginales)));

        $conn->commit();

        header("Location: mov_salida.php?guardado=" . $codigo);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    }
}


// cargar factura a editar

$codigoEditar = (int)($_GET["editar"] ?? 0);

// si falla, reabre la factura
if ($codigoEditar === 0 && ($_POST["accion"] ?? "") === "actualizar") {
    $codigoEditar = (int)($_POST["cod_movimiento"] ?? 0);
}

if ($codigoEditar > 0) {
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para editar movimientos.";
        $tipoMensaje = "error";
    } else {

        // datos de la factura
        $stmt = $conn->prepare(
            "SELECT f.*, c.nombre AS nombre_cliente FROM factura_salida f
             LEFT JOIN cliente c ON f.cedula_cliente = c.cedula_cliente
             WHERE f.cod_movimiento = ?"
        );
        $stmt->bind_param("i", $codigoEditar);
        $stmt->execute();
        $factura_editar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$factura_editar) {
            $mensaje = "El movimiento a editar no existe.";
            $tipoMensaje = "error";
        } else {

            // productos
            $stmt = $conn->prepare("SELECT cod_producto, nombre_producto, precio, cantidad FROM mov_salida WHERE cod_movimiento = ? ORDER BY cod_producto");
            $stmt->bind_param("i", $codigoEditar);
            $stmt->execute();
            $detalles_editar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $cedulaSeleccionada = $factura_editar["cedula_cliente"];
            $clienteSeleccionadoTexto = $cedulaSeleccionada . " - " . $factura_editar["nombre_cliente"];
        }
    }
}


// mensajes de exito

if (($_GET["mensaje"] ?? "") === "eliminado") {
    $mensaje = "Movimiento eliminado correctamente.";
    $tipoMensaje = "ok";
}

if (isset($_GET["guardado"]) && $tipoMensaje !== "error") {
    $mensaje = "Movimiento #" . (int)$_GET["guardado"] . " procesado exitosamente.";
    $tipoMensaje = "ok";
}


// tablas del administrador

if ($esAdmin) {
    // detalle
    $resultadoResumen = $conn->query(
        "SELECT cod_movimiento, cod_producto, nombre_producto, precio, cantidad, fecha, cedula_cliente
         FROM mov_salida ORDER BY cod_movimiento DESC, cod_producto ASC"
    );

    // facturas
    $resultadoFacturas = $conn->query(
        "SELECT f.cod_movimiento, f.fecha, f.nota, f.cedula_cliente,
                f.productos AS numero_productos, f.total_venta AS total, c.nombre AS nombre_cliente
         FROM factura_salida f
         LEFT JOIN cliente c ON f.cedula_cliente = c.cedula_cliente
         ORDER BY f.cod_movimiento DESC"
    );
}

// formulario: empleado siempre, admin al editar
$mostrarForm = (!$esAdmin || $factura_editar !== null);

// factura recien guardada
$codigoRecienGuardado = (!$esAdmin && isset($_GET["guardado"])) ? (int)$_GET["guardado"] : 0;
?>

<?php if ($verFactura): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?= escapar($codigoFactura) ?></title>
    <link rel="stylesheet" href="../css/factura.css">
    <link rel="stylesheet" href="../css/menu.css?v=20261118">
</head>
<body>

    <?php if (!$facturaVer): ?>
        <p class="factura-vacia">La factura no existe.</p>
    <?php else: ?>

        <!-- titulo -->
        <div class="centro">
            <h2>Supermercado La Granja</h2>
            <h3>FACTURA DE VENTA N° <?= escapar($facturaVer["cod_movimiento"]) ?></h3>
        </div>

        <!-- fecha -->
        <p>
            <strong>Fecha:</strong> <?= escapar($facturaVer["fecha"]) ?><br>
            <strong>Atendió:</strong> <?= escapar($usuario) ?>
        </p>

        <!-- cliente -->
        <p>
            <strong>Cliente:</strong> <?= escapar($facturaVer["nombre"] ?? "Sin nombre") ?><br>
            <strong>Cédula:</strong> <?= escapar($facturaVer["cedula_cliente"]) ?><br>
            <strong>Teléfono:</strong> <?= escapar($facturaVer["telefono"] ?? "") ?><br>
            <strong>Email:</strong> <?= escapar($facturaVer["email"] ?? "") ?><br>
            <strong>Dirección:</strong> <?= escapar($facturaVer["direccion"] ?? "") ?><br>
            <strong>Nota:</strong> <?= escapar($facturaVer["nota"] ?? "") ?>
        </p>

        <!-- productos -->
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="derecha">Precio U.</th>
                    <th class="derecha">Cant.</th>
                    <th class="derecha">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detallesFactura as $detalle): ?>
                    <tr>
                        <td><?= escapar($detalle["cod_producto"]) ?></td>
                        <td><?= escapar($detalle["nombre_producto"]) ?></td>
                        <td class="derecha"><?= "$" . number_format((float)$detalle["precio"], 2, ",", ".") ?></td>
                        <td class="derecha"><?= escapar($detalle["cantidad"]) ?></td>
                        <td class="derecha"><?= "$" . number_format((float)($detalle["precio"] * $detalle["cantidad"]), 2, ",", ".") ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="derecha total">TOTAL</td>
                    <td class="derecha total"><?= "$" . number_format((float)$totalFactura, 2, ",", ".") ?></td>
                </tr>
            </tfoot>
        </table>

        <p class="factura-gracias">¡Gracias por su compra!</p>

    <?php endif; ?>
</body>
</html>
<?php exit(); endif; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- el celular ve la pagina como en el computador -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimiento de Salida | La Granja</title>

    <!-- estilos de la pagina -->
    <link rel="stylesheet" href="../css/mov_salida.css?v=20261120">
</head>

<body class="pagina-salida">

    <!-- encabezado -->
    <header class="encabezado">

        <div class="encabezado-marca">
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
        </div>

        <div class="encabezado-herramientas">
            <div class="encabezado-fecha">
                <img src="../img/calendario.png" alt="" class="icono-herramienta">
                <span><?= date("d/m/Y") ?></span>
            </div>

            <a href="ayuda.php#inventario"
                class="boton-herramienta boton-notificaciones" aria-label="Productos con pocas existencias">
                <img src="../img/notificaciones.png" alt="" class="icono-herramienta">
                <?php if ($totalStockBajo > 0): ?>
                    <span class="numero-notificaciones"><?= $totalStockBajo ?></span>
                <?php endif; ?>
            </a>

            <a href="ayuda.php" class="boton-herramienta" aria-label="Centro de ayuda">
                <img src="../img/ayuda.png" alt="" class="icono-herramienta">
            </a>

            <div class="encabezado-usuario">
                <span class="usuario-inicial"><?= escapar($inicial) ?></span>
                <div>
                    <strong><?= escapar($usuario) ?></strong>
                    <p><?= escapar($nombreCargo) ?></p>
                </div>
            </div>
        </div>

        <!-- ola verde del encabezado -->
        <img src="../img/ola.png" alt="" class="encabezado-ola">

        </header>

    <!-- fondo del menu lateral -->
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

            <button type="button" class="boton-cerrar" id="cerrarMenu" aria-label="Cerrar menú lateral">
                <img src="../img/cerrar.png" alt="" class="icono">
            </button>
        </div>

        <!-- usuario -->
        <div class="menu-lateral-usuario">
            <div class="usuario-inicial usuario-grande"><?= escapar($inicial) ?></div>
            <div>
                <strong><?= escapar($usuario) ?></strong>
                <p><?= escapar($nombreCargo) ?></p>
            </div>
        </div>

        <!-- enlaces -->
        <nav class="navegacion">
            <p class="titulo-navegacion">MENÚ PRINCIPAL</p>

            <div class="lista-navegacion">
                <a href="menu.php" class="enlace-menu">
                    <span class="caja-icono"><img src="../img/inicio.png" alt="" class="icono"></span>
                    <span>Inicio</span>
                </a>

                <a href="cliente.php" class="enlace-menu">
                    <span class="caja-icono"><img src="../img/cliente.png" alt="" class="icono"></span>
                    <span>Cliente</span>
                </a>

                <a href="mov_salida.php" class="enlace-menu activo" aria-current="page">
                    <span class="caja-icono"><img src="../img/salida.png" alt="" class="icono"></span>
                    <span>Movimiento de salida</span>
                </a>

                <!-- solo administrador -->
                <?php if ($esAdmin): ?>
                    <a href="producto.php" class="enlace-menu">
                        <span class="caja-icono"><img src="../img/producto.png" alt="" class="icono"></span>
                        <span>Productos</span>
                    </a>

                    <a href="mov_entrada.php" class="enlace-menu">
                        <span class="caja-icono"><img src="../img/entrada.png" alt="" class="icono"></span>
                        <span>Movimiento de entrada</span>
                    </a>

                    <a href="proveedor.php" class="enlace-menu">
                        <span class="caja-icono"><img src="../img/proveedor.png" alt="" class="icono"></span>
                        <span>Proveedor</span>
                    </a>

                    <a href="inventario.php" class="enlace-menu">
                        <span class="caja-icono"><img src="../img/inventario.png" alt="" class="icono"></span>
                        <span>Inventario</span>
                    </a>

                    <a href="reportes.php" class="enlace-menu">
                        <span class="caja-icono"><img src="../img/reportes.png" alt="" class="icono"></span>
                        <span>Reportes</span>
                    </a>

                    <a href="edita_usuario.php" class="enlace-menu">
                        <span class="caja-icono"><img src="../img/usuario.png" alt="" class="icono"></span>
                        <span>Modificar usuario</span>
                    </a>
                <?php endif; ?>
            </div>
        </nav>

        <!-- cerrar sesion -->
        <form action="cerrar_sesion.php" method="POST" class="form-salir">
            <button type="submit" name="salir" value="1" class="boton-salir">
                <span class="caja-icono caja-icono-roja"><img src="../img/salir.png" alt="" class="icono"></span>
                <span>Cerrar sesión</span>
            </button>
        </form>

    </aside>

    <!-- contenido principal -->
    <main class="contenido">

        <!-- titulo de la pagina -->
        <section class="titulo-pagina">
            <span class="titulo-icono"><img src="../img/salida.png" alt="" class="icono"></span>
            <div>
                <h2>Movimiento de Salida</h2>
                <p><?= $esAdmin ? "Consulta, edita o elimina los movimientos de salida registrados." : "Registra salidas de productos e imprime facturas de venta." ?></p>
            </div>
        </section>

        <!-- mensaje -->
        <?php if ($mensaje !== ""): ?>
            <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
                <?= escapar($mensaje) ?>
            </div>
        <?php endif; ?>

        <!-- formulario -->
        <?php if ($mostrarForm): ?>
            <section class="panel">
                <h3 class="panel-titulo">
                    <?= $factura_editar ? "Editar Movimiento #" . escapar($factura_editar["cod_movimiento"]) : "Registrar Salida de Productos" ?>
                </h3>

                <form method="POST" id="formularioFactura" action="mov_salida.php" class="formulario" novalidate>
                    <input type="hidden" name="accion" value="<?= $factura_editar ? "actualizar" : "guardar" ?>">

                    <?php if ($factura_editar): ?>
                        <input type="hidden" name="cod_movimiento" value="<?= escapar($factura_editar["cod_movimiento"]) ?>">
                    <?php endif; ?>

                    <!-- codigo y fecha -->
                    <div class="campo">
                        <label>Código de movimiento</label>
                        <input type="text" readonly value="<?= $factura_editar ? escapar($factura_editar["cod_movimiento"]) : "Automático al guardar" ?>">
                    </div>

                    <div class="campo">
                        <label>Fecha de emisión</label>
                        <input type="date" readonly value="<?= $factura_editar ? escapar($factura_editar["fecha"]) : date("Y-m-d") ?>">
                    </div>

                    <!-- nota -->
                    <div class="campo campo-ancho">
                        <label for="nota">Nota</label>
                        <textarea id="nota" name="nota" rows="3" placeholder="Nota u observación (opcional)"><?= escapar($factura_editar["nota"] ?? "") ?></textarea>
                    </div>

                    <!-- cliente -->
                    <div class="campo campo-ancho">
                        <label for="cliente_busqueda">Cédula o Nombre del Cliente <span class="obligatorio">*</span></label>
                        <input type="text" id="cliente_busqueda" name="cliente_busqueda" list="lista_clientes" autocomplete="off" placeholder="Escriba para buscar cliente..." value="<?= escapar($clienteSeleccionadoTexto) ?>">
                        <datalist id="lista_clientes"></datalist>
                        <input type="hidden" name="cedula_cliente" id="cedula_cliente" value="<?= escapar($cedulaSeleccionada) ?>">
                        <p id="estado_cliente" class="estado-texto"></p>
                    </div>

                    <!-- productos -->
                    <div class="campo campo-ancho">
                        <label>Detalle de Productos <span class="obligatorio">*</span></label>
                        <datalist id="lista_productos"></datalist>

                        <div class="tabla-contenedor">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th width="15%">Código <span class="obligatorio">*</span></th>
                                        <th>Producto <span class="obligatorio">*</span></th>
                                        <th width="18%" class="derecha">Precio Unitario</th>
                                        <th width="15%" class="derecha">Cantidad <span class="obligatorio">*</span></th>
                                        <th width="18%" class="derecha">Subtotal</th>
                                        <th width="10%">Acción</th>
                                    </tr>
                                </thead>

                                <tbody id="productos">
                                    <?php foreach ($detalles_editar as $detalle): ?>
                                        <tr>
                                            <td>
                                                <input class="codigo" type="number" name="cod_producto[]" min="1" value="<?= escapar($detalle["cod_producto"]) ?>">
                                            </td>
                                            <td>
                                                <input class="nombre-producto" type="text" name="nombre_producto[]" list="lista_productos" autocomplete="off" placeholder="Escriba código o nombre..." value="<?= escapar($detalle["nombre_producto"]) ?>">
                                            </td>
                                            <td>
                                                <input class="precio" type="number" name="precio[]" min="0" step="0.01" readonly value="<?= escapar($detalle["precio"]) ?>">
                                            </td>
                                            <td>
                                                <input class="cantidad" type="number" name="cantidad[]" min="1" step="1" value="<?= escapar($detalle["cantidad"]) ?>">
                                            </td>
                                            <td class="subtotal derecha">$0</td>
                                            <td>
                                                <button type="button" class="boton boton-chico boton-eliminar quitar">Quitar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>

                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="derecha total">TOTAL</th>
                                        <th id="totalFactura" class="derecha total">$0</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- botones -->
                    <div class="botones campo-ancho">
                        <button type="button" class="boton boton-secundario" id="btnAgregarProducto">+ Agregar producto</button>

                        <button type="submit" class="boton boton-principal">
                            <?= $factura_editar ? "Actualizar Movimiento" : "Guardar Movimiento" ?>
                        </button>

                        <?php if ($codigoRecienGuardado > 0): ?>
                            <button type="button" class="boton boton-factura btn-ver-factura" data-codigo="<?= $codigoRecienGuardado ?>">Generar Factura</button>
                        <?php endif; ?>

                        <?php if ($factura_editar): ?>
                            <a href="mov_salida.php" class="boton boton-secundario">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <!-- tablas -->
        <?php if ($esAdmin): ?>

            <!-- detalle de movimientos -->
            <section class="panel">
                <h3 class="panel-titulo">Detalle de Movimientos de Salida</h3>

                <div class="buscador">
                    <input type="search" class="buscar-tabla" data-tabla="tablaDetalle" placeholder="Buscar por código, producto, cliente o fecha...">
                </div>

                <div class="tabla-contenedor tabla-scroll">
                    <table class="tabla" id="tablaDetalle">
                        <thead>
                            <tr>
                                <th>Movimiento</th>
                                <th>Código producto</th>
                                <th>Nombre del producto</th>
                                <th class="derecha">Precio</th>
                                <th class="derecha">Cantidad</th>
                                <th>Fecha</th>
                                <th>Cédula cliente</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody id="cuerpoDetalle">
                            <?php if ($resultadoResumen && $resultadoResumen->num_rows > 0): ?>
                                <?php while ($prod = $resultadoResumen->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= escapar($prod["cod_movimiento"]) ?></td>
                                        <td><?= escapar($prod["cod_producto"]) ?></td>
                                        <td><?= escapar($prod["nombre_producto"]) ?></td>
                                        <td class="derecha"><?= "$" . number_format((float)$prod["precio"], 2, ",", ".") ?></td>
                                        <td class="derecha"><?= escapar($prod["cantidad"]) ?></td>
                                        <td><?= escapar($prod["fecha"]) ?></td>
                                        <td><?= escapar($prod["cedula_cliente"]) ?></td>
                                        <td class="tabla-acciones">
                                            <a href="mov_salida.php?editar=<?= urlencode((string)$prod["cod_movimiento"]) ?>" class="boton boton-chico boton-editar">Editar</a>

                                            <form action="mov_salida.php" method="POST" class="form-eliminar">
                                                <input type="hidden" name="cod_movimiento" value="<?= escapar($prod["cod_movimiento"]) ?>">
                                                <button type="submit" name="eliminar_movimiento" value="1" class="boton boton-chico boton-eliminar">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="tabla-vacia">No se encontraron movimientos.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- historial de facturas -->
            <section class="panel">
                <h3 class="panel-titulo">Historial de Salidas / Facturas</h3>

                <div class="buscador">
                    <input type="search" class="buscar-tabla" data-tabla="tablaFacturas" placeholder="Buscar por código, cédula, cliente o fecha...">
                </div>

                <div class="tabla-contenedor tabla-scroll">
                    <table class="tabla" id="tablaFacturas">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Cédula - Cliente</th>
                                <th>Nota</th>
                                <th class="derecha">Productos</th>
                                <th class="derecha">Total Venta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody id="cuerpoFacturas">
                            <?php if ($resultadoFacturas && $resultadoFacturas->num_rows > 0): ?>
                                <?php while ($fila = $resultadoFacturas->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= escapar($fila["cod_movimiento"]) ?></td>
                                        <td><?= escapar($fila["fecha"]) ?></td>
                                        <td><?= escapar($fila["cedula_cliente"]) . " - " . escapar($fila["nombre_cliente"] ?? "Sin Nombre") ?></td>
                                        <td><?= escapar($fila["nota"] ?? "") !== "" ? escapar($fila["nota"]) : "Sin nota" ?></td>
                                        <td class="derecha"><?= escapar($fila["numero_productos"]) ?></td>
                                        <td class="derecha"><?= "$" . number_format((float)$fila["total"], 2, ",", ".") ?></td>
                                        <td class="tabla-acciones">
                                            <button type="button" class="boton boton-chico boton-factura btn-ver-factura" data-codigo="<?= (int)$fila["cod_movimiento"] ?>">Factura</button>
                                            <a href="mov_salida.php?editar=<?= (int)$fila["cod_movimiento"] ?>" class="boton boton-chico boton-editar">Editar</a>

                                            <form action="mov_salida.php" method="POST" class="form-eliminar">
                                                <input type="hidden" name="cod_movimiento" value="<?= escapar($fila["cod_movimiento"]) ?>">
                                                <button type="submit" name="eliminar_movimiento" value="1" class="boton boton-chico boton-eliminar">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="tabla-vacia">No se encontraron registros.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php endif; ?>
    </main>

    <!-- ventana eliminar -->
    <?php if ($esAdmin): ?>
        <div class="modal" id="modalEliminar" data-modulo="salida" hidden>
            <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
                <div class="modal__icono">
                    <img src="../img/error.png" alt="Error" width="80" height="80">
                </div>

                <h3 class="modal__titulo" id="tituloEliminar">Eliminar movimiento de salida</h3>
                <p class="modal__texto" id="textoEliminar">¿Seguro que deseas eliminar esta salida y todos sus productos? Las cantidades volverán al inventario.</p>

                <div class="modal__botones">
                    <button type="button" class="boton boton-secundario" id="cancelarEliminar">Cancelar</button>
                    <button type="button" class="boton boton-eliminar" id="confirmarEliminar">Eliminar</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- pie de pagina -->
    <footer class="pie-pagina">

        <div class="footer-contenido">

            <!-- ayuda -->
            <section class="footer-ayuda">
                <h4>AYUDA Y SOPORTE</h4>

                <p class="footer-descripcion">
                    Encuentra orientación para utilizar correctamente
                    el sistema de gestión de inventario.
                </p>

                <a href="ayuda.php" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
                <a href="ayuda.php#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
                <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
                <a href="ayuda.php#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
                <a href="ayuda.php#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
                <a href="ayuda.php#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
            </section>

            <!-- informacion -->
            <section class="footer-informacion">
                <h4>INFORMACIÓN DEL SISTEMA</h4>

                <p>
                    <strong>Plataforma administrativa</strong><br>
                    Herramienta para organizar y controlar los productos del supermercado.
                </p>

                <p>
                    <strong>Gestión de clientes</strong><br>
                    Registro, consulta y actualización de la información de los clientes.
                </p>

                <p>
                    <strong>Control de movimientos</strong><br>
                    Seguimiento de las entradas y salidas de mercancía.
                </p>

                <div class="footer-nota">
                    <strong>Uso del sistema</strong>
                    <p>Acceso destinado a empleados y administradores autorizados.</p>
                </div>
            </section>

        </div>

        <!-- parte inferior -->
        <div class="footer-inferior">
            <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
            <p>Sistema de gestión de inventario</p>
        </div>

    </footer>

    <script src="../js/mov_salida.js"></script>

</body>
</html>
