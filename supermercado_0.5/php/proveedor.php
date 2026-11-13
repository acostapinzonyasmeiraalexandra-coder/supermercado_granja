<?php
session_start();

// si no ha iniciado sesion, volver al ingreso
if (!isset($_SESSION["usuario"])) {
    header("Location: ../index.php");
    exit();
}

// datos del usuario que inicio sesion
$usuario = $_SESSION["usuario"];
$rol = (string) ($_SESSION["rol"] ?? ""); // 1 administrador | 2 empleado
$esAdmin = ($rol === "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr($usuario, 0, 1));

// esta pagina es solo para el administrador
if (!$esAdmin) {
    header("Location: menu.php");
    exit();
}

// conexion a la base de datos
include("conexion.php");

// evita que el texto rompa el html
function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

// muestra el icono del menu
function icono(string $nombre): string {
    return '<img src="../img/' . h($nombre) . '.png" alt="" class="icono">';
}

// variables de la pagina
$mensaje = "";
// esta variable almacena el tipo de mensaje (ok o error)
$tipoMensaje = "";
// esta variable almacena los datos del registro que se va a editar
$proveedorEditar = null;
// esta variable almacena el texto que se escribe en el buscador
$busqueda = trim($_GET["buscar"] ?? "");

// si no es admin lo devuelve a la pagina
if (!$esAdmin && (
    $_SERVER["REQUEST_METHOD"] === "POST" ||
    isset($_GET["accion"]) ||
    isset($_GET["buscar"])
)) {
    // redirige a proveedor.php
    header("Location: proveedor.php");
    exit();
}

// eliminar proveedor
if ($esAdmin && $_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion_formulario"] ?? "") === "eliminar") {
    // esta variable almacena el codigo validado como numero entero
    $codigo = filter_var($_POST["cod_proveedor"] ?? "", FILTER_VALIDATE_INT);

    if ($codigo) {
        // intenta ejecutar el codigo y atrapa los errores
        try {
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("DELETE FROM proveedor WHERE cod_proveedor = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $codigo);
            // ejecuta la consulta
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $mensaje = "Proveedor eliminado correctamente.";
                $tipoMensaje = "error"; // rojo al eliminar
            } else {
                $mensaje = "No se encontró el proveedor seleccionado.";
                $tipoMensaje = "error";
            }

            // cierra la consulta
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
if ($esAdmin && ($_GET["accion"] ?? "") === "editar" && isset($_GET["cod_proveedor"])) {
    // esta variable almacena el codigo validado como numero entero
    $codigo = filter_var($_GET["cod_proveedor"], FILTER_VALIDATE_INT);

    if ($codigo) {
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare("SELECT cod_proveedor, nombre, telefono, direccion, email FROM proveedor WHERE cod_proveedor = ?");
        // envia los datos a la consulta
        $stmt->bind_param("i", $codigo);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $proveedorEditar = $stmt->get_result()->fetch_assoc();
        // cierra la consulta
        $stmt->close();

        if (!$proveedorEditar) {
            $mensaje = "No se encontró el proveedor que deseas editar.";
            $tipoMensaje = "error";
        }
    }
}

// registrar o actualizar proveedor
if ($esAdmin && $_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion_formulario"] ?? "") !== "eliminar") {
    // datos que llegan del formulario
    $accionFormulario = $_POST["accion_formulario"] ?? "guardar";
    // esta variable almacena el codigo validado como numero entero
    $codigo = filter_var($_POST["cod_proveedor"] ?? "", FILTER_VALIDATE_INT);
    // datos que llegan del formulario
    $nombre = trim($_POST["nombre"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $direccion = trim($_POST["direccion"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if (
        !$codigo || $nombre === "" || !preg_match('/^[\p{L}\s]+$/u', $nombre) ||
        !ctype_digit($telefono) || strlen($telefono) < 7 || strlen($telefono) > 10 ||
        ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL))
    ) {
        $mensaje = "Completa los campos obligatorios. El nombre solo puede tener letras, el teléfono solo números (7 a 10 dígitos) y el correo, si lo escribes, debe ser válido.";
        $tipoMensaje = "error";

        $proveedorEditar = [
            "cod_proveedor" => $_POST["cod_proveedor"] ?? "",
            "nombre" => $nombre,
            "telefono" => $telefono,
            "direccion" => $direccion,
            "email" => $email
        ];
    } else {
        // intenta ejecutar el codigo y atrapa los errores
        try {
            // si es actualizar hace update, si no hace insert
            if ($accionFormulario === "actualizar") {
                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("UPDATE proveedor SET nombre = ?, telefono = ?, direccion = ?, email = ? WHERE cod_proveedor = ?");
                // envia los datos a la consulta
                $stmt->bind_param("ssssi", $nombre, $telefono, $direccion, $email, $codigo);
                // ejecuta la consulta
                $stmt->execute();
                // cierra la consulta
                $stmt->close();

                // redirige a proveedor.php
                header("Location: proveedor.php?estado=actualizado");
                exit();
            } else {
                // prepara la consulta sql de forma segura
                $stmt = $conn->prepare("INSERT INTO proveedor (cod_proveedor, nombre, telefono, direccion, email) VALUES (?, ?, ?, ?, ?)");
                // envia los datos a la consulta
                $stmt->bind_param("issss", $codigo, $nombre, $telefono, $direccion, $email);
                // ejecuta la consulta
                $stmt->execute();
                // cierra la consulta
                $stmt->close();

                // redirige a proveedor.php
                header("Location: proveedor.php?estado=registrado");
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
// esta variable almacena un total que se va sumando
$totalProveedores = 0;

if ($esAdmin) {
    if ($busqueda !== "") {
        // esta variable almacena el texto buscado con comodines para usar like
        $termino = "%" . $busqueda . "%";

        // esta variable almacena la consulta sql
        $sql = "SELECT cod_proveedor, nombre, telefono, direccion, email
                FROM proveedor
                WHERE CAST(cod_proveedor AS CHAR) LIKE ?
                   OR nombre LIKE ?
                   OR telefono LIKE ?
                   OR direccion LIKE ?
                   OR email LIKE ?
                ORDER BY cod_proveedor ASC";

        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare($sql);
        // envia los datos a la consulta
        $stmt->bind_param("sssss", $termino, $termino, $termino, $termino, $termino);
        // ejecuta la consulta
        $stmt->execute();
        // obtiene el resultado de la consulta
        $proveedores = $stmt->get_result();
        // cierra la consulta
        $stmt->close();
    } else {
        // ejecuta la consulta sql
        $proveedores = $conn->query("SELECT cod_proveedor, nombre, telefono, direccion, email FROM proveedor ORDER BY cod_proveedor ASC");
    }

    $totalProveedores = $proveedores ? $proveedores->num_rows : 0;
}

// esta variable almacena la primera letra del usuario para el encabezado
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
    <!-- hoja de estilos de la pagina -->
    <link rel="stylesheet" href="../css/proveedor.css?v=3">
</head>
<!-- cuerpo de la pagina -->
<body class="pagina-proveedor">

<!-- encabezado -->
<header class="encabezado">
    <button type="button" class="boton-menu" id="abrirMenu"
        aria-label="Abrir menú lateral" aria-controls="menuLateral" aria-expanded="false">
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
        <span class="usuario-inicial"><?= h($inicial) ?></span>
        <div>
            <strong><?= h($usuario) ?></strong>
            <p><?= $esAdmin ? "Administrador" : "Empleado" ?></p>
        </div>
    </div>
</header>

<!-- fondo que oscurece la pagina cuando el menu esta abierto -->
<div class="fondo-menu" id="fondoMenu"></div>

<!-- menu lateral -->
<aside class="menu-lateral" id="menuLateral" aria-label="Menú principal">
    <div class="menu-lateral-encabezado">
        <div class="menu-lateral-marca">
            <img src="../img/logo.png" alt="Logo de Supermercado La Granja">
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
        <div class="usuario-inicial usuario-grande"><?= h($inicial) ?></div>
        <div>
            <strong><?= h($usuario) ?></strong>
            <p><?= $esAdmin ? "Administrador" : "Empleado" ?></p>
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
                <span>Clientes</span>
            </a>
            <a href="mov_salida.php" class="enlace-menu">
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
                <a href="proveedor.php" class="enlace-menu activo" aria-current="page">
                    <span class="caja-icono"><?= icono("proveedor") ?></span>
                    <span>Proveedores</span>
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
    <?php if (!$esAdmin): ?>
        <!-- panel de contenido -->
        <section class="panel">
            <h3 class="panel-titulo">Acceso de empleado</h3>
            <p>Tu cargo no tiene permiso para registrar, editar, eliminar ni consultar la tabla de proveedores.</p>
            <!-- botones del formulario -->
            <div class="botones" style="margin-top: 18px;">
                <a class="boton boton-principal" href="menu.php">Volver al menú principal</a>
            </div>
        </section>
    <?php else: ?>
        <!-- formulario de proveedor -->
        <section class="panel">
            <h3 class="panel-titulo"><?= $proveedorEditar ? "Editar proveedor" : "Ingresar proveedor" ?></h3>

            <!-- formulario -->
            <form action="proveedor.php" method="POST" id="formularioProveedor" class="formulario">
                <input type="hidden" name="accion_formulario" value="<?= $proveedorEditar ? "actualizar" : "guardar" ?>">

                <div class="campo">
                    <label for="cod_proveedor">Código del proveedor <span class="obligatorio">*</span></label>
                    <input type="text" id="cod_proveedor" name="cod_proveedor" inputmode="numeric" pattern="[0-9]+" maxlength="10" data-tipo="numeros" data-error="Escribe solo números." required
                        placeholder="Ingrese código"
                        value="<?= h($proveedorEditar["cod_proveedor"] ?? "") ?>"
                        <?= $proveedorEditar ? "readonly" : "" ?>>
                </div>

                <div class="campo">
                    <label for="nombre">Nombre del proveedor <span class="obligatorio">*</span></label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+" data-tipo="letras" data-error="Escribe solo letras, sin números." required
                        placeholder="Ingrese nombre"
                        value="<?= h($proveedorEditar["nombre"] ?? "") ?>">
                </div>

                <div class="campo">
                    <label for="telefono">Teléfono <span class="obligatorio">*</span></label>
                    <input type="tel" id="telefono" name="telefono" inputmode="numeric" pattern="[0-9]{7,10}" minlength="7" maxlength="10" data-tipo="numeros" data-error="Escribe solo números (entre 7 y 10 dígitos)." required
                        placeholder="Ingrese teléfono"
                        value="<?= h($proveedorEditar["telefono"] ?? "") ?>">
                </div>

                <div class="campo">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" maxlength="120"
                        placeholder="Ingrese correo electrónico"
                        value="<?= h($proveedorEditar["email"] ?? "") ?>">
                </div>

                <div class="campo campo-ancho">
                    <label for="direccion">Dirección</label>
                    <input type="text" id="direccion" name="direccion" maxlength="180"
                        placeholder="Ingrese dirección"
                        value="<?= h($proveedorEditar["direccion"] ?? "") ?>">
                </div>

                <!-- botones del formulario -->
                <div class="botones campo-ancho">
                    <?php if ($proveedorEditar): ?>
                        <button type="submit" class="boton boton-principal boton-registrar">
                            Actualizar proveedor
                        </button>
                        <a href="proveedor.php" class="boton boton-secundario">Cancelar</a>
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
            <!-- formulario de busqueda -->
            <form action="proveedor.php" method="GET" class="buscador" id="formBuscador">
                <input type="search" name="buscar" id="campoBuscar" autocomplete="off" aria-label="Buscar proveedores"
                    placeholder="Código, nombre, teléfono, dirección o correo electrónico..."
                    value="<?= h($busqueda) ?>">
                <a href="proveedor.php" class="boton boton-secundario" id="limpiarBusqueda"
                    <?= $busqueda === "" ? "hidden" : "" ?>>Mostrar todos</a>
            </form>
        </section>

        <!-- tabla de proveedores -->
        <section class="panel">
            <h3 class="panel-titulo">Listado de proveedores</h3>
            <div class="tabla-contenedor tabla-scroll">
                <!-- tabla con los registros -->
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
                    <tbody id="cuerpoTabla">
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
                                            href="proveedor.php?accion=editar&amp;cod_proveedor=<?= urlencode((string)$fila["cod_proveedor"]) ?>">
                                            Editar
                                        </a>
                                        <!-- formulario para eliminar el registro -->
                                        <form action="proveedor.php" method="POST" class="form-eliminar">
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

        <!-- ventana para confirmar la eliminacion -->
        <div class="modal" id="modalEliminar" hidden>
            <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
                <div class="modal__icono modal__icono--aviso">!</div>
                <h3 class="modal__titulo" id="tituloEliminar">Eliminar proveedor</h3>
                <p class="modal__texto" id="textoEliminar">¿Seguro que deseas eliminar este registro?</p>
                <div class="modal__botones">
                    <button type="button" class="boton boton-secundario" id="cancelarEliminar">Cancelar</button>
                    <button type="button" class="boton boton-eliminar" id="confirmarEliminar">Eliminar</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <!-- enlaces de ayuda -->
        <section class="footer-ayuda">
            <h4>AYUDA Y SOPORTE</h4>
            <p class="footer-descripcion">
                Encuentra orientación para utilizar correctamente el sistema de gestión de inventario.
            </p>
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

    <!-- parte inferior del pie de pagina -->
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<script src = "../js/proveedor.js"></script>

</body>
</html>