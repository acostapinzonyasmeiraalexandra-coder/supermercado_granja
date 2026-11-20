// botones y fondo del menu lateral
const abrirMenu = document.getElementById("abrirMenu");
const cerrarMenu = document.getElementById("cerrarMenu");
const fondoMenu = document.getElementById("fondoMenu");

// abre el menu lateral
function abrirMenuLateral() {
    document.body.classList.add("menu-abierto");
    abrirMenu.setAttribute("aria-expanded", "true");
}

// cierra el menu lateral
function cerrarMenuLateral() {
    document.body.classList.remove("menu-abierto");
    abrirMenu.setAttribute("aria-expanded", "false");
}

// clic en los botones y en el fondo oscuro
abrirMenu.addEventListener("click", abrirMenuLateral);
cerrarMenu.addEventListener("click", cerrarMenuLateral);
fondoMenu.addEventListener("click", cerrarMenuLateral);

// cierra el menu al presionar escape
document.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape") {
        cerrarMenuLateral();
    }
});