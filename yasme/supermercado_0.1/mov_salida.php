<?php

// usuario y rol llegan por la url
$usuario = trim((string) ($_GET["usuario"] ?? ""));
$cargo = (string) ($_GET["rol"] ?? "");

if ($usuario === "" || ($cargo !== "1" && $cargo !== "2")) {
    header("Location: index.html");
    exit();
}

// se agregan a los enlaces para no perder la sesion
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($cargo);
$parametrosHtml = htmlspecialchars($parametros, ENT_QUOTES, "UTF-8");

$esAdmin = ($cargo === "1");
$inicial = strtoupper(substr(trim($usuario), 0, 1));
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";

// evita que el texto rompa el html
function escapar($dato): string {
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

function icono(string $nombre): string {
    return '<img src="img/' . escapar($nombre) . '.png" alt="" class="icono">';
}

// da formato de pesos a un numero
function formatoMoneda($valor): string {
    return "$" . number_format((float)$valor, 2, ",", ".");
}

// conexion a la base de datos
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once "conexion.php";

if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("Error: No se encontró una conexión válida a la base de datos.");
}

$conn->set_charset("utf8mb4");

// arma la factura para imprimir
function mostrarFactura($conn, $codigo) {
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

    // productos de la factura y su total
    $detalles = [];
    $total = 0;

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
            $total += ($detalle["precio"] * $detalle["cantidad"]);
        }
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
        <script>
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
    try {
        $codigoProducto = (int) $_GET["producto_codigo"];
        $stmt = $conn->prepare("SELECT cod_producto, nombre, precio FROM producto WHERE cod_producto = ?");
        $stmt->bind_param("i", $codigoProducto);
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
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["existe" => false]);
    }
    exit();
}

// busca clientes por cedula o nombre (ajax)
if (isset($_GET["buscar_cliente"])) {
    header("Content-Type: application/json; charset=utf-8");
    try {
        $textoCliente = trim($_GET["buscar_cliente"]);
        if ($textoCliente === "") {
            echo json_encode([]);
            exit();
        }

        $parteCedula = explode(" - ", $textoCliente)[0];
        $filtro = "%" . $textoCliente . "%";
        $filtroCedula = "%" . trim($parteCedula) . "%";

        $stmt = $conn->prepare(
            "SELECT cedula_cliente, nombre FROM cliente
             WHERE CAST(cedula_cliente AS CHAR) LIKE ? OR nombre LIKE ?
             ORDER BY CASE WHEN CAST(cedula_cliente AS CHAR) = ? THEN 0 WHEN nombre = ? THEN 0 ELSE 1 END, nombre ASC
             LIMIT 10"
        );
        $stmt->bind_param("ssss", $filtroCedula, $filtro, $parteCedula, $textoCliente);
        $stmt->execute();
        $resClientes = $stmt->get_result();

        $lista = [];
        while ($c = $resClientes->fetch_assoc()) {
            $lista[] = ["cedula" => $c["cedula_cliente"], "nombre" => $c["nombre"]];
        }
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
$tipoMensaje = "";
$factura_editar = null;
$detalles_editar = [];
$cedulaSeleccionada = "";
$clienteSeleccionadoTexto = "";
$busqueda = trim((string)($_GET["buscar"] ?? ""));

// eliminar movimiento (solo admin)
if (isset($_GET["borrar"])) {
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para eliminar movimientos.";
        $tipoMensaje = "error";
    } else {
        $codigo = (int)$_GET["borrar"];
        $transaccion = false;
        try {
            $conn->begin_transaction();
            $transaccion = true;

            $stmt = $conn->prepare("DELETE FROM detalle_salida WHERE cod_movimiento = ?");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM factura_salida WHERE cod_movimiento = ?");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
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
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para editar movimientos.";
        $tipoMensaje = "error";
    } else {
        $codigo = (int)$_GET["editar"];
        $stmt = $conn->prepare("SELECT * FROM factura_salida WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $factura_editar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($factura_editar) {
            $stmt = $conn->prepare("SELECT * FROM detalle_salida WHERE cod_movimiento = ? ORDER BY id_detalle");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();
            $resDet = $stmt->get_result();
            while ($d = $resDet->fetch_assoc()) {
                $detalles_editar[] = $d;
            }
            $stmt->close();

            $cedulaSeleccionada = (int) $factura_editar["cedula_cliente"];
            $stmt = $conn->prepare("SELECT cedula_cliente, nombre FROM cliente WHERE cedula_cliente = ?");
            $stmt->bind_param("i", $cedulaSeleccionada);
            $stmt->execute();
            $cli = $stmt->get_result()->fetch_assoc();
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
            $stmt = $conn->prepare("SELECT cedula_cliente FROM cliente WHERE cedula_cliente = ?");
            $stmt->bind_param("i", $cedula);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $stmt->close();
                throw new Exception("El cliente seleccionado no se encuentra registrado.");
            }
            $stmt->close();

            if ($accion === "actualizar") {
                $stmt = $conn->prepare("UPDATE factura_salida SET cedula_cliente = ? WHERE cod_movimiento = ?");
                $stmt->bind_param("ii", $cedula, $codigo);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM detalle_salida WHERE cod_movimiento = ?");
                $stmt->bind_param("i", $codigo);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO factura_salida (fecha, nota, cedula_cliente) VALUES (CURDATE(), '', ?)");
                $stmt->bind_param("i", $cedula);
                $stmt->execute();
                $codigo = $conn->insert_id;
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

                $stmtDetalle->bind_param("iisdi", $codigo, $codP, $nomP, $preP, $cantP);
                $stmtDetalle->execute();
            }

            $consultaStock->close();
            $stmtDetalle->close();

            $conn->commit();
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
    $codigoGuardado = (int)$_GET["guardado"];
    $mensaje = "Movimiento #{$codigoGuardado} procesado exitosamente.";
    $tipoMensaje = "ok";
}

// consultas de las tablas del admin
if ($esAdmin) {
    // total de salidas por producto
    $resultadoResumen = $conn->query(
        "SELECT d.cod_producto, d.nombre_producto, MAX(d.precio) AS precio_unitario, SUM(d.cantidad) AS cantidad_total
         FROM detalle_salida d
         INNER JOIN factura_salida f ON f.cod_movimiento = d.cod_movimiento
         GROUP BY d.cod_producto, d.nombre_producto
         ORDER BY d.nombre_producto ASC"
    );

    // lista de facturas con su total
    $sqlFacturas = "SELECT f.cod_movimiento, f.fecha, f.cedula_cliente, c.nombre AS nombre_cliente,
                           COALESCE(SUM(d.precio * d.cantidad), 0) AS total,
                           COUNT(d.id_detalle) AS numero_productos
                    FROM factura_salida f
                    LEFT JOIN cliente c ON f.cedula_cliente = c.cedula_cliente
                    LEFT JOIN detalle_salida d ON f.cod_movimiento = d.cod_movimiento";

    if ($busqueda !== "") {
        $sqlFacturas .= " WHERE CAST(f.cod_movimiento AS CHAR) LIKE ?
                             OR CAST(f.cedula_cliente AS CHAR) LIKE ?
                             OR f.fecha LIKE ?
                             OR c.nombre LIKE ?";
    }

    $sqlFacturas .= " GROUP BY f.cod_movimiento, f.fecha, f.cedula_cliente, c.nombre ORDER BY f.cod_movimiento DESC";

    $stmtLista = $conn->prepare($sqlFacturas);
    if ($busqueda !== "") {
        $f = "%" . $busqueda . "%";
        $stmtLista->bind_param("ssss", $f, $f, $f, $f);
    }
    $stmtLista->execute();
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
    <link rel="stylesheet" href="css/mov_salida.css">
</head>
<body class="pagina-salida">

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
    <div class="encabezado-usuario">
        <span class="usuario-inicial"><?= escapar($inicial) ?></span>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>
</header>

<div class="fondo-menu" id="fondoMenu"></div>

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

    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= escapar($inicial) ?></div>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>

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

    <form action="menu.php?<?= $parametrosHtml ?>" method="POST" class="form-salir">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja"><?= icono("salir") ?></span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<main class="contenido">
    <section class="titulo-pagina">
        <span class="titulo-icono"><?= icono("salida") ?></span>
        <div>
            <h2>Movimiento de Salida</h2>
            <p><?= $esAdmin ? "Consulta, edita o elimina los movimientos de salida registrados." : "Registra salidas de productos e imprime facturas de venta." ?></p>
        </div>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- formulario de salida -->
    <?php if ($mostrarForm): ?>
        <section class="panel">
            <h3 class="panel-titulo"><?= $factura_editar ? "Editar Movimiento #".escapar($factura_editar["cod_movimiento"]) : "Registrar Salida de Productos" ?></h3>

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
        <section class="panel">
            <h3 class="panel-titulo">Resumen Consolidado de Salidas por Producto</h3>
            <div class="tabla-contenedor">
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

        <section class="panel">
            <h3 class="panel-titulo">Buscar Movimientos Registrados</h3>
            <form method="GET" action="mov_salida.php" class="buscador">
                <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?>">
                <input type="hidden" name="rol" value="<?= htmlspecialchars($cargo, ENT_QUOTES, "UTF-8") ?>">
                <input type="text" name="buscar" placeholder="Código, cédula o cliente..." value="<?= escapar($busqueda) ?>">
                <button type="submit" class="boton boton-principal">Buscar</button>
                <?php if ($busqueda !== ""): ?>
                    <a href="mov_salida.php?<?= $parametrosHtml ?>" class="boton boton-secundario">Mostrar Todos</a>
                <?php endif; ?>
            </form>
        </section>

        <section class="panel">
            <h3 class="panel-titulo">Historial de Salidas / Facturas</h3>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Cédula - Cliente</th>
                            <th class="derecha">Productos</th>
                            <th class="derecha">Total Venta</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultadoFacturas && $resultadoFacturas->num_rows > 0): ?>
                            <?php while ($fila = $resultadoFacturas->fetch_assoc()): ?>
                                <tr>
                                    <td><?= escapar($fila["cod_movimiento"]) ?></td>
                                    <td><?= escapar($fila["fecha"]) ?></td>
                                    <td><?= escapar($fila["cedula_cliente"]) . " - " . escapar($fila["nombre_cliente"] ?? "Sin Nombre") ?></td>
                                    <td class="derecha"><?= escapar($fila["numero_productos"]) ?></td>
                                    <td class="derecha"><?= formatoMoneda($fila["total"]) ?></td>
                                    <td class="tabla-acciones">
                                        <button type="button" class="boton boton-chico boton-factura" onclick="generarFactura(<?= $fila['cod_movimiento'] ?>)">Factura</button>
                                        <a href="mov_salida.php?<?= $parametrosHtml ?>&amp;editar=<?= $fila['cod_movimiento'] ?>" class="boton boton-chico boton-editar">Editar</a>
                                        <a href="mov_salida.php?<?= $parametrosHtml ?>&amp;borrar=<?= $fila['cod_movimiento'] ?>" class="boton boton-chico boton-eliminar" onclick="return confirm('¿Seguro que deseas eliminar este movimiento?');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="tabla-vacia">No se encontraron registros de salidas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<iframe name="marcoFactura" id="marcoFactura" title="Factura" style="display: none;"></iframe>

<footer class="pie-pagina">
    <div class="footer-contenido">
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

    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<script>
(() => {
    // abrir y cerrar el menu lateral
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

    if (abrir) abrir.addEventListener("click", mostrarMenu);
    if (cerrar) cerrar.addEventListener("click", ocultarMenu);
    if (fondo) fondo.addEventListener("click", ocultarMenu);
    document.addEventListener("keydown", e => { if (e.key === "Escape") ocultarMenu(); });
})();

// busqueda de clientes, productos y total
const campoCliente = document.getElementById("cliente_busqueda");
const campoCedula = document.getElementById("cedula_cliente");
const listaClientes = document.getElementById("lista_clientes");
const estadoCliente = document.getElementById("estado_cliente");
let consultaClientes = 0;

function formato(valor) {
    return valor.toLocaleString("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 2 });
}

function generarFactura(codigo) {
    const marco = document.getElementById("marcoFactura");
    marco.src = "mov_salida.php?<?= $parametros ?>&factura=" + encodeURIComponent(codigo) + "&t=" + Date.now();
}

if (campoCliente) {
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

    campoCliente.addEventListener("change", async function () {
        const texto = campoCliente.value.trim();
        if (texto === "") return;

        try {
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

function conectarFila(fila) {
    const codigo = fila.querySelector(".codigo");
    const nombre = fila.querySelector(".nombre-producto");
    const precio = fila.querySelector(".precio");
    const cantidad = fila.querySelector(".cantidad");
    const quitar = fila.querySelector(".quitar");

    codigo.addEventListener("change", () => buscarProducto(fila));
    codigo.addEventListener("keydown", (e) => {
        if (e.key === "Enter") { e.preventDefault(); buscarProducto(fila); }
    });

    nombre.addEventListener("input", calcularTotal);
    precio.addEventListener("input", calcularTotal);
    cantidad.addEventListener("input", calcularTotal);

    quitar.addEventListener("click", () => {
        fila.remove();
        calcularTotal();
    });
}

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

async function buscarProducto(fila) {
    const campoCod = fila.querySelector(".codigo");
    const campoNom = fila.querySelector(".nombre-producto");
    const campoPre = fila.querySelector(".precio");
    const cod = campoCod.value.trim();

    if (cod === "") {
        campoNom.value = ""; campoPre.value = ""; calcularTotal(); return;
    }

    try {
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

function calcularTotal() {
    let total = 0;
    document.querySelectorAll("#productos tr").forEach(fila => {
        const precio = parseFloat(fila.querySelector(".precio").value) || 0;
        const cantidad = parseInt(fila.querySelector(".cantidad").value) || 0;
        const subtotal = precio * cantidad;

        fila.querySelector(".subtotal").textContent = formato(subtotal);
        total += subtotal;
    });
    const elemTotal = document.getElementById("totalFactura");
    if (elemTotal) elemTotal.textContent = formato(total);
}

document.querySelectorAll("#productos tr").forEach(conectarFila);
if (document.querySelectorAll("#productos tr").length === 0 && document.getElementById("productos")) {
    agregarProducto();
}
calcularTotal();
</script>
</body>
</html>