<?php
/* 
   permisos
    */

session_start();

// Si no ha iniciado sesion, vuelve al inicio.
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["cargo"])) {
    header("Location: index.html");
    exit();
}

// Cargo: "1" = administrador, "2" = empleado.
$cargo = (string)$_SESSION["cargo"];

// Si el cargo no es 1 ni 2, va al menu.
if ($cargo != "1" && $cargo != "2") {
    header("Location: menu.php");
    exit();
}

// true si es administrador, false si es empleado.
$esAdmin = ($cargo == "1");


/* 
   conexion base de datos
  */

// Los errores de MySQL se vuelven excepciones (para el try/catch).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include("conexion.php");

// La conexion puede llamarse $conn o $conexion.
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}

if (!isset($conn)) {
    die("Error: no se pudo conectar con la base de datos.");
}

// Para que se vean bien las tildes y la ñ.
$conn->set_charset("utf8mb4");


/* =====================================================
   2. FUNCIONES
   ===================================================== */

// Limpia un texto antes de mostrarlo en pantalla.
function e($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

// Muestra un numero como dinero. Ejemplo: 1500 -> $1.500,00
function formatoMoneda($valor)
{
    return "$" . number_format((float)$valor, 2, ",", ".");
}

// Muestra la factura de un movimiento, lista para imprimir.
function mostrarFactura($conn, $codigo)
{
    // Buscamos los datos de la factura y del cliente.
    $stmt = $conn->prepare(
        "SELECT f.cod_movimiento, f.fecha, f.cedula_cliente,
                c.nombre, c.telefono, c.email, c.direccion
         FROM factura_salida f
         LEFT JOIN cliente c ON f.cedula_cliente = c.cedula_cliente
         WHERE f.cod_movimiento = ?"
    );
    $stmt->bind_param("i", $codigo);
    $stmt->execute();
    $factura = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $detalles = [];
    $total = 0;

    // Si la factura existe, buscamos sus productos y sumamos el total.
    if ($factura) {
        $stmt = $conn->prepare(
            "SELECT cod_producto, nombre_producto, precio, cantidad
             FROM detalle_salida
             WHERE cod_movimiento = ?
             ORDER BY id_detalle"
        );
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $resDetalles = $stmt->get_result();

        while ($detalle = $resDetalles->fetch_assoc()) {
            $detalles[] = $detalle;
            $total = $total + ($detalle["precio"] * $detalle["cantidad"]);
        }

        $stmt->close();
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Factura <?php echo e($codigo); ?></title>
    </head>
    <body>

        <?php if (!$factura): ?>

            <p>La factura no existe.</p>

            <p class="no-imprimir">
                <button type="button" onclick="window.close()">Cerrar</button>
            </p>

        <?php else: ?>

            <div class="centro">
                <h2>Supermercado La Granja</h2>
                <h3>FACTURA DE VENTA N° <?php echo e($factura["cod_movimiento"]); ?></h3>
            </div>

            <p>
                <strong>Fecha:</strong> <?php echo e($factura["fecha"]); ?><br>
                <strong>Atendió:</strong> <?php echo e($_SESSION["usuario"]); ?>
            </p>

            <p>
                <strong>Cliente:</strong> <?php echo e($factura["nombre"] ?? "Sin nombre"); ?><br>
                <strong>Cédula:</strong> <?php echo e($factura["cedula_cliente"]); ?><br>
                <strong>Teléfono:</strong> <?php echo e($factura["telefono"] ?? ""); ?><br>
                <strong>Email:</strong> <?php echo e($factura["email"] ?? ""); ?><br>
                <strong>Dirección:</strong> <?php echo e($factura["direccion"] ?? ""); ?>
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th class="derecha">Precio unitario</th>
                        <th class="derecha">Cantidad</th>
                        <th class="derecha">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?php echo e($detalle["cod_producto"]); ?></td>
                            <td><?php echo e($detalle["nombre_producto"]); ?></td>
                            <td class="derecha"><?php echo formatoMoneda($detalle["precio"]); ?></td>
                            <td class="derecha"><?php echo e($detalle["cantidad"]); ?></td>
                            <td class="derecha">
                                <?php echo formatoMoneda($detalle["precio"] * $detalle["cantidad"]); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="derecha total">TOTAL</td>
                        <td class="derecha total"><?php echo formatoMoneda($total); ?></td>
                    </tr>
                </tfoot>
            </table>

            <p class="centro">¡Gracias por su compra!</p>

            <p class="centro no-imprimir">
                <button type="button" onclick="window.print()">Imprimir factura</button>
                <button type="button" onclick="window.close()">Cerrar</button>
            </p>

        <?php endif; ?>

        <script>
            // Si la factura se carga dentro del marco oculto, se imprime sola.
            window.addEventListener("load", function () {
                if (window.frameElement) {
                    setTimeout(function () {
                        window.print();
                    }, 300);
                }
            });
        </script>

    </body>
    </html>
    <?php
}


/* 
   3. VARIABLES INICIALES
    */

$mensaje = "";                    // Texto del aviso.
$tipoMensaje = "";                // "bien" (verde) o "error" (rojo).
$factura_editar = null;           // Factura que se esta editando.
$detalles_editar = [];            // Productos de la factura que se edita.
$cedulaSeleccionada = "";         // Cedula del cliente elegido.
$clienteSeleccionadoTexto = "";   // Texto "cedula - nombre".

// Texto del buscador del administrador.
$busqueda = "";
if (isset($_GET["buscar"])) {
    $busqueda = trim($_GET["buscar"]);
}


/* 
   autocompletar productos
   */

if (isset($_GET["producto_codigo"])) {

    header("Content-Type: application/json; charset=utf-8");

    try {
        $codigoProducto = trim($_GET["producto_codigo"]);

        $stmt = $conn->prepare(
            "SELECT cod_producto, nombre, precio
             FROM producto
             WHERE cod_producto = ?"
        );
        $stmt->bind_param("s", $codigoProducto);
        $stmt->execute();
        $producto = $stmt->get_result()->fetch_assoc();
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

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(["existe" => false]);
    }

    exit(); // No hay que dibujar la pagina.
}


/* 
   busca clientes
  */

if (isset($_GET["buscar_cliente"])) {

    header("Content-Type: application/json; charset=utf-8");

    try {
        $textoCliente = trim($_GET["buscar_cliente"]);

        // Si no escribio nada, devolvemos una lista vacia.
        if ($textoCliente == "") {
            echo json_encode([]);
            exit();
        }

        // Si el texto es "123 - Ana", la cedula es lo de antes del " - ".
        $parteCedula = explode(" - ", $textoCliente)[0];

        // Los % significan "que contenga este texto".
        $filtro = "%" . $textoCliente . "%";
        $filtroCedula = "%" . trim($parteCedula) . "%";

        $stmt = $conn->prepare(
            "SELECT cedula_cliente, nombre
             FROM cliente
             WHERE CAST(cedula_cliente AS CHAR) LIKE ?
                OR nombre LIKE ?
             ORDER BY
                CASE
                    WHEN CAST(cedula_cliente AS CHAR) = ? THEN 0
                    WHEN nombre = ? THEN 0
                    ELSE 1
                END,
                nombre ASC
             LIMIT 10"
        );
        $stmt->bind_param("ssss", $filtroCedula, $filtro, $parteCedula, $textoCliente);
        $stmt->execute();
        $resClientes = $stmt->get_result();

        $lista = [];

        while ($cliente = $resClientes->fetch_assoc()) {
            $lista[] = [
                "cedula" => $cliente["cedula_cliente"],
                "nombre" => $cliente["nombre"]
            ];
        }

        $stmt->close();

        echo json_encode($lista, JSON_UNESCAPED_UNICODE);

    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode([]);
    }

    exit();
}


/* 
   generar factura
   */

if (isset($_GET["factura"])) {

    try {
        mostrarFactura($conn, intval($_GET["factura"]));

    } catch (Exception $ex) {
        echo "No se pudo generar la factura: " . e($ex->getMessage());
    }

    exit(); // No hay que dibujar el resto de la pagina.
}


/* 
  eliminar un movimiento 
   */

if (isset($_GET["borrar"])) {

    if (!$esAdmin) {
        $mensaje = "No tiene permiso para eliminar movimientos.";
        $tipoMensaje = "error";

    } else {
        $codigo = intval($_GET["borrar"]);
        $transaccionIniciada = false;

        try {
            // Con la transaccion se borra TODO o no se borra NADA.
            $conn->begin_transaction();
            $transaccionIniciada = true;

            // Primero los productos de la factura.
            $stmt = $conn->prepare("DELETE FROM detalle_salida WHERE cod_movimiento = ?");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            $stmt->close();

            // Despues la factura.
            $stmt = $conn->prepare("DELETE FROM factura_salida WHERE cod_movimiento = ?");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            $eliminado = $stmt->affected_rows;
            $stmt->close();

            if ($eliminado == 0) {
                throw new Exception("El movimiento no existe.");
            }

            $conn->commit();
            $transaccionIniciada = false;

            header("Location: mov_salida.php?mensaje=eliminado");
            exit();

        } catch (Exception $ex) {
            if ($transaccionIniciada) {
                $conn->rollback();
            }

            $mensaje = "Error al eliminar: " . $ex->getMessage();
            $tipoMensaje = "error";
        }
    }
}

// Aviso de eliminado (se muestra en rojo).
if (isset($_GET["mensaje"]) && $_GET["mensaje"] == "eliminado" && $esAdmin) {
    $mensaje = "Movimiento eliminado correctamente.";
    $tipoMensaje = "error";
}


/* 
  editar movimientos
    */

if (isset($_GET["editar"])) {

    if (!$esAdmin) {
        $mensaje = "No tiene permiso para editar movimientos.";
        $tipoMensaje = "error";

    } else {
        $codigo = intval($_GET["editar"]);

        // Buscamos la factura.
        $stmt = $conn->prepare("SELECT * FROM factura_salida WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $factura_editar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($factura_editar) {

            // Buscamos los productos de la factura.
            $stmt = $conn->prepare(
                "SELECT * FROM detalle_salida
                 WHERE cod_movimiento = ?
                 ORDER BY id_detalle"
            );
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            $resDetalles = $stmt->get_result();

            while ($detalle = $resDetalles->fetch_assoc()) {
                $detalles_editar[] = $detalle;
            }

            $stmt->close();

            // Buscamos el cliente para mostrarlo en el formulario.
            $cedulaSeleccionada = $factura_editar["cedula_cliente"];

            $stmt = $conn->prepare(
                "SELECT cedula_cliente, nombre FROM cliente WHERE cedula_cliente = ?"
            );
            $stmt->bind_param("s", $cedulaSeleccionada);
            $stmt->execute();
            $clienteActual = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($clienteActual) {
                $clienteSeleccionadoTexto = $clienteActual["cedula_cliente"] . " - " . $clienteActual["nombre"];
            }

        } else {
            $mensaje = "El movimiento que intenta editar no existe.";
            $tipoMensaje = "error";
        }
    }
}


/* 
  guardar y actualizar
    */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Datos que llegan del formulario.
    $accion = "";
    if (isset($_POST["accion"])) {
        $accion = $_POST["accion"];
    }

    $codigo = 0;
    if (isset($_POST["cod_movimiento"])) {
        $codigo = intval($_POST["cod_movimiento"]);
    }

    $cedula = "";
    if (isset($_POST["cedula_cliente"])) {
        $cedula = trim($_POST["cedula_cliente"]);
    }

    // Listas de productos (una posicion por cada fila).
    $codigos = [];
    if (isset($_POST["cod_producto"])) {
        $codigos = $_POST["cod_producto"];
    }

    $nombres = [];
    if (isset($_POST["nombre_producto"])) {
        $nombres = $_POST["nombre_producto"];
    }

    $precios = [];
    if (isset($_POST["precio"])) {
        $precios = $_POST["precio"];
    }

    $cantidades = [];
    if (isset($_POST["cantidad"])) {
        $cantidades = $_POST["cantidad"];
    }

    // Revisamos que los datos basicos esten completos.
    if (
        !is_array($codigos) ||
        !is_array($nombres) ||
        !is_array($precios) ||
        !is_array($cantidades) ||
        $cedula == "" ||
        count($codigos) == 0 ||
        count($codigos) != count($nombres) ||
        count($codigos) != count($precios) ||
        count($codigos) != count($cantidades)
    ) {
        $mensaje = "Seleccione un cliente registrado y agregue al menos un producto.";
        $tipoMensaje = "error";

    } else {
        $transaccionIniciada = false;

        try {
            // Permisos:
            // - Solo el administrador actualiza.
            // - Solo el empleado guarda movimientos nuevos.
            if ($accion == "actualizar" && !$esAdmin) {
                throw new Exception("No tiene permiso para actualizar movimientos.");
            }

            if ($accion == "guardar" && $esAdmin) {
                throw new Exception("El administrador no registra salidas nuevas.");
            }

            
            $conn->begin_transaction();
            $transaccionIniciada = true;

            //  Comprobar que el cliente exista 
            $stmt = $conn->prepare(
                "SELECT cedula_cliente FROM cliente
                 WHERE CAST(cedula_cliente AS CHAR) = ?"
            );
            $stmt->bind_param("s", $cedula);
            $stmt->execute();
            $existeCliente = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$existeCliente) {
                throw new Exception(
                    "La cédula no corresponde a un cliente registrado. " .
                    "Seleccione el cliente de las sugerencias."
                );
            }

            // actualizar una factura que ya existe
            if ($accion == "actualizar") {

                if ($codigo <= 0) {
                    throw new Exception("Código de movimiento inválido.");
                }

                // Comprobamos que la factura exista.
                $stmt = $conn->prepare(
                    "SELECT cod_movimiento FROM factura_salida WHERE cod_movimiento = ?"
                );
                $stmt->bind_param("i", $codigo);
                $stmt->execute();
                $existeMovimiento = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if (!$existeMovimiento) {
                    throw new Exception("El movimiento no existe.");
                }

                // Cambiamos el cliente de la factura.
                $stmt = $conn->prepare(
                    "UPDATE factura_salida
                     SET cedula_cliente = ?, nota = ''
                     WHERE cod_movimiento = ?"
                );
                $stmt->bind_param("si", $cedula, $codigo);
                $stmt->execute();
                $stmt->close();

                // Borramos los productos anteriores para guardar los nuevos.
                // Asi el stock no cuenta dos veces lo que se edita.
                $stmt = $conn->prepare("DELETE FROM detalle_salida WHERE cod_movimiento = ?");
                $stmt->bind_param("i", $codigo);
                $stmt->execute();
                $stmt->close();

            // --- GUARDAR una factura nueva ---
            } elseif ($accion == "guardar") {

                // Creamos la factura con la fecha de hoy.
                $stmt = $conn->prepare(
                    "INSERT INTO factura_salida (fecha, nota, cedula_cliente)
                     VALUES (CURDATE(), '', ?)"
                );
                $stmt->bind_param("s", $cedula);
                $stmt->execute();

                // Codigo que MySQL le asigno automaticamente.
                $codigo = $conn->insert_id;
                $stmt->close();

                if ($codigo <= 0) {
                    throw new Exception("No se pudo generar el código del movimiento.");
                }

            } else {
                throw new Exception("La acción solicitada no es válida.");
            }

            // --- Consulta para guardar cada producto ---
            $stmtDetalle = $conn->prepare(
                "INSERT INTO detalle_salida
                 (cod_movimiento, cod_producto, nombre_producto, precio, cantidad)
                 VALUES (?, ?, ?, ?, ?)"
            );

            // --- Consulta para saber cuantas unidades hay disponibles ---
            // (todo lo que entro) - (todo lo que salio)
            $consultaStock = $conn->prepare(
                "SELECT
                    COALESCE((
                        SELECT SUM(me.cantidad)
                        FROM mov_entrada me
                        WHERE me.cod_producto = ?
                    ), 0)
                    -
                    COALESCE((
                        SELECT SUM(ds.cantidad)
                        FROM detalle_salida ds
                        INNER JOIN factura_salida fs
                            ON fs.cod_movimiento = ds.cod_movimiento
                        WHERE ds.cod_producto = ?
                    ), 0) AS disponible"
            );

            // --- Recorremos cada producto del formulario ---
            for ($i = 0; $i < count($codigos); $i++) {

                // Convertimos a numero entero (false si no es valido).
                $codProducto = filter_var($codigos[$i], FILTER_VALIDATE_INT);
                $cantidad = filter_var($cantidades[$i], FILTER_VALIDATE_INT);

                $nombre = trim((string)$nombres[$i]);
                $precioTexto = trim((string)$precios[$i]);

                // Validamos los datos del producto.
                if (
                    $codProducto === false || $codProducto <= 0 ||
                    $nombre == "" ||
                    $precioTexto == "" || !is_numeric($precioTexto) ||
                    (float)$precioTexto < 0 ||
                    $cantidad === false || $cantidad <= 0
                ) {
                    throw new Exception("Revise el código, nombre, precio y cantidad de los productos.");
                }

                // Comprobamos que el producto exista.
                $verificar = $conn->prepare(
                    "SELECT cod_producto, nombre FROM producto WHERE cod_producto = ?"
                );
                $verificar->bind_param("i", $codProducto);
                $verificar->execute();
                $productoExiste = $verificar->get_result()->fetch_assoc();
                $verificar->close();

                if (!$productoExiste) {
                    throw new Exception("No existe el producto con código " . $codProducto . ".");
                }

                // Calculamos cuantas unidades hay disponibles.
                $consultaStock->bind_param("ii", $codProducto, $codProducto);
                $consultaStock->execute();
                $datosStock = $consultaStock->get_result()->fetch_assoc();
                $disponible = (int)$datosStock["disponible"];

                // Si no hay nada disponible, no se puede sacar.
                if ($disponible <= 0) {
                    throw new Exception(
                        "No se puede registrar la salida de " . $productoExiste["nombre"] .
                        ": no tiene existencias disponibles. " .
                        "Registre primero una entrada del producto."
                    );
                }

                // Si piden mas de lo que hay, tampoco.
                if ($cantidad > $disponible) {
                    throw new Exception(
                        "No hay suficientes existencias de " . $productoExiste["nombre"] .
                        ". Disponibles: " . $disponible .
                        ". Cantidad solicitada: " . $cantidad . "."
                    );
                }

                // Guardamos el producto en la factura.
                $precio = (float)$precioTexto;

                // i = entero, s = texto, d = decimal.
                $stmtDetalle->bind_param("iisdi", $codigo, $codProducto, $nombre, $precio, $cantidad);
                $stmtDetalle->execute();
            }

            $consultaStock->close();
            $stmtDetalle->close();

            //  confirmamos los cambios.
            $conn->commit();
            $transaccionIniciada = false;

            header("Location: mov_salida.php?guardado=" . urlencode((string)$codigo));
            exit();

        } catch (Exception $ex) {
            // Algo fallo: deshacemos todo.
            if ($transaccionIniciada) {
                $conn->rollback();
            }

            $mensaje = "No se pudo guardar el movimiento: " . $ex->getMessage();
            $tipoMensaje = "error";
        }
    }
}

// Aviso de guardado o actualizado (se muestra en verde).
if (isset($_GET["guardado"]) && $tipoMensaje != "error") {
    $mensaje = "Movimiento #" . (int)$_GET["guardado"] . " guardado o actualizado correctamente.";
    $tipoMensaje = "bien";
}

// Codigo del movimiento recien guardado (para el boton "Generar factura").

$codigoRecienGuardado = 0;

if (!$esAdmin && isset($_GET["guardado"]) && $tipoMensaje == "bien") {
    $codigoRecienGuardado = (int)$_GET["guardado"];
}


/* consultar tablas */

if ($esAdmin) {

    // Tabla de movimientos registrados 

    $sql = "SELECT
                f.cod_movimiento,
                f.fecha,
                f.cedula_cliente,
                c.nombre AS nombre_cliente,
                COALESCE(SUM(d.precio * d.cantidad), 0) AS total,
                COUNT(d.id_detalle) AS numero_productos
            FROM factura_salida f
            LEFT JOIN cliente c ON f.cedula_cliente = c.cedula_cliente
            LEFT JOIN detalle_salida d ON f.cod_movimiento = d.cod_movimiento";

    // Si escribio algo en el buscador, filtramos.
    if ($busqueda != "") {
        $sql = $sql . " WHERE
                    CAST(f.cod_movimiento AS CHAR) LIKE ?
                    OR CAST(f.cedula_cliente AS CHAR) LIKE ?
                    OR f.fecha LIKE ?
                    OR c.nombre LIKE ?
                    OR EXISTS (
                        SELECT 1
                        FROM detalle_salida dx
                        WHERE dx.cod_movimiento = f.cod_movimiento
                        AND (
                            dx.nombre_producto LIKE ?
                            OR CAST(dx.cod_producto AS CHAR) LIKE ?
                        )
                    )";
    }

    // Agrupamos por factura y ordenamos de la mas nueva a la mas antigua.
    $sql = $sql . " GROUP BY f.cod_movimiento, f.fecha, f.cedula_cliente, c.nombre
                    ORDER BY f.cod_movimiento DESC";

    $stmtLista = $conn->prepare($sql);

    // Si hay busqueda, llenamos los 6 signos ?
    if ($busqueda != "") {
        $texto = "%" . $busqueda . "%";
        $stmtLista->bind_param("ssssss", $texto, $texto, $texto, $texto, $texto, $texto);
    }

    $stmtLista->execute();
    $resultado = $stmtLista->get_result();

    // Tabla de resumen por producto 

    $sqlResumen = "SELECT
                        d.cod_producto,
                        d.nombre_producto,
                        MAX(d.precio) AS precio_unitario,
                        SUM(d.cantidad) AS cantidad_total
                   FROM detalle_salida d
                   INNER JOIN factura_salida f ON f.cod_movimiento = d.cod_movimiento
                   GROUP BY d.cod_producto, d.nombre_producto
                   ORDER BY d.nombre_producto ASC";

    $resultadoResumen = $conn->query($sqlResumen);
}



// Empleado: siempre ve el formulario.
// Administrador: solo cuando esta editando.
$mostrarFormulario = (!$esAdmin || $factura_editar != null);

// A donde se envia el formulario (asi no arrastra "?guardado=").
if ($factura_editar) {
    $accionFormulario = "mov_salida.php?editar=" . (int)$factura_editar["cod_movimiento"];
} else {
    $accionFormulario = "mov_salida.php";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Movimiento de salida</title>
</head>
<body>

 
    <h2>MOVIMIENTO DE SALIDA</h2>
    <form action="menu.php" method="GET">
        <button type="submit">Menú principal</button>
    </form>


    <!-- Mensaje: verde si salio bien, rojo si es error o eliminado -->
    <?php if ($mensaje != ""): ?>
        <?php if ($tipoMensaje == "bien"): ?>
            <p style="color: green;">
                <strong><?php echo e($mensaje); ?></strong>
            </p>
        <?php else: ?>
            <p style="color: red;">
                <strong><?php echo e($mensaje); ?></strong>
            </p>
        <?php endif; ?>
    <?php endif; ?>


    <!-- formulario empleado -->
    <?php if ($mostrarFormulario): ?>

        <h3>
            <?php
            if ($factura_editar) {
                echo "Editar movimiento";
            } else {
                echo "Registrar movimiento";
            }
            ?>
        </h3>

        <form method="POST" id="formularioFactura" action="<?php echo e($accionFormulario); ?>">

            <!-- Le dice a PHP si se guarda uno nuevo o se actualiza uno existente -->
            <input type="hidden" name="accion"
                   value="<?php echo $factura_editar ? "actualizar" : "guardar"; ?>">

            <!-- Al editar, tambien enviamos el codigo de la factura -->
            <?php if ($factura_editar): ?>
                <input type="hidden" name="cod_movimiento"
                       value="<?php echo e($factura_editar["cod_movimiento"]); ?>">
            <?php endif; ?>

            <label>Código de movimiento:</label><br>
            <input type="text" readonly
                   value="<?php
                   if ($factura_editar) {
                       echo e($factura_editar["cod_movimiento"]);
                   } else {
                       echo "Automático al guardar";
                   }
                   ?>">
            <br><br>

            <label>Fecha:</label><br>
            <input type="date" readonly
                   value="<?php
                   if ($factura_editar) {
                       echo e($factura_editar["fecha"]);
                   } else {
                       echo date("Y-m-d");
                   }
                   ?>">
            <br><br>

            <!-- Cliente: se escribe cedula o nombre y salen sugerencias -->
            <label>Cédula o nombre del cliente:</label><br>
            <input type="text"
                   id="cliente_busqueda"
                   list="lista_clientes"
                   autocomplete="off"
                   placeholder="Escriba la cédula o el nombre"
                   value="<?php echo e($clienteSeleccionadoTexto); ?>"
                   required>

            <!-- Aqui JavaScript pone las sugerencias -->
            <datalist id="lista_clientes"></datalist>

            <!-- Campo oculto: la cedula que realmente se envia -->
            <input type="hidden" name="cedula_cliente" id="cedula_cliente"
                   value="<?php echo e($cedulaSeleccionada); ?>">

            <p id="estado_cliente"></p>

            <h3>Productos de salida</h3>

            <table border="1" cellpadding="5" cellspacing="0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre del producto</th>
                        <th>Precio unitario</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <!-- Las filas de productos se agregan aqui -->
                <tbody id="productos">
                    <?php /* Al editar, mostramos los productos que ya tenia la factura */ ?>
                    <?php foreach ($detalles_editar as $detalle): ?>
                        <tr>
                            <td>
                                <input class="codigo" type="number" name="cod_producto[]"
                                       min="1" required
                                       value="<?php echo e($detalle["cod_producto"]); ?>">
                            </td>
                            <td>
                                <input class="nombre-producto" type="text" name="nombre_producto[]"
                                       required
                                       value="<?php echo e($detalle["nombre_producto"]); ?>">
                            </td>
                            <td>
                                <input class="precio" type="number" name="precio[]"
                                       min="0" step="0.01" required
                                       value="<?php echo e($detalle["precio"]); ?>">
                            </td>
                            <td>
                                <input class="cantidad" type="number" name="cantidad[]"
                                       min="1" step="1" required
                                       value="<?php echo e($detalle["cantidad"]); ?>">
                            </td>
                            <td class="subtotal">$0</td>
                            <td>
                                <button type="button" class="quitar">Quitar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="4">TOTAL</th>
                        <th id="totalFactura">$0</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>

            <br>

            <button type="button" onclick="agregarProducto()">Agregar producto</button>

            <br><br>

            <!-- Boton de guardar o actualizar -->
            <button type="submit">
                <?php
                if ($factura_editar) {
                    echo "Actualizar movimiento";
                } else {
                    echo "Guardar movimiento";
                }
                ?>
            </button>

            <!-- Al lado de guardar: "Generar factura" (solo empleado, despues de guardar).
                 Asi el empleado elige si imprime o no. -->
            <?php if ($codigoRecienGuardado > 0): ?>
                <button type="button" onclick="generarFactura(<?php echo $codigoRecienGuardado; ?>)">
                    Generar factura
                </button>
            <?php endif; ?>

            <?php if ($factura_editar): ?>
                <a href="mov_salida.php">Cancelar edición</a>
            <?php endif; ?>

        </form>

        <script>
            
            // JAVASCRIPT DEL FORMULARIO (corre en el navegador)
            

            // Elementos de la pagina que vamos a usar.
            const campoCliente = document.getElementById("cliente_busqueda");
            const campoCedula = document.getElementById("cedula_cliente");
            const listaClientes = document.getElementById("lista_clientes");
            const estadoCliente = document.getElementById("estado_cliente");

            // Sirve para ignorar respuestas viejas si el usuario sigue escribiendo.
            let consultaClientes = 0;


            // Convierte un numero a pesos. Ejemplo: 1500 -> $1.500
            function formato(valor) {
                return valor.toLocaleString("es-CO", {
                    style: "currency",
                    currency: "COP",
                    maximumFractionDigits: 2
                });
            }


            // Carga la factura en el marco oculto y se imprime sola.
            // El "&t=" evita que el navegador repita la carga anterior.
            function generarFactura(codigo) {
                const marco = document.getElementById("marcoFactura");
                marco.src = "mov_salida.php?factura=" + encodeURIComponent(codigo) + "&t=" + Date.now();
            }


            //  Sugerencias de clientes mientras se escribe 
            campoCliente.addEventListener("input", async function () {

                // Si cambia el texto, la cedula elegida ya no sirve.
                campoCedula.value = "";
                estadoCliente.textContent = "";

                const texto = campoCliente.value.trim();
                consultaClientes = consultaClientes + 1;
                const consultaActual = consultaClientes;

                // Si borro todo, vaciamos las sugerencias.
                if (texto == "") {
                    listaClientes.replaceChildren();
                    return;
                }

                try {
                    const respuesta = await fetch("mov_salida.php?buscar_cliente=" + encodeURIComponent(texto));

                    if (!respuesta.ok) {
                        throw new Error("No se pudo buscar el cliente.");
                    }

                    const clientes = await respuesta.json();

                    // Si ya escribio otra cosa, ignoramos esta respuesta.
                    if (consultaActual != consultaClientes) {
                        return;
                    }

                    // Llenamos la lista de sugerencias.
                    listaClientes.replaceChildren();

                    clientes.forEach(function (cliente) {
                        const opcion = document.createElement("option");
                        opcion.value = cliente.cedula + " - " + cliente.nombre;
                        listaClientes.appendChild(opcion);
                    });

                } catch (error) {
                    estadoCliente.textContent = "Error al buscar clientes.";
                    console.error(error);
                }
            });


            //  Confirmar que el cliente escrito si existe 
            // Devuelve true si el cliente es valido y false si no.
            async function resolverCliente() {
                const texto = campoCliente.value.trim();

                if (texto == "") {
                    campoCedula.value = "";
                    estadoCliente.textContent = "Escriba la cédula o el nombre del cliente.";
                    return false;
                }

                try {
                    const respuesta = await fetch("mov_salida.php?buscar_cliente=" + encodeURIComponent(texto));

                    if (!respuesta.ok) {
                        throw new Error("Error al consultar el cliente.");
                    }

                    const clientes = await respuesta.json();

                    // Buscamos el cliente que coincida con lo escrito.
                    const cliente = clientes.find(function (c) {
                        const cedula = String(c.cedula).trim();
                        const nombre = String(c.nombre).trim();

                        return texto == cedula ||
                               texto.toLowerCase() == nombre.toLowerCase() ||
                               texto == cedula + " - " + nombre;
                    });

                    if (!cliente) {
                        campoCedula.value = "";
                        estadoCliente.textContent = "Cliente no encontrado. Seleccione un cliente registrado.";
                        return false;
                    }

                    // Cliente valido: guardamos su cedula en el campo oculto.
                    campoCedula.value = String(cliente.cedula);
                    campoCliente.value = cliente.cedula + " - " + cliente.nombre;
                    estadoCliente.textContent = "Cliente seleccionado: " + cliente.nombre;
                    return true;

                } catch (error) {
                    campoCedula.value = "";
                    estadoCliente.textContent = "No se pudo verificar el cliente.";
                    console.error(error);
                    return false;
                }
            }

            // Cuando termina de escribir el cliente, lo verificamos.
            campoCliente.addEventListener("change", resolverCliente);


            //  Filas de productos 

            // Le da vida a una fila: buscar producto, recalcular y quitar.
            function conectarFila(fila) {
                const codigo = fila.querySelector(".codigo");
                const nombre = fila.querySelector(".nombre-producto");
                const precio = fila.querySelector(".precio");
                const cantidad = fila.querySelector(".cantidad");
                const quitar = fila.querySelector(".quitar");

                // Al cambiar el codigo, buscamos el producto.
                codigo.addEventListener("change", function () {
                    buscarProducto(fila);
                });

                // Si pulsa Enter en el codigo, buscamos sin enviar el formulario.
                codigo.addEventListener("keydown", function (evento) {
                    if (evento.key == "Enter") {
                        evento.preventDefault();
                        buscarProducto(fila);
                    }
                });

                // Al cambiar nombre, precio o cantidad, recalculamos.
                nombre.addEventListener("input", calcularTotal);
                precio.addEventListener("input", calcularTotal);
                cantidad.addEventListener("input", calcularTotal);

                // Boton Quitar: elimina la fila y recalcula.
                quitar.addEventListener("click", function () {
                    fila.remove();
                    calcularTotal();
                });
            }


            // Agrega una fila vacia a la tabla de productos.
            function agregarProducto() {
                const fila = document.createElement("tr");

                fila.innerHTML = `
                    <td><input class="codigo" type="number" name="cod_producto[]" min="1" required></td>
                    <td><input class="nombre-producto" type="text" name="nombre_producto[]" required></td>
                    <td><input class="precio" type="number" name="precio[]" min="0" step="0.01" required></td>
                    <td><input class="cantidad" type="number" name="cantidad[]" min="1" step="1" value="1" required></td>
                    <td class="subtotal">$0</td>
                    <td><button type="button" class="quitar">Quitar</button></td>
                `;

                document.getElementById("productos").appendChild(fila);

                conectarFila(fila);
                calcularTotal();
            }


            // Busca el producto por su codigo y rellena nombre y precio.
            async function buscarProducto(fila) {
                const campoCodigo = fila.querySelector(".codigo");
                const campoNombre = fila.querySelector(".nombre-producto");
                const campoPrecio = fila.querySelector(".precio");

                const codigo = campoCodigo.value.trim();

                // Si borro el codigo, vaciamos nombre y precio.
                if (codigo == "") {
                    campoNombre.value = "";
                    campoPrecio.value = "";
                    calcularTotal();
                    return;
                }

                try {
                    const respuesta = await fetch("mov_salida.php?producto_codigo=" + encodeURIComponent(codigo));

                    if (!respuesta.ok) {
                        throw new Error("No se pudo consultar el producto.");
                    }

                    const producto = await respuesta.json();

                    if (!producto.existe) {
                        campoNombre.value = "";
                        campoPrecio.value = "";
                        calcularTotal();
                        alert("No existe un producto con ese código.");
                        return;
                    }

                    // Rellenamos la fila con los datos del producto.
                    campoCodigo.value = producto.codigo;
                    campoNombre.value = producto.nombre;
                    campoPrecio.value = producto.precio;
                    calcularTotal();

                } catch (error) {
                    console.error(error);
                    alert("No se pudo consultar el producto.");
                }
            }


            // ---------- Calculo de subtotales y total ----------
            function calcularTotal() {
                let total = 0;

                // Recorremos cada fila de la tabla.
                document.querySelectorAll("#productos tr").forEach(function (fila) {
                    const precio = parseFloat(fila.querySelector(".precio").value) || 0;
                    const cantidad = parseInt(fila.querySelector(".cantidad").value) || 0;

                    const subtotal = precio * cantidad;

                    fila.querySelector(".subtotal").textContent = formato(subtotal);
                    total = total + subtotal;
                });

                document.getElementById("totalFactura").textContent = formato(total);
            }


            // ---------- Validar antes de enviar el formulario ----------
            document.getElementById("formularioFactura").addEventListener("submit", async function (evento) {

                // Detenemos el envio para revisar primero.
                evento.preventDefault();

                // 1. El cliente debe existir.
                const clienteValido = await resolverCliente();

                if (!clienteValido) {
                    alert("Seleccione un cliente registrado de las sugerencias.");
                    campoCliente.focus();
                    return;
                }

                // 2. Debe haber al menos un producto.
                const filas = document.querySelectorAll("#productos tr");

                if (filas.length == 0) {
                    alert("Agregue al menos un producto.");
                    return;
                }

                // 3. Cada producto debe tener datos correctos.
                for (const fila of filas) {
                    const codigo = fila.querySelector(".codigo").value.trim();
                    const nombre = fila.querySelector(".nombre-producto").value.trim();
                    const precioTexto = fila.querySelector(".precio").value;
                    const cantidadTexto = fila.querySelector(".cantidad").value;

                    const precio = Number(precioTexto);
                    const cantidad = Number(cantidadTexto);

                    if (
                        codigo == "" || Number(codigo) <= 0 ||
                        nombre == "" ||
                        precioTexto == "" || !Number.isFinite(precio) || precio < 0 ||
                        cantidadTexto == "" || !Number.isInteger(cantidad) || cantidad < 1
                    ) {
                        alert("Revise el código, nombre, precio y cantidad.");
                        return;
                    }
                }

                // Todo bien: enviamos el formulario.
                // (PHP revisa las existencias otra vez antes de guardar.)
                this.submit();
            });


            // ---------- Al cargar la pagina ----------

            // Si estamos editando, activamos las filas que PHP ya dibujo.
            document.querySelectorAll("#productos tr").forEach(function (fila) {
                conectarFila(fila);
            });

            // Si no hay ninguna fila (registro nuevo), creamos una vacia.
            if (document.querySelectorAll("#productos tr").length == 0) {
                agregarProducto();
            }

            calcularTotal();
        </script>

    <?php endif; // fin del formulario ?>


    <!-- =====================================================
         TABLAS (solo administrador)
         ===================================================== -->
    <?php if ($esAdmin): ?>

        <!-- Tabla 1: unidades que salieron de cada producto -->
        <h3>Movimiento de salida de Productos</h3>

        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Código del producto</th>
                    <th>Nombre del producto</th>
                    <th>Precio unitario</th>
                    <th>Cantidad total de salida</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($producto = $resultadoResumen->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo e($producto["cod_producto"]); ?></td>
                        <td><?php echo e($producto["nombre_producto"]); ?></td>
                        <td><?php echo formatoMoneda($producto["precio_unitario"]); ?></td>
                        <td><?php echo e($producto["cantidad_total"]); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <hr>

        <!-- Tabla 2: facturas con buscador -->
        <h3>Movimientos registrados por cliente</h3>

        <form method="GET">
            <label>Buscar movimiento o cliente:</label>
            <input type="text" name="buscar"
                   value="<?php echo e($busqueda); ?>"
                   placeholder="Código, cédula, cliente o producto">
            <button type="submit">Buscar</button>
            <a href="mov_salida.php">Mostrar todos</a>
        </form>

        <br>

        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Código de movimiento</th>
                    <th>Fecha</th>
                    <th>Cédula - Cliente</th>
                    <th>Productos</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultado->num_rows > 0): ?>
                    <?php while ($fila = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($fila["cod_movimiento"]); ?></td>
                            <td><?php echo e($fila["fecha"]); ?></td>
                            <td>
                                <?php
                                $nombreCliente = "Sin nombre";
                                if ($fila["nombre_cliente"] != null) {
                                    $nombreCliente = $fila["nombre_cliente"];
                                }

                                echo e($fila["cedula_cliente"]) . " - " . e($nombreCliente);
                                ?>
                            </td>
                            <td><?php echo e($fila["numero_productos"]); ?></td>
                            <td><?php echo formatoMoneda($fila["total"]); ?></td>
                            <td>
                                <!-- Boton para generar la factura de este movimiento -->
                                <form method="GET" action="mov_salida.php" target="marcoFactura">
                                    <input type="hidden" name="factura"
                                           value="<?php echo e($fila["cod_movimiento"]); ?>">
                                    <button type="submit">Factura</button>
                                </form>

                                |

                                <a href="?editar=<?php echo e($fila["cod_movimiento"]); ?>">Editar</a>

                                |

                                <a href="?borrar=<?php echo e($fila["cod_movimiento"]); ?>"
                                   onclick="return confirm('¿Está segura de eliminar este movimiento?');">
                                    Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No se encontraron movimientos.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    <?php endif; // fin de las tablas del administrador ?>

    <br>

    <!-- Marco oculto: carga la factura sin cambiar de pagina -->
    <iframe name="marcoFactura" id="marcoFactura" title="Factura para imprimir"
            style="display: none;"></iframe>

    <!-- Boton para volver al menu -->
   
</body>
</html>
<?php
// Cerramos la consulta de la lista (solo existe para el administrador).
if (isset($stmtLista)) {
    $stmtLista->close();
}

$conn->close();
?>