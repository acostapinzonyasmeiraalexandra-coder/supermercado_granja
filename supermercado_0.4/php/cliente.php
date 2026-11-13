<?php
// inicia la sesion
session_start();

// si no ha iniciado sesion, volver al ingreso
if (!isset($_SESSION["usuario"])) {
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

// esta funcion evita que el texto rompa el html
function escapar($dato): string
{
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

// esta funcion muestra un icono de la carpeta img
function icono(string $nombre): string
{
    return '<img src="../img/' . escapar($nombre) . '.png" alt="" class="icono">';
}

// esta funcion valida los datos del cliente (correo y direccion son opcionales)
function validarCliente(string $cedula, string $nombre, string $telefono, string $email): void
{
    // campos obligatorios: cedula, nombre y telefono
    if ($cedula === "" || $nombre === "" || $telefono === "") {
        throw new InvalidArgumentException("Completa los campos obligatorios.");
    }

    // la cedula solo puede tener numeros
    if (!ctype_digit($cedula) || (int)$cedula < 1) {
        throw new InvalidArgumentException("La cédula solo puede tener números.");
    }

    // el nombre solo puede tener letras y espacios
    if (!preg_match('/^[\p{L}\s]+$/u', $nombre)) {
        throw new InvalidArgumentException("El nombre solo puede tener letras.");
    }

    // el telefono solo puede tener numeros (entre 7 y 10 digitos)
    if (!ctype_digit($telefono) || strlen($telefono) < 7 || strlen($telefono) > 10) {
        throw new InvalidArgumentException("El teléfono debe tener solo números (entre 7 y 10 dígitos).");
    }

    // el correo es opcional, pero si se escribe debe ser valido
    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException("Escribe un correo electrónico válido.");
    }
}

// esta variable almacena el mensaje que se muestra al usuario
$mensaje = "";
// esta variable almacena el tipo de mensaje (ok o error)
$tipoMensaje = "";
// esta variable almacena los datos del registro que se va a editar
$clienteEditar = null;
// esta variable almacena el texto que se escribe en el buscador
$busqueda = "";
$clientes = null;

// 3. eliminar cliente (solo administrador)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_cliente"])) {
    // bloquea la accion si el usuario no es administrador
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para eliminar clientes.";
        $tipoMensaje = "error";
    } else {
        // datos que llegan del formulario
        $cedulaEliminar = trim((string)($_POST["cedula_cliente"] ?? ""));
        // intenta ejecutar el codigo y atrapa los errores
        try {
            if (!ctype_digit($cedulaEliminar)) {
                // lanza un error para avisar que el dato no es valido
                throw new InvalidArgumentException("Cédula no válida.");
            }

            // convierte la cedula a numero entero
            $cedulaNumero = (int)$cedulaEliminar;
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("DELETE FROM cliente WHERE cedula_cliente = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $cedulaNumero);
            // ejecuta la consulta
            $stmt->execute();
            $eliminados = $stmt->affected_rows;
            // cierra la consulta
            $stmt->close();

            if ($eliminados > 0) {
                // redirige a cliente.php
                header("Location: cliente.php?mensaje=eliminado");
                exit();
            }

            $mensaje = "No se encontró el cliente que deseas eliminar.";
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            // si falla la base de datos guarda el mensaje de error
            $mensaje = ((int)$e->getCode() === 1451)
                ? "No se puede eliminar el cliente porque tiene movimientos registrados."
                : "Ocurrió un error al eliminar el cliente.";
            $tipoMensaje = "error";
        } catch (InvalidArgumentException $e) {
            // si algun dato no es valido guarda el mensaje de validacion
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

// 4. cargar cliente para editar (solo administrador)
if (isset($_GET["accion"]) && $_GET["accion"] === "editar") {
    // bloquea la accion si el usuario no es administrador
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para editar clientes.";
        $tipoMensaje = "error";
    } else {
        // datos que llegan por la url
        $cedulaBuscar = trim((string)($_GET["cedula_cliente"] ?? ""));
        // intenta ejecutar el codigo y atrapa los errores
        try {
            if (!ctype_digit($cedulaBuscar)) {
                // lanza un error para avisar que el dato no es valido
                throw new InvalidArgumentException("Cédula no válida.");
            }

            // convierte la cedula a numero entero
            $cedulaNumero = (int)$cedulaBuscar;
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare("SELECT * FROM cliente WHERE cedula_cliente = ?");
            // envia los datos a la consulta
            $stmt->bind_param("i", $cedulaNumero);
            // ejecuta la consulta
            $stmt->execute();
            // obtiene el resultado de la consulta
            $clienteEditar = $stmt->get_result()->fetch_assoc();
            // cierra la consulta
            $stmt->close();

            if (!$clienteEditar) {
                $mensaje = "No se encontró el cliente seleccionado.";
                $tipoMensaje = "error";
            }
        } catch (InvalidArgumentException $e) {
            // si algun dato no es valido guarda el mensaje de validacion
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            // si falla la base de datos guarda el mensaje de error
            $mensaje = "No se pudieron cargar los datos del cliente.";
            $tipoMensaje = "error";
        }
    }
}

// 5. actualizar cliente (solo administrador)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar"])) {
    // bloquea la accion si el usuario no es administrador
    if (!$esAdmin) {
        $mensaje = "No tienes permiso para actualizar clientes.";
        $tipoMensaje = "error";
    } else {
        // datos que llegan del formulario
        $cedula = trim((string)($_POST["cedula_cliente"] ?? ""));
        $nombre = trim((string)($_POST["nombre"] ?? ""));
        $telefono = trim((string)($_POST["telefono"] ?? ""));
        $email = trim((string)($_POST["email"] ?? ""));
        $direccion = trim((string)($_POST["direccion"] ?? ""));

        // intenta ejecutar el codigo y atrapa los errores
        try {
            // revisa que los datos sean validos
            validarCliente($cedula, $nombre, $telefono, $email);

            // convierte la cedula a numero entero
            $cedulaNumero = (int)$cedula;
            // prepara la consulta sql de forma segura
            $stmt = $conn->prepare(
                "UPDATE cliente
                 SET nombre = ?, telefono = ?, email = ?, direccion = ?
                 WHERE cedula_cliente = ?"
            );
            // envia los datos a la consulta
            $stmt->bind_param("ssssi", $nombre, $telefono, $email, $direccion, $cedulaNumero);
            // ejecuta la consulta
            $stmt->execute();
            // cierra la consulta
            $stmt->close();

            // redirige a cliente.php
            header("Location: cliente.php?mensaje=actualizado");
            exit();
        } catch (InvalidArgumentException $e) {
            // si algun dato no es valido guarda el mensaje de validacion
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        } catch (mysqli_sql_exception $e) {
            // si falla la base de datos guarda el mensaje de error
            $mensaje = "Error al actualizar el cliente. Verifica los datos.";
            $tipoMensaje = "error";
        }
    }
}

// 6. registrar cliente (administrador y empleado)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {
    // datos que llegan del formulario
    $cedula = trim((string)($_POST["cedula_cliente"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $telefono = trim((string)($_POST["telefono"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $direccion = trim((string)($_POST["direccion"] ?? ""));

    // intenta ejecutar el codigo y atrapa los errores
    try {
        // revisa que los datos sean validos
        validarCliente($cedula, $nombre, $telefono, $email);

        // convierte la cedula a numero entero
        $cedulaNumero = (int)$cedula;
        // prepara la consulta sql de forma segura
        $stmt = $conn->prepare(
            "INSERT INTO cliente (cedula_cliente, nombre, telefono, email, direccion)
             VALUES (?, ?, ?, ?, ?)"
        );
        // envia los datos a la consulta
        $stmt->bind_param("issss", $cedulaNumero, $nombre, $telefono, $email, $direccion);
        // ejecuta la consulta
        $stmt->execute();
        // cierra la consulta
        $stmt->close();

        // redirige a cliente.php
        header("Location: cliente.php?mensaje=registrado");
        exit();
    } catch (InvalidArgumentException $e) {
        // si algun dato no es valido guarda el mensaje de validacion
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
        $mensaje = ((int)$e->getCode() === 1062)
            ? "La cédula ya está registrada."
            : "Error al registrar el cliente. Verifica los datos.";
        $tipoMensaje = "error";
    }
}

// 7. mensajes despues de redirecciones
if (isset($_GET["mensaje"])) {
    $mensajes = [
        "registrado" => ["Cliente registrado correctamente.", "ok"],
        "actualizado" => ["Cliente actualizado correctamente.", "ok"],
        "eliminado" => ["Cliente eliminado correctamente.", "error"]
    ];
    // datos que llegan por la url
    $clave = (string)$_GET["mensaje"];
    if (isset($mensajes[$clave])) {
        [$mensaje, $tipoMensaje] = $mensajes[$clave];
    }
}

// 8. listado y busqueda (solo administrador)
if ($esAdmin) {
    // intenta ejecutar el codigo y atrapa los errores
    try {
        // esta variable almacena el texto que se escribe en el buscador
        $busqueda = trim((string)($_GET["buscar"] ?? ""));
        if ($busqueda !== "") {
            // esta variable almacena el texto buscado con comodines para usar like
            $filtro = "%" . $busqueda . "%";
            // prepara la consulta sql de forma segura
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
            // envia los datos a la consulta
            $stmt->bind_param("sssss", $filtro, $filtro, $filtro, $filtro, $filtro);
            // ejecuta la consulta
            $stmt->execute();
            // obtiene el resultado de la consulta
            $clientes = $stmt->get_result();
        } else {
            // ejecuta la consulta sql
            $clientes = $conn->query(
                "SELECT cedula_cliente, nombre, telefono, email, direccion
                 FROM cliente
                 ORDER BY cedula_cliente ASC"
            );
        }
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
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
    <?php // hoja de estilos de la pagina (sube el numero v cada vez que cambies el css) ?>
    <link rel="stylesheet" href="../css/cliente.css?v=2">
</head>
<?php // cuerpo de la pagina ?>
<body class="pagina-cliente">

<?php // encabezado de la pagina ?>
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

    <?php // datos del usuario que inicio sesion ?>
    <div class="encabezado-usuario">
        <span class="usuario-inicial"><?= escapar($inicial) ?></span>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>
</header>

<?php // fondo que oscurece la pagina cuando el menu esta abierto ?>
<div class="fondo-menu" id="fondoMenu"></div>

<?php // menu lateral ?>
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

    <?php // datos del usuario en el menu lateral ?>
    <div class="menu-lateral-usuario">
        <div class="usuario-inicial usuario-grande"><?= escapar($inicial) ?></div>
        <div>
            <strong><?= escapar($usuario) ?></strong>
            <p><?= escapar($nombreCargo) ?></p>
        </div>
    </div>

    <?php // enlaces de navegacion ?>
    <nav class="navegacion">
        <p class="titulo-navegacion">MENÚ PRINCIPAL</p>

        <div class="lista-navegacion">
            <a href="menu.php" class="enlace-menu">
                <span class="caja-icono"><?= icono("inicio") ?></span>
                <span>Inicio</span>
            </a>

            <a href="cliente.php" class="enlace-menu activo" aria-current="page">
                <span class="caja-icono"><?= icono("cliente") ?></span>
                <span>Cliente</span>
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

    <?php // formulario para cerrar sesion ?>
    <form action="cerrar_sesion.php" method="POST" class="form-salir">
        <button type="submit" name="salir" value="1" class="boton-salir">
            <span class="caja-icono caja-icono-roja"><?= icono("salir") ?></span>
            <span>Cerrar sesión</span>
        </button>
    </form>
</aside>

<?php // contenido principal ?>
<main class="contenido">
    <?php // titulo de la pagina ?>
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

    <?php // mensaje de aviso ?>
    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php // formulario: el empleado lo ve siempre; el administrador solo al editar ?>
    <?php if (!$esAdmin || $clienteEditar): ?>
    <?php // panel de contenido ?>
    <section class="panel">
        <h3 class="panel-titulo"><?= $clienteEditar ? "Editar cliente" : "Ingresar cliente" ?></h3>

       <?php // formulario ?>
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
                    inputmode="numeric" pattern="[0-9]{7,10}" minlength="7" maxlength="10"
                    data-tipo="numeros" data-error="Escribe solo números (entre 7 y 10 dígitos)."
                    placeholder="Ingrese teléfono"
                    value="<?= escapar($clienteEditar["telefono"] ?? ($_POST["telefono"] ?? "")) ?>">
            </div>

            <div class="campo">
                <label for="email">Correo electrónico <span class="opcional"></span></label>
                <input id="email" type="email" name="email" maxlength="50"
                    placeholder="Ingrese correo"
                    value="<?= escapar($clienteEditar["email"] ?? ($_POST["email"] ?? "")) ?>">
            </div>

            <div class="campo campo-ancho">
                <label for="direccion">Dirección <span class="opcional"></span></label>
                <input id="direccion" type="text" name="direccion" maxlength="50"
                    placeholder="Ingrese dirección"
                    value="<?= escapar($clienteEditar["direccion"] ?? ($_POST["direccion"] ?? "")) ?>">
            </div>

            <?php // botones del formulario ?>
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

    <?php // buscador y tabla: solo administrador ?>
    <?php if ($esAdmin): ?>
        <?php // panel de contenido ?>
        <section class="panel">
            <h3 class="panel-titulo">Buscar cliente</h3>
            <?php // formulario de busqueda (busca en vivo mientras se escribe) ?>
            <form action="cliente.php" method="GET" class="buscador" id="formBuscador">
                <input type="text" name="buscar" id="buscador" autocomplete="off"
                    placeholder="Cédula, nombre, teléfono, correo o dirección..."
                    value="<?= escapar($busqueda) ?>">
    
            </form>
        </section>

        <?php // panel de contenido ?>
        <section class="panel">
            <h3 class="panel-titulo">Listado de clientes</h3>
            <div class="tabla-contenedor">
                <?php // tabla con los registros ?>
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
                                    <?php // formulario para eliminar el registro (pide confirmacion en la ventana) ?>
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

        <?php // ventana para confirmar la eliminacion ?>
        <div class="modal" id="modalEliminar" hidden>
            <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="tituloEliminar">
                <div class="modal__icono modal__icono--aviso">!</div>
                <h3 class="modal__titulo" id="tituloEliminar">Eliminar cliente</h3>
                <p class="modal__texto" id="textoEliminar">¿Seguro que deseas eliminar este cliente?</p>
                <div class="modal__botones">
                    <button type="button" class="boton boton-secundario" id="cancelarEliminar">Cancelar</button>
                    <button type="button" class="boton boton-eliminar" id="confirmarEliminar">Eliminar</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php // pie de pagina ?>
<footer class="pie-pagina">
    <div class="footer-contenido">
        <?php // enlaces de ayuda ?>
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

        <?php // informacion del sistema ?>
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

    <?php // parte inferior del pie de pagina ?>
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<?php // codigo javascript de la pagina ?>
<script>
// ========== menu lateral ==========
(() => {
    // elementos de la pagina que se usan en el codigo
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

    // escucha el clic del usuario
    abrir.addEventListener("click", mostrarMenu);
    cerrar.addEventListener("click", ocultarMenu);
    fondo.addEventListener("click", ocultarMenu);

    // escucha las teclas que presiona el usuario
    document.addEventListener("keydown", evento => {
        if (evento.key === "Escape") ocultarMenu();
    });
})();

// ========== solo letras o solo numeros ==========
// quita en vivo lo que no corresponde mientras la persona escribe
document.querySelectorAll("[data-tipo]").forEach(function (campo) {
    campo.addEventListener("input", function () {
        if (campo.dataset.tipo === "letras") {
            // deja solo letras (con tildes y ñ) y espacios
            campo.value = campo.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s]/g, "");
        } else if (campo.dataset.tipo === "numeros") {
            // deja solo numeros
            campo.value = campo.value.replace(/\D/g, "");
        }
    });
});

// ========== mensajes de validacion en español ==========
document.querySelectorAll(".formulario input").forEach(function (campo) {
    // cuando el campo no es valido, muestra el mensaje en español
    campo.addEventListener("invalid", function () {
        // limpia el mensaje anterior para recalcular el estado
        campo.setCustomValidity("");

        if (campo.validity.valueMissing) {
            // campo vacio
            campo.setCustomValidity("Por favor, llena este campo.");
        } else if (campo.validity.typeMismatch) {
            // correo mal escrito
            campo.setCustomValidity("Escribe un correo válido. Ejemplo: maria.gomez@correo.com");
        } else if (campo.validity.patternMismatch) {
            // formato incorrecto (letras o numeros)
            campo.setCustomValidity(campo.dataset.error || "El formato no es válido.");
        } else if (campo.validity.tooShort) {
            // muy corto
            campo.setCustomValidity("Debe tener al menos " + campo.minLength + " caracteres.");
        }
    });

    // borra el mensaje cuando la persona corrige el campo
    campo.addEventListener("input", function () {
        campo.setCustomValidity("");
    });
});

// ========== ventana para confirmar la eliminacion ==========
(() => {
    const modal = document.getElementById("modalEliminar");
    // si no hay ventana (empleado), no hace nada
    if (!modal) return;

    const texto = document.getElementById("textoEliminar");
    const botonConfirmar = document.getElementById("confirmarEliminar");
    const botonCancelar = document.getElementById("cancelarEliminar");
    // formulario que se va a enviar si la persona confirma
    let formularioPendiente = null;

    // abre la ventana con el nombre del cliente
    function abrirModal(formulario) {
        formularioPendiente = formulario;
        const fila = formulario.closest("tr");
        const nombre = fila ? fila.children[1].textContent.trim() : "";
        texto.textContent = nombre
            ? "¿Seguro que deseas eliminar al cliente " + nombre + "? Esta acción no se puede deshacer."
            : "¿Seguro que deseas eliminar este cliente? Esta acción no se puede deshacer.";
        modal.hidden = false;
        botonCancelar.focus();
    }

    // cierra la ventana sin eliminar
    function cerrarModal() {
        modal.hidden = true;
        formularioPendiente = null;
    }

    // intercepta el envio de cualquier formulario de eliminar (tambien los de la busqueda en vivo)
    document.addEventListener("submit", function (e) {
        if (e.target.classList.contains("form-eliminar")) {
            e.preventDefault();
            abrirModal(e.target);
        }
    });

    // confirmar: agrega el dato que espera php y envia el formulario
    botonConfirmar.addEventListener("click", function () {
        if (!formularioPendiente) return;
        const oculto = document.createElement("input");
        oculto.type = "hidden";
        oculto.name = "eliminar_cliente";
        oculto.value = "1";
        formularioPendiente.appendChild(oculto);
        formularioPendiente.submit();
    });

    // cancelar con el boton
    botonCancelar.addEventListener("click", cerrarModal);

    // cancelar al hacer clic en el fondo oscuro
    modal.addEventListener("click", function (e) {
        if (e.target === modal) cerrarModal();
    });

    // cancelar con la tecla Escape
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && !modal.hidden) cerrarModal();
    });
})();

// ========== busqueda en vivo ==========
(() => {
    const entrada = document.getElementById("buscador");
    const tabla = document.getElementById("tablaClientes");
    const formulario = document.getElementById("formBuscador");
    const botonLimpiar = document.getElementById("limpiarBusqueda");
    // si no hay buscador (empleado), no hace nada
    if (!entrada || !tabla) return;

    let temporizador = null;
    let controlador = null;

    // pide la tabla filtrada al servidor y la reemplaza
    async function buscar() {
        const texto = entrada.value.trim();
        const url = "cliente.php" + (texto !== "" ? "?buscar=" + encodeURIComponent(texto) : "");

        // cancela la busqueda anterior si todavia no termina
        if (controlador) controlador.abort();
        controlador = new AbortController();

        try {
            const respuesta = await fetch(url, { signal: controlador.signal, credentials: "same-origin" });
            const html = await respuesta.text();
            const documento = new DOMParser().parseFromString(html, "text/html");
            const nueva = documento.getElementById("tablaClientes");

            // solo reemplaza si llego la tabla
            if (nueva) {
                tabla.innerHTML = nueva.innerHTML;
                history.replaceState(null, "", url);
            }
        } catch (error) {
            if (error.name !== "AbortError") console.error(error);
        }
    }

    // busca 300 milisegundos despues de dejar de escribir
    entrada.addEventListener("input", function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(buscar, 300);
    });

    // el boton buscar tambien funciona (sin recargar la pagina)
    formulario.addEventListener("submit", function (e) {
        e.preventDefault();
        clearTimeout(temporizador);
        buscar();
    });

    // el boton limpiar borra el texto y muestra todos
    botonLimpiar.addEventListener("click", function () {
        entrada.value = "";
        buscar();
        entrada.focus();
    });
})();
</script>
</body>
</html>