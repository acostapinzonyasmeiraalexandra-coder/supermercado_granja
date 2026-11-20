<?php
// carga la sesion con la configuracion segura
include_once(__DIR__ . "/sesion.php");

// elimina las variables de sesion
$_SESSION = [];

// borra la cookie de sesion del navegador
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}

// destruye la sesion en el servidor
session_destroy();

// evita volver a la pagina anterior
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// redirige al inicio de sesion
header("Location: ../index.php");
exit();
?>
