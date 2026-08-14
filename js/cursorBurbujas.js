/* Rastro de burbujas que sigue al mouse.
   El puntero del sistema NO se toca: las burbujas son un agregado encima.
   Se desactiva donde no corresponde (tactil, o si se pidio menos movimiento). */
(function () {
    'use strict';

    var INTERVALO = 70;    // ms minimos entre burbujas: sin esto se generan cientos al mover rapido
    var VIDA = 1500;       // debe coincidir con la animacion del CSS
    var MAX = 40;          // tope de burbujas vivas, por si algun timer no alcanza a limpiar

    // Solo con mouse de verdad. En celular no hay puntero y seria gasto de bateria.
    if (!window.matchMedia || !window.matchMedia('(pointer: fine)').matches) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var ultima = 0;
    var vivas = 0;

    document.addEventListener('mousemove', function (e) {
        var ahora = Date.now();
        if (ahora - ultima < INTERVALO || vivas >= MAX) return;
        ultima = ahora;

        var burbuja = document.createElement('span');
        var tamano = 6 + Math.random() * 10;

        burbuja.className = 'cursor-burbuja';
        burbuja.setAttribute('aria-hidden', 'true');
        burbuja.style.width = tamano.toFixed(1) + 'px';
        burbuja.style.height = tamano.toFixed(1) + 'px';
        burbuja.style.left = (e.clientX + (Math.random() * 18 - 9)).toFixed(0) + 'px';
        burbuja.style.top = e.clientY + 'px';
        burbuja.style.animationDuration = (VIDA / 1000 * (0.8 + Math.random() * 0.4)).toFixed(2) + 's';

        document.body.appendChild(burbuja);
        vivas++;

        setTimeout(function () {
            if (burbuja.parentNode) burbuja.parentNode.removeChild(burbuja);
            vivas--;
        }, VIDA);
    }, { passive: true });
})();
