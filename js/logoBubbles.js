/* Burbujas de jabon que suben desde el logo del header.
   Se generan por JS para que cada una tenga tamano, posicion y ritmo distintos:
   con valores fijos en el CSS el ciclo se nota repetido. */
(function () {
    'use strict';

    function crearBurbujas() {
        // El <a> calza con la imagen; #logo es mas ancho por su padding-left
        var logo = document.querySelector('#header #logo a') || document.querySelector('#header #logo');
        if (!logo || logo.querySelector('.logo-bubbles')) return;

        // Si el visitante pidio menos movimiento en su sistema, no se animan
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var contenedor = document.createElement('span');
        contenedor.className = 'logo-bubbles';
        contenedor.setAttribute('aria-hidden', 'true');

        for (var i = 0; i < 10; i++) {
            var burbuja = document.createElement('i');
            var tamano = 4 + Math.random() * 7;

            burbuja.style.width = tamano.toFixed(1) + 'px';
            burbuja.style.height = tamano.toFixed(1) + 'px';
            burbuja.style.left = (Math.random() * 100).toFixed(1) + '%';
            // left posiciona el borde izquierdo: se corre media burbuja para que
            // quede centrada en su posicion y no sobresalga por la derecha
            burbuja.style.marginLeft = (-tamano / 2).toFixed(1) + 'px';
            burbuja.style.animationDelay = (Math.random() * 4.5).toFixed(2) + 's';
            burbuja.style.animationDuration = (3.6 + Math.random() * 2.4).toFixed(2) + 's';

            contenedor.appendChild(burbuja);
        }

        logo.appendChild(contenedor);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', crearBurbujas);
    } else {
        crearBurbujas();
    }
})();
