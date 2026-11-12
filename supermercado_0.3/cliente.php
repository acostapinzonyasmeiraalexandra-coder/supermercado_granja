<?php

// 1. usuario y permisos cargo 1 administrador | cargo 2 empleado
// el usuario y el cargo llegan por la url (usuario y rol).
$usuario = trim((string) ($_GET["usuario"] ?? ""));
$cargo = (string) ($_GET["rol"] ?? "");

// si faltan los datos de acceso devuelve al inicio de sesion
if ($usuario === "" || ($cargo !== "1" && $cargo !== "2")) {
    // redirige a index.html
    header("Location: index.html");
    exit();
}

// datos que se agregan a cada enlace y formulario para no perder el usuario.
$parametros = "usuario=" . urlencode($usuario) . "&rol=" . urlencode($cargo);
// esta variable es igual que parametros pero protegida para usarla en el html
$parametrosHtml = htmlspecialchars($parametros, ENT_QUOTES, "UTF-8");

// esta variable indica si el usuario es administrador (cargo 1)
$esAdmin = ($cargo === "1");
// esta variable almacena la primera letra del usuario para el encabezado
$inicial = strtoupper(substr(trim($usuario), 0, 1));
// esta variable almacena el texto del cargo para mostrarlo en pantalla
$nombreCargo = $esAdmin ? "Administrador" : "Empleado";

// esta funcion evita que el texto rompa el html
function escapar($dato): string
{
    return htmlspecialchars((string)$dato, ENT_QUOTES, "UTF-8");
}

// esta funcion muestra un icono de la carpeta img
function icono(string $nombre): string
{
    return '<img src="img/' . escapar($nombre) . '.png" alt="" class="icono">';
}

// 2. conexion
require_once "conexion.php";

// acepta otro nombre para la variable de conexion
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}
// si no hay una conexion valida detiene la pagina
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("No se encontró una conexión válida en conexion.php.");
}

// hace que los errores de mysql se manejen como excepciones
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// define la codificacion de los caracteres de la base de datos
$conn->set_charset("utf8mb4");

// esta variable almacena el mensaje que se muestra al usuario
$mensaje = "";
// esta variable almacena el tipo de mensaje (ok o error)
$tipoMensaje = "";
// esta variable almacena los datos del registro que se va a editar
$clienteEditar = null;

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
                header("Location: cliente.php?" . $parametros . "&mensaje=eliminado");
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
            if (
                !ctype_digit($cedula) || $nombre === "" || $telefono === "" ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) || $direccion === ""
            ) {
                // lanza un error para avisar que el dato no es valido
                throw new InvalidArgumentException("Completa todos los campos correctamente.");
            }

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
            header("Location: cliente.php?" . $parametros . "&mensaje=actualizado");
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
        if (
            !ctype_digit($cedula) || (int)$cedula < 1 ||
            $nombre === "" || $telefono === "" ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) || $direccion === ""
        ) {
            // lanza un error para avisar que el dato no es valido
            throw new InvalidArgumentException("Completa todos los campos correctamente.");
        }

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
        header("Location: cliente.php?" . $parametros . "&mensaje=registrado");
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

// 8. listado de clientes (solo administrador), la busqueda la hace React en el navegador
$listaClientes = [];
if ($esAdmin) {
    // intenta ejecutar el codigo y atrapa los errores
    try {
        // ejecuta la consulta sql
        $resultadoClientes = $conn->query(
            "SELECT cedula_cliente, nombre, telefono, email, direccion
             FROM cliente
             ORDER BY cedula_cliente ASC"
        );

        // recorre cada fila y la guarda en la lista
        while ($fila = $resultadoClientes->fetch_assoc()) {
            $listaClientes[] = $fila;
        }
    } catch (mysqli_sql_exception $e) {
        // si falla la base de datos guarda el mensaje de error
        $mensaje = "No se pudo cargar el listado de clientes.";
        $tipoMensaje = "error";
    }
}

// envia los datos de php a react de forma segura
$clientesJson = json_encode(
    $listaClientes,
    JSON_HEX_TAG | 
    JSON_HEX_APOS | 
    JSON_HEX_QUOT | 
    JSON_HEX_AMP | 
    JSON_UNESCAPED_UNICODE
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes | La Granja</title>
    <!-- hoja de estilos de la pagina -->
    <link rel="stylesheet" href="css/cliente.css">

    <!-- React sencillo desde CDN -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
</head>
<!-- cuerpo de la pagina -->
<body class="pagina-cliente">

<!-- encabezado de la pagina -->
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

    <!-- mensaje de aviso -->
    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= escapar($tipoMensaje) ?>" role="status">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- formulario: el empleado lo ve siempre; el administrador solo al editar -->
    <?php if (!$esAdmin || $clienteEditar): ?>
    <!-- panel de contenido -->
    <section class="panel">
        <h3 class="panel-titulo"><?= $clienteEditar ? "Editar cliente" : "Ingresar cliente" ?></h3>

        <!-- formulario -->
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

            <!-- botones del formulario -->
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
        <!-- aqui React dibuja el buscador y la tabla -->
        <div id="app-clientes"></div>
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
    // escucha el clic del usuario
    cerrar.addEventListener("click", ocultarMenu);
    // escucha el clic del usuario
    fondo.addEventListener("click", ocultarMenu);

    // escucha las teclas que presiona el usuario
    document.addEventListener("keydown", evento => {
        if (evento.key === "Escape") ocultarMenu();
    });
})();
</script>
<?php if ($esAdmin): ?>
<!-- datos iniciales que envia php a React -->
<script>
    window.clientesIniciales = <?= $clientesJson ?: "[]" ?>;
    window.datosUsuario = {
        usuario: <?= json_encode($usuario, JSON_UNESCAPED_UNICODE) ?>,
        rol: <?= json_encode($cargo) ?>
    };
</script>

<!-- busqueda automatica con React -->
<script type="text/babel">

    const { useState } = React;

    function AplicacionClientes() {

        // esta variable almacena el texto que se escribe en el buscador
        const [busqueda, setBusqueda] = useState("");

        const lista = window.clientesIniciales || [];
        const datos = window.datosUsuario;

        // usuario y cargo para no perderlos en los enlaces
        const parametros = "usuario=" + encodeURIComponent(datos.usuario) +
                           "&rol=" + encodeURIComponent(datos.rol);

        // filtra mientras el usuario escribe
        const filtrados = lista.filter(item => {

            const texto = busqueda.toLowerCase().trim();

            return (
                String(item.cedula_cliente).toLowerCase().includes(texto) ||
                String(item.nombre).toLowerCase().includes(texto) ||
                String(item.telefono).toLowerCase().includes(texto) ||
                String(item.email).toLowerCase().includes(texto) ||
                String(item.direccion).toLowerCase().includes(texto)
            );
        });

        return (
            <div>

                {/* buscador */}
                <section className="panel">
                    <h3 className="panel-titulo">Buscar cliente</h3>
                    <div className="buscador">
                        <input
                            type="text"
                            placeholder="Cédula, nombre, teléfono, correo o dirección..."
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
                    <h3 className="panel-titulo">Listado de clientes</h3>
                    <div className="tabla-contenedor">
                        <table className="tabla">
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

                                {filtrados.length > 0 ? (

                                    filtrados.map(item => (

                                        <tr key={item.cedula_cliente}>
                                            <td>{item.cedula_cliente}</td>
                                            <td>{item.nombre}</td>
                                            <td>{item.telefono}</td>
                                            <td>{item.email}</td>
                                            <td>{item.direccion}</td>
                                            <td className="tabla-acciones">
                                                <a
                                                    className="boton boton-chico boton-editar"
                                                    href={"cliente.php?" + parametros + "&accion=editar&cedula_cliente=" + encodeURIComponent(item.cedula_cliente)}
                                                >
                                                    Editar
                                                </a>

                                                <form
                                                    action={"cliente.php?" + parametros}
                                                    method="POST"
                                                    className="form-eliminar"
                                                    onSubmit={(e) => {
                                                        if (!confirm("¿Seguro que deseas eliminar este cliente?")) {
                                                            e.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    <input type="hidden" name="cedula_cliente" value={item.cedula_cliente} />
                                                    <button type="submit" name="eliminar_cliente" value="1" className="boton boton-chico boton-eliminar">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>

                                    ))

                                ) : (

                                    <tr>
                                        <td colSpan="6" className="tabla-vacia">No se encontraron clientes.</td>
                                    </tr>

                                )}

                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        );
    }

    ReactDOM.createRoot(document.getElementById("app-clientes")).render(<AplicacionClientes />);

</script>
<?php endif; ?>
</body>
</html>
