const abrir = document.getElementById("abrirMenu");
const cerrar = document.getElementById("cerrarMenu");
const fondo = document.getElementById("fondoMenu");

// esta funcion abre el menu lateral
function mostrarMenu() {
    document.body.classList.add("menu-abierto");
    abrir.setAttribute("aria-expanded", "true");
}

// esta funcion cierra el menu lateral
function ocultarMenu() {
    document.body.classList.remove("menu-abierto");
    abrir.setAttribute("aria-expanded", "false");
}

// escucha el clic del usuario
abrir.addEventListener("click", mostrarMenu);
// escucha el clic del usuario
cerrar.addEventListener("click", ocultarMenu);
// escucha el clic del usuario
fondo.addEventListener("click", ocultarMenu);

// escucha las teclas que presiona el usuario
document.addEventListener("keydown", function(evento) {
    if (evento.key === "Escape") {
        ocultarMenu();
    }
});

// permite escribir solo el tipo de dato indicado
document.querySelectorAll("[data-tipo]").forEach(function(campo) {
    campo.addEventListener("input", function() {
        if (campo.dataset.tipo === "letras") {
            campo.value = campo.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s]/g, "");
        } else if (campo.dataset.tipo === "numeros") {
            campo.value = campo.value.replace(/\D/g, "");
        }
    });
});

// muestra los mensajes de validacion
document.querySelectorAll(".formulario input, .formulario select").forEach(function(campo) {
    campo.addEventListener("invalid", function() {
        campo.setCustomValidity("");
        if (campo.validity.valueMissing) {
            campo.setCustomValidity("Por favor, completa este campo.");
        } else if (campo.validity.typeMismatch) {
            campo.setCustomValidity("Escribe un correo válido.");
        } else if (campo.validity.patternMismatch) {
            campo.setCustomValidity(campo.dataset.error || "El formato no es válido.");
        } else if (campo.validity.tooShort) {
            campo.setCustomValidity("El dato es demasiado corto.");
        }
    });

    campo.addEventListener("input", function() {
        campo.setCustomValidity("");
    });
});

// ventana para confirmar la eliminacion
(function() {
    const modal = document.getElementById("modalEliminar");
    if (!modal) return;

    const texto = document.getElementById("textoEliminar");
    const confirmar = document.getElementById("confirmarEliminar");
    const cancelar = document.getElementById("cancelarEliminar");
    let formularioPendiente = null;

    function cerrarModal() {
        modal.hidden = true;
        formularioPendiente = null;
    }

    document.addEventListener("submit", function(evento) {
        if (evento.target.classList.contains("form-eliminar")) {
            evento.preventDefault();
            formularioPendiente = evento.target;
            const fila = formularioPendiente.closest("tr");
            const usuario = fila ? fila.children[6].textContent.trim() : "";
            texto.textContent = usuario
                ? "¿Seguro que deseas eliminar al usuario " + usuario + "? Esta acción no se puede deshacer."
                : "¿Seguro que deseas eliminar este usuario?";
            modal.hidden = false;
            cancelar.focus();
        }
    });

    confirmar.addEventListener("click", function() {
        if (formularioPendiente) {
            const boton = document.createElement("input");
            boton.type = "hidden";
            boton.name = "eliminar_usuario";
            boton.value = "1";
            formularioPendiente.appendChild(boton);
            formularioPendiente.submit();
        }
    });

    cancelar.addEventListener("click", cerrarModal);

    modal.addEventListener("click", function(evento) {
        if (evento.target === modal) cerrarModal();
    });

    document.addEventListener("keydown", function(evento) {
        if (evento.key === "Escape" && !modal.hidden) cerrarModal();
    });
})();
 const buscarUsuario = document.getElementById("buscarUsuario");
    const filasUsuarios = document.querySelectorAll(".fila-usuario");
    const filaSinResultados = document.getElementById("filaSinResultados");

    buscarUsuario.addEventListener("input", function () {
        const texto = this.value.toLowerCase().trim();
        let encontrados = 0;

        filasUsuarios.forEach(function (fila) {
            const contenido = fila.textContent.toLowerCase();

            if (contenido.includes(texto)) {
                fila.hidden = false;
                encontrados++;
            } else {
                fila.hidden = true;
            }
        });

        filaSinResultados.hidden = encontrados > 0;
    });

    // muestra el aviso si no hay usuarios registrados
    if (filasUsuarios.length === 0) {
        filaSinResultados.hidden = false;

    }
    
