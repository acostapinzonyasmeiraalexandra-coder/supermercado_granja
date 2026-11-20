<?php
// ========== inicio de sesion ==========
// session_start debe ir antes de cualquier html
// si el usuario ya inicio sesion, se manda directo al menu
include_once(__DIR__ . "/sesion.php");

if (!empty($_SESSION["usuario"])) {
    header("Location: menu.php");
    exit();
}

// ========== conexion a la base de datos ==========
// mysqli_report hace que los errores de la base de datos se puedan atrapar con try/catch
// conexion.php debe crear la variable $conn
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once("conexion.php");

// ========== funcion para mostrar texto seguro ==========
// evita que lo que escribe la persona dañe el html
function e($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

// ========== variables de la pagina ==========
// $mensaje es el texto de la ventana de aviso
// $tipoMensaje puede ser "ok" (exito) o "error"
$mensaje = "";
$tipoMensaje = "";

// ========== registrar usuario ==========
// se ejecuta solo cuando se presiona el boton "Registrar usuario"
if (isset($_POST["guardar"])) {

    // se recogen los datos del formulario y se les quitan los espacios sobrantes
    $cedula          = trim((string)($_POST["cedula"] ?? ""));
    $nombre          = trim((string)($_POST["nombre"] ?? ""));
    $apellido        = trim((string)($_POST["apellido"] ?? ""));
    $telefono        = trim((string)($_POST["telefono"] ?? ""));
    $direccion       = trim((string)($_POST["direccion"] ?? ""));
    $email           = trim((string)($_POST["email"] ?? ""));
    $usuario         = trim((string)($_POST["usuario"] ?? ""));
    $contra          = (string)($_POST["contra"] ?? "");
    $id_tipo_usuario = (int)($_POST["id_tipo_usuario"] ?? 0);

    try {

        // validaciones: si alguna falla, se lanza un error que atrapa el catch
        // telefono y direccion son los unicos campos que no son obligatorios
        if ($cedula === "" || $nombre === "" || $apellido === "" ||
            $email === "" || $usuario === "" || $contra === "") {
            throw new InvalidArgumentException("Completa todos los campos obligatorios.");
        }

        // la cedula solo numeros, entre 6 y 10 digitos
        if (!ctype_digit($cedula) || strlen($cedula) < 6 || strlen($cedula) > 10) {
            throw new InvalidArgumentException("La cédula debe tener solo números (entre 6 y 10 dígitos).");
        }

        // nombre y apellido solo letras y espacios
        if (!preg_match('/^[\p{L}\s]+$/u', $nombre) || !preg_match('/^[\p{L}\s]+$/u', $apellido)) {
            throw new InvalidArgumentException("El nombre y el apellido solo pueden tener letras.");
        }

        // telefono: si lo escribe, solo numeros entre 7 y 10 digitos
        if ($telefono !== "" && (!ctype_digit($telefono) || strlen($telefono) < 7 || strlen($telefono) > 10)) {
            throw new InvalidArgumentException("El teléfono debe tener solo números (entre 7 y 10 dígitos).");
        }

        // correo con formato valido
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Escribe un correo electrónico válido.");
        }

        // usuario: letras, numeros, punto, guion y guion bajo
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $usuario)) {
            throw new InvalidArgumentException("El usuario solo puede tener letras, números, punto, guion o guion bajo.");
        }

        // contraseña minimo 8 caracteres
        if (strlen($contra) < 8) {
            throw new InvalidArgumentException("La contraseña debe tener mínimo 8 caracteres.");
        }

        // cargo: 1 = administrador, 2 = empleado
        if (!in_array($id_tipo_usuario, [1, 2], true)) {
            throw new InvalidArgumentException("Selecciona un cargo válido.");
        }

        // la contraseña nunca se guarda en texto plano: se protege con password_hash
        // PASSWORD_DEFAULT usa el algoritmo mas seguro de la version de php (bcrypt o mejor)
        $contraProtegida = password_hash($contra, PASSWORD_DEFAULT);

        // guardar en la base de datos con consulta preparada (evita inyeccion sql)
        // la cedula se envia como texto para que no se desborde un numero grande
        $stmt = $conn->prepare(
            "INSERT INTO usuario
            (cedula, nombre, apellido, telefono, direccion, email, usuario, contra, id_tipo_usuario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssssssi",
            $cedula,
            $nombre,
            $apellido,
            $telefono,
            $direccion,
            $email,
            $usuario,
            $contraProtegida,
            $id_tipo_usuario
        );

        $stmt->execute();
        $stmt->close();

        // registro exitoso: se prepara el mensaje y se limpia el formulario
        $mensaje = "Usuario registrado correctamente. Ahora inicia sesión.";
        $tipoMensaje = "ok";
        $_POST = [];

    } catch (InvalidArgumentException $error) {
        // error de validacion: algun dato no es correcto
        $mensaje = $error->getMessage();
        $tipoMensaje = "error";

    } catch (mysqli_sql_exception $error) {
        // error de base de datos
        // 1062 significa que la cedula o el usuario ya existen
        if ($error->getCode() == 1062) {
            $mensaje = "La cédula o el nombre de usuario ya están registrados.";
        } else {
            // el error tecnico se guarda en el log y no se le muestra a la persona
            error_log($error->getMessage());
            $mensaje = "No se pudo registrar el usuario. Verifica los datos e inténtalo nuevamente.";
        }
        $tipoMensaje = "error";
    }
}

// cargo que queda seleccionado en la lista (0 = "Seleccione")
$cargoSeleccionado = (int)($_POST["id_tipo_usuario"] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de usuarios | La Granja</title>
    <link rel="stylesheet" href="../css/registro.css?v=4">
    <link rel="stylesheet" href="../css/menu.css?v=20261118">
</head>
<body class="pagina-registro">

<!-- ========== encabezado con logo y nombre del sistema ========== -->
<header class="encabezado">
    <a href="../index.php" class="encabezado-logo">
        <img src="../img/logo.png" alt="Logo de La Granja">
    </a>
    <div class="encabezado-texto">
        <h1>Supermercado La Granja</h1>
        <p>Sistema de gestión de inventario</p>
    </div>
</header>

<!-- ========== contenido principal ========== -->
<main class="contenido">

    <!-- titulo de la pagina -->
    <section class="titulo-pagina">
        <div class="titulo-icono">
            <img src="../img/usuario.png" alt="Icono de usuario">
        </div>
        <div>
            <h2>Crear una cuenta</h2>
            <p>Completa tus datos para registrarte en el sistema.</p>
        </div>
    </section>

    <!-- panel con el formulario -->
    <section class="panel">
        <div class="panel-encabezado">
            <div>
                <h3>Información del usuario</h3>
                <p>Ingresa tus datos personales y de acceso.</p>
            </div>
        </div>

        <!--
            Formulario de registro.
            Cada campo filtra lo que se escribe con "oninput":
            - cedula y telefono: solo dejan numeros
            - nombre y apellido: solo dejan letras y espacios
            Ademas tienen required / pattern para validar al enviar.
        -->
        <form action="registro.php" method="POST" class="formulario">

            <!-- datos personales -->
            <div class="campo">
                <label for="cedula">Cédula <span class="obligatorio">*</span></label>
                <input type="text" id="cedula" name="cedula" required
                       inputmode="numeric" pattern="[0-9]{6,10}" maxlength="10"
                       oninput="this.value = this.value.replace(/\D/g, '')"
                       title="Escribe solo números (entre 6 y 10 dígitos)."
                       placeholder="Ingrese cédula"
                       value="<?= e($_POST["cedula"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="nombre">Nombre <span class="obligatorio">*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="50"
                       pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+"
                       oninput="this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/g, '')"
                       title="Escribe solo letras, sin números."
                       placeholder="Ingrese nombre"
                       value="<?= e($_POST["nombre"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="apellido">Apellido <span class="obligatorio">*</span></label>
                <input type="text" id="apellido" name="apellido" required maxlength="50"
                       pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+"
                       oninput="this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/g, '')"
                       title="Escribe solo letras, sin números."
                       placeholder="Ingrese apellido"
                       value="<?= e($_POST["apellido"] ?? "") ?>">
            </div>

            <!-- telefono (no obligatorio) -->
            <div class="campo">
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono"
                       inputmode="numeric" pattern="[0-9]{7,10}" maxlength="10"
                       oninput="this.value = this.value.replace(/\D/g, '')"
                       title="Escribe solo números (entre 7 y 10 dígitos)."
                       placeholder="Ingrese teléfono"
                       value="<?= e($_POST["telefono"] ?? "") ?>">
            </div>

            <!-- direccion (no obligatoria) -->
            <div class="campo campo-ancho">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion" maxlength="50"
                       placeholder="Ingrese dirección"
                       value="<?= e($_POST["direccion"] ?? "") ?>">
            </div>

            <div class="campo campo-ancho">
                <label for="email">Correo electrónico <span class="obligatorio">*</span></label>
                <input type="email" id="email" name="email" required maxlength="50"
                       placeholder="Ingrese correo"
                       value="<?= e($_POST["email"] ?? "") ?>">
            </div>

            <!-- datos de acceso al sistema -->
            <div class="separador-formulario">
                <h3>Datos de acceso</h3>
                <p>Estos datos te permitirán ingresar al sistema.</p>
            </div>

            <div class="campo">
                <label for="usuario">Nombre de usuario <span class="obligatorio">*</span></label>
                <input type="text" id="usuario" name="usuario" required maxlength="50"
                       pattern="[A-Za-z0-9._\-]+"
                       oninput="this.value = this.value.replace(/[^A-Za-z0-9._-]/g, '')"
                       title="Usa letras, números, punto, guion o guion bajo."
                       autocomplete="username"
                       placeholder="Ingrese usuario"
                       value="<?= e($_POST["usuario"] ?? "") ?>">
            </div>

            <div class="campo">
                <label for="contra">Contraseña <span class="obligatorio">*</span></label>
                <input type="password" id="contra" name="contra" required
                       minlength="8" maxlength="50"
                       autocomplete="new-password"
                       placeholder="Mínimo 8 caracteres">
            </div>

            <!-- cargo: administrador o empleado -->
            <div class="campo campo-ancho">
                <label for="id_tipo_usuario">Cargo <span class="obligatorio">*</span></label>
                <select id="id_tipo_usuario" name="id_tipo_usuario" required>
                    <option value="" <?= ($cargoSeleccionado === 0) ? "selected" : "" ?>>Seleccione</option>
                    <option value="1" <?= ($cargoSeleccionado === 1) ? "selected" : "" ?>>Administrador</option>
                    <option value="2" <?= ($cargoSeleccionado === 2) ? "selected" : "" ?>>Empleado</option>
                </select>
            </div>

            <!-- boton para enviar el formulario -->
            <div class="botones campo-ancho">
                <button type="submit" name="guardar" class="boton boton-principal">Registrar usuario</button>
            </div>

        </form>

        <!-- enlace para volver al inicio de sesion -->
        <p class="texto-login">¿Ya tienes cuenta? <a href="../index.php">Iniciar sesión</a></p>
    </section>
</main>

<!-- ========== pie de pagina ========== -->
<footer class="pie-pagina">
    <div class="footer-contenido">

        <!-- enlaces de ayuda -->
        <section class="footer-ayuda">
            <h4>Ayuda y soporte</h4>
            <p class="footer-descripcion">Consulta la información necesaria para utilizar correctamente el sistema de inventario.</p>
            <a href="ayuda.php" class="footer-enlace"><span class="footer-icono">?</span>Centro de ayuda</a>
            <a href="ayuda.php#uso" class="footer-enlace"><span class="footer-icono">✓</span>Cómo utilizar el sistema</a>
            <a href="ayuda.php#soporte" class="footer-enlace"><span class="footer-icono">✉</span>Soporte</a>
        </section>

        <!-- informacion del sistema -->
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

    <!-- derechos de autor -->
    <div class="footer-inferior">
        <p>&copy; <?= date("Y") ?> Supermercado La Granja. Todos los derechos reservados.</p>
        <p>Sistema de gestión de inventario</p>
    </div>
</footer>

<!--
    ========== ventana de aviso ==========
    Solo aparece si hay un mensaje (exito o error).
    - exito: muestra el chulo verde (img/exito.png)
    - error: muestra la equis roja (img/error.png)
    data-exito le dice al js si debe mandar al inicio de sesion al cerrar.
-->
<?php if ($mensaje !== ""): ?>
    <div class="modal" id="modalAviso" data-exito="<?= ($tipoMensaje === "ok") ? "1" : "0" ?>">
        <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="modalTitulo">

            <div class="modal__icono">
                <?php if ($tipoMensaje === "ok"): ?>
                    <img src="../img/exito.png" alt="Registro exitoso">
                <?php else: ?>
                    <img src="../img/error.png" alt="Error">
                <?php endif; ?>
            </div>

            <h3 class="modal__titulo" id="modalTitulo">
                <?= ($tipoMensaje === "ok") ? "¡Registro exitoso!" : "Error" ?>
            </h3>
            <p class="modal__texto"><?= e($mensaje) ?></p>
            <button type="button" class="modal__boton modal__boton--<?= e($tipoMensaje) ?>" id="cerrarModal">Aceptar</button>
        </div>
    </div>
<?php endif; ?>

<!-- js que controla el cierre de la ventana de aviso -->
<script src="../js/registro.js?v=5"></script>

</body>
</html>