 // ========== solo letras o solo numeros ==========
    // quita en vivo lo que no corresponde mientras la persona escribe
    document.querySelectorAll("[data-tipo]").forEach(function (campo) {
        campo.addEventListener("input", function () {
            if (campo.dataset.tipo === "letras") {
                // deja solo letras (con tildes y ñ) y espacios
                campo.value = campo.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s]/g, "");
            } else if (campo.dataset.tipo === "numeros") {
                // deja solo numeros
                campo.value = campo.value.replace(/\D/g, "");
            }
        });
    });

    // ========== mensajes de validacion en español ==========
    document.querySelectorAll(".formulario input, .formulario select").forEach(function (campo) {
        // cuando el campo no es valido, muestra el mensaje en español
        campo.addEventListener("invalid", function () {
            // limpia el mensaje anterior para recalcular el estado
            campo.setCustomValidity("");

            if (campo.validity.valueMissing) {
                // campo vacio
                campo.setCustomValidity(
                    campo.tagName === "SELECT"
                        ? "Por favor, selecciona una opción."
                        : "Por favor, llena este campo."
                );
            } else if (campo.validity.typeMismatch) {
                // correo mal escrito
                campo.setCustomValidity("Escribe un correo válido. Ejemplo: juan.perez@correo.com");
            } else if (campo.validity.patternMismatch) {
                // formato incorrecto (letras o numeros)
                campo.setCustomValidity(campo.dataset.error || "El formato no es válido.");
            } else if (campo.validity.tooShort) {
                // muy corto
                campo.setCustomValidity("Debe tener al menos " + campo.minLength + " caracteres.");
            }
        });

        // borra el mensaje cuando la persona corrige el campo
        campo.addEventListener("input", function () {
            campo.setCustomValidity("");
        });
        campo.addEventListener("change", function () {
            campo.setCustomValidity("");
        });
    });

    // ========== ventana de aviso ==========
    const modal = document.getElementById("modalAviso");

    if (modal) {
        // true si el registro salio bien
        const exito = <?= ($tipoMensaje === "ok") ? "true" : "false" ?>;
        const botonCerrar = document.getElementById("cerrarModal");

        // cierra la ventana (si fue exito, manda al inicio de sesion)
        function cerrarModal() {
            if (exito) {
                window.location.href = "../index.php";
            } else {
                modal.remove();
            }
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
    }