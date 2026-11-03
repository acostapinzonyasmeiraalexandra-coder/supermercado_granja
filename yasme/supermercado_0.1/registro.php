<?php
// conexion
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include("conexion.php");

// revisa que haya conexion a la base de datos
if (!isset($conn) && isset($conexion)) {
    $conn = $conexion;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("No se pudo conectar con la base de datos.");
}
$conn->set_charset("utf8mb4");

// evita que el texto rompa el html
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

// variables de la pagina
$mensaje = "";
$tipoMensaje = "";

// registrar usuario
if (isset($_POST["guardar"])) {
    // datos que llegan del formulario
    $cedula = trim((string)($_POST["cedula"] ?? ""));
    $nombre = trim((string)($_POST["nombre"] ?? ""));
    $apellido = trim((string)($_POST["apellido"] ?? ""));
    $telefono = trim((string)($_POST["telefono"] ?? ""));
    $direccion = trim((string)($_POST["direccion"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $usuario = trim((string)($_POST["usuario"] ?? ""));
    $contra = (string)($_POST["contra"] ?? "");
    $id_tipo_usuario = (int)($_POST["id_tipo_usuario"] ?? 0);

    try {
        // revisa que los datos sean validos
        if (!ctype_digit($cedula) || $nombre === "" || $apellido === "" || $telefono === "" ||
            $direccion === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $usuario === "" || strlen($contra) < 8) {
            throw new InvalidArgumentException("Completa todos los campos correctamente.");
        }

        // el cargo solo puede ser 1 (administrador) o 2 (empleado)
        if (!in_array($id_tipo_usuario, [1, 2], true)) {
            throw new InvalidArgumentException("Selecciona un cargo válido.");
        }

        // guarda el usuario en la base de datos
        $cedulaNumero = (int)$cedula;
        $stmt = $conn->prepare(
            "INSERT INTO usuario
            (cedula, nombre, apellido, telefono, direccion, email, usuario, contra, id_tipo_usuario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "isssssssi",
            $cedulaNumero,
            $nombre,
            $apellido,
            $telefono,
            $direccion,
            $email,
            $usuario,
            $contra,
            $id_tipo_usuario
        );
        $stmt->execute();
        $stmt->close();

        // avisa y manda al inicio de sesion
        echo "<script>
            alert('Usuario registrado correctamente. Ahora inicia sesión.');
            window.location.href = 'index.html';
        </script>";
        exit();
    } catch (InvalidArgumentException $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    } catch (mysqli_sql_exception $e) {
        // 1062 = cedula o usuario repetido
        if ($e->getCode() == 1062) {
            $mensaje = "La cédula o el nombre de usuario ya están registrados.";
        } else {
            error_log($e->getMessage());
            $mensaje = "No se pudo registrar el usuario. Verifica los datos e inténtalo nuevamente.";
        }
        $tipoMensaje = "error";
    }
}

// cargo que queda marcado en el formulario (por defecto empleado)
$cargoSeleccionado = (int)($_POST["id_tipo_usuario"] ?? 2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de usuarios | La Granja</title>
    <link rel="stylesheet" href="css/registro.css">
</head>
<body class="pagina-registro">

<!-- encabezado -->
<header class="encabezado">
    <a href="index.html" class="encabezado-logo">
        <img src="img/logo.png" alt="Logo de La Granja">
    </a>
    <div class="encabezado-texto">
        <h1>Supermercado La Granja</h1>
        <p>Sistema de gestión de inventario</p>
    </div>
</header>

<!-- contenido principal -->
<main class="contenido">
    <section class="titulo-pagina">
        <div class="titulo-icono">
            <img src="img/usuario.png" alt="Icono de usuario">
        </div>
        <div>
            <h2>Crear una cuenta</h2>
            <p>Completa tus datos para registrarte en el sistema.</p>
        </div>
    </section>

    <!-- mensaje de aviso -->
    <?php if ($mensaje !== ""): ?>
        <div class="mensaje mensaje-<?= e($tipoMensaje) ?>" role="alert">
            <?= e($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- formulario de registro -->
    <section class="panel">
        <div class="panel-encabezado">
            <div>
                <h3>Información del usuario</h3>
                <p>Ingresa tus datos personales y de acceso.</p>
            </div>
        </div>

        <form action="registro.php" method="POST" class="formulario">

            <!-- datos personales -->
            <div class="campo">
                <label for="cedula">Cédula *</label>
                <input type="number" id="cedula" name="cedula" required min="1" placeholder="Número de cédula" value="<?= e($_POST["cedula"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="50" placeholder="Ingresa tu nombre" value="<?= e($_POST["nombre"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="apellido">Apellido *</label>
                <input type="text" id="apellido" name="apellido" required maxlength="50" placeholder="Ingresa tu apellido" value="<?= e($_POST["apellido"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="telefono">Teléfono *</label>
                <input type="tel" id="telefono" name="telefono" required maxlength="50" placeholder="Número de teléfono" value="<?= e($_POST["telefono"] ?? "") ?>">
            </div>

            <div class="campo campo-ancho">
                <label for="direccion">Dirección *</label>
                <input type="text" id="direccion" name="direccion" required maxlength="50" placeholder="Dirección de residencia" value="<?= e($_POST["direccion"] ?? "") ?>">
            </div>

            <div class="campo campo-ancho">
                <label for="email">Correo electrónico *</label>
                <input type="email" id="email" name="email" required maxlength="50" placeholder="ejemplo@correo.com" value="<?= e($_POST["email"] ?? "") ?>">
            </div>

            <!-- datos de acceso -->
            <div class="separador-formulario">
                <h3>Datos de acceso</h3>
                <p>Estos datos te permitirán ingresar al sistema.</p>
            </div>

            <div class="campo">
                <label for="usuario">Nombre de usuario *</label>
                <input type="text" id="usuario" name="usuario" required maxlength="50" autocomplete="username" placeholder="Crea tu nombre de usuario" value="<?= e($_POST["usuario"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="contra">Contraseña *</label>
                <input type="password" id="contra" name="contra" required minlength="8" maxlength="50" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
            </div>

            <!-- cargo del usuario -->
            <div class="campo campo-ancho">
                <label for="id_tipo_usuario">Cargo *</label>
                <select id="id_tipo_usuario" name="id_tipo_usuario" required>
                    <option value="1" <?= ($cargoSeleccionado === 1) ? "selected" : "" ?>>Administrador</option>
                    <option value="2" <?= ($cargoSeleccionado !== 1) ? "selected" : "" ?>>Empleado</option>
                </select>
            </div>

            <!-- boton de registrar -->
            <div class="botones campo-ancho">
                <button type="submit" name="guardar" class="boton boton-principal">Registrar usuario</button>
            </div>

        </form>

        <!-- enlace para iniciar sesion -->
        <p class="texto-login">¿Ya tienes cuenta? <a href="index.html">Iniciar sesión</a></p>
    </section>
</main>

<!-- pie de pagina -->
<footer class="pie-pagina">
    <div class="footer-contenido">
        <section class="footer-ayuda">
            <h4>Ayuda y soporte</h4>
            <p class="footer-descripcion">Consulta la información necesaria para utilizar correctamente el sistema de inventario.</p>
            <a href="ayuda.php" class="footer-enlace"><span class="footer-icono">?</span>Centro de ayuda</a>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">✓</span>Cómo utilizar el sistema</a>
            <a href="ayuda.php#soporte" class="footer-enlace"><span class="footer-icono">✉</span>Soporte</a>
        </section>
        <section class="footer-informacion">
            <h4>Información del sistema</h4>
            <p><strong>Supermercado La Granja</strong><br>Sistema de gestión de inventario.</p>
            <p>Este sistema permite gestionar la información relacionada con los productos y los movimientos del inventario.</p>
            <div class="footer-nota">
                <strong>Registro de usuarios</strong>
                <p>Protege tus datos de acceso y no compartas tu contraseña con otras personas.</p>
            </div>
        </section>
    </div>
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

</body>
</html>
