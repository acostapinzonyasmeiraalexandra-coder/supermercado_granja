<?php

// usuario y rol llegan por la url
$usuario = trim((string) ($_GET["usuario"] ?? ""));
$cargo = (string) ($_GET["rol"] ?? "");

// si faltan los datos de acceso devuelve al inicio de sesion
if ($usuario === "" || ($cargo !== "1" && $cargo !== "2")) {
    // redirige a index.html
    header("Location: index.html");
    exit();
}

// se agregan a los enlaces para no perder la sesion
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($cargo);
// esta variable es igual que parametros pero protegida para usarla en el html
$parametrosHtml = htmlspecialchars($parametros, ENT_QUOTES, "UTF-8");

// esta variable indica si el usuario es administrador (cargo 1)
$esAdmin = ($cargo === "1");
// esta variable almacena la primera letra del usuario para el encabezado
$inicial = strtoupper(substr(trim($usuario), 0, 1));
// esta variable almacena el texto del cargo para mostrarlo en pantalla
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";

// evita que el texto rompa el html
function escapar($dato): string {
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

// esta funcion muestra un icono de la carpeta img
function icono(string $nombre): string {
    return '<img src="img/' . escapar($nombre) . '.png" alt="" class="icono">';
}

// da formato de pesos a un numero
function formatoMoneda($valor): string {
    return "$" . number_format((float)$valor, 2, ",", ".");
}

// conexion a la base de datos
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// carga el archivo que hace la conexion con la base de datos
require_once "conexion.php";

// acepta otro nombre para la variable de conexion
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}
// si no hay una conexion valida detiene la pagina
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("Error: No se encontró una conexión válida a la base de datos.");
}

// define la codificacion de los caracteres de la base de datos
$conn->set_charset("utf8mb4");

// arma la factura para imprimir
function mostrarFactura($conn, $codigo) {
    // prepara la consulta sql de forma segura
    $stmt = $conn->prepare(
        "SELECT f.cod_movimiento, f.fecha, f.cedula_cliente,
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
             FROM detalle_salida
             WHERE cod_movimiento = ?
             ORDER BY id_detalle"
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
        <style>
            body { font-family: sans-serif; padding: 20px; font-size: 14px; }
            .centro { text-align: center; }
            .derecha { text-align: right; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #ccc; padding: 8px; }
            th { background: #f0fdf4; }
            .total { font-weight: bold; }
            @media print { .no-imprimir { display: none; } }
        </style>
    </head>
    <!-- cuerpo de la pagina -->
    <body>
        <?php if (!$factura): ?>
            <p>La factura no existe.</p>
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
                <strong>Dirección:</strong> <?= escapar($factura["direccion"] ?? "") ?>
            </p>
            <!-- tabla con los registros -->
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
            <p class="centro" style="margin-top:20px;">¡Gracias por su compra!</p>
            <p class="centro no-imprimir" style="margin-top:15px;">
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
    mostrarFactura($conn, (int)$_GET["factura"]);
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
if (isset($_GET["borrar"])) {
    // bloquea la accion si el usuario no es administrador
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para eliminar movimientos.";
        $tipoMensaje = "error";
    } else {
        // datos que llegan por la url
        $codigo = (int)$_GET["borrar"];
        $transaccion = false;
        // intenta ejecutar el codigo y atrapa los errores
        try {
            $conn->begin_transaction();
            $transaccion = true;

            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("DELETE FROM detalle_salida WHERE cod_movimiento = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $codigo);
            // ejecuta la consulta
            $stmt->execute();
            // cierra la consulta
            $stmt->close();

            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("DELETE FROM factura_salida WHERE cod_movimiento = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $codigo);
            // ejecuta la consulta
            $stmt->execute();
            // cierra la consulta
            $stmt->close();

            $conn->commit();
            // redirige a mov_salida.php
            header("Location: mov_salida.php?" . $parametros . "&mensaje=eliminado");
            exit();
        } catch (Exception $e) {
            if ($transaccion) $conn->rollback();
            $mensaje = "Error al eliminar: " . $e->getMessage();
            $tipoMensaje = "error";
        }
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
            $stmt = $conn->prepare("SELECT * FROM detalle_salida WHERE cod_movimiento = ? ORDER BY id_detalle");
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

            if ($accion === "actualizar") {
                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("UPDATE factura_salida SET cedula_cliente = ? WHERE cod_movimiento = ?");
                // envia los datos a la consulta
                $stmt->bind_param("ii", $cedula, $codigo);
                // ejecuta la consulta
                $stmt->execute();
                // cierra la consulta
                $stmt->close();

                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("DELETE FROM detalle_salida WHERE cod_movimiento = ?");
                // envia los datos a la consulta
                $stmt->bind_param("i", $codigo);
                // ejecuta la consulta
                $stmt->execute();
                // cierra la consulta
                $stmt->close();
            } else {
                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("INSERT INTO factura_salida (fecha, nota, cedula_cliente) VALUES (CURDATE(), '', ?)");
                // envia los datos a la consulta
                $stmt->bind_param("i", $cedula);
                // ejecuta la consulta
                $stmt->execute();
                $codigo = $conn->insert_id;
                // cierra la consulta
                $stmt->close();
            }

            // guarda cada producto en detalle_salida
            $stmtDetalle = $conn->prepare(
                "INSERT INTO detalle_salida (cod_movimiento, cod_producto, nombre_producto, precio, cantidad)
                 VALUES (?, ?, ?, ?, ?)"
            );

            // stock = entradas - salidas
            $consultaStock = $conn->prepare(
                "SELECT COALESCE((SELECT SUM(cantidad) FROM mov_entrada WHERE cod_producto = ?), 0) -
                        COALESCE((SELECT SUM(ds.cantidad) FROM detalle_salida ds
                                  INNER JOIN factura_salida fs ON fs.cod_movimiento = ds.cod_movimiento
                                  WHERE ds.cod_producto = ?), 0) AS disponible"
            );

            for ($i = 0; $i < count($codigos); $i++) {
                $codP = filter_var($codigos[$i], FILTER_VALIDATE_INT);
                $cantP = filter_var($cantidades[$i], FILTER_VALIDATE_INT);
                $nomP = trim((string)$nombres[$i]);
                $preP = (float)$precios[$i];

                if (!$codP || $codP <= 0 || $nomP === "" || $preP < 0 || !$cantP || $cantP <= 0) {
                    throw new Exception("Hay campos inválidos en la lista de productos.");
                }

                $consultaStock->bind_param("ii", $codP, $codP);
                $consultaStock->execute();
                $disponible = (int)($consultaStock->get_result()->fetch_assoc()["disponible"] ?? 0);

                // no deja vender mas de lo que hay
                if ($cantP > $disponible) {
                    throw new Exception("Stock insuficiente para '{$nomP}'. Disponibles: {$disponible}.");
                }

                // envia los datos a la consulta
                $stmtDetalle->bind_param("iisdi", $codigo, $codP, $nomP, $preP, $cantP);
                // ejecuta la consulta
                $stmtDetalle->execute();
            }

            $consultaStock->close();
            // cierra la consulta
            $stmtDetalle->close();

            $conn->commit();
            // redirige a mov_salida.php
            header("Location: mov_salida.php?" . $parametros . "&guardado=" . $codigo);
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
    $tipoMensaje = "error";
}
if (isset($_GET["guardado"]) && $tipoMensaje !== "error") {
    // datos que llegan por la url
    $codigoGuardado = (int)$_GET["guardado"];
    $mensaje = "Movimiento #{$codigoGuardado} procesado exitosamente.";
    $tipoMensaje = "ok";
}

// consultas de las tablas del admin
$listaFacturas = [];
if ($esAdmin) {
    // total de salidas por producto
    $resultadoResumen = $conn->query(
        "SELECT d.cod_producto, d.nombre_producto, MAX(d.precio) AS precio_unitario, SUM(d.cantidad) AS cantidad_total
         FROM detalle_salida d
         INNER JOIN factura_salida f ON f.cod_movimiento = d.cod_movimiento
         GROUP BY d.cod_producto, d.nombre_producto
         ORDER BY d.nombre_producto ASC"
    );

    // lista de facturas con su total, la busqueda la hace React en el navegador
    $resultadoFacturas = $conn->query(
        "SELECT f.cod_movimiento, f.fecha, f.cedula_cliente, c.nombre AS nombre_cliente,
                COALESCE(SUM(d.precio * d.cantidad), 0) AS total,
                COUNT(d.id_detalle) AS numero_productos
         FROM factura_salida f
         LEFT JOIN cliente c ON f.cedula_cliente = c.cedula_cliente
         LEFT JOIN detalle_salida d ON f.cod_movimiento = d.cod_movimiento
         GROUP BY f.cod_movimiento, f.fecha, f.cedula_cliente, c.nombre
         ORDER BY f.cod_movimiento DESC"
    );

    // recorre cada fila y la guarda en la lista
    while ($fila = $resultadoFacturas->fetch_assoc()) {
        // se arman los textos que se ven en la tabla
        $fila["nombre_cliente"] = $fila["nombre_cliente"] ?? "Sin Nombre";
        $fila["cliente_texto"] = $fila["cedula_cliente"] . " - " . $fila["nombre_cliente"];
        $fila["total_texto"] = formatoMoneda($fila["total"]);
        $listaFacturas[] = $fila;
    }
}

// envia los datos de php a react de forma segura
$salidasJson = json_encode(
    $listaFacturas,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);

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
    <link rel="stylesheet" href="css/mov_salida.css">

    <!-- React sencillo desde CDN -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
</head>
<!-- cuerpo de la pagina -->
<body class="pagina-salida">

<!-- encabezado de la pagina -->
<header class="encabezado">
    <button type="button" class="boton-menu" id="abrirMenu" aria-label="Abrir menú lateral">
        <?= icono("menu") ?>
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
            <img src="img/logo.png" alt="Logo de La Granja">
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
            <a href="menu.php?<?= $parametrosHtml ?>" class="enlace-menu">
                <span class="caja-icono"><?= icono("inicio") ?></span>
                <span>Inicio</span>
            </a>
            <a href="cliente.php?<?= $parametrosHtml ?>" class="enlace-menu">
                <span class="caja-icono"><?= icono("cliente") ?></span>
                <span>Cliente</span>
            </a>
            <a href="mov_salida.php?<?= $parametrosHtml ?>" class="enlace-menu activo" aria-current="page">
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
                <a href="inventario.php?<?= $parametrosHtml ?>" class="enlace-menu">
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

    <!-- formulario para cerrar sesion -->
    <form action="menu.php?<?= $parametrosHtml ?>" method="POST" class="form-salir">
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
            <form method="POST" id="formularioFactura" action="mov_salida.php?<?= $parametrosHtml ?>" class="formulario">
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
                    <label for="cliente_busqueda">Cédula o Nombre del Cliente</label>
                    <input type="text" id="cliente_busqueda" list="lista_clientes" autocomplete="off" placeholder="Escriba para buscar cliente..." value="<?= escapar($clienteSeleccionadoTexto) ?>" required>
                    <datalist id="lista_clientes"></datalist>
                    <input type="hidden" name="cedula_cliente" id="cedula_cliente" value="<?= escapar($cedulaSeleccionada) ?>">
                    <p id="estado_cliente" class="estado-texto"></p>
                </div>

                <div class="campo campo-ancho" style="margin-top:10px;">
                    <label>Detalle de Productos</label>
                    <div class="tabla-contenedor">
                        <!-- tabla con los registros -->
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">Código</th>
                                    <th>Producto</th>
                                    <th style="width: 18%;" class="derecha">Precio Unitario</th>
                                    <th style="width: 15%;" class="derecha">Cantidad</th>
                                    <th style="width: 18%;" class="derecha">Subtotal</th>
                                    <th style="width: 10%;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="productos">
                                <?php foreach ($detalles_editar as $detalle): ?>
                                    <tr>
                                        <td>
                                            <input class="codigo" type="number" name="cod_producto[]" min="1" required value="<?= escapar($detalle["cod_producto"]) ?>">
                                        </td>
                                        <td>
                                            <input class="nombre-producto" type="text" name="nombre_producto[]" required value="<?= escapar($detalle["nombre_producto"]) ?>">
                                        </td>
                                        <td>
                                            <input class="precio" type="number" name="precio[]" min="0" step="0.01" required value="<?= escapar($detalle["precio"]) ?>">
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
                        <a href="mov_salida.php?<?= $parametrosHtml ?>" class="boton boton-secundario">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- tablas del admin -->
    <?php if ($esAdmin): ?>
        <!-- panel de contenido -->
        <section class="panel">
            <h3 class="panel-titulo">Resumen Consolidado de Salidas por Producto</h3>
            <div class="tabla-contenedor">
                <!-- tabla con los registros -->
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre del Producto</th>
                            <th class="derecha">Precio Unitario Ref.</th>
                            <th class="derecha">Cantidad Total Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultadoResumen && $resultadoResumen->num_rows > 0): ?>
                            <?php while ($prod = $resultadoResumen->fetch_assoc()): ?>
                                <tr>
                                    <td><?= escapar($prod["cod_producto"]) ?></td>
                                    <td><?= escapar($prod["nombre_producto"]) ?></td>
                                    <td class="derecha"><?= formatoMoneda($prod["precio_unitario"]) ?></td>
                                    <td class="derecha"><?= escapar($prod["cantidad_total"]) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="tabla-vacia">No hay movimientos registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- aqui React dibuja el buscador y el historial de salidas -->
        <div id="app-salidas"></div>
    <?php endif; ?>
</main>

<iframe name="marcoFactura" id="marcoFactura" title="Factura" style="display: none;"></iframe>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <!-- enlaces de ayuda -->
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.</p>
            <a href="ayuda.php?<?= $parametrosHtml ?>" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
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

<!-- codigo javascript de la pagina -->
<script>
// funcion que se ejecuta sola al cargar la pagina
(() => {
    // abrir y cerrar el menu lateral
    const abrir = document.getElementById("abrirMenu");
    const cerrar = document.getElementById("cerrarMenu");
    const fondo = document.getElementById("fondoMenu");

    // esta funcion abre el menu lateral
    function mostrarMenu() {
        document.body.classList.add("menu-abierto");
        abrir.setAttribute("aria-expanded", "true");
    }
    // esta funcion cierra el menu lateral
    function ocultarMenu() {
        document.body.classList.remove("menu-abierto");
        abrir.setAttribute("aria-expanded", "false");
    }

    if (abrir) abrir.addEventListener("click", mostrarMenu);
    if (cerrar) cerrar.addEventListener("click", ocultarMenu);
    if (fondo) fondo.addEventListener("click", ocultarMenu);
    // escucha las teclas que presiona el usuario
    document.addEventListener("keydown", e => { if (e.key === "Escape") ocultarMenu(); });
})();

// busqueda de clientes, productos y total
const campoCliente = document.getElementById("cliente_busqueda");
const campoCedula = document.getElementById("cedula_cliente");
const listaClientes = document.getElementById("lista_clientes");
const estadoCliente = document.getElementById("estado_cliente");
let consultaClientes = 0;

// esta funcion da formato de pesos a un valor
function formato(valor) {
    return valor.toLocaleString("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 2 });
}

// esta funcion carga la factura en el marco para imprimirla
function generarFactura(codigo) {
    // elementos de la pagina que se usan en el codigo
    const marco = document.getElementById("marcoFactura");
    marco.src = "mov_salida.php?<?= $parametros ?>&factura=" + encodeURIComponent(codigo) + "&t=" + Date.now();
}

if (campoCliente) {
    // escucha lo que el usuario escribe
    campoCliente.addEventListener("input", async function () {
        campoCedula.value = "";
        if (estadoCliente) estadoCliente.textContent = "";
        const texto = campoCliente.value.trim();
        consultaClientes++;
        const actual = consultaClientes;

        if (texto === "") {
            listaClientes.replaceChildren();
            return;
        }

        try {
            // pide los datos al servidor
            const resp = await fetch("mov_salida.php?<?= $parametros ?>&buscar_cliente=" + encodeURIComponent(texto));
            if (!resp.ok) return;
            const clientes = await resp.json();
            if (actual !== consultaClientes) return;

            listaClientes.replaceChildren();
            clientes.forEach(c => {
                const opt = document.createElement("option");
                opt.value = c.cedula + " - " + c.nombre;
                listaClientes.appendChild(opt);
            });
        } catch (e) { console.error(e); }
    });

    // escucha cuando el usuario cambia el valor del campo
    campoCliente.addEventListener("change", async function () {
        const texto = campoCliente.value.trim();
        if (texto === "") return;

        try {
            // pide los datos al servidor
            const resp = await fetch("mov_salida.php?<?= $parametros ?>&buscar_cliente=" + encodeURIComponent(texto));
            const clientes = await resp.json();
            const cliente = clientes.find(c => {
                const cd = String(c.cedula).trim();
                const nm = String(c.nombre).trim();
                return texto === cd || texto.toLowerCase() === nm.toLowerCase() || texto === cd + " - " + nm;
            });

            if (cliente) {
                campoCedula.value = String(cliente.cedula);
                campoCliente.value = cliente.cedula + " - " + cliente.nombre;
                if (estadoCliente) estadoCliente.textContent = "Cliente verificado: " + cliente.nombre;
            } else {
                campoCedula.value = "";
                if (estadoCliente) estadoCliente.textContent = "Cliente no válido. Elige una opción de las sugerencias.";
            }
        } catch (e) { console.error(e); }
    });
}

// esta funcion conecta los eventos de una fila de la tabla de productos
function conectarFila(fila) {
    const codigo = fila.querySelector(".codigo");
    const nombre = fila.querySelector(".nombre-producto");
    const precio = fila.querySelector(".precio");
    const cantidad = fila.querySelector(".cantidad");
    const quitar = fila.querySelector(".quitar");

    // escucha cuando el usuario cambia el valor del campo
    codigo.addEventListener("change", () => buscarProducto(fila));
    // escucha las teclas que presiona el usuario
    codigo.addEventListener("keydown", (e) => {
        if (e.key === "Enter") { e.preventDefault(); buscarProducto(fila); }
    });

    // escucha lo que el usuario escribe
    nombre.addEventListener("input", calcularTotal);
    // escucha lo que el usuario escribe
    precio.addEventListener("input", calcularTotal);
    // escucha lo que el usuario escribe
    cantidad.addEventListener("input", calcularTotal);

    // escucha el clic del usuario
    quitar.addEventListener("click", () => {
        fila.remove();
        calcularTotal();
    });
}

// esta funcion agrega una fila nueva para otro producto
function agregarProducto() {
    const fila = document.createElement("tr");
    fila.innerHTML = `
        <td><input class="codigo" type="number" name="cod_producto[]" min="1" required></td>
        <td><input class="nombre-producto" type="text" name="nombre_producto[]" required></td>
        <td><input class="precio" type="number" name="precio[]" min="0" step="0.01" required></td>
        <td><input class="cantidad" type="number" name="cantidad[]" min="1" step="1" value="1" required></td>
        <td class="subtotal derecha">$0</td>
        <td><button type="button" class="boton boton-chico boton-eliminar quitar">Quitar</button></td>
    `;
    document.getElementById("productos").appendChild(fila);
    conectarFila(fila);
    calcularTotal();
}

// esta funcion busca el producto por codigo y llena el nombre y el precio
async function buscarProducto(fila) {
    const campoCod = fila.querySelector(".codigo");
    const campoNom = fila.querySelector(".nombre-producto");
    const campoPre = fila.querySelector(".precio");
    const cod = campoCod.value.trim();

    if (cod === "") {
        campoNom.value = ""; campoPre.value = ""; calcularTotal(); return;
    }

    try {
        // pide los datos al servidor
        const resp = await fetch("mov_salida.php?<?= $parametros ?>&producto_codigo=" + encodeURIComponent(cod));
        const prod = await resp.json();

        if (prod.existe) {
            campoCod.value = prod.codigo;
            campoNom.value = prod.nombre;
            campoPre.value = prod.precio;
            calcularTotal();
        } else {
            campoNom.value = ""; campoPre.value = ""; calcularTotal();
            alert("No se encontró producto con ese código.");
        }
    } catch (e) { console.error(e); }
}

// esta funcion calcula el subtotal de cada fila y el total de la factura
function calcularTotal() {
    let total = 0;
    document.querySelectorAll("#productos tr").forEach(fila => {
        const precio = parseFloat(fila.querySelector(".precio").value) || 0;
        const cantidad = parseInt(fila.querySelector(".cantidad").value) || 0;
        const subtotal = precio * cantidad;

        fila.querySelector(".subtotal").textContent = formato(subtotal);
        total += subtotal;
    });
    // elementos de la pagina que se usan en el codigo
    const elemTotal = document.getElementById("totalFactura");
    if (elemTotal) elemTotal.textContent = formato(total);
}

document.querySelectorAll("#productos tr").forEach(conectarFila);
if (document.querySelectorAll("#productos tr").length === 0 && document.getElementById("productos")) {
    agregarProducto();
}
calcularTotal();
</script>
<?php if ($esAdmin): ?>
<!-- datos iniciales que envia php a React -->
<script>
    window.salidasIniciales = <?= $salidasJson ?: "[]" ?>;
    window.datosUsuario = {
        usuario: <?= json_encode($usuario, JSON_UNESCAPED_UNICODE) ?>,
        rol: <?= json_encode($cargo) ?>
    };
</script>

<!-- busqueda automatica con React -->
<script type="text/babel">

    const { useState } = React;

    function AplicacionSalidas() {

        // esta variable almacena el texto que se escribe en el buscador
        const [busqueda, setBusqueda] = useState("");

        const lista = window.salidasIniciales || [];
        const datos = window.datosUsuario;

        // usuario y cargo para no perderlos en los enlaces
        const parametros = "usuario=" + encodeURIComponent(datos.usuario) +
                           "&rol=" + encodeURIComponent(datos.rol);

        // filtra mientras el usuario escribe
        const filtrados = lista.filter(item => {

            const texto = busqueda.toLowerCase().trim();

            return (
                String(item.cod_movimiento).toLowerCase().includes(texto) ||
                String(item.cedula_cliente).toLowerCase().includes(texto) ||
                String(item.fecha).toLowerCase().includes(texto) ||
                String(item.nombre_cliente).toLowerCase().includes(texto)
            );
        });

        return (
            <div>

                {/* buscador */}
                <section className="panel">
                    <h3 className="panel-titulo">Buscar Movimientos Registrados</h3>
                    <div className="buscador">
                        <input
                            type="text"
                            placeholder="Código, cédula o cliente..."
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                        />
                        {busqueda !== "" && (
                            <button type="button" className="boton boton-secundario" onClick={() => setBusqueda("")}>
                                Mostrar todos
                            </button>
                        )}
                    </div>
                </section>

                {/* tabla de resultados */}
                <section className="panel">
                    <h3 className="panel-titulo">Historial de Salidas / Facturas</h3>
                    <div className="tabla-contenedor">
                        <table className="tabla">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Fecha</th>
                                    <th>Cédula - Cliente</th>
                                    <th className="derecha">Productos</th>
                                    <th className="derecha">Total Venta</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>

                                {filtrados.length > 0 ? (

                                    filtrados.map(item => (

                                        <tr key={item.cod_movimiento}>
                                            <td>{item.cod_movimiento}</td>
                                            <td>{item.fecha}</td>
                                            <td>{item.cliente_texto}</td>
                                            <td className="derecha">{item.numero_productos}</td>
                                            <td className="derecha">{item.total_texto}</td>
                                            <td className="tabla-acciones">
                                                <button
                                                    type="button"
                                                    className="boton boton-chico boton-factura"
                                                    onClick={() => generarFactura(item.cod_movimiento)}
                                                >
                                                    Factura
                                                </button>

                                                <a
                                                    className="boton boton-chico boton-editar"
                                                    href={"mov_salida.php?" + parametros + "&editar=" + encodeURIComponent(item.cod_movimiento)}
                                                >
                                                    Editar
                                                </a>

                                                <a
                                                    className="boton boton-chico boton-eliminar"
                                                    href={"mov_salida.php?" + parametros + "&borrar=" + encodeURIComponent(item.cod_movimiento)}
                                                    onClick={(e) => {
                                                        if (!confirm("¿Seguro que deseas eliminar este movimiento?")) {
                                                            e.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    Eliminar
                                                </a>
                                            </td>
                                        </tr>

                                    ))

                                ) : (

                                    <tr>
                                        <td colSpan="6" className="tabla-vacia">No se encontraron registros de salidas.</td>
                                    </tr>

                                )}

                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        );
    }

    ReactDOM.createRoot(document.getElementById("app-salidas")).render(<AplicacionSalidas />);

</script>
<?php endif; ?>
</body>
</html>