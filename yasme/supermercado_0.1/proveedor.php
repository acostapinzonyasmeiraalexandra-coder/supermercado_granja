<?php

require_once "conexion.php";

// usuario y rol que llegan por la url
$usuario = trim((string) ($_GET["usuario"] ?? ""));
$cargo = (string) ($_GET["rol"] ?? "");

if ($usuario === "" || ($cargo !== "1" && $cargo !== "2")) {
    header("Location: index.html");
    exit();
}

// se agregan a los enlaces para no perder la sesion
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($cargo);
$parametrosHtml = htmlspecialchars($parametros, ENT_QUOTES, "UTF-8");

// el rol 1 es administrador
$esAdministrador = ($cargo === "1");

// revisa la conexion a la base de datos
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Error: No hay conexión válida con la base de datos.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// evita que el texto rompa el html
function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

// muestra el icono del menu
function icono(string $nombre): string {
    return '<img src="img/' . h($nombre) . '.png" alt="" class="icono">';
}

// variables de la pagina
$mensaje = "";
$tipoMensaje = "";
$proveedorEditar = null;
$busqueda = trim($_GET["buscar"] ?? "");

// si no es admin lo devuelve a la pagina
if (!$esAdministrador && (
    $_SERVER["REQUEST_METHOD"] === "POST" ||
    isset($_GET["accion"]) ||
    isset($_GET["buscar"])
)) {
    header("Location: proveedor.php?" . $parametros);
    exit();
}

// eliminar proveedor
if ($esAdministrador && $_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion_formulario"] ?? "") === "eliminar") {
    $codigo = filter_var($_POST["cod_proveedor"] ?? "", FILTER_VALIDATE_INT);

    if ($codigo) {
        try {
            $stmt = $conn->prepare("DELETE FROM proveedor WHERE cod_proveedor = ?");
            $stmt->bind_param("i", $codigo);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $mensaje = "Proveedor eliminado correctamente.";
                $tipoMensaje = "error"; // rojo al eliminar
            } else {
                $mensaje = "No se encontró el proveedor seleccionado.";
                $tipoMensaje = "error";
            }

            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // 1451 = el proveedor tiene registros relacionados
            if ((int)$e->getCode() === 1451) {
                $mensaje = "No se puede eliminar este proveedor porque tiene registros relacionados.";
            } else {
                $mensaje = "Ocurrió un error al eliminar el proveedor.";
            }
            $tipoMensaje = "error";
        }
    }
}

// buscar el proveedor que se va a editar
if ($esAdministrador && ($_GET["accion"] ?? "") === "editar" && isset($_GET["cod_proveedor"])) {
    $codigo = filter_var($_GET["cod_proveedor"], FILTER_VALIDATE_INT);

    if ($codigo) {
        $stmt = $conn->prepare("SELECT cod_proveedor, nombre, telefono, direccion, email FROM proveedor WHERE cod_proveedor = ?");
        $stmt->bind_param("i", $codigo);
        $stmt->execute();
        $proveedorEditar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$proveedorEditar) {
            $mensaje = "No se encontró el proveedor que deseas editar.";
            $tipoMensaje = "error";
        }
    }
}

// registrar o actualizar proveedor
if ($esAdministrador && $_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion_formulario"] ?? "") !== "eliminar") {
    $accionFormulario = $_POST["accion_formulario"] ?? "guardar";
    $codigo = filter_var($_POST["cod_proveedor"] ?? "", FILTER_VALIDATE_INT);
    $nombre = trim($_POST["nombre"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $direccion = trim($_POST["direccion"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if (!$codigo || $nombre === "" || $telefono === "" || $direccion === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Completa todos los campos y verifica que el correo electrónico sea válido.";
        $tipoMensaje = "error";

        $proveedorEditar = [
            "cod_proveedor" => $_POST["cod_proveedor"] ?? "",
            "nombre" => $nombre,
            "telefono" => $telefono,
            "direccion" => $direccion,
            "email" => $email
        ];
    } else {
        try {
            // si es actualizar hace update, si no hace insert
            if ($accionFormulario === "actualizar") {
                $stmt = $conn->prepare("UPDATE proveedor SET nombre = ?, telefono = ?, direccion = ?, email = ? WHERE cod_proveedor = ?");
                $stmt->bind_param("ssssi", $nombre, $telefono, $direccion, $email, $codigo);
                $stmt->execute();
                $stmt->close();

                header("Location: proveedor.php?" . $parametros . "&estado=actualizado");
                exit();
            } else {
                $stmt = $conn->prepare("INSERT INTO proveedor (cod_proveedor, nombre, telefono, direccion, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $codigo, $nombre, $telefono, $direccion, $email);
                $stmt->execute();
                $stmt->close();

                header("Location: proveedor.php?" . $parametros . "&estado=registrado");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            // 1062 = codigo repetido
            if ((int)$e->getCode() === 1062) {
                $mensaje = "El código de proveedor ya existe. Ingresa un código diferente.";
            } else {
                $mensaje = "No fue posible guardar los datos. Verifica la información e inténtalo nuevamente.";
            }

            $tipoMensaje = "error";

            $proveedorEditar = [
                "cod_proveedor" => $codigo,
                "nombre" => $nombre,
                "telefono" => $telefono,
                "direccion" => $direccion,
                "email" => $email
            ];
        }
    }
}

// mensajes despues de registrar o actualizar
if (isset($_GET["estado"])) {
    if ($_GET["estado"] === "registrado") {
        $mensaje = "Proveedor registrado correctamente.";
        $tipoMensaje = "ok";
    } elseif ($_GET["estado"] === "actualizado") {
        $mensaje = "Proveedor actualizado correctamente.";
        $tipoMensaje = "ok";
    }
}

// consultar y buscar proveedores para la tabla
$proveedores = null;
$totalProveedores = 0;

if ($esAdministrador) {
    if ($busqueda !== "") {
        $termino = "%" . $busqueda . "%";

        $sql = "SELECT cod_proveedor, nombre, telefono, direccion, email
                FROM proveedor
                WHERE CAST(cod_proveedor AS CHAR) LIKE ?
                   OR nombre LIKE ?
                   OR telefono LIKE ?
                   OR direccion LIKE ?
                   OR email LIKE ?
                ORDER BY cod_proveedor ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $termino, $termino, $termino, $termino, $termino);
        $stmt->execute();
        $proveedores = $stmt->get_result();
        $stmt->close();
    } else {
        $proveedores = $conn->query("SELECT cod_proveedor, nombre, telefono, direccion, email FROM proveedor ORDER BY cod_proveedor ASC");
    }

    $totalProveedores = $proveedores ? $proveedores->num_rows : 0;
}

$inicial = function_exists("mb_substr")
    ? mb_strtoupper(mb_substr(trim($usuario), 0, 1, "UTF-8"), "UTF-8")
    : strtoupper(substr(trim($usuario), 0, 1));
?>
<!-- pagina html -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores | Supermercado La Granja</title>
    <link rel="stylesheet" href="css/proveedor.css">
</head>
<body class="pagina-proveedor">

<!-- encabezado -->
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
        <span class="usuario-inicial"><?= h($inicial) ?></span>
        <div>
            <strong><?= h($usuario) ?></strong>
            <p><?= $esAdministrador ? "Administrador" : "Empleado" ?></p>
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
            <?= icono("cerrar") ?>
        </button>
    </div>

    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= h($inicial) ?></div>
        <div>
            <strong><?= h($usuario) ?></strong>
            <p><?= $esAdministrador ? "Administrador" : "Empleado" ?></p>
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
                <span>Clientes</span>
            </a>
            <a href="mov_salida.php?<?= $parametrosHtml ?>" class="enlace-menu">
                <span class="caja-icono"><?= icono("salida") ?></span>
                <span>Movimiento de salida</span>
            </a>

            <?php if ($esAdministrador): ?>
                <a href="producto.php?<?= $parametrosHtml ?>" class="enlace-menu">
                    <span class="caja-icono"><?= icono("producto") ?></span>
                    <span>Productos</span>
                </a>
                <a href="mov_entrada.php?<?= $parametrosHtml ?>" class="enlace-menu">
                    <span class="caja-icono"><?= icono("entrada") ?></span>
                    <span>Movimiento de entrada</span>
                </a>
                <a href="proveedor.php?<?= $parametrosHtml ?>" class="enlace-menu activo" aria-current="page">
                    <span class="caja-icono"><?= icono("proveedor") ?></span>
                    <span>Proveedores</span>
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

<!-- contenido principal -->
<main class="contenido">
    <section class="titulo-pagina">
        <span class="titulo-icono"><?= icono("proveedor") ?></span>
        <div>
            <h2>Proveedores</h2>
            <p>Registra, busca, edita y administra la información de los proveedores.</p>
        </div>
    </section>

    <!-- mensaje de aviso -->
    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= h($tipoMensaje) ?>" role="status">
            <?= h($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- el empleado no tiene acceso -->
    <?php if (!$esAdministrador): ?>
        <section class="panel">
            <h3 class="panel-titulo">Acceso de empleado</h3>
            <p>Tu cargo no tiene permiso para registrar, editar, eliminar ni consultar la tabla de proveedores.</p>
            <div class="botones" style="margin-top: 18px;">
                <a class="boton boton-principal" href="menu.php?<?= $parametrosHtml ?>">Volver al menú principal</a>
            </div>
        </section>
    <?php else: ?>
        <!-- formulario de proveedor -->
        <section class="panel">
            <h3 class="panel-titulo"><?= $proveedorEditar ? "Editar proveedor" : "Ingresar proveedor" ?></h3>

            <form action="proveedor.php?<?= $parametrosHtml ?>" method="POST" id="formularioProveedor" class="formulario">
                <input type="hidden" name="accion_formulario" value="<?= $proveedorEditar ? "actualizar" : "guardar" ?>">

                <div class="campo">
                    <label for="cod_proveedor">Código del proveedor</label>
                    <input type="number" id="cod_proveedor" name="cod_proveedor" min="1" required
                        placeholder="Ingrese código"
                        value="<?= h($proveedorEditar["cod_proveedor"] ?? "") ?>"
                        <?= $proveedorEditar ? "readonly" : "" ?>>
                </div>

                <div class="campo">
                    <label for="nombre">Nombre del proveedor</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" required
                        placeholder="Ingrese nombre"
                        value="<?= h($proveedorEditar["nombre"] ?? "") ?>">
                </div>

                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" maxlength="25" required
                        placeholder="Ingrese teléfono"
                        value="<?= h($proveedorEditar["telefono"] ?? "") ?>">
                </div>

                <div class="campo">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" maxlength="120" required
                        placeholder="Ingrese correo electrónico"
                        value="<?= h($proveedorEditar["email"] ?? "") ?>">
                </div>

                <div class="campo campo-ancho">
                    <label for="direccion">Dirección</label>
                    <input type="text" id="direccion" name="direccion" maxlength="180" required
                        placeholder="Ingrese dirección"
                        value="<?= h($proveedorEditar["direccion"] ?? "") ?>">
                </div>

                <div class="botones campo-ancho">
                    <?php if ($proveedorEditar): ?>
                        <button type="submit" class="boton boton-principal boton-registrar">
                            Actualizar proveedor
                        </button>
                        <a href="proveedor.php?<?= $parametrosHtml ?>" class="boton boton-secundario">Cancelar</a>
                    <?php else: ?>
                        <button type="submit" class="boton boton-principal boton-registrar">
                            Registrar proveedor
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- buscador -->
        <section class="panel">
            <h3 class="panel-titulo">Buscar proveedor</h3>
            <form action="proveedor.php" method="GET" class="buscador">
                <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?>">
                <input type="hidden" name="rol" value="<?= htmlspecialchars($cargo, ENT_QUOTES, "UTF-8") ?>">
                <input type="search" name="buscar" aria-label="Buscar proveedores"
                    placeholder="Código, nombre, teléfono, dirección o correo electrónico..."
                    value="<?= h($busqueda) ?>">
                <button type="submit" class="boton boton-principal">Buscar</button>
                <?php if ($busqueda !== ""): ?>
                    <a href="proveedor.php?<?= $parametrosHtml ?>" class="boton boton-secundario">Mostrar todos</a>
                <?php endif; ?>
            </form>
        </section>

        <!-- tabla de proveedores -->
        <section class="panel">
            <h3 class="panel-titulo">Listado de proveedores</h3>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Correo electrónico</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($proveedores && $proveedores->num_rows > 0): ?>
                            <?php while ($fila = $proveedores->fetch_assoc()): ?>
                                <tr>
                                    <td><?= h($fila["cod_proveedor"]) ?></td>
                                    <td><?= h($fila["nombre"]) ?></td>
                                    <td><?= h($fila["telefono"]) ?></td>
                                    <td><?= h($fila["direccion"]) ?></td>
                                    <td><?= h($fila["email"]) ?></td>
                                    <td class="tabla-acciones">
                                        <a class="boton boton-chico boton-editar"
                                            href="proveedor.php?<?= $parametrosHtml ?>&amp;accion=editar&amp;cod_proveedor=<?= urlencode((string)$fila["cod_proveedor"]) ?>">
                                            Editar
                                        </a>
                                        <form action="proveedor.php?<?= $parametrosHtml ?>" method="POST" class="form-eliminar"
                                            onsubmit="return confirm('¿Seguro que deseas eliminar este proveedor?');">
                                            <input type="hidden" name="accion_formulario" value="eliminar">
                                            <input type="hidden" name="cod_proveedor" value="<?= h($fila["cod_proveedor"]) ?>">
                                            <button type="submit" class="boton boton-chico boton-eliminar">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="tabla-vacia">No se encontraron proveedores registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="total-registros">Total de registros: <?= (int)$totalProveedores ?></p>
        </section>
    <?php endif; ?>
</main>

<!-- pie de pagina -->
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
            <p><strong>Gestión de productos</strong><br>
                Registro, consulta y actualización de información de los productos.</p>
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

<!-- abrir y cerrar el menu lateral -->
<script>
const abrirMenu = document.getElementById("abrirMenu");
const cerrarMenu = document.getElementById("cerrarMenu");
const fondoMenu = document.getElementById("fondoMenu");

function mostrarMenu() {
    document.body.classList.add("menu-abierto");
    abrirMenu.setAttribute("aria-expanded", "true");
}

function ocultarMenu() {
    document.body.classList.remove("menu-abierto");
    abrirMenu.setAttribute("aria-expanded", "false");
}

abrirMenu.addEventListener("click", mostrarMenu);
cerrarMenu.addEventListener("click", ocultarMenu);
fondoMenu.addEventListener("click", ocultarMenu);

document.addEventListener("keydown", function(evento) {
    if (evento.key === "Escape") ocultarMenu();
});
</script>

</body>
</html>