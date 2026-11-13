
        // elementos de la pagina que se usan en el codigo
        const abrirMenu = document.getElementById("abrirMenu");
        const cerrarMenu = document.getElementById("cerrarMenu");
        const fondoMenu = document.getElementById("fondoMenu");

        // escucha el clic del usuario
        abrirMenu.addEventListener("click", () => {
            document.body.classList.add("menu-abierto");
            abrirMenu.setAttribute("aria-expanded", "true");
        });

        // esta funcion cierra el menu lateral
        function cerrarMenuLateral() {
            document.body.classList.remove("menu-abierto");
            abrirMenu.setAttribute("aria-expanded", "false");
        }

        // escucha el clic del usuario
        cerrarMenu.addEventListener("click", cerrarMenuLateral);
        // escucha el clic del usuario
        fondoMenu.addEventListener("click", cerrarMenuLateral);

        // escucha las teclas que presiona el usuario
        document.addEventListener("keydown", (evento) => {
            if (evento.key === "Escape") {
                cerrarMenuLateral();
            }
        });