<?php

// 1. usuario y permisos cargo 1 administrador | cargo 2 empleado
// el usuario y el cargo llegan por la url (usuario y rol).
$usuario = trim((string) ($_GET["usuario"] ?? ""));
$cargo = (string) ($_GET["rol"] ?? "");

if ($usuario === "" || ($cargo !== "1" && $cargo !== "2")) {
    header("Location: index.html");
    exit();
}

// datos que se agregan a cada enlace y formulario para no perder el usuario.
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($cargo);
$parametrosHtml = htmlspecialchars($parametros, ENT_QUOTES, "UTF-8");

$esAdmin = ($cargo === "1");
$inicial = strtoupper(substr(trim($usuario), 0, 1));
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";

function escapar($dato): string
{
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

function icono(string $nombre): string
{
    return '<img src="img/' . escapar($nombre) . '.png" alt="" class="icono">';
}

// 2. conexión
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
$clienteEditar = null;
$busqueda = "";
$clientes = null;

// 3. eliminar cliente (solo administrador)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_cliente"])) {
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para eliminar clientes.";
        $tipoMensaje = "error";
    } else {
        $cedulaEliminar = trim((string)($_POST["cedula_cliente"] ?? ""));
        try {
            if (!ctype_digit($cedulaEliminar)) {
                throw new InvalidArgumentException("Cédula no válida.");
            }

            $cedulaNumero = (int)$cedulaEliminar;
            $stmt = $conn->prepare("DELETE FROM cliente WHERE cedula_cliente = ?");
            $stmt->bind_param("i", $cedulaNumero);
            $stmt->execute();
            $eliminados = $stmt->affected_rows;
            $stmt->close();

            if ($eliminados > 0) {
                header("Location: cliente.php?" . $parametros . "&mensaje=eliminado");
                exit();
            }

            $mensaje = "No se encontró el cliente que deseas eliminar.";
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            $mensaje = ((int)$e->getCode() === 1451)
                ? "No se puede eliminar el cliente porque tiene movimientos registrados."
                : "Ocurrió un error al eliminar el cliente.";
            $tipoMensaje = "error";
        } catch (InvalidArgumentException $e) {
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

// 4. cargar cliente para editar (solo administrador)
if (isset($_GET["accion"]) && $_GET["accion"] === "editar") {
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para editar clientes.";
        $tipoMensaje = "error";
    } else {
        $cedulaBuscar = trim((string)($_GET["cedula_cliente"] ?? ""));
        try {
            if (!ctype_digit($cedulaBuscar)) {
                throw new InvalidArgumentException("Cédula no válida.");
            }

            $cedulaNumero = (int)$cedulaBuscar;
            $stmt = $conn->prepare("SELECT * FROM cliente WHERE cedula_cliente = ?");
            $stmt->bind_param("i", $cedulaNumero);
            $stmt->execute();
            $clienteEditar = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$clienteEditar) {
                $mensaje = "No se encontró el cliente seleccionado.";
                $tipoMensaje = "error";
            }
        } catch (InvalidArgumentException $e) {
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "No se pudieron cargar los datos del cliente.";
            $tipoMensaje = "error";
        }
    }
}

// 5. actualizar cliente (solo administrador)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar"])) {
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para actualizar clientes.";
        $tipoMensaje = "error";
    } else {
        $cedula = trim((string)($_POST["cedula_cliente"] ?? ""));
        $nombre = trim((string)($_POST["nombre"] ?? ""));
        $telefono = trim((string)($_POST["telefono"] ?? ""));
        $email = trim((string)($_POST["email"] ?? ""));
        $direccion = trim((string)($_POST["direccion"] ?? ""));

        try {
            if (
                !ctype_digit($cedula) || $nombre === "" || $telefono === "" ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) || $direccion === ""
            ) {
                throw new InvalidArgumentException("Completa todos los campos correctamente.");
            }

            $cedulaNumero = (int)$cedula;
            $stmt = $conn->prepare(
                "UPDATE cliente
                 SET nombre = ?, telefono = ?, email = ?, direccion = ?
                 WHERE cedula_cliente = ?"
            );
            $stmt->bind_param("ssssi", $nombre, $telefono, $email, $direccion, $cedulaNumero);
            $stmt->execute();
            $stmt->close();

            header("Location: cliente.php?" . $parametros . "&mensaje=actualizado");
            exit();
        } catch (InvalidArgumentException $e) {
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "Error al actualizar el cliente. Verifica los datos.";
            $tipoMensaje = "error";
        }
    }
}

// 6. registrar cliente (administrador y empleado)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {
    $cedula = trim((string)($_POST["cedula_cliente"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $telefono = trim((string)($_POST["telefono"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $direccion = trim((string)($_POST["direccion"] ?? ""));

    try {
        if (
            !ctype_digit($cedula) || (int)$cedula < 1 ||
            $nombre === "" || $telefono === "" ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) || $direccion === ""
        ) {
            throw new InvalidArgumentException("Completa todos los campos correctamente.");
        }

        $cedulaNumero = (int)$cedula;
        $stmt = $conn->prepare(
            "INSERT INTO cliente (cedula_cliente, nombre, telefono, email, direccion)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("issss", $cedulaNumero, $nombre, $telefono, $email, $direccion);
        $stmt->execute();
        $stmt->close();

        header("Location: cliente.php?" . $parametros . "&mensaje=registrado");
        exit();
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        $mensaje = ((int)$e->getCode() === 1062)
            ? "La cédula ya está registrada."
            : "Error al registrar el cliente. Verifica los datos.";
        $tipoMensaje = "error";
    }
}

// 7. mensajes después de redirecciones
if (isset($_GET["mensaje"])) {
    $mensajes = [
        "registrado" => ["Cliente registrado correctamente.", "ok"],
        "actualizado" => ["Cliente actualizado correctamente.", "ok"],
        "eliminado" => ["Cliente eliminado correctamente.", "error"]
    ];
    $clave = (string)$_GET["mensaje"];
    if (isset($mensajes[$clave])) {
        [$mensaje, $tipoMensaje] = $mensajes[$clave];
    }
}

// 8. listado y búsqueda (solo administrador)
if ($esAdmin) {
    try {
        $busqueda = trim((string)($_GET["buscar"] ?? ""));
        if ($busqueda !== "") {
            $filtro = "%" . $busqueda . "%";
            $stmt = $conn->prepare(
                "SELECT cedula_cliente, nombre, telefono, email, direccion
                 FROM cliente
                 WHERE CAST(cedula_cliente AS CHAR) LIKE ?
                    OR nombre LIKE ?
                    OR telefono LIKE ?
                    OR email LIKE ?
                    OR direccion LIKE ?
                 ORDER BY cedula_cliente ASC"
            );
            $stmt->bind_param("sssss", $filtro, $filtro, $filtro, $filtro, $filtro);
            $stmt->execute();
            $clientes = $stmt->get_result();
        } else {
            $clientes = $conn->query(
                "SELECT cedula_cliente, nombre, telefono, email, direccion
                 FROM cliente
                 ORDER BY cedula_cliente ASC"
            );
        }
    } catch (mysqli_sql_exception $e) {
        $mensaje = "No se pudo cargar el listado de clientes.";
        $tipoMensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes | La Granja</title>
    <link rel="stylesheet" href="css/cliente.css">
</head>
<body class="pagina-cliente">

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

            <a href="cliente.php?<?= $parametrosHtml ?>" class="enlace-menu activo" aria-current="page">
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
        <span class="titulo-icono"><?= icono("cliente") ?></span>
        <div>
            <h2>Clientes</h2>
            <p>
                <?= $esAdmin
                    ? "Busca, edita o elimina los clientes registrados."
                    : "Registra un cliente nuevo en el sistema." ?>
            </p>
        </div>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- formulario: el empleado lo ve siempre; el administrador solo al editar -->
    <?php if (!$esAdmin || $clienteEditar): ?>
    <section class="panel">
        <h3 class="panel-titulo"><?= $clienteEditar ? "Editar cliente" : "Ingresar cliente" ?></h3>

        <form action="cliente.php?<?= $parametrosHtml ?>" method="POST" class="formulario">
            <?php if ($clienteEditar): ?>
                <input type="hidden" name="cedula_cliente"
                    value="<?= escapar($clienteEditar["cedula_cliente"]) ?>">
                <div class="campo">
                    <label for="cedula_visible">Cédula del cliente</label>
                    <input id="cedula_visible" type="number"
                        value="<?= escapar($clienteEditar["cedula_cliente"]) ?>" disabled>
                </div>
            <?php else: ?>
                <div class="campo">
                    <label for="cedula_cliente">Cédula del cliente</label>
                    <input id="cedula_cliente" type="number" name="cedula_cliente" min="1" required
                        placeholder="Ingrese cédula">
                </div>
            <?php endif; ?>

            <div class="campo">
                <label for="nombre">Nombre</label>
                <input id="nombre" type="text" name="nombre" required
                    value="<?= escapar($clienteEditar["nombre"] ?? "") ?>" placeholder="Ingrese nombre">
            </div>

            <div class="campo">
                <label for="telefono">Teléfono</label>
                <input id="telefono" type="text" name="telefono" required
                    value="<?= escapar($clienteEditar["telefono"] ?? "") ?>" placeholder="Ingrese teléfono">
            </div>

            <div class="campo">
                <label for="email">Correo electrónico</label>
                <input id="email" type="email" name="email" required
                    value="<?= escapar($clienteEditar["email"] ?? "") ?>" placeholder="Ingrese correo">
            </div>

            <div class="campo campo-ancho">
                <label for="direccion">Dirección</label>
                <input id="direccion" type="text" name="direccion" required
                    value="<?= escapar($clienteEditar["direccion"] ?? "") ?>" placeholder="Ingrese dirección">
            </div>

            <div class="botones campo-ancho">
                <?php if ($clienteEditar): ?>
                    <button type="submit" name="actualizar" value="1" class="boton boton-principal">
                        Actualizar cliente
                    </button>
                    <a href="cliente.php?<?= $parametrosHtml ?>" class="boton boton-secundario">Cancelar</a>
                <?php else: ?>
                    <button type="submit" name="guardar" value="1" class="boton boton-principal">
                        Registrar cliente
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <!-- buscador y tabla: solo administrador -->
    <?php if ($esAdmin): ?>
        <section class="panel">
            <h3 class="panel-titulo">Buscar cliente</h3>
            <form action="cliente.php" method="GET" class="buscador">
                <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?>">
                <input type="hidden" name="rol" value="<?= htmlspecialchars($cargo, ENT_QUOTES, "UTF-8") ?>">
                <input type="text" name="buscar"
                    placeholder="Cédula, nombre, teléfono, correo o dirección..."
                    value="<?= escapar($busqueda) ?>">
                <button type="submit" class="boton boton-principal">Buscar</button>
                <?php if ($busqueda !== ""): ?>
                    <a href="cliente.php?<?= $parametrosHtml ?>" class="boton boton-secundario">Mostrar todos</a>
                <?php endif; ?>
            </form>
        </section>

        <section class="panel">
            <h3 class="panel-titulo">Listado de clientes</h3>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Dirección</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($clientes && $clientes->num_rows > 0): ?>
                        <?php while ($fila = $clientes->fetch_assoc()): ?>
                            <tr>
                                <td><?= escapar($fila["cedula_cliente"]) ?></td>
                                <td><?= escapar($fila["nombre"]) ?></td>
                                <td><?= escapar($fila["telefono"]) ?></td>
                                <td><?= escapar($fila["email"]) ?></td>
                                <td><?= escapar($fila["direccion"]) ?></td>
                                <td class="tabla-acciones">
                                    <a class="boton boton-chico boton-editar"
                                        href="cliente.php?<?= $parametrosHtml ?>&amp;accion=editar&amp;cedula_cliente=<?= urlencode((string)$fila["cedula_cliente"]) ?>">
                                        Editar
                                    </a>
                                    <form action="cliente.php?<?= $parametrosHtml ?>" method="POST" class="form-eliminar"
                                        onsubmit="return confirm('¿Seguro que deseas eliminar este cliente?');">
                                        <input type="hidden" name="cedula_cliente"
                                            value="<?= escapar($fila["cedula_cliente"]) ?>">
                                        <button type="submit" name="eliminar_cliente" value="1"
                                            class="boton boton-chico boton-eliminar">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="tabla-vacia">No se encontraron clientes.</td>
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
(() => {
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

    document.addEventListener("keydown", evento => {
        if (evento.key === "Escape") ocultarMenu();
    });
})();
</script>
</body>
</html>
