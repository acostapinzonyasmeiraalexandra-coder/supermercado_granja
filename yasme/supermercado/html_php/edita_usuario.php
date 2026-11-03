<?php
session_start();

// Si no ha iniciado sesión, al inicio
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["cargo"])) {
    header("Location: index.html");
    exit();
}

// Solo el administrador (cargo 1) puede entrar aquí
if ($_SESSION["cargo"] !== "1") {
    die("No tienes permiso para entrar aquí. <a href='menu.php'>Volver al menú</a>");
}

include("conexion.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$usuario_sesion = $_SESSION["usuario"];
$mensaje = "";
$usuario_editar = null;
$busqueda = "";


/* ELIMINAR */

if (isset($_GET['borrar'])) {

    $cedula = intval($_GET['borrar']);

    try {

        // No deja eliminar al usuario que tiene la sesión abierta
        $stmt = $conn->prepare("DELETE FROM usuario WHERE cedula = ? AND usuario <> ?");
        $stmt->bind_param("is", $cedula, $usuario_sesion);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $mensaje = "Usuario eliminado correctamente.";
        } else {
            $mensaje = "No se eliminó: el usuario no existe o es tu propio usuario.";
        }

        $stmt->close();

    } catch (mysqli_sql_exception $e) {

        $mensaje = "Error al eliminar: " . $e->getMessage();
    }
}


/* EDITAR (cargar los datos en el formulario) */

if (isset($_GET['editar'])) {

    $cedula = intval($_GET['editar']);

    $stmt = $conn->prepare("SELECT * FROM usuario WHERE cedula = ?");
    $stmt->bind_param("i", $cedula);
    $stmt->execute();
    $res = $stmt->get_result();
    $usuario_editar = $res->fetch_assoc();
    $stmt->close();
}


/* ACTUALIZAR */

if (isset($_POST['actualizar'])) {

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

        if ($contra != "") {

            // Si escribió una contraseña nueva, se cambia
            $stmt = $conn->prepare("UPDATE usuario SET nombre = ?, apellido = ?, telefono = ?, direccion = ?, email = ?, usuario = ?, contra = ?, id_tipo_usuario = ? WHERE cedula = ?");
            $stmt->bind_param("sssssssii", $nombre, $apellido, $telefono, $direccion, $email, $usuario, $contra, $id_tipo_usuario, $cedula);

        } else {

            // Si la dejó vacía, se queda la contraseña que ya tenía
            $stmt = $conn->prepare("UPDATE usuario SET nombre = ?, apellido = ?, telefono = ?, direccion = ?, email = ?, usuario = ?, id_tipo_usuario = ? WHERE cedula = ?");
            $stmt->bind_param("ssssssii", $nombre, $apellido, $telefono, $direccion, $email, $usuario, $id_tipo_usuario, $cedula);
        }

        $stmt->execute();
        $stmt->close();

        $mensaje = "Usuario actualizado correctamente.";

    } catch (mysqli_sql_exception $e) {

        $mensaje = "Error al actualizar: " . $e->getMessage();
    }
}


/* BUSCAR */

if (isset($_GET['buscar'])) {

    $busqueda = trim($_GET['buscar']);
}


/* LISTAR USUARIOS (sin mostrar la contraseña) */

$sql = "SELECT cedula, nombre, apellido, telefono, email, direccion, usuario, id_tipo_usuario
        FROM usuario";

if ($busqueda !== "") {

    $sql .= " WHERE cedula LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR telefono LIKE ? OR email LIKE ? OR direccion LIKE ? OR usuario LIKE ?";
}

$sql .= " ORDER BY cedula ASC";

$stmt = $conn->prepare($sql);

if ($busqueda !== "") {

    $texto = "%" . $busqueda . "%";

    $stmt->bind_param("sssssss", $texto, $texto, $texto, $texto, $texto, $texto, $texto);
}

$stmt->execute();
$resultado = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios</title>
</head>
<body>

<h2>Usuarios</h2>

<?php if ($mensaje != ""): ?>
    <p><?php echo htmlspecialchars($mensaje); ?></p>
<?php endif; ?>


<?php if ($usuario_editar): ?>

    <!-- FORMULARIO ACTUALIZAR -->

    <h3>Modificar Usuario</h3>

    <form method="POST">

        <label>Cédula:</label><br>
        <input type="number" name="cedula" value="<?php echo $usuario_editar['cedula']; ?>" readonly><br><br>

        <label>Nombre:</label><br>
        <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario_editar['nombre'] ?? ''); ?>" required><br><br>

        <label>Apellido:</label><br>
        <input type="text" name="apellido" value="<?php echo htmlspecialchars($usuario_editar['apellido'] ?? ''); ?>" required><br><br>

        <label>Teléfono:</label><br>
        <input type="text" name="telefono" value="<?php echo htmlspecialchars($usuario_editar['telefono'] ?? ''); ?>" required><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" value="<?php echo htmlspecialchars($usuario_editar['email'] ?? ''); ?>" required><br><br>

        <label>Dirección:</label><br>
        <input type="text" name="direccion" value="<?php echo htmlspecialchars($usuario_editar['direccion'] ?? ''); ?>" required><br><br>

        <label>Usuario (login):</label><br>
        <input type="text" name="usuario" value="<?php echo htmlspecialchars($usuario_editar['usuario'] ?? ''); ?>" required><br><br>

        <label>Contraseña nueva (vacía = no cambiarla):</label><br>
        <input type="password" name="contra"><br><br>

        <label>Cargo:</label><br>
        <select name="id_tipo_usuario" required>
            <option value="1" <?php if ($usuario_editar['id_tipo_usuario'] == 1) echo 'selected'; ?>>Administrador</option>
            <option value="2" <?php if ($usuario_editar['id_tipo_usuario'] == 2) echo 'selected'; ?>>Empleado</option>
        </select><br><br>

        <button type="submit" name="actualizar">Actualizar</button>

        <a href="edita_usuario.php">Cancelar</a>

    </form>

<?php else: ?>

    <form action="menu.php">
        <button type="submit">Menu Principal</button>
    </form>

<?php endif; ?>

<br>

<!-- BUSCAR -->

<form method="GET">

    <input type="text" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar">

    <button type="submit">Buscar</button>

    <a href="edita_usuario.php">Mostrar todos</a>

</form>


<br>


<!-- TABLA -->

<table border="1" cellpadding="5" cellspacing="0">

    <tr>
        <th>Cédula</th>
        <th>Nombre</th>
        <th>Apellido</th>
        <th>Teléfono</th>
        <th>Email</th>
        <th>Dirección</th>
        <th>Usuario</th>
        <th>Cargo</th>
        <th>Acciones</th>
    </tr>

    <?php while ($fila = $resultado->fetch_assoc()): ?>

        <tr>
            <td><?php echo $fila['cedula']; ?></td>
            <td><?php echo htmlspecialchars($fila['nombre'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($fila['apellido'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($fila['telefono'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($fila['email'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($fila['direccion'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($fila['usuario'] ?? ''); ?></td>
            <td>
                <?php
                if ($fila['id_tipo_usuario'] == 1) {
                    echo "Administrador";
                } elseif ($fila['id_tipo_usuario'] == 2) {
                    echo "Empleado";
                } else {
                    echo "Sin cargo";
                }
                ?>
            </td>
            <td><a href="?editar=<?php echo $fila['cedula']; ?>"> Actualizar </a> |
                <a href="?borrar=<?php echo $fila['cedula']; ?>"
                   onclick="return confirm('¿Desea eliminar este usuario?');">
                    Eliminar
                </a>
            </td>
        </tr>

    <?php endwhile; ?>

</table>

<br>

</body>
</html>

<?php

$stmt->close();
$conn->close();

?>