 // elementos de la ventana
            const modal = document.getElementById("modalError");
            const botonCerrar = document.getElementById("cerrarModal");

            // cierra la ventana y limpia el ?error de la url
            function cerrarModal() {
                modal.remove();
                history.replaceState(null, "", window.location.pathname);
            }

            // cerrar con el boton
            botonCerrar.addEventListener("click", cerrarModal);

            // cerrar al hacer clic en el fondo oscuro
            modal.addEventListener("click", function (e) {
                if (e.target === modal) {
                    cerrarModal();
                }
            });

            // cerrar con la tecla Escape
            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape") {
                    cerrarModal();
                }
            });

            // pone el foco en el boton para poder cerrar con Enter
            botonCerrar.focus();