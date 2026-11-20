// menu lateral
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