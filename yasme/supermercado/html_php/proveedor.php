<?php
session_start();

// Solo el administrador (cargo 1) puede entrar aquí
if (!isset($_SESSION["usuario"]) || $_SESSION["cargo"] != "1") {
    header("Location: menu.php");
    exit();
}


    include("conexion.php");

    // compatibilidad con la variable de conexion

    if (!isset($conn) && isset($conexion)) {
        $conn = $conexion;
    }

    $mensaje = "";
    $proveedor_editar = null;
    $busqueda = "";

    // Activar el modo de reporte de excepciones

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // 1. Accion eliminar proveedor

    if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['cod_proveedor'])) {

        $cod_eliminar = $_GET['cod_proveedor'];

        try {

            $sql_del = "DELETE FROM proveedor WHERE cod_proveedor = ?";
            $stmt_del = $conn->prepare($sql_del);
            $stmt_del->bind_param("i", $cod_eliminar);
            $stmt_del->execute();

            $mensaje = "<p style='color:green; font-weight:bold;'>Proveedor con codigo "
            . htmlspecialchars($cod_eliminar) . " eliminado correctamente.</p>";

            $stmt_del->close();

        } catch (mysqli_sql_exception $e) {

            // Codigo 1451: restriccion de llave foranea

            if ($e->getCode() == 1451) {

                $mensaje = "<p style='color:red; font-weight:bold;'>No se puede eliminar el proveedor porque tiene productos registrados.</p>";

            } else {

                $mensaje = "<p style='color:red; font-weight:bold;'>Error al eliminar: "
                . htmlspecialchars($e->getMessage()) . "</p>";

            }
        }
    }

    // 2. Accion editar proveedor

    if (isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['cod_proveedor'])) {

        $cod_buscar = $_GET['cod_proveedor'];

        try {

            $sql_buscar = "SELECT * FROM proveedor WHERE cod_proveedor = ?";
            $stmt_buscar = $conn->prepare($sql_buscar);
            $stmt_buscar->bind_param("i", $cod_buscar);
            $stmt_buscar->execute();

            $res = $stmt_buscar->get_result();
            $proveedor_editar = $res->fetch_assoc();

            $stmt_buscar->close();

        } catch (mysqli_sql_exception $e) {

            $mensaje = "<p style='color:red; font-weight:bold;'>Error al cargar datos: "
            . htmlspecialchars($e->getMessage()) . "</p>";

        }
    }

    // 3. Formulario guardar y modificar POST

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        // Registrar nuevo proveedor

        if (isset($_POST["guardar"])) {

            $cod_proveedor = $_POST["cod_proveedor"];
            $nombre = $_POST["nombre"];
            $telefono = $_POST["telefono"];
            $direccion = $_POST["direccion"];
            $email = $_POST["email"];

            try {

                $sql = "INSERT INTO proveedor
                (cod_proveedor, nombre, telefono, direccion, email)
                VALUES (?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issss", $cod_proveedor, $nombre, $telefono, $direccion, $email);
                $stmt->execute();

                $mensaje = "<p style='color:green; font-weight:bold;'>Proveedor registrado correctamente.</p>";

                $stmt->close();

            } catch (mysqli_sql_exception $e) {

                // Codigo 1062: clave primaria duplicada

                if ($e->getCode() == 1062) {

                    $mensaje = "<p style='color:red; font-weight:bold;'>Error: El codigo "
                    . htmlspecialchars($cod_proveedor) . " ya esta registrado.</p>";

                } else {

                    $mensaje = "<p style='color:red; font-weight:bold;'>Error al registrar: "
                    . htmlspecialchars($e->getMessage()) . "</p>";

                }
            }
        }

        // Actualizar proveedor

        if (isset($_POST["actualizar"])) {

            $cod_proveedor = $_POST["cod_proveedor"];
            $nombre = $_POST["nombre"];
            $telefono = $_POST["telefono"];
            $direccion = $_POST["direccion"];
            $email = $_POST["email"];

            try {

                $sql_up = "UPDATE proveedor
                           SET nombre = ?, telefono = ?, direccion = ?, email = ?
                           WHERE cod_proveedor = ?";

                $stmt_up = $conn->prepare($sql_up);
                $stmt_up->bind_param("ssssi", $nombre, $telefono, $direccion, $email, $cod_proveedor);
                $stmt_up->execute();

                $mensaje = "<p style='color:green; font-weight:bold;'>Proveedor actualizado correctamente.</p>";

                $stmt_up->close();

            } catch (mysqli_sql_exception $e) {

                $mensaje = "<p style='color:red; font-weight:bold;'>Error al actualizar: "
                . htmlspecialchars($e->getMessage()) . "</p>";

            }
        }
    }

    // 4. Buscar y listar proveedores

    if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {

        $busqueda = trim($_GET['buscar']);
        $param_busqueda = "%" . $busqueda . "%";

        $sql_listar = "SELECT cod_proveedor, nombre, telefono, direccion, email
                       FROM proveedor
                       WHERE cod_proveedor LIKE ?
                       OR nombre LIKE ?
                       OR telefono LIKE ?
                       OR direccion LIKE ?
                       OR email LIKE ?";

        $stmt_list = $conn->prepare($sql_listar);
        $stmt_list->bind_param(
            "sssss",
            $param_busqueda,
            $param_busqueda,
            $param_busqueda,
            $param_busqueda,
            $param_busqueda
        );
        $stmt_list->execute();

        $proveedores = $stmt_list->get_result();

    } else {

        $sql_listar = "SELECT cod_proveedor, nombre, telefono, direccion, email
                       FROM proveedor
                       ORDER BY cod_proveedor ASC";

        $proveedores = $conn->query($sql_listar);
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bodega - Proveedores</title>
</head>
<body>

    <?php echo $mensaje; ?>

    <!-- Formulario para editar proveedor -->

    <?php if ($proveedor_editar) : ?>

        <h2>Editar Proveedor</h2>

        <form action="" method="POST">

            <input type="hidden" name="cod_proveedor"
            value="<?php echo htmlspecialchars($proveedor_editar['cod_proveedor']); ?>">

            <label>Codigo Proveedor:</label><br>
            <input type="number"
            value="<?php echo htmlspecialchars($proveedor_editar['cod_proveedor']); ?>"
            disabled><br><br>

            <label>Nombre:</label><br>
            <input type="text" name="nombre"
            value="<?php echo htmlspecialchars($proveedor_editar['nombre']); ?>"
            required><br><br>

            <label>Telefono:</label><br>
            <input type="text" name="telefono"
            value="<?php echo htmlspecialchars($proveedor_editar['telefono']); ?>"
            required><br><br>

            <label>Direccion:</label><br>
            <input type="text" name="direccion"
            value="<?php echo htmlspecialchars($proveedor_editar['direccion']); ?>"
            required><br><br>

            <label>Email:</label><br>
            <input type="email" name="email"
            value="<?php echo htmlspecialchars($proveedor_editar['email']); ?>"
            required><br><br>

            <button type="submit" name="actualizar">Actualizar Proveedor</button>

            <a href="?">Cancelar</a>

        </form>

    <?php else: ?>

        <!-- Formulario para registrar proveedor -->

        <h2>Ingresar Proveedor</h2>

        <form action="" method="POST">

            <label>Codigo Proveedor:</label><br>
            <input type="number" name="cod_proveedor"
            required placeholder="Ingrese codigo"><br><br>

            <label>Nombre:</label><br>
            <input type="text" name="nombre"
            required placeholder="Ingrese nombre"><br><br>

            <label>Telefono:</label><br>
            <input type="text" name="telefono"
            required placeholder="Ingrese telefono"><br><br>

            <label>Direccion:</label><br>
            <input type="text" name="direccion"
            required placeholder="Ingrese direccion"><br><br>

            <label>Email:</label><br>
            <input type="email" name="email"
            required placeholder="Ingrese email"><br><br>

            <button type="submit" name="guardar">Registrar</button>

        </form>

    <?php endif; ?>

    <br>

    <form action="menu.php" method="POST" style="display:inline;">
        <button type="submit">Menu Principal</button>
    </form>

    <hr>
    <hr>

    <!-- Caja de texto para buscar proveedor -->

    <h3>Buscar Proveedor</h3>

    <form action="" method="GET">

        <input type="text" name="buscar"
        placeholder="Ingrese codigo, nombre, telefono o email..."
        value="<?php echo htmlspecialchars($busqueda); ?>">

        <button type="submit">Buscar</button>

        <?php if (!empty($busqueda)): ?>
            <a href="?">Mostrar todos</a>
        <?php endif; ?>

    </form>

    <br>

    <!-- Tabla de resultados de proveedores -->

    <h3>Listado de Proveedores</h3>

    <table border="1" cellpadding="5" cellspacing="0">

        <thead>

            <tr>
                <th>Codigo</th>
                <th>Nombre</th>
                <th>Telefono</th>
                <th>Direccion</th>
                <th>Email</th>
                <th>Acciones</th>
            </tr>

        </thead>

        <tbody>

            <?php if ($proveedores && $proveedores->num_rows > 0) : ?>

                <?php while ($fila = $proveedores->fetch_assoc()) : ?>

                    <tr>

                        <td><?php echo htmlspecialchars($fila['cod_proveedor']); ?></td>
                        <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($fila['telefono']); ?></td>
                        <td><?php echo htmlspecialchars($fila['direccion']); ?></td>
                        <td><?php echo htmlspecialchars($fila['email']); ?></td>

                        <td>

                            <a href="?accion=editar&cod_proveedor=<?php echo urlencode($fila['cod_proveedor']); ?>">Editar</a> |

                            <a href="?accion=eliminar&cod_proveedor=<?php echo urlencode($fila['cod_proveedor']); ?>"
                            onclick="return confirm('¿Seguro que deseas eliminar este proveedor?');">Eliminar</a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6">No se encontraron proveedores.</td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</body>
</html>

<?php
$conn->close();
?>