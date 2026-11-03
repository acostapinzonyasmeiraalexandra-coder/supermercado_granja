<?php
session_start();

include("conexion.php");

// Si entran a esta página sin llenar el formulario, regresar al inicio
if (!isset($_POST["usuario"]) || !isset($_POST["contra"]) || !isset($_POST["cargo"])) {
    header("Location: index.html");
    exit();
}

$usuario  = $_POST["usuario"];
$password = $_POST["contra"];
$cargo    = intval($_POST["cargo"]);

// 1. Buscar en la tabla usuario
$stmt = $conn->prepare("SELECT * FROM usuario WHERE usuario = ? AND contra = ? AND id_tipo_usuario = ?");

// 2. "ssi" = string, string, integer
$stmt->bind_param("ssi", $usuario, $password, $cargo);

// 3. Ejecutar
$stmt->execute();

// 4. Obtener el resultado
$resultado = $stmt->get_result();

// 5. Validar si encontró coincidencia
if ($resultado->num_rows > 0) {

    // Guardar los datos en la sesión (menu.php los necesita)
    session_regenerate_id(true);
    $_SESSION["usuario"] = $usuario;
    $_SESSION["cargo"] = (string)$cargo;

    header("Location: menu.php");
    exit();

} else {

    echo "<script>
            alert('Usuario o Contraseña incorrectos');
            window.location.href = 'index.html';
          </script>";
}

// 6. Cerrar conexiones
$stmt->close();
$conn->close();

?>
