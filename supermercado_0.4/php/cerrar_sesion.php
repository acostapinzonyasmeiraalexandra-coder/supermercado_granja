<?php
// inicia la sesion para poder cerrarla (solo si no esta activa)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// evita que el navegador guarde la pagina en cache
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // proxies

// vacia todas las variables de sesion
$_SESSION = [];

// elimina la cookie de sesion del navegador
if (ini_get("session.use_cookies")) {
    // lee los parametros con los que se creo la cookie
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires"  => time() - 42000,
            "path"     => $parametros["path"],
            "domain"   => $parametros["domain"],
            "secure"   => $parametros["secure"],
            "httponly" => $parametros["httponly"],
            "samesite" => $parametros["samesite"] ?? "Lax"
        ]
    );
}

// destruye la sesion en el servidor (solo si esta activa)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// regresa al inicio de sesion
header("Location: ../index.php");
exit();
?>