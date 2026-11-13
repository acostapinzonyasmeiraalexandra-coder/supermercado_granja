<?php
// pagina de inicio de sesion
session_start();

// si ya inicio sesion, lo manda directo al menu
if (!empty($_SESSION["usuario"])) {
    header("Location: php/menu.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supermercado La Granja</title>
    <?php // hoja de estilos de la pagina ?>
    <link rel="stylesheet" href="css/index.css">
</head>
<?php // cuerpo de la pagina ?>
<body class="pagina">

    <?php // ========== encabezado ========== ?>
    <header class="encabezado">
        <h2 class="encabezado__titulo">Supermercado La Granja</h2>
    </header>

    <?php // ========== contenido principal ========== ?>
    <main class="contenedor">

        <?php // tarjeta del login ?>
        <section class="tarjeta">

            <?php // formulario de ingreso ?>
            <form class="formulario" action="php/usuario.php" method="POST">

                <?php // cargo (obligatorio) ?>
                <div class="campo">
                    <label class="campo__etiqueta" for="cargo">
                        Cargo: <span class="campo__obligatorio">*</span>
                    </label>
                    <select class="campo__select" id="cargo" name="cargo" required>
                        <option value="">seleccione</option>
                        <option value="1">Administrador</option>
                        <option value="2">Empleado</option>
                    </select>
                </div>

                <?php // usuario (obligatorio) ?>
                <div class="campo">
                    <label class="campo__etiqueta" for="usuario">
                        Usuario: <span class="campo__obligatorio">*</span>
                    </label>
                    <input class="campo__input" type="text" id="usuario" name="usuario" required>
                </div>

                <?php // contrasena (obligatorio) ?>
                <div class="campo">
                    <label class="campo__etiqueta" for="contra">
                        Contraseña: <span class="campo__obligatorio">*</span>
                    </label>
                    <input class="campo__input" type="password" id="contra" name="contra" required>
                </div>

                <?php // boton ingresar ?>
                <button class="boton boton--principal" type="submit" name="ingresar">Ingresar</button>

            </form>

            <?php // boton para ir al registro ?>
            <form class="formulario formulario--registro" action="php/registro.php">
                <button class="boton boton--secundario" type="submit">Registrarse</button>
            </form>

        </section>

    </main>

    <?php // ventana de error (solo aparece si el login fallo) ?>
    <?php if (isset($_GET["error"])): ?>
        <div class="modal" id="modalError">
            <div class="modal__caja" role="alertdialog" aria-modal="true" aria-labelledby="modalTitulo">
                <div class="modal__icono">&#10005;</div>
                <h3 class="modal__titulo" id="modalTitulo">Error</h3>
                <p class="modal__texto">Usuario o contraseña incorrectos</p>
                <button class="boton boton--principal" type="button" id="cerrarModal">Aceptar</button>
            </div>
        </div>

        <script>
            // elementos de la ventana
            const modal = document.getElementById("modalError");
            const botonCerrar = document.getElementById("cerrarModal");

            // cierra la ventana y limpia el ?error de la url
            function cerrarModal() {
                modal.remove();
                history.replaceState(null, "", window.location.pathname);
            }

            // cerrar con el boton
            botonCerrar.addEventListener("click", cerrarModal);

            // cerrar al hacer clic en el fondo oscuro
            modal.addEventListener("click", function (e) {
                if (e.target === modal) {
                    cerrarModal();
                }
            });

            // cerrar con la tecla Escape
            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape") {
                    cerrarModal();
                }
            });

            // pone el foco en el boton para poder cerrar con Enter
            botonCerrar.focus();
        </script>
    <?php endif; ?>

</body>
</html>