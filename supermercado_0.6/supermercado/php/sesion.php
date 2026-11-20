<?php
// configuracion central de la sesion
// todas las paginas cargan este archivo en lugar de llamar session_start() directamente

// tiempo maximo de inactividad en segundos (30 minutos)
if (!defined("TIEMPO_INACTIVIDAD")) {
    define("TIEMPO_INACTIVIDAD", 1800);
}

// ruta a la que se manda al usuario cuando la sesion expira
// index.php puede cambiarla antes de incluir este archivo
if (!isset($rutaLogin)) {
    $rutaLogin = "../index.php";
}

if (session_status() === PHP_SESSION_NONE) {

    // detecta si la pagina se abre con https
    $esHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");

    // solo acepta ids de sesion creados por el servidor y solo por cookie
    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");

    // la cookie no se puede leer con javascript y no se envia desde otros sitios
    session_set_cookie_params([
        "lifetime" => 0,
        "path"     => "/",
        "secure"   => $esHttps,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}

// cierra la sesion si el usuario estuvo inactivo mucho tiempo
if (isset($_SESSION["usuario"])) {
    $ahora = time();

    if (isset($_SESSION["ultima_actividad"]) && ($ahora - (int)$_SESSION["ultima_actividad"]) > TIEMPO_INACTIVIDAD) {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }

        session_destroy();
        header("Location: " . $rutaLogin . "?error=3");
        exit();
    }

    // renueva el tiempo de actividad
    $_SESSION["ultima_actividad"] = $ahora;
}
