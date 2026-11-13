<?php
session_start();

// si no ha iniciado sesion, volver al ingreso
if (!isset($_SESSION["usuario"], $_SESSION["rol"]) || !in_array((string)$_SESSION["rol"], ["1", "2"], true)) {
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

// evita que el texto rompa el html
function escapar($dato): string {
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

// esta funcion muestra un icono de la carpeta img
function icono(string $nombre): string {
    return '<img src="../img/' . escapar($nombre) . '.png" alt="" class="icono">';
}

// da formato de pesos a un numero
function formatoMoneda($valor): string {
    return "$" . number_format((float)$valor, 2, ",", ".");
}

// deja la tabla inventario igual a entradas - salidas para cada producto
function recalcularInventario($conn, array $codigosProductos) {
    // prepara la consulta sql de forma segura
    $stmt = $conn->prepare(
        "INSERT INTO inventario (cod_producto, cantidad)
         SELECT ?, COALESCE((SELECT SUM(cantidad) FROM mov_entrada WHERE cod_producto = ?), 0) -
                   COALESCE((SELECT SUM(cantidad) FROM mov_salida WHERE cod_producto = ?), 0)
         ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)"
    );
    // recorre cada producto sin repetirlos
    foreach (array_unique($codigosProductos) as $codProducto) {
        $codProducto = (int)$codProducto;
        // envia los datos a la consulta
        $stmt->bind_param("iii", $codProducto, $codProducto, $codProducto);
        // ejecuta la consulta
        $stmt->execute();
    }
    // cierra la consulta
    $stmt->close();
}

// arma la factura para imprimir
function mostrarFactura($conn, $codigo, $usuario) {
    // prepara la consulta sql de forma segura
    $stmt = $conn->prepare(
        "SELECT f.cod_movimiento, f.fecha, f.nota, f.cedula_cliente,
                c.nombre, c.telefono, c.email, c.direccion
         FROM factura_salida f
         LEFT JOIN cliente c ON f.cedula_cliente = c.cedula_cliente
         WHERE f.cod_movimiento = ?"
    );
    // envia los datos a la consulta
    $stmt->bind_param("i", $codigo);
    // ejecuta la consulta
    $stmt->execute();
    // obtiene el resultado de la consulta
    $factura = $stmt->get_result()->fetch_assoc();
    // cierra la consulta
    $stmt->close();

    // productos de la factura y su total
    $detalles = [];
    // esta variable almacena un total que se va sumando
    $total = 0;

    if ($factura) {
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare(
            "SELECT cod_producto, nombre_producto, precio, cantidad
             FROM mov_salida
             WHERE cod_movimiento = ?
             ORDER BY cod_producto"
        );
        // envia los datos a la consulta
        $stmt->bind_param("i", $codigo);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $resDetalles = $stmt->get_result();

        // recorre cada fila del resultado
        while ($detalle = $resDetalles->fetch_assoc()) {
            $detalles[] = $detalle;
            $total += ($detalle["precio"] * $detalle["cantidad"]);
        }
        // cierra la consulta
        $stmt->close();
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Factura <?= escapar($codigo) ?></title>
        <!-- hoja de estilos de la factura -->
        <link rel="stylesheet" href="../css/factura.css">
    </head>
    <!-- cuerpo de la pagina -->
    <body>
        <?php if (!$factura): ?>
            <p class="factura-vacia">La factura no existe.</p>
        <?php else: ?>
            <div class="centro">
                <h2>Supermercado La Granja</h2>
                <h3>FACTURA DE VENTA N° <?= escapar($factura["cod_movimiento"]) ?></h3>
            </div>
            <p>
                <strong>Fecha:</strong> <?= escapar($factura["fecha"]) ?><br>
                <strong>Atendió:</strong> <?= escapar($usuario) ?>
            </p>
            <p>
                <strong>Cliente:</strong> <?= escapar($factura["nombre"] ?? "Sin nombre") ?><br>
                <strong>Cédula:</strong> <?= escapar($factura["cedula_cliente"]) ?><br>
                <strong>Teléfono:</strong> <?= escapar($factura["telefono"] ?? "") ?><br>
                <strong>Email:</strong> <?= escapar($factura["email"] ?? "") ?><br>
                <strong>Dirección:</strong> <?= escapar($factura["direccion"] ?? "") ?><br>
                <strong>Nota:</strong> <?= escapar($factura["nota"] ?? "") ?>
            </p>
            <!-- tabla con los registros -->
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto <span class="obligatorio">*</span></th>
                        <th class="derecha">Precio U.</th>
                        <th class="derecha">Cant.</th>
                        <th class="derecha">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?= escapar($detalle["cod_producto"]) ?></td>
                            <td><?= escapar($detalle["nombre_producto"]) ?></td>
                            <td class="derecha"><?= formatoMoneda($detalle["precio"]) ?></td>
                            <td class="derecha"><?= escapar($detalle["cantidad"]) ?></td>
                            <td class="derecha"><?= formatoMoneda($detalle["precio"] * $detalle["cantidad"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="derecha total">TOTAL</td>
                        <td class="derecha total"><?= formatoMoneda($total) ?></td>
                    </tr>
                </tfoot>
            </table>
            <p class="factura-gracias">¡Gracias por su compra!</p>
            <p class="factura-botones no-imprimir">
                <button type="button" onclick="window.print()">Imprimir factura</button>
                <button type="button" onclick="window.close()">Cerrar</button>
            </p>
        <?php endif; ?>
        <!-- codigo javascript de la pagina -->
        <script>
            // escucha cuando termina de cargar la pagina
            window.addEventListener("load", function () {
                if (window.frameElement) {
                    setTimeout(function () { window.print(); }, 300);
                }
            });
        </script>
    </body>
    </html>
    <?php
}

if (isset($_GET["factura"])) {
    // se envia el usuario porque dentro de la funcion no se ve la variable $usuario
    mostrarFactura($conn, (int)$_GET["factura"], $usuario);
    exit();
}

// busca un producto por su codigo (ajax)
if (isset($_GET["producto_codigo"])) {
    header("Content-Type: application/json; charset=utf-8");
    // intenta ejecutar el codigo y atrapa los errores
    try {
        // datos que llegan por la url
        $codigoProducto = (int) $_GET["producto_codigo"];
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare("SELECT cod_producto, nombre, precio FROM producto WHERE cod_producto = ?");
        // envia los datos a la consulta
        $stmt->bind_param("i", $codigoProducto);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $producto = $stmt->get_result()->fetch_assoc();
        // cierra la consulta
        $stmt->close();

        if ($producto) {
            echo json_encode([
                "existe" => true,
                "codigo" => $producto["cod_producto"],
                "nombre" => $producto["nombre"],
                "precio" => $producto["precio"]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(["existe" => false]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["existe" => false]);
    }
    exit();
}

// busca clientes por cedula o nombre (ajax)
if (isset($_GET["buscar_cliente"])) {
    header("Content-Type: application/json; charset=utf-8");
    // intenta ejecutar el codigo y atrapa los errores
    try {
        // datos que llegan por la url
        $textoCliente = trim($_GET["buscar_cliente"]);
        if ($textoCliente === "") {
            echo json_encode([]);
            exit();
        }

        $parteCedula = explode(" - ", $textoCliente)[0];
        // esta variable almacena el texto buscado con comodines para usar like
        $filtro = "%" . $textoCliente . "%";
        $filtroCedula = "%" . trim($parteCedula) . "%";

        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare(
            "SELECT cedula_cliente, nombre FROM cliente
             WHERE CAST(cedula_cliente AS CHAR) LIKE ? OR nombre LIKE ?
             ORDER BY CASE WHEN CAST(cedula_cliente AS CHAR) = ? THEN 0 WHEN nombre = ? THEN 0 ELSE 1 END, nombre ASC
             LIMIT 10"
        );
        // envia los datos a la consulta
        $stmt->bind_param("ssss", $filtroCedula, $filtro, $parteCedula, $textoCliente);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $resClientes = $stmt->get_result();

        // esta variable almacena una lista que se llena mas adelante
        $lista = [];
        // recorre cada fila del resultado
        while ($c = $resClientes->fetch_assoc()) {
            $lista[] = ["cedula" => $c["cedula_cliente"], "nombre" => $c["nombre"]];
        }
        // cierra la consulta
        $stmt->close();
        echo json_encode($lista, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([]);
    }
    exit();
}

// busca productos por codigo o nombre para el autocompletar (ajax)
if (isset($_GET["buscar_producto"])) {
    header("Content-Type: application/json; charset=utf-8");
    // intenta ejecutar el codigo y atrapa los errores
    try {
        // datos que llegan por la url
        $textoProducto = trim($_GET["buscar_producto"]);
        if ($textoProducto === "") {
            echo json_encode([]);
            exit();
        }

        $parteCodigo = trim(explode(" - ", $textoProducto)[0]);
        // esta variable almacena el texto buscado con comodines para usar like
        $filtro = "%" . $textoProducto . "%";
        $filtroCodigo = "%" . $parteCodigo . "%";

        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare(
            "SELECT cod_producto, nombre, precio FROM producto
             WHERE CAST(cod_producto AS CHAR) LIKE ? OR nombre LIKE ?
             ORDER BY CASE WHEN CAST(cod_producto AS CHAR) = ? THEN 0 WHEN nombre = ? THEN 0 ELSE 1 END, nombre ASC
             LIMIT 10"
        );
        // envia los datos a la consulta
        $stmt->bind_param("ssss", $filtroCodigo, $filtro, $parteCodigo, $textoProducto);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $resProductos = $stmt->get_result();

        // esta variable almacena una lista que se llena mas adelante
        $lista = [];
        // recorre cada fila del resultado
        while ($p = $resProductos->fetch_assoc()) {
            $lista[] = ["codigo" => $p["cod_producto"], "nombre" => $p["nombre"], "precio" => $p["precio"]];
        }
        // cierra la consulta
        $stmt->close();
        echo json_encode($lista, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([]);
    }
    exit();
}

// variables para guardar, editar y eliminar
$mensaje = "";
// esta variable almacena el tipo de mensaje (ok o error)
$tipoMensaje = "";
$factura_editar = null;
// esta variable almacena una lista que se llena mas adelante
$detalles_editar = [];
$cedulaSeleccionada = "";
$clienteSeleccionadoTexto = "";
// esta variable almacena el texto que se escribe en el buscador
$busqueda = trim((string)($_GET["buscar"] ?? ""));

// eliminar movimiento (solo admin)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_movimiento"])) {
    if (!$esAdmin) {
        header("Location: mov_salida.php?mensaje=sin_permiso");
        exit();
    }

    $codigoEliminar = trim((string)($_POST["cod_movimiento"] ?? ""));
    $transaccion = false;

    try {
        if ($codigoEliminar === "" || !ctype_digit($codigoEliminar) || (int)$codigoEliminar < 1) {
            throw new InvalidArgumentException("Código de movimiento no válido.");
        }

        $codigoEliminar = (int)$codigoEliminar;
        $conn->begin_transaction();
        $transaccion = true;

        // comprueba que la factura exista antes de eliminar
        $stmt = $conn->prepare("SELECT cod_movimiento FROM factura_salida WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigoEliminar);
        $stmt->execute();
        $facturaExiste = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$facturaExiste) {
            throw new RuntimeException("No se encontró el movimiento que deseas eliminar.");
        }

        // guarda los productos para actualizar el inventario despues
        $productosBorrados = [];
        $stmt = $conn->prepare("SELECT cod_producto FROM mov_salida WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigoEliminar);
        $stmt->execute();
        $resBorrados = $stmt->get_result();
        while ($filaBorrada = $resBorrados->fetch_assoc()) {
            $productosBorrados[] = (int)$filaBorrada["cod_producto"];
        }
        $stmt->close();

        // primero elimina los detalles y despues la factura
        $stmt = $conn->prepare("DELETE FROM mov_salida WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigoEliminar);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM factura_salida WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigoEliminar);
        $stmt->execute();
        $eliminados = $stmt->affected_rows;
        $stmt->close();

        if ($eliminados < 1) {
            throw new RuntimeException("No se pudo eliminar el movimiento.");
        }

        // devuelve al inventario las cantidades de los productos eliminados
        if (!empty($productosBorrados)) {
            recalcularInventario($conn, $productosBorrados);
        }

        $conn->commit();
        header("Location: mov_salida.php?mensaje=eliminado");
        exit();
    } catch (Throwable $e) {
        if ($transaccion) {
            $conn->rollback();
        }
        if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) {
            $mensaje = $e->getMessage();
        } else {
            $mensaje = "No se pudo eliminar el movimiento. Verifica que no tenga registros relacionados.";
        }
        $tipoMensaje = "error";
    }
}

// cargar movimiento para editar (solo admin)
if (isset($_GET["editar"])) {
    // bloquea la accion si el usuario no es administrador
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para editar movimientos.";
        $tipoMensaje = "error";
    } else {
        // datos que llegan por la url
        $codigo = (int)$_GET["editar"];
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare("SELECT * FROM factura_salida WHERE cod_movimiento = ?");
        // envia los datos a la consulta
        $stmt->bind_param("i", $codigo);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $factura_editar = $stmt->get_result()->fetch_assoc();
        // cierra la consulta
        $stmt->close();

        if ($factura_editar) {
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("SELECT cod_producto, nombre_producto, precio, cantidad FROM mov_salida WHERE cod_movimiento = ? ORDER BY cod_producto");
            // envia los datos a la consulta
            $stmt->bind_param("i", $codigo);
            // ejecuta la consulta
            $stmt->execute();
            // obtiene el resultado de la consulta
            $resDet = $stmt->get_result();
            // recorre cada fila del resultado
            while ($d = $resDet->fetch_assoc()) {
                $detalles_editar[] = $d;
            }
            // cierra la consulta
            $stmt->close();

            $cedulaSeleccionada = (int) $factura_editar["cedula_cliente"];
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("SELECT cedula_cliente, nombre FROM cliente WHERE cedula_cliente = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $cedulaSeleccionada);
            // ejecuta la consulta
            $stmt->execute();
            // obtiene el resultado de la consulta
            $cli = $stmt->get_result()->fetch_assoc();
            // cierra la consulta
            $stmt->close();

            if ($cli) {
                $clienteSeleccionadoTexto = $cli["cedula_cliente"] . " - " . $cli["nombre"];
            }
        } else {
            $mensaje = "El movimiento a editar no existe.";
            $tipoMensaje = "error";
        }
    }
}

// guardar o actualizar movimiento
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // datos que llegan del formulario
    $accion = $_POST["accion"] ?? "";
    $codigo = (int)($_POST["cod_movimiento"] ?? 0);
    $cedula = trim((string)($_POST["cedula_cliente"] ?? ""));
    $nota = trim((string)($_POST["nota"] ?? ""));

    $codigos = $_POST["cod_producto"] ?? [];
    $nombres = $_POST["nombre_producto"] ?? [];
    $precios = $_POST["precio"] ?? [];
    $cantidades = $_POST["cantidad"] ?? [];

    if (
        !is_array($codigos) || count($codigos) === 0 ||
        $cedula === "" || count($codigos) !== count($nombres) ||
        count($codigos) !== count($precios) || count($codigos) !== count($cantidades)
    ) {
        $mensaje = "Selecciona un cliente válido y agrega al menos un producto.";
        $tipoMensaje = "error";
    } else {
        $transaccion = false;
        // intenta ejecutar el codigo y atrapa los errores
        try {
            if ($accion === "actualizar" && !$esAdmin) {
                throw new Exception("No tienes permiso para actualizar movimientos.");
            }
            if ($accion === "guardar" && $esAdmin) {
                throw new Exception("El administrador no registra nuevas salidas.");
            }

            $conn->begin_transaction();
            $transaccion = true;

            // revisa que el cliente exista
            $cedula = (int) $cedula;
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("SELECT cedula_cliente FROM cliente WHERE cedula_cliente = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $cedula);
            // ejecuta la consulta
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                // cierra la consulta
                $stmt->close();
                throw new Exception("El cliente seleccionado no se encuentra registrado.");
            }
            // cierra la consulta
            $stmt->close();

            // precios que ya tenia el movimiento (se conservan al editar)
            $preciosOriginales = [];

            if ($accion === "actualizar") {
                // revisa que el movimiento exista y lo bloquea mientras se edita
                $stmt = $conn->prepare("SELECT cod_movimiento FROM factura_salida WHERE cod_movimiento = ? FOR UPDATE");
                // envia los datos a la consulta
                $stmt->bind_param("i", $codigo);
                // ejecuta la consulta
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    // cierra la consulta
                    $stmt->close();
                    throw new Exception("El movimiento que intentas actualizar no existe.");
                }
                // cierra la consulta
                $stmt->close();

                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("SELECT cod_producto, precio FROM mov_salida WHERE cod_movimiento = ?");
                // envia los datos a la consulta
                $stmt->bind_param("i", $codigo);
                // ejecuta la consulta
                $stmt->execute();
                // obtiene el resultado de la consulta
                $resOriginal = $stmt->get_result();
                // recorre cada fila del resultado
                while ($o = $resOriginal->fetch_assoc()) {
                    $preciosOriginales[(int)$o["cod_producto"]] = (float)$o["precio"];
                }
                // cierra la consulta
                $stmt->close();

                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("UPDATE factura_salida SET cedula_cliente = ?, nota = ? WHERE cod_movimiento = ?");
                $stmt->bind_param("isi", $cedula, $nota, $codigo);
                // ejecuta la consulta
                $stmt->execute();
                // cierra la consulta
                $stmt->close();

                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("DELETE FROM mov_salida WHERE cod_movimiento = ?");
                // envia los datos a la consulta
                $stmt->bind_param("i", $codigo);
                // ejecuta la consulta
                $stmt->execute();
                // cierra la consulta
                $stmt->close();
            } else {
                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("SELECT COALESCE(MAX(cod_movimiento), 0) + 1 AS siguiente FROM factura_salida FOR UPDATE");
                // ejecuta la consulta
                $stmt->execute();
                // el codigo nuevo es el siguiente al ultimo (1, 2, 3, 4...) sin saltos
                $codigo = (int)$stmt->get_result()->fetch_assoc()["siguiente"];
                // cierra la consulta
                $stmt->close();

                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("INSERT INTO factura_salida (cod_movimiento, fecha, nota, cedula_cliente) VALUES (?, CURDATE(), ?, ?)");
                $stmt->bind_param("isi", $codigo, $nota, $cedula);
                // ejecuta la consulta
                $stmt->execute();
                // cierra la consulta
                $stmt->close();
            }

            // fecha de la factura, se repite en cada producto de mov_salida
            $stmt = $conn->prepare("SELECT fecha FROM factura_salida WHERE cod_movimiento = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $codigo);
            // ejecuta la consulta
            $stmt->execute();
            $fechaMov = $stmt->get_result()->fetch_assoc()["fecha"];
            // cierra la consulta
            $stmt->close();

            // guarda cada producto en mov_salida
            $stmtDetalle = $conn->prepare(
                "INSERT INTO mov_salida (cod_movimiento, fecha, precio, cantidad, cod_producto, nombre_producto, cedula_cliente)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            // si un producto se repite en la lista, se suma la cantidad en la misma fila
            $stmtSumar = $conn->prepare("UPDATE mov_salida SET cantidad = cantidad + ? WHERE cod_movimiento = ? AND cod_producto = ?");
            // esta variable guarda los productos que ya tienen su fila
            $yaGuardados = [];

            // busca el nombre y el precio reales del producto en la base de datos
            $consultaProducto = $conn->prepare("SELECT nombre, precio FROM producto WHERE cod_producto = ? FOR UPDATE");
            // productos que cambian de stock (los que tenia y los nuevos)
            $codigosAfectados = array_keys($preciosOriginales);

            // stock = entradas - salidas
            $consultaStock = $conn->prepare(
                "SELECT COALESCE((SELECT SUM(cantidad) FROM mov_entrada WHERE cod_producto = ?), 0) -
                        COALESCE((SELECT SUM(ms.cantidad) FROM mov_salida ms
                                  INNER JOIN factura_salida fs ON fs.cod_movimiento = ms.cod_movimiento
                                  WHERE ms.cod_producto = ?), 0) AS disponible"
            );

            for ($i = 0; $i < count($codigos); $i++) {
                $codP = filter_var($codigos[$i], FILTER_VALIDATE_INT);
                $cantP = filter_var($cantidades[$i], FILTER_VALIDATE_INT);

                if (!$codP || $codP <= 0 || !$cantP || $cantP <= 0) {
                    throw new Exception("Hay campos inválidos en la lista de productos.");
                }

                // el nombre y el precio se toman de la tabla producto
                $consultaProducto->bind_param("i", $codP);
                $consultaProducto->execute();
                $productoBD = $consultaProducto->get_result()->fetch_assoc();
                if (!$productoBD) {
                    throw new Exception("El producto con código {$codP} no existe.");
                }
                if ($productoBD["precio"] === null) {
                    throw new Exception("El producto '{$productoBD["nombre"]}' no tiene precio registrado.");
                }
                $nomP = $productoBD["nombre"];
                // si el producto ya estaba en el movimiento conserva su precio
                $preP = $preciosOriginales[$codP] ?? (float)$productoBD["precio"];
                $codigosAfectados[] = $codP;

                $consultaStock->bind_param("ii", $codP, $codP);
                $consultaStock->execute();
                $disponible = (int)($consultaStock->get_result()->fetch_assoc()["disponible"] ?? 0);

                // no deja vender mas de lo que hay
                if ($cantP > $disponible) {
                    throw new Exception("Stock insuficiente para '{$nomP}'. Disponibles: {$disponible}.");
                }

                if (isset($yaGuardados[$codP])) {
                    // el producto ya estaba en la lista: se suma la cantidad
                    $stmtSumar->bind_param("iii", $cantP, $codigo, $codP);
                    $stmtSumar->execute();
                } else {
                    // envia los datos a la consulta
                    $stmtDetalle->bind_param("isdiisi", $codigo, $fechaMov, $preP, $cantP, $codP, $nomP, $cedula);
                    // ejecuta la consulta
                    $stmtDetalle->execute();
                    $yaGuardados[$codP] = true;
                }
            }

            $consultaStock->close();
            $consultaProducto->close();
            // cierra la consulta
            $stmtDetalle->close();
            $stmtSumar->close();

            // guarda en la factura la cantidad de productos y el total de la venta
            $stmtTotales = $conn->prepare(
                "SELECT COUNT(*) AS productos, COALESCE(SUM(precio * cantidad), 0) AS total_venta
                 FROM mov_salida WHERE cod_movimiento = ?"
            );
            $stmtTotales->bind_param("i", $codigo);
            $stmtTotales->execute();
            $datosTotales = $stmtTotales->get_result()->fetch_assoc();
            $stmtTotales->close();

            $productosFactura = (int)($datosTotales["productos"] ?? 0);
            $totalFactura = (float)($datosTotales["total_venta"] ?? 0);
            $stmtActualizarFactura = $conn->prepare(
                "UPDATE factura_salida SET productos = ?, total_venta = ? WHERE cod_movimiento = ?"
            );
            $stmtActualizarFactura->bind_param("idi", $productosFactura, $totalFactura, $codigo);
            $stmtActualizarFactura->execute();
            $stmtActualizarFactura->close();

            // actualiza el stock de los productos en inventario
            recalcularInventario($conn, $codigosAfectados);

            $conn->commit();
            // redirige a mov_salida.php
            header("Location: mov_salida.php?guardado=" . $codigo);
            exit();
        } catch (Exception $e) {
            if ($transaccion) $conn->rollback();
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

// mensajes que llegan por la url
if (isset($_GET["mensaje"]) && $_GET["mensaje"] === "eliminado") {
    $mensaje = "Movimiento eliminado correctamente.";
    $tipoMensaje = "ok";
}
if (isset($_GET["mensaje"]) && $_GET["mensaje"] === "error_eliminar") {
    $mensaje = "No se pudo eliminar el movimiento. Verifica que no tenga registros relacionados.";
    $tipoMensaje = "error";
}
if (isset($_GET["mensaje"]) && $_GET["mensaje"] === "sin_permiso") {
    $mensaje = "No tienes permiso para eliminar movimientos.";
    $tipoMensaje = "error";
}
if (isset($_GET["guardado"]) && $tipoMensaje !== "error") {
    // datos que llegan por la url
    $codigoGuardado = (int)$_GET["guardado"];
    $mensaje = "Movimiento #{$codigoGuardado} procesado exitosamente.";
    $tipoMensaje = "ok";
}

// consultas de las tablas del admin
if ($esAdmin) {
    // movimientos detallados para la primera tabla
    $sqlResumen = "SELECT m.cod_movimiento, m.cod_producto, m.nombre_producto, m.precio,
                          m.cantidad, m.fecha, m.cedula_cliente
                   FROM mov_salida m
                   INNER JOIN factura_salida f ON f.cod_movimiento = m.cod_movimiento";

    if ($busqueda !== "") {
        $sqlResumen .= " WHERE CAST(m.cod_movimiento AS CHAR) LIKE ?
                         OR CAST(m.cod_producto AS CHAR) LIKE ?
                         OR m.nombre_producto LIKE ?
                         OR CAST(m.cedula_cliente AS CHAR) LIKE ?
                         OR m.fecha LIKE ?";
    }

    $sqlResumen .= " ORDER BY m.cod_movimiento DESC, m.cod_producto ASC";
    $stmtResumen = $conn->prepare($sqlResumen);

    if ($busqueda !== "") {
        $f = "%" . $busqueda . "%";
        $stmtResumen->bind_param("sssss", $f, $f, $f, $f, $f);
    }

    $stmtResumen->execute();
    $resultadoResumen = $stmtResumen->get_result();

    // lista de facturas con su total
    $sqlFacturas = "SELECT f.cod_movimiento, f.fecha, f.nota, f.cedula_cliente,
                           f.productos AS numero_productos, f.total_venta AS total,
                           c.nombre AS nombre_cliente
                    FROM factura_salida f
                    LEFT JOIN cliente c ON f.cedula_cliente = c.cedula_cliente";

    if ($busqueda !== "") {
        // agrega mas condiciones a la consulta sql
        $sqlFacturas .= " WHERE CAST(f.cod_movimiento AS CHAR) LIKE ?
                             OR CAST(f.cedula_cliente AS CHAR) LIKE ?
                             OR f.fecha LIKE ?
                             OR f.nota LIKE ?
                             OR c.nombre LIKE ?";
    }

    // ordena las facturas desde la mas reciente
    $sqlFacturas .= " ORDER BY f.cod_movimiento DESC";

    // prepara la consulta sql de forma segura
    $stmtLista = $conn->prepare($sqlFacturas);
    if ($busqueda !== "") {
        $f = "%" . $busqueda . "%";
        // envia los datos a la consulta
        $stmtLista->bind_param("sssss", $f, $f, $f, $f, $f);
    }
    // ejecuta la consulta
    $stmtLista->execute();
    // obtiene el resultado de la consulta
    $resultadoFacturas = $stmtLista->get_result();
}

$mostrarForm = (!$esAdmin || $factura_editar !== null);
$codigoRecienGuardado = (!$esAdmin && isset($_GET["guardado"])) ? (int)$_GET["guardado"] : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimiento de Salida | La Granja</title>
    <!-- hoja de estilos de la pagina -->
    <link rel="stylesheet" href="../css/mov_salida.css?v=5">
</head>
<!-- cuerpo de la pagina -->
<body class="pagina-salida">

<!-- encabezado de la pagina -->
<header class="encabezado">
    <button type="button" class="boton-menu" id="abrirMenu" aria-label="Abrir menú lateral">
        <?= icono("menu") ?>
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
        <span class="usuario-inicial"><?= escapar($inicial) ?></span>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>
</header>

<!-- fondo que oscurece la pagina cuando el menu esta abierto -->
<div class="fondo-menu" id="fondoMenu"></div>

<!-- menu lateral -->
<aside class="menu-lateral" id="menuLateral">
    <div class="menu-lateral-encabezado">
        <div class="menu-lateral-marca">
            <img src="../img/logo.png" alt="Logo de La Granja">
            <div>
                <h2>Supermercado</h2>
                <p>La Granja</p>
            </div>
        </div>
        <button type="button" class="boton-cerrar" id="cerrarMenu" aria-label="Cerrar menú lateral">
            <?= icono("cerrar") ?>
        </button>
    </div>

    <!-- datos del usuario en el menu lateral -->
    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= escapar($inicial) ?></div>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>

    <!-- enlaces de navegacion -->
    <nav class="navegacion">
        <p class="titulo-navegacion">MENÚ PRINCIPAL</p>
        <div class="lista-navegacion">
            <a href="menu.php" class="enlace-menu">
                <span class="caja-icono"><?= icono("inicio") ?></span>
                <span>Inicio</span>
            </a>
            <a href="cliente.php" class="enlace-menu">
                <span class="caja-icono"><?= icono("cliente") ?></span>
                <span>Cliente</span>
            </a>
            <a href="mov_salida.php" class="enlace-menu activo" aria-current="page">
                <span class="caja-icono"><?= icono("salida") ?></span>
                <span>Movimiento de salida</span>
            </a>
            <?php if ($esAdmin): ?>
                <a href="producto.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("producto") ?></span>
                    <span>Productos</span>
                </a>
                <a href="mov_entrada.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("entrada") ?></span>
                    <span>Movimiento de entrada</span>
                </a>
                <a href="proveedor.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("proveedor") ?></span>
                    <span>Proveedor</span>
                </a>
                <a href="inventario.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("inventario") ?></span>
                    <span>Inventario</span>
                </a>
                <a href="reportes.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("reportes") ?></span>
                    <span>Reportes</span>
                </a>
                <a href="edita_usuario.php" class="enlace-menu">
                    <span class="caja-icono"><?= icono("usuario") ?></span>
                    <span>Modificar usuario</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- formulario para cerrar sesion -->
    <form action="cerrar_sesion.php" method="POST" class="form-salir">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja"><?= icono("salir") ?></span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<!-- contenido principal -->
<main class="contenido">
    <!-- titulo de la pagina -->
    <section class="titulo-pagina">
        <span class="titulo-icono"><?= icono("salida") ?></span>
        <div>
            <h2>Movimiento de Salida</h2>
            <p><?= $esAdmin ? "Consulta, edita o elimina los movimientos de salida registrados." : "Registra salidas de productos e imprime facturas de venta." ?></p>
        </div>
    </section>

    <!-- mensaje de aviso -->
    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- formulario de salida -->
    <?php if ($mostrarForm): ?>
        <!-- panel de contenido -->
        <section class="panel">
            <h3 class="panel-titulo"><?= $factura_editar ? "Editar Movimiento #".escapar($factura_editar["cod_movimiento"]) : "Registrar Salida de Productos" ?></h3>

            <!-- formulario -->
            <form method="POST" id="formularioFactura" action="mov_salida.php" class="formulario">
                <input type="hidden" name="accion" value="<?= $factura_editar ? "actualizar" : "guardar" ?>">
                <?php if ($factura_editar): ?>
                    <input type="hidden" name="cod_movimiento" value="<?= escapar($factura_editar["cod_movimiento"]) ?>">
                <?php endif; ?>

                <div class="campo">
                    <label>Código de movimiento</label>
                    <input type="text" readonly value="<?= $factura_editar ? escapar($factura_editar["cod_movimiento"]) : "Automático al guardar" ?>">
                </div>

                <div class="campo">
                    <label>Fecha de emisión</label>
                    <input type="date" readonly value="<?= $factura_editar ? escapar($factura_editar["fecha"]) : date("Y-m-d") ?>">
                </div>

                <div class="campo campo-ancho">
                    <label for="nota">Nota</label>
                    <textarea id="nota" name="nota" rows="3" placeholder="Nota u observación (opcional)"><?= escapar($factura_editar["nota"] ?? "") ?></textarea>
                </div>

                <div class="campo campo-ancho">
                    <label for="cliente_busqueda">Cédula o Nombre del Cliente <span class="obligatorio">*</span></label>
                    <input type="text" id="cliente_busqueda" list="lista_clientes" autocomplete="off" placeholder="Escriba para buscar cliente..." value="<?= escapar($clienteSeleccionadoTexto) ?>" required>
                    <datalist id="lista_clientes"></datalist>
                    <input type="hidden" name="cedula_cliente" id="cedula_cliente" value="<?= escapar($cedulaSeleccionada) ?>">
                    <p id="estado_cliente" class="estado-texto"></p>
                </div>

                <div class="campo campo-ancho">
                    <label>Detalle de Productos <span class="obligatorio">*</span></label>
                    <!-- sugerencias de productos por codigo o nombre -->
                    <datalist id="lista_productos"></datalist>
                    <div class="tabla-contenedor">
                        <!-- tabla con los registros -->
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
                                            <input class="codigo" type="number" name="cod_producto[]" min="1" required value="<?= escapar($detalle["cod_producto"]) ?>">
                                        </td>
                                        <td>
                                            <input class="nombre-producto" type="text" name="nombre_producto[]" list="lista_productos" autocomplete="off" placeholder="Escriba código o nombre..." required value="<?= escapar($detalle["nombre_producto"]) ?>">
                                        </td>
                                        <td>
                                            <input class="precio" type="number" name="precio[]" min="0" step="0.01" readonly required value="<?= escapar($detalle["precio"]) ?>">
                                        </td>
                                        <td>
                                            <input class="cantidad" type="number" name="cantidad[]" min="1" step="1" required value="<?= escapar($detalle["cantidad"]) ?>">
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

                <!-- botones del formulario -->
                <div class="botones campo-ancho">
                    <button type="button" class="boton boton-secundario" onclick="agregarProducto()">+ Agregar producto</button>
                    <button type="submit" class="boton boton-principal"><?= $factura_editar ? "Actualizar Movimiento" : "Guardar Movimiento" ?></button>
                    <?php if ($codigoRecienGuardado > 0): ?>
                        <button type="button" class="boton boton-factura" onclick="generarFactura(<?= $codigoRecienGuardado ?>)">Generar Factura</button>
                    <?php endif; ?>
                    <?php if ($factura_editar): ?>
                        <a href="mov_salida.php" class="boton boton-secundario">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- tablas del admin -->
    <?php if ($esAdmin): ?>
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
                                    <td class="derecha"><?= formatoMoneda($prod["precio"]) ?></td>
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

        <!-- panel de contenido -->
        <section class="panel">
            <h3 class="panel-titulo">Historial de Salidas / Facturas</h3>
            <div class="buscador">
                <input type="search" class="buscar-tabla" data-tabla="tablaFacturas" placeholder="Buscar por código, cédula, cliente o fecha...">
            </div>
            <div class="tabla-contenedor tabla-scroll">
                <!-- tabla con los registros -->
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
                                    <td class="derecha"><?= formatoMoneda($fila["total"]) ?></td>
                                    <td class="tabla-acciones">
                                        <button type="button" class="boton boton-chico boton-factura" onclick="generarFactura(<?= $fila['cod_movimiento'] ?>)">Factura</button>
                                        <a href="mov_salida.php?editar=<?= $fila['cod_movimiento'] ?>" class="boton boton-chico boton-editar">Editar</a>
                                        <form action="mov_salida.php" method="POST" class="form-eliminar">
                                            <input type="hidden" name="cod_movimiento" value="<?= escapar($fila["cod_movimiento"]) ?>">
                                            <button type="submit" name="eliminar_movimiento" value="1" class="boton boton-chico boton-eliminar">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="tabla-vacia">No se encontraron registros.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php if ($esAdmin): ?>
    <!-- ventana para confirmar la eliminacion, igual a la de clientes -->
    <div class="modal" id="modalEliminar" hidden>
        <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
            <div class="modal__icono modal__icono--aviso">!</div>
            <h3 class="modal__titulo" id="tituloEliminar">Eliminar movimiento</h3>
            <p class="modal__texto" id="textoEliminar">¿Seguro que deseas eliminar este movimiento?</p>
            <div class="modal__botones">
                <button type="button" class="boton boton-secundario" id="cancelarEliminar">Cancelar</button>
                <button type="button" class="boton boton-eliminar" id="confirmarEliminar">Eliminar</button>
            </div>
        </div>
    </div>
<?php endif; ?>
<iframe name="marcoFactura" id="marcoFactura" title="Factura" hidden></iframe>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <!-- enlaces de ayuda -->
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.</p>
            <a href="ayuda.php" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
            <a href="ayuda.php#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
            <a href="ayuda.php#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
            <a href="ayuda.php#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
            <a href="ayuda.php#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
        </section>

        <!-- informacion del sistema -->
        <section class="footer-informacion">
            <h4>INFORMACIÓN DEL SISTEMA</h4>
            <p><strong>Plataforma administrativa</strong><br>Herramienta para organizar y controlar los productos del supermercado.</p>
            <p><strong>Gestión de clientes</strong><br>Registro, consulta y actualización de la información de los clientes.</p>
            <p><strong>Control de movimientos</strong><br>Seguimiento de las entradas y salidas de mercancía.</p>
            <div class="footer-nota">
                <strong>Uso del sistema</strong>
                <p>Acceso destinado a empleados y administradores autorizados.</p>
            </div>
        </section>
    </div>

    <!-- parte inferior del pie de pagina -->
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>


<script src = "../js/mov_salida.js"></script>

</body>
</html>