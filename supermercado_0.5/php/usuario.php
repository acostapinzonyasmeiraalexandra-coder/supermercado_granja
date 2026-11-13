<?php
// inicia la sesion
session_start();

// conexion a la base de datos
include("conexion.php");

// si entran a esta pagina sin enviar el formulario, vuelven al inicio
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit();
}

// datos del formulario
$usuario = trim((string)($_POST["usuario"] ?? ""));
$password = (string)($_POST["contra"] ?? "");
$cargo = (int)($_POST["cargo"] ?? 0);

// busca el usuario en la base de datos
$stmt = $conn->prepare(
    "SELECT usuario, contra, id_tipo_usuario
     FROM usuario
     WHERE usuario = ? AND id_tipo_usuario = ?"
);
$stmt->bind_param("si", $usuario, $cargo);
$stmt->execute();
$resultado = $stmt->get_result();
// recorre los usuarios con ese nombre y cargo hasta encontrar el que coincide con la contrasena
$fila = null;
while ($candidato = $resultado->fetch_assoc()) {
    if (password_verify($password, $candidato["contra"])) {
        $fila = $candidato;
        break;
    }
}
$stmt->close();
$conn->close();

// si los datos son correctos (la contrasena ya se comparo con el hash guardado)
if ($fila) {
    // genera un identificador de sesion nuevo (mas seguro)
    session_regenerate_id(true);

    // guarda los datos del usuario en la sesion
    $_SESSION["usuario"] = $fila["usuario"];
    $_SESSION["rol"] = (string)$fila["id_tipo_usuario"];

    // manda al menu
    header("Location: menu.php");
    exit();
}

// datos incorrectos: vuelve al inicio y muestra la ventana de error
header("Location: ../index.php?error=1");
exit();
?>