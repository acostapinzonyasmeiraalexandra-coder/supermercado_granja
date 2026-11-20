// ========== ventana de aviso ==========
// este archivo solo controla la ventana de aviso, no toca el formulario
const modal = document.getElementById("modalAviso");

// solo se ejecuta si la ventana existe (es decir, si hubo un mensaje)
if (modal) {

    // data-exito lo manda php: "1" si el registro salio bien
    const exito = modal.dataset.exito === "1";
    const botonCerrar = document.getElementById("cerrarModal");

    // cierra la ventana: si fue exito va al inicio de sesion, si fue error solo se cierra
    function cerrarModal() {
        if (exito) {
            window.location.href = "../index.php";
        } else {
            modal.remove();
        }
    }

    // formas de cerrar: boton, clic en el fondo oscuro o tecla Escape
    botonCerrar.addEventListener("click", cerrarModal);

    modal.addEventListener("click", function (e) {
        if (e.target === modal) {
            cerrarModal();
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            cerrarModal();
        }
    });

    // el foco queda en el boton para poder cerrar con Enter
    botonCerrar.focus();
}