<?php
// Verificar el usuario y su cargo.
$usuario = trim((string) ($_GET["usuario"] ?? ""));
$cargo = (string) ($_GET["rol"] ?? "");

if ($usuario === "" || ($cargo !== "1" && $cargo !== "2")) {
    header("Location: index.html");
    exit();
}

// Mantener el usuario y el cargo en los enlaces.
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($cargo);
$parametrosHtml = htmlspecialchars($parametros, ENT_QUOTES, "UTF-8");

$esAdmin = ($cargo === "1");
$inicial = strtoupper(substr(trim($usuario), 0, 1));
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";

// Proteger los datos que se muestran en la página.
function escapar($dato): string {
    return htmlspecialchars((string) $dato, ENT_QUOTES, "UTF-8");
}

// Mostrar los iconos de la página.
function icono(string $nombre): string {
    return '<img src="img/' . escapar($nombre) . '.png" alt="" class="icono">';
}

// Dar formato al precio.
function formatoMoneda($valor): string {
    return "$" . number_format((float) $valor, 2, ",", ".");
}

// Conectar con la base de datos.
require_once "conexion.php";

if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("No se encontró una conexión válida en conexion.php.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$mensaje = "";
$tipoMensaje = "";
$movimientoEditar = null;
$busqueda = "";
$resultado = null;

$proveedorTexto = "";
$proveedorCodigo = "";
$productoTexto = "";
$productoCodigo = "";

// Buscar proveedores y productos para el autocompletado.
function responderBusqueda(mysqli $conn, string $tabla, string $campoCodigo, string $textoBuscado): void {
    header("Content-Type: application/json; charset=utf-8");

    try {
        $textoBuscado = trim($textoBuscado);

        if ($textoBuscado === "") {
            echo json_encode([]);
            exit;
        }

        $parteCodigo = trim(explode(" - ", $textoBuscado)[0]);
        $filtro = "%" . $textoBuscado . "%";
        $filtroCodigo = "%" . $parteCodigo . "%";

        $stmt = $conn->prepare(
            "SELECT $campoCodigo AS codigo, nombre
             FROM $tabla
             WHERE CAST($campoCodigo AS CHAR) LIKE ? OR nombre LIKE ?
             ORDER BY
                CASE WHEN CAST($campoCodigo AS CHAR) = ? OR nombre = ? THEN 0 ELSE 1 END,
                nombre ASC
             LIMIT 10"
        );

        $stmt->bind_param("ssss", $filtroCodigo, $filtro, $parteCodigo, $textoBuscado);
        $stmt->execute();

        $lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode($lista, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([]);
    }

    exit;
}

// Consultar el nombre de un proveedor o producto.
function textoRelacion(mysqli $conn, string $tabla, string $campoCodigo, int $codigo): string {
    $stmt = $conn->prepare("SELECT nombre FROM $tabla WHERE $campoCodigo = ?");
    $stmt->bind_param("i", $codigo);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ? $codigo . " - " . $fila["nombre"] : "";
}

// Buscar proveedores y productos.
if (isset($_GET["buscar_proveedor"])) {
    responderBusqueda($conn, "proveedor", "cod_proveedor", (string) $_GET["buscar_proveedor"]);
}

if (isset($_GET["buscar_producto"])) {
    responderBusqueda($conn, "producto", "cod_producto", (string) $_GET["buscar_producto"]);
}

// Eliminar movimientos solamente como administrador.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_movimiento"])) {
    try {
        if (!$esAdmin) {
            throw new InvalidArgumentException("No tienes permiso para eliminar movimientos de entrada.");
        }

        $codigo = trim((string) ($_POST["cod_movimiento"] ?? ""));

        if (!ctype_digit($codigo)) {
            throw new InvalidArgumentException("Código de movimiento no válido.");
        }

        $codNum = (int) $codigo;
        $stmt = $conn->prepare("DELETE FROM mov_entrada WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codNum);
        $stmt->execute();

        $eliminados = $stmt->affected_rows;
        $stmt->close();

        if ($eliminados > 0) {
            header("Location: mov_entrada.php?" . $parametros . "&mensaje=eliminado");
            exit();
        }

        throw new InvalidArgumentException("No se encontró el movimiento de entrada que deseas eliminar.");
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1451) {
            $mensaje = "No se puede eliminar el movimiento porque está relacionado con otros registros.";
        } else {
            $mensaje = "Error al eliminar el movimiento de entrada.";
        }

        $tipoMensaje = "error";
    }
}

// Cargar los datos del movimiento que se va a editar.
if (($_GET["accion"] ?? "") === "editar") {
    try {
        if (!$esAdmin) {
            throw new InvalidArgumentException("No tienes permiso para editar movimientos de entrada.");
        }

        $codigo = trim((string) ($_GET["cod_movimiento"] ?? ""));

        if (!ctype_digit($codigo)) {
            throw new InvalidArgumentException("Código de movimiento no válido.");
        }

        $codNum = (int) $codigo;
        $stmt = $conn->prepare("SELECT * FROM mov_entrada WHERE cod_movimiento = ?");
        $stmt->bind_param("i", $codNum);
        $stmt->execute();

        $movimientoEditar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$movimientoEditar) {
            throw new InvalidArgumentException("No se encontró el movimiento seleccionado.");
        }

        $proveedorCodigo = $movimientoEditar["cod_proveedor"];
        $productoCodigo = $movimientoEditar["cod_producto"];

        $proveedorTexto = textoRelacion($conn, "proveedor", "cod_proveedor", (int) $proveedorCodigo);
        $productoTexto = textoRelacion($conn, "producto", "cod_producto", (int) $productoCodigo);
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        $mensaje = "Error al cargar el movimiento de entrada.";
        $tipoMensaje = "error";
    }
}

// Guardar un movimiento nuevo o actualizar uno existente.
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST["guardar"]) || isset($_POST["actualizar"]))) {
    $esActualizar = isset($_POST["actualizar"]);

    $codigo = trim((string) ($_POST["cod_movimiento"] ?? ""));
    $fecha = trim((string) ($_POST["fecha"] ?? ""));
    $precio = trim((string) ($_POST["precio"] ?? ""));
    $cantidad = trim((string) ($_POST["cantidad"] ?? ""));
    $nota = trim((string) ($_POST["nota"] ?? ""));
    $codProveedor = trim((string) ($_POST["cod_proveedor"] ?? ""));
    $codProducto = trim((string) ($_POST["cod_producto"] ?? ""));

    try {
        if ($esActualizar && !$esAdmin) {
            throw new InvalidArgumentException("No tienes permiso para actualizar movimientos de entrada.");
        }

        if (
            !ctype_digit($codigo) || $fecha === "" ||
            !is_numeric($precio) || (float) $precio < 0 ||
            !ctype_digit($cantidad) || (int) $cantidad <= 0 ||
            !ctype_digit($codProveedor) || !ctype_digit($codProducto)
        ) {
            throw new InvalidArgumentException("Completa todos los campos obligatorios con valores válidos.");
        }

        $codNum = (int) $codigo;
        $precioNum = (float) $precio;
        $cantidadNum = (int) $cantidad;
        $provNum = (int) $codProveedor;
        $prodNum = (int) $codProducto;

        if ($esActualizar) {
            $stmt = $conn->prepare(
                "UPDATE mov_entrada
                 SET fecha = ?, precio = ?, cantidad = ?, nota = ?, cod_proveedor = ?, cod_producto = ?
                 WHERE cod_movimiento = ?"
            );

            $stmt->bind_param("sdisiii", $fecha, $precioNum, $cantidadNum, $nota, $provNum, $prodNum, $codNum);
            $destino = "actualizado";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO mov_entrada
                 (cod_movimiento, fecha, precio, cantidad, nota, cod_proveedor, cod_producto)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param("isdisii", $codNum, $fecha, $precioNum, $cantidadNum, $nota, $provNum, $prodNum);
            $destino = "registrado";
        }

        $stmt->execute();
        $stmt->close();

        header("Location: mov_entrada.php?" . $parametros . "&mensaje=" . $destino);
        exit();
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            $mensaje = "El código del movimiento ya está registrado.";
        } elseif ($e->getCode() === 1452) {
            $mensaje = "El proveedor o producto seleccionado no existe.";
        } else {
            $mensaje = "Error al procesar la solicitud.";
        }

        $tipoMensaje = "error";
    }
}

// Mostrar el mensaje de cada acción.
$mensajesOk = [
    "registrado" => "Movimiento de entrada registrado correctamente.",
    "actualizado" => "Movimiento de entrada actualizado correctamente.",
    "eliminado" => "Movimiento de entrada eliminado correctamente."
];

$clave = (string) ($_GET["mensaje"] ?? "");

if (isset($mensajesOk[$clave])) {
    $mensaje = $mensajesOk[$clave];

    if ($clave === "eliminado") {
        $tipoMensaje = "eliminado";
    } else {
        $tipoMensaje = "ok";
    }
}

// Buscar y mostrar movimientos para el administrador.
if ($esAdmin) {
    try {
        $busqueda = trim((string) ($_GET["buscar"] ?? ""));

        $sql = "SELECT m.cod_movimiento, m.fecha, m.precio, m.cantidad, m.nota,
                       m.cod_proveedor, p.nombre AS nombre_proveedor,
                       m.cod_producto, pr.nombre AS nombre_producto
                FROM mov_entrada m
                LEFT JOIN proveedor p ON m.cod_proveedor = p.cod_proveedor
                LEFT JOIN producto pr ON m.cod_producto = pr.cod_producto";

        if ($busqueda !== "") {
            $filtro = "%" . $busqueda . "%";

            $stmt = $conn->prepare(
                $sql . " WHERE CAST(m.cod_movimiento AS CHAR) LIKE ?
                            OR m.fecha LIKE ?
                            OR CAST(m.cod_proveedor AS CHAR) LIKE ?
                            OR p.nombre LIKE ?
                            OR CAST(m.cod_producto AS CHAR) LIKE ?
                            OR pr.nombre LIKE ?
                         ORDER BY m.cod_movimiento DESC"
            );

            $stmt->bind_param("ssssss", $filtro, $filtro, $filtro, $filtro, $filtro, $filtro);
            $stmt->execute();
            $resultado = $stmt->get_result();
        } else {
            $resultado = $conn->query($sql . " ORDER BY m.cod_movimiento DESC");
        }
    } catch (mysqli_sql_exception $e) {
        $mensaje = "No se pudo cargar el listado de movimientos de entrada.";
        $tipoMensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Entrada | La Granja</title>
    <link rel="stylesheet" href="css/mov_entrada.css">
</head>
<body class="pagina-entrada">

<header class="encabezado">
    <button type="button" class="boton-menu" id="abrirMenu"
        aria-label="Abrir menú lateral" aria-controls="menuLateral" aria-expanded="false">
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

            <a href="mov_salida.php?<?= $parametrosHtml ?>" class="enlace-menu">
                <span class="caja-icono"><?= icono("salida") ?></span>
                <span>Movimiento de salida</span>
            </a>

            <?php if ($esAdmin): ?>
                <a href="producto.php?<?= $parametrosHtml ?>" class="enlace-menu">
                    <span class="caja-icono"><?= icono("producto") ?></span>
                    <span>Productos</span>
                </a>

                <a href="mov_entrada.php?<?= $parametrosHtml ?>" class="enlace-menu activo" aria-current="page">
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
        <span class="titulo-icono"><?= icono("entrada") ?></span>
        <div>
            <h2>Movimiento de Entrada</h2>
            <p>
                <?= $esAdmin
                    ? "Registra, busca, edita o elimina los movimientos de entrada."
                    : "Registra una nueva entrada de productos en el inventario." ?>
            </p>
        </div>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- Formulario de entrada para los usuarios -->
    <section class="panel">
        <h3 class="panel-titulo">
            <?= $movimientoEditar ? "Editar movimiento de entrada" : "Ingresar movimiento de entrada" ?>
        </h3>

        <form action="mov_entrada.php?<?= $parametrosHtml ?>" method="POST" id="formularioEntrada" class="formulario">
            <input type="hidden" name="<?= $movimientoEditar ? "actualizar" : "guardar" ?>" value="1">

            <?php if ($movimientoEditar): ?>
                <input type="hidden" name="cod_movimiento" value="<?= escapar($movimientoEditar["cod_movimiento"]) ?>">
                <div class="campo">
                    <label for="codigo_visible">Código de movimiento</label>
                    <input id="codigo_visible" type="number" value="<?= escapar($movimientoEditar["cod_movimiento"]) ?>" disabled>
                </div>
            <?php else: ?>
                <div class="campo">
                    <label for="cod_movimiento">Código de movimiento</label>
                    <input id="cod_movimiento" type="number" name="cod_movimiento" min="1" required placeholder="Ingrese código">
                </div>
            <?php endif; ?>

            <div class="campo">
                <label for="fecha">Fecha</label>
                <input id="fecha" type="date" name="fecha" required
                    value="<?= escapar($movimientoEditar["fecha"] ?? date("Y-m-d")) ?>">
            </div>

            <div class="campo">
                <label for="precio">Precio unitario</label>
                <input id="precio" type="number" step="0.01" min="0" name="precio" required
                    value="<?= escapar($movimientoEditar["precio"] ?? "") ?>" placeholder="Ingrese precio">
            </div>

            <div class="campo">
                <label for="cantidad">Cantidad</label>
                <input id="cantidad" type="number" min="1" step="1" name="cantidad" required
                    value="<?= escapar($movimientoEditar["cantidad"] ?? "") ?>" placeholder="Ingrese cantidad">
            </div>

            <!-- Seleccionar el proveedor -->
            <div class="campo">
                <label for="proveedor_busqueda">Proveedor</label>
                <input type="text" id="proveedor_busqueda" list="lista_proveedores" autocomplete="off"
                    placeholder="Escriba código o nombre" value="<?= escapar($proveedorTexto) ?>" required>
                <datalist id="lista_proveedores"></datalist>
                <input type="hidden" name="cod_proveedor" id="cod_proveedor" value="<?= escapar($proveedorCodigo) ?>">
                <p id="estado_proveedor" class="texto-campo-error"></p>
            </div>

            <!-- Seleccionar el producto -->
            <div class="campo">
                <label for="producto_busqueda">Producto</label>
                <input type="text" id="producto_busqueda" list="lista_productos" autocomplete="off"
                    placeholder="Escriba código o nombre" value="<?= escapar($productoTexto) ?>" required>
                <datalist id="lista_productos"></datalist>
                <input type="hidden" name="cod_producto" id="cod_producto" value="<?= escapar($productoCodigo) ?>">
                <p id="estado_producto" class="texto-campo-error"></p>
            </div>

            <!-- Escribir una observación -->
            <div class="campo campo-ancho">
                <label for="nota">Nota u observación</label>
                <textarea id="nota" name="nota" placeholder="Ingrese nota u observación"><?= escapar($movimientoEditar["nota"] ?? "") ?></textarea>
            </div>

            <div class="botones campo-ancho">
                <?php if ($movimientoEditar): ?>
                    <button type="submit" class="boton boton-principal">Actualizar movimiento</button>
                    <a href="mov_entrada.php?<?= $parametrosHtml ?>" class="boton boton-secundario">Cancelar</a>
                <?php else: ?>
                    <button type="submit" class="boton boton-principal">Registrar movimiento</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Buscador y tabla del administrador -->
    <?php if ($esAdmin): ?>
        <section class="panel">
            <h3 class="panel-titulo">Buscar movimiento de entrada</h3>

            <form action="mov_entrada.php" method="GET" class="buscador">
                <input type="hidden" name="usuario" value="<?= escapar($usuario) ?>">
                <input type="hidden" name="rol" value="<?= escapar($cargo) ?>">

                <input type="text" name="buscar" placeholder="Código, fecha, proveedor o producto..."
                    value="<?= escapar($busqueda) ?>">

                <button type="submit" class="boton boton-principal">Buscar</button>

                <?php if ($busqueda !== ""): ?>
                    <a href="mov_entrada.php?<?= $parametrosHtml ?>" class="boton boton-secundario">Mostrar todos</a>
                <?php endif; ?>
            </form>
        </section>

        <section class="panel">
            <h3 class="panel-titulo">Listado de movimientos de entrada</h3>

            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Precio</th>
                            <th>Cantidad</th>
                            <th>Nota</th>
                            <th>Proveedor</th>
                            <th>Producto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while ($fila = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?= escapar($fila["cod_movimiento"]) ?></td>
                                    <td><?= escapar($fila["fecha"]) ?></td>
                                    <td><?= formatoMoneda($fila["precio"]) ?></td>
                                    <td><?= escapar($fila["cantidad"]) ?></td>
                                    <td><?= escapar($fila["nota"] ?? "") ?></td>
                                    <td><?= escapar($fila["cod_proveedor"] . " - " . ($fila["nombre_proveedor"] ?? "Sin nombre")) ?></td>
                                    <td><?= escapar($fila["cod_producto"] . " - " . ($fila["nombre_producto"] ?? "Sin nombre")) ?></td>
                                    <td class="tabla-acciones">
                                        <a class="boton boton-chico boton-editar"
                                            href="mov_entrada.php?<?= $parametrosHtml ?>&amp;accion=editar&amp;cod_movimiento=<?= urlencode((string) $fila["cod_movimiento"]) ?>">
                                            Editar
                                        </a>

                                        <form action="mov_entrada.php?<?= $parametrosHtml ?>" method="POST" class="form-eliminar"
                                            onsubmit="return confirm('¿Seguro que deseas eliminar este movimiento?');">
                                            <input type="hidden" name="cod_movimiento" value="<?= escapar($fila["cod_movimiento"]) ?>">
                                            <button type="submit" name="eliminar_movimiento" value="1"
                                                class="boton boton-chico boton-eliminar">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="tabla-vacia">No se encontraron movimientos de entrada.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer class="pie-pagina">
    <div class="footer-contenido">
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">
                Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.
            </p>

            <a href="ayuda.php?<?= $parametrosHtml ?>" class="footer-enlace"><span class="footer-icono">ⓘ</span>Centro de ayuda</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#privacidad" class="footer-enlace"><span class="footer-icono">♧</span>Privacidad y seguridad</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#uso" class="footer-enlace"><span class="footer-icono">▤</span>Manual de uso del sistema</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#soporte" class="footer-enlace"><span class="footer-icono">?</span>Asistencia técnica</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#inventario" class="footer-enlace"><span class="footer-icono">▣</span>Gestión y control de inventario</a>
            <a href="ayuda.php?<?= $parametrosHtml ?>#movimientos" class="footer-enlace"><span class="footer-icono">↔</span>Movimientos de entrada y salida</a>
        </section>

        <section class="footer-informacion">
            <h4>INFORMACIÓN DEL SISTEMA</h4>

            <p><strong>Plataforma administrativa</strong><br>
                Herramienta para organizar y controlar los productos del supermercado.</p>

            <p><strong>Gestión de clientes</strong><br>
                Registro, consulta y actualización de la información de los clientes.</p>

            <p><strong>Control de movimientos</strong><br>
                Seguimiento de las entradas y salidas de mercancía.</p>

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
// Abrir y cerrar el menú lateral.
const abrir = document.getElementById("abrirMenu");
const cerrar = document.getElementById("cerrarMenu");
const fondo = document.getElementById("fondoMenu");

abrir.addEventListener("click", function () {
    document.body.classList.add("menu-abierto");
    abrir.setAttribute("aria-expanded", "true");
});

function ocultarMenu() {
    document.body.classList.remove("menu-abierto");
    abrir.setAttribute("aria-expanded", "false");
}

cerrar.addEventListener("click", ocultarMenu);
fondo.addEventListener("click", ocultarMenu);

document.addEventListener("keydown", function (evento) {
    if (evento.key === "Escape") {
        ocultarMenu();
    }
});

// Buscar proveedores y productos.
function crearAutocompletado(datos) {
    const input = document.getElementById(datos.campo);
    const lista = document.getElementById(datos.lista);
    const oculto = document.getElementById(datos.oculto);
    const estado = document.getElementById(datos.estado);

    async function buscar(texto) {
        const respuesta = await fetch(
            `mov_entrada.php?<?= $parametros ?>&${datos.parametro}=${encodeURIComponent(texto)}`
        );

        if (!respuesta.ok) {
            throw new Error("No se pudo buscar.");
        }

        return respuesta.json();
    }

    input.addEventListener("input", async function () {
        oculto.value = "";
        estado.textContent = "";

        const texto = input.value.trim();

        if (texto === "") {
            lista.replaceChildren();
            return;
        }

        try {
            const opciones = await buscar(texto);
            lista.replaceChildren();

            opciones.forEach(function (fila) {
                const opcion = document.createElement("option");
                opcion.value = `${fila.codigo} - ${fila.nombre}`;
                lista.appendChild(opcion);
            });
        } catch (error) {
            estado.textContent = `Error al buscar ${datos.nombre}.`;
        }
    });

    async function resolver() {
        const texto = input.value.trim();

        if (texto === "") {
            oculto.value = "";
            estado.textContent = `Escriba el código o el nombre del ${datos.nombre}.`;
            return false;
        }

        try {
            const opciones = await buscar(texto);

            const encontrada = opciones.find(function (fila) {
                return texto === String(fila.codigo).trim() ||
                    texto.toLowerCase() === String(fila.nombre).trim().toLowerCase() ||
                    texto === `${fila.codigo} - ${fila.nombre}`;
            });

            if (!encontrada) {
                oculto.value = "";
                estado.textContent = `${datos.nombre} no encontrado. Seleccione una sugerencia.`;
                return false;
            }

            oculto.value = String(encontrada.codigo);
            input.value = `${encontrada.codigo} - ${encontrada.nombre}`;
            estado.textContent = "";
            return true;
        } catch (error) {
            oculto.value = "";
            estado.textContent = `No se pudo verificar el ${datos.nombre}.`;
            return false;
        }
    }

    input.addEventListener("change", resolver);
    return resolver;
}

// Preparar la búsqueda del proveedor y del producto.
const resolverProveedor = crearAutocompletado({
    campo: "proveedor_busqueda",
    lista: "lista_proveedores",
    oculto: "cod_proveedor",
    estado: "estado_proveedor",
    parametro: "buscar_proveedor",
    nombre: "proveedor"
});

const resolverProducto = crearAutocompletado({
    campo: "producto_busqueda",
    lista: "lista_productos",
    oculto: "cod_producto",
    estado: "estado_producto",
    parametro: "buscar_producto",
    nombre: "producto"
});

// Revisar los datos antes de guardar.
const formEntrada = document.getElementById("formularioEntrada");
let validado = false;

formEntrada.addEventListener("submit", async function (evento) {
    if (validado) {
        return;
    }

    evento.preventDefault();

    if (!await resolverProveedor()) {
        alert("Seleccione un proveedor registrado de las sugerencias.");
        document.getElementById("proveedor_busqueda").focus();
        return;
    }

    if (!await resolverProducto()) {
        alert("Seleccione un producto registrado de las sugerencias.");
        document.getElementById("producto_busqueda").focus();
        return;
    }

    validado = true;
    formEntrada.requestSubmit();
});
</script>

</body>
</html>
