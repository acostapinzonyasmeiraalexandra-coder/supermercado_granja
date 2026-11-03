<?php
include("conexion.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mensaje = "";

// Procesar el formulario cuando se presione el botón Guardar
if (isset($_POST['guardar'])) {

    $cedula = intval($_POST['cedula']);
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $telefono = $_POST['telefono'];
    $direccion = $_POST['direccion'];
    $email = $_POST['email'];
    $usuario = $_POST['usuario'];
    $contra = $_POST['contra'];
    $id_tipo_usuario = intval($_POST['id_tipo_usuario']);

    try {

        $stmt = $conn->prepare("INSERT INTO usuario (cedula, nombre, apellido, telefono, direccion, email, usuario, contra, id_tipo_usuario)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssi", $cedula, $nombre, $apellido, $telefono, $direccion, $email, $usuario, $contra, $id_tipo_usuario);
        $stmt->execute();
        $stmt->close();

        // Después de registrarse, debe iniciar sesión
        echo "<script>
                alert('Usuario registrado correctamente. Ahora inicia sesión.');
                window.location.href = 'index.html';
              </script>";
        exit();

    } catch (mysqli_sql_exception $e) {

        if ($e->getCode() == 1062) {
            $mensaje = "Error: la cédula o el usuario ya están registrados.";
        } else {
            $mensaje = "Error al guardar el usuario: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuarios</title>
</head>
<body>

    <h2>Registrar Nuevo Usuario</h2>

    <?php if ($mensaje != ""): ?>
        <p style="color:red;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>

    <!-- action="" envía los datos a este mismo archivo -->
    <form action="" method="POST">

        <label>Cédula:</label><br>
        <input type="number" name="cedula" required placeholder="Número de Cédula"><br><br>

        <label>Nombre:</label><br>
        <input type="text" name="nombre" required placeholder="Nombre"><br><br>

        <label>Apellido:</label><br>
        <input type="text" name="apellido" required placeholder="Apellido"><br><br>

        <label>Teléfono:</label><br>
        <input type="text" name="telefono" required placeholder="Teléfono"><br><br>

        <label>Dirección:</label><br>
        <input type="text" name="direccion" required placeholder="Dirección"><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required placeholder="Correo Electrónico"><br><br>

        <label>Nombre de Usuario (Login):</label><br>
        <input type="text" name="usuario" required placeholder="Usuario para ingresar"><br><br>

        <label>Contraseña:</label><br>
        <input type="password" name="contra" required placeholder="Contraseña"><br><br>

        <label>Cargo / Rol:</label><br>
        <select name="id_tipo_usuario" required>
            <option value="">seleccione</option>
            <option value="1">Administrador</option>
            <option value="2">Empleado</option>
        </select><br><br>

        <button type="submit" name="guardar">Guardar</button>
        <button type="reset">Borrar</button>

    </form><br><br>

    <!-- Botón para regresar al inicio -->
    <form action="index.html" method="GET">
        <button type="submit">Regresar</button>
    </form>

</body>
</html>
