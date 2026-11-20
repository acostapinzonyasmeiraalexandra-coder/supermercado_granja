// ventana de error del login
const modal = document.getElementById("modalError");

// solo se ejecuta si la ventana existe
if (modal) {
    const botonCerrar = document.getElementById("cerrarModal");

    // cierra la ventana y quita ?error de la direccion
    function cerrarModal() {
        modal.remove();
        window.history.replaceState(null, "", window.location.pathname);
    }

    // cerrar con el boton
    botonCerrar.addEventListener("click", cerrarModal);

    // cerrar al hacer clic en el fondo oscuro
    modal.addEventListener("click", function (e) {
        if (e.target === modal) {
            cerrarModal();
        }
    });

    // cerrar con Escape
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            cerrarModal();
        }
    });

    // foco en el boton para cerrar con Enter
    botonCerrar.focus();
}