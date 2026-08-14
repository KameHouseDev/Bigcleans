/* Oculta la pantalla de carga.
   No depende de jQuery a proposito: si una libreria falla en cargar, el visitante
   igual tiene que poder ver la pagina. */
(function () {
    'use strict';

    var pantalla = document.getElementById('preloader');
    if (!pantalla) return;

    // El llenado del logo dura 1.5s: se espera ese tiempo mas un respiro, para que
    // la animacion siempre alcance a completarse aunque la pagina cargue al instante.
    var MIN_VISIBLE = 1750;
    var MAX_ESPERA = 6000;   // red de seguridad: nadie queda atrapado detras del preloader
    var inicio = Date.now();
    var yaSeOculto = false;

    function ocultar() {
        if (yaSeOculto) return;
        yaSeOculto = true;

        setTimeout(function () {
            pantalla.classList.add('preloader-oculto');
            setTimeout(function () {
                if (pantalla.parentNode) pantalla.parentNode.removeChild(pantalla);
            }, 600); // coincide con la transicion de opacidad del CSS
        }, Math.max(0, MIN_VISIBLE - (Date.now() - inicio)));
    }

    if (document.readyState === 'complete') {
        ocultar();
    } else {
        window.addEventListener('load', ocultar);
    }

    // El inicio carga un video de varios MB y 'load' puede demorar mas de la cuenta
    setTimeout(ocultar, MAX_ESPERA);
})();
