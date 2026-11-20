<?php
// inicia la sesion
include_once(__DIR__ . "/sesion.php");

// verifica el usuario y el rol
if (
    !isset($_SESSION["usuario"], $_SESSION["rol"]) ||
    trim((string)$_SESSION["usuario"]) === "" ||
    !in_array((string)$_SESSION["rol"], ["1", "2"], true)
) {
    header("Location: ../index.php");
    exit();
}

// evita mostrar datos guardados
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// datos del usuario
$usuario = $_SESSION["usuario"];
$esAdmin = ((string)$_SESSION["rol"] === "1");
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";
$inicial = strtoupper(substr($usuario, 0, 1));

// conexion a la base de datos
include("conexion.php");

// cuenta los productos con pocas existencias para las notificaciones
$totalStockBajo = 0;
try {
    $consultaStockBajo = $conn->query("SELECT COUNT(*) AS total FROM producto WHERE stock <= stock_minimo");
    $totalStockBajo = (int)($consultaStockBajo->fetch_assoc()["total"] ?? 0);
} catch (Throwable $error) {
    $totalStockBajo = 0;
}

// muestra los datos de forma segura
function escapar($dato): string {
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

// variables de la pagina
$mensaje = "";
$tipoMensaje = "";
$clienteEditar = null;
$busqueda = "";
$clientes = null;

// elimina cliente
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_cliente"])) {
    $cedula = trim((string)($_POST["cedula_cliente"] ?? ""));

    try {
        if (!$esAdmin) {
            throw new InvalidArgumentException("No tienes permiso para eliminar clientes.");
        }
        if (!ctype_digit($cedula)) {
            throw new InvalidArgumentException("Cédula no válida.");
        }

        $cedula = (int)$cedula;
        $stmt = $conn->prepare("DELETE FROM cliente WHERE cedula_cliente = ?");
        $stmt->bind_param("i", $cedula);
        $stmt->execute();
        $eliminados = $stmt->affected_rows;
        $stmt->close();

        if ($eliminados < 1) {
            throw new InvalidArgumentException("No se encontró el cliente que deseas eliminar.");
        }

        header("Location: cliente.php?mensaje=eliminado");
        exit();

    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // 1451 = el cliente tiene movimientos registrados
        $mensaje = ((int)$e->getCode() === 1451)
            ? "No se puede eliminar el cliente porque tiene movimientos registrados."
            : "Ocurrió un error al eliminar el cliente.";
        $tipoMensaje = "error";
    }
}

// busca cliente para editar
if (($_GET["accion"] ?? "") === "editar") {
    $cedula = trim((string)($_GET["cedula_cliente"] ?? ""));

    try {
        if (!$esAdmin) {
            throw new InvalidArgumentException("No tienes permiso para editar clientes.");
        }
        if (!ctype_digit($cedula)) {
            throw new InvalidArgumentException("Cédula no válida.");
        }

        $cedula = (int)$cedula;
        $stmt = $conn->prepare("SELECT * FROM cliente WHERE cedula_cliente = ?");
        $stmt->bind_param("i", $cedula);
        $stmt->execute();
        $clienteEditar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$clienteEditar) {
            throw new InvalidArgumentException("No se encontró el cliente seleccionado.");
        }

    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        $mensaje = "No se pudieron cargar los datos del cliente.";
        $tipoMensaje = "error";
    }
}

// registra o actualiza cliente
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST["guardar"]) || isset($_POST["actualizar"]))) {
    $actualizar = isset($_POST["actualizar"]);
    $cedula = trim((string)($_POST["cedula_cliente"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $telefono = trim((string)($_POST["telefono"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $direccion = trim((string)($_POST["direccion"] ?? ""));

    try {
        // revisa los datos
        if ($actualizar && !$esAdmin) {
            throw new InvalidArgumentException("No tienes permiso para actualizar clientes.");
        }
        if ($cedula === "" || $nombre === "" || $telefono === "") {
            throw new InvalidArgumentException("Completa los campos obligatorios.");
        }
        if (!ctype_digit($cedula) || (int)$cedula < 1) {
            throw new InvalidArgumentException("La cédula solo puede tener números.");
        }
        if (!preg_match('/^[\p{L}\s]+$/u', $nombre)) {
            throw new InvalidArgumentException("El nombre solo puede tener letras.");
        }
        if (!ctype_digit($telefono) || strlen($telefono) < 7 || strlen($telefono) > 10) {
            throw new InvalidArgumentException("El teléfono debe tener entre 7 y 10 números.");
        }
        if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Escribe un correo electrónico válido.");
        }

        $cedula = (int)$cedula;

        // actualiza o inserta
        if ($actualizar) {
            $stmt = $conn->prepare("UPDATE cliente SET nombre = ?, telefono = ?, email = ?, direccion = ? WHERE cedula_cliente = ?");
            $stmt->bind_param("ssssi", $nombre, $telefono, $email, $direccion, $cedula);
        } else {
            $stmt = $conn->prepare("INSERT INTO cliente (cedula_cliente, nombre, telefono, email, direccion) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $cedula, $nombre, $telefono, $email, $direccion);
        }
        $stmt->execute();
        $stmt->close();

        header("Location: cliente.php?mensaje=" . ($actualizar ? "actualizado" : "registrado"));
        exit();

    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // 1062 = la cedula ya existe
        if ($actualizar) {
            $mensaje = "Error al actualizar el cliente. Verifica los datos.";
        } else {
            $mensaje = ((int)$e->getCode() === 1062)
                ? "La cédula ya está registrada."
                : "Error al registrar el cliente. Verifica los datos.";
        }
        $tipoMensaje = "error";
    }

    // si falla al actualizar, el formulario sigue abierto con los datos escritos
    if ($actualizar && $esAdmin) {
        $clienteEditar = [
            "cedula_cliente" => $_POST["cedula_cliente"] ?? "",
            "nombre" => $nombre,
            "telefono" => $telefono,
            "email" => $email,
            "direccion" => $direccion
        ];
    }
}

// muestra mensajes
$mensajes = [
    "registrado" => ["Cliente registrado correctamente.", "ok"],
    "actualizado" => ["Cliente actualizado correctamente.", "ok"],
    "eliminado" => ["Cliente eliminado correctamente.", "error"]
];

$clave = $_GET["mensaje"] ?? "";

if (is_string($clave) && isset($mensajes[$clave])) {
    [$mensaje, $tipoMensaje] = $mensajes[$clave];
}

// busca y muestra los clientes
if ($esAdmin) {
    try {
        $busqueda = trim((string)($_GET["buscar"] ?? ""));
        $filtro = "%" . $busqueda . "%";

        // con el filtro vacio (%%) muestra todos los clientes
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
    <!-- ancho fijo: el celular muestra la pagina igual que en el computador -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes | La Granja</title>
    <!-- estilos de la pagina -->
    <link rel="stylesheet" href="../css/cliente.css?v=20261120">
</head>

<body class="pagina-cliente">

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
                <img src="../img/logo.png" alt="Logo de Supermercado La Granja">
                <div>
                    <h2>Supermercado</h2>
                    <p>La Granja</p>
                </div>
            </div>

            <button type="button" class="boton-cerrar" id="cerrarMenu" aria-label="Cerrar menú lateral">
                <img src="../img/cerrar.png" alt="" class="icono">
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
                <a href="menu.php" class="enlace-menu">
                    <span class="caja-icono"><img src="../img/inicio.png" alt="" class="icono"></span>
                    <span>Inicio</span>
                </a>

                <a href="cliente.php" class="enlace-menu activo" aria-current="page">
                    <span class="caja-icono"><img src="../img/cliente.png" alt="" class="icono"></span>
                    <span>Cliente</span>
                </a>

                <a href="mov_salida.php" class="enlace-menu">
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

        <!-- titulo -->
        <section class="titulo-pagina">
            <span class="titulo-icono"><img src="../img/cliente.png" alt="" class="icono"></span>
            <div>
                <h2>Clientes</h2>
                <p>
                    <?= $esAdmin
                        ? "Busca, edita o elimina los clientes registrados."
                        : "Registra un cliente nuevo en el sistema." ?>
                </p>
            </div>
        </section>

        <!-- mensaje -->
        <?php if ($mensaje !== ""): ?>
            <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
                <?= escapar($mensaje) ?>
            </div>
        <?php endif; ?>

        <!-- formulario (empleado siempre, administrador al editar) -->
        <?php if (!$esAdmin || $clienteEditar): ?>
            <section class="panel">
                <h3 class="panel-titulo"><?= $clienteEditar ? "Editar cliente" : "Ingresar cliente" ?></h3>

                <form action="cliente.php" method="POST" class="formulario">

                    <?php if ($clienteEditar): ?>
                        <input type="hidden" name="cedula_cliente"
                            value="<?= escapar($clienteEditar["cedula_cliente"]) ?>">

                        <div class="campo">
                            <label for="cedula_visible">Cédula del cliente <span class="obligatorio">*</span></label>
                            <input id="cedula_visible" type="text"
                                value="<?= escapar($clienteEditar["cedula_cliente"]) ?>" disabled>
                        </div>
                    <?php else: ?>
                        <div class="campo">
                            <label for="cedula_cliente">Cédula del cliente <span class="obligatorio">*</span></label>
                            <input id="cedula_cliente" type="text" name="cedula_cliente" required
                                inputmode="numeric" pattern="[0-9]+" maxlength="10"
                                data-tipo="numeros" data-error="Escribe solo números."
                                placeholder="Ingrese cédula"
                                value="<?= escapar($_POST["cedula_cliente"] ?? "") ?>">
                        </div>
                    <?php endif; ?>

                    <div class="campo">
                        <label for="nombre">Nombre <span class="obligatorio">*</span></label>
                        <input id="nombre" type="text" name="nombre" required maxlength="50"
                            pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+"
                            data-tipo="letras" data-error="Escribe solo letras, sin números."
                            placeholder="Ingrese nombre"
                            value="<?= escapar($clienteEditar["nombre"] ?? ($_POST["nombre"] ?? "")) ?>">
                    </div>

                    <div class="campo">
                        <label for="telefono">Teléfono <span class="obligatorio">*</span></label>
                        <input id="telefono" type="tel" name="telefono" required
                            inputmode="numeric" pattern="[0-9]{7,10}"
                            minlength="7" maxlength="10"
                            data-tipo="numeros" data-error="Escribe solo números (entre 7 y 10 dígitos)."
                            placeholder="Ingrese teléfono"
                            value="<?= escapar($clienteEditar["telefono"] ?? ($_POST["telefono"] ?? "")) ?>">
                    </div>

                    <div class="campo">
                        <label for="email">Correo electrónico <span class="opcional">(opcional)</span></label>
                        <input id="email" type="email" name="email" maxlength="50"
                            placeholder="Ingrese correo"
                            value="<?= escapar($clienteEditar["email"] ?? ($_POST["email"] ?? "")) ?>">
                    </div>

                    <div class="campo campo-ancho">
                        <label for="direccion">Dirección <span class="opcional">(opcional)</span></label>
                        <input id="direccion" type="text" name="direccion" maxlength="50"
                            placeholder="Ingrese dirección"
                            value="<?= escapar($clienteEditar["direccion"] ?? ($_POST["direccion"] ?? "")) ?>">
                    </div>

                    <!-- botones -->
                    <div class="botones campo-ancho">
                        <?php if ($clienteEditar): ?>
                            <button type="submit" name="actualizar" value="1" class="boton boton-principal">
                                Actualizar cliente
                            </button>
                            <a href="cliente.php" class="boton boton-secundario">Cancelar</a>
                        <?php else: ?>
                            <button type="submit" name="guardar" value="1" class="boton boton-principal">
                                Registrar cliente
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <!-- buscador y tabla (solo administrador) -->
        <?php if ($esAdmin): ?>
            <section class="panel">
                <h3 class="panel-titulo">Buscar cliente</h3>

                <form action="cliente.php" method="GET" class="buscador" id="formBuscador">
                    <input type="text" name="buscar" id="buscador" autocomplete="off"
                        placeholder="Cédula, nombre, teléfono, correo o dirección..."
                        value="<?= escapar($busqueda) ?>">
                </form>
            </section>

            <section class="panel">
                <h3 class="panel-titulo">Listado de clientes</h3>

                <div class="tabla-contenedor tabla-scroll">
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

                        <tbody id="tablaClientes">
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
                                                href="cliente.php?accion=editar&amp;cedula_cliente=<?= urlencode((string)$fila["cedula_cliente"]) ?>">
                                                Editar
                                            </a>

                                            <form action="cliente.php" method="POST" class="form-eliminar">
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

            <!-- ventana para confirmar que se elimina -->
            <div class="modal" id="modalEliminar" data-modulo="cliente" hidden>
                <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
                    <div class="modal__icono">
                        <img src="../img/error.png" alt="Error" width="80" height="80">
                    </div>

                    <h3 class="modal__titulo" id="tituloEliminar">Eliminar cliente</h3>
                    <p class="modal__texto" id="textoEliminar">¿Seguro que deseas eliminar este cliente? Esta acción no se puede deshacer.</p>

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

            <!-- ayuda -->
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

            <!-- informacion -->
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

        <!-- parte inferior -->
        <div class="footer-inferior">
            <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
            <p>Sistema de gestión de inventario</p>
        </div>
    </footer>

    <script src="../js/cliente.js"></script>
</body>
</html>
