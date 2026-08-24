/* Cotizador de reparaciones en terreno.

   Las fotos se reducen en el navegador y se suben DE INMEDIATO, apenas se
   toman. Dos razones: el servidor limita los archivos por envio (si se pasa,
   descarta los sobrantes en silencio), y en un edificio con mala señal es
   mejor repartir la subida durante la visita que mandar todo junto al final. */
(function () {
    'use strict';

    var CFG = window.COTIZADOR || {};
    var MAX_ITEMS = CFG.maxItems || 30;
    var MAX_FOTOS = CFG.maxFotosItem || 4;
    var LADO = 1400;           // px del lado mayor
    var CALIDAD = 0.72;
    var BORRADOR = 'bigcleans-cotizacion';

    var items = [];            // { id, descripcion, precio, fotos: [] }
    var proximoId = 1;
    var editandoId = CFG.editando ? CFG.editando.id : '';

    var $ = function (s) { return document.querySelector(s); };
    var cont = $('#items');
    var plantilla = $('#plantilla-item');

    /* ---------- utilidades ---------- */

    function pesos(n) {
        return '$' + (n || 0).toLocaleString('es-CL', { maximumFractionDigits: 0 });
    }

    function soloDigitos(s) {
        return (s || '').replace(/[^0-9]/g, '');
    }

    /** Normaliza un telefono chileno al formato que necesita wa.me */
    function telefonoWa(valor) {
        var d = soloDigitos(valor);
        if (!d) return '';
        if (d.indexOf('56') === 0) return d;
        if (d.length === 9 && d.charAt(0) === '9') return '56' + d;
        if (d.length === 8) return '569' + d;
        return d;
    }

    function total() {
        return items.reduce(function (t, i) { return t + (i.precio || 0); }, 0);
    }

    /* ---------- fotos ---------- */

    function redimensionar(archivo) {
        return new Promise(function (resolver, rechazar) {
            var lector = new FileReader();
            lector.onerror = function () { rechazar(new Error('No se pudo leer la foto')); };
            lector.onload = function () {
                var img = new Image();
                img.onerror = function () { rechazar(new Error('Archivo de imagen invalido')); };
                img.onload = function () {
                    var escala = Math.min(1, LADO / Math.max(img.width, img.height));
                    var w = Math.round(img.width * escala);
                    var h = Math.round(img.height * escala);
                    var lienzo = document.createElement('canvas');
                    lienzo.width = w;
                    lienzo.height = h;
                    var ctx = lienzo.getContext('2d');
                    ctx.fillStyle = '#fff';        // el JPEG no tiene transparencia
                    ctx.fillRect(0, 0, w, h);
                    ctx.drawImage(img, 0, 0, w, h);
                    var mini = lienzo.toDataURL('image/jpeg', 0.5);
                    lienzo.toBlob(function (blob) {
                        resolver({ blob: blob, preview: mini });
                    }, 'image/jpeg', CALIDAD);
                };
                img.src = lector.result;
            };
            lector.readAsDataURL(archivo);
        });
    }

    function subir(blob) {
        var datos = new FormData();
        datos.append('foto', blob, 'foto.jpg');
        return fetch('subir.php', { method: 'POST', body: datos })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) throw new Error(res.error || 'No se pudo subir');
                return res;
            });
    }

    /* ---------- items ---------- */

    function pintarFotos(nodo, item) {
        var tira = nodo.querySelector('.tira');
        tira.innerHTML = '';

        item.fotos.forEach(function (foto, idx) {
            var caja = document.createElement('div');
            caja.className = 'foto';
            if (foto.estado === 'subiendo') caja.className += ' subiendo';
            if (foto.estado === 'error') caja.className += ' con-error';

            if (foto.preview) {
                var img = document.createElement('img');
                img.src = foto.preview;
                caja.appendChild(img);
            }

            if (foto.estado === 'subiendo') {
                var sp = document.createElement('span');
                sp.className = 'mini-spinner';
                caja.appendChild(sp);
            }

            if (foto.estado === 'error') {
                var re = document.createElement('button');
                re.type = 'button';
                re.className = 'reintentar';
                re.textContent = 'Reintentar';
                re.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    reintentar(nodo, item, foto);
                });
                caja.appendChild(re);
            }

            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'quitar-foto';
            x.textContent = '×';
            x.title = 'Quitar foto';
            x.addEventListener('click', function (ev) {
                ev.preventDefault();
                item.fotos.splice(idx, 1);
                pintarFotos(nodo, item);
                actualizar();
            });
            caja.appendChild(x);

            tira.appendChild(caja);
        });

        nodo.querySelector('.agregar-foto').hidden = item.fotos.length >= MAX_FOTOS;
    }

    function reintentar(nodo, item, foto) {
        if (!foto.blob) return;
        foto.estado = 'subiendo';
        pintarFotos(nodo, item);
        actualizar();
        subir(foto.blob).then(function (res) {
            foto.estado = 'ok';
            foto.token = res.token;
            pintarFotos(nodo, item);
            actualizar();
        }).catch(function () {
            foto.estado = 'error';
            pintarFotos(nodo, item);
            actualizar();
        });
    }

    function agregarItem(datos) {
        if (items.length >= MAX_ITEMS) return;

        var item = datos || { id: proximoId++, descripcion: '', precio: 0, fotos: [] };
        if (datos && datos.id >= proximoId) proximoId = datos.id + 1;
        item.fotos = item.fotos || [];
        items.push(item);

        var nodo = plantilla.content.cloneNode(true).querySelector('.item');
        nodo.dataset.id = item.id;

        var entrada = nodo.querySelector('input[type="file"]');
        var desc = nodo.querySelector('.item-desc');
        var monto = nodo.querySelector('.item-monto');

        desc.value = item.descripcion || '';
        if (item.precio) monto.value = item.precio.toLocaleString('es-CL');

        entrada.addEventListener('change', function () {
            var archivo = entrada.files && entrada.files[0];
            entrada.value = '';                      // permite repetir la misma foto
            if (!archivo || item.fotos.length >= MAX_FOTOS) return;

            var foto = { estado: 'subiendo', preview: '', token: '', blob: null };
            item.fotos.push(foto);
            pintarFotos(nodo, item);
            actualizar();

            redimensionar(archivo).then(function (r) {
                foto.blob = r.blob;
                foto.preview = r.preview;
                pintarFotos(nodo, item);
                return subir(r.blob);
            }).then(function (res) {
                foto.estado = 'ok';
                foto.token = res.token;
                pintarFotos(nodo, item);
                actualizar();
            }).catch(function (e) {
                foto.estado = 'error';
                pintarFotos(nodo, item);
                actualizar();
                console.error(e);
            });
        });

        desc.addEventListener('input', function () {
            item.descripcion = desc.value;
            actualizar();
        });

        // El precio se escribe con separador de miles a medida que se teclea
        monto.addEventListener('input', function () {
            var n = parseInt(soloDigitos(monto.value), 10) || 0;
            item.precio = n;
            monto.value = n ? n.toLocaleString('es-CL') : '';
            actualizar();
        });

        nodo.querySelector('.item-borrar').addEventListener('click', function () {
            items = items.filter(function (i) { return i.id !== item.id; });
            nodo.remove();
            actualizar();
        });

        cont.appendChild(nodo);
        pintarFotos(nodo, item);
        actualizar();
        if (!datos) desc.focus();
    }

    function actualizar() {
        $('#total').textContent = pesos(total());
        $('#contador').textContent = items.length;

        var validos = items.filter(function (i) { return i.descripcion.trim() && i.precio > 0; });
        var subiendo = items.some(function (i) {
            return i.fotos.some(function (f) { return f.estado === 'subiendo'; });
        });

        var btn = $('#generar');
        btn.disabled = validos.length === 0 || subiendo;
        btn.textContent = subiendo ? 'Subiendo fotos...' : (editandoId ? 'Guardar cambios' : 'Generar cotización');

        guardarBorrador();
    }

    /* ---------- borrador ---------- */

    function datosFormulario() {
        return {
            cliente: $('#cliente').value.trim(),
            telefono: $('#telefono').value.trim(),
            email: $('#email').value.trim(),
            direccion: $('#direccion').value.trim(),
            observaciones: $('#observaciones').value.trim()
        };
    }

    function guardarBorrador() {
        if (editandoId) return;          // corrigiendo: no se pisa el borrador nuevo

        var base = datosFormulario();
        // Solo el token y la miniatura: los blobs no caben en localStorage
        base.items = items.map(function (i) {
            return {
                id: i.id,
                descripcion: i.descripcion,
                precio: i.precio,
                fotos: i.fotos.filter(function (f) { return f.estado === 'ok'; })
                    .map(function (f) {
                        return { estado: 'ok', token: f.token, preview: f.preview,
                                 nombre: f.nombre, existente: f.existente };
                    })
            };
        });

        try {
            localStorage.setItem(BORRADOR, JSON.stringify(base));
        } catch (e) {
            // Sin espacio: se sacrifican las miniaturas, que es lo pesado.
            // Perder el texto escrito seria mucho peor.
            try {
                base.items.forEach(function (i) {
                    i.fotos.forEach(function (f) { f.preview = ''; });
                });
                localStorage.setItem(BORRADOR, JSON.stringify(base));
            } catch (e2) { /* se sigue sin borrador */ }
        }
    }

    function cargarBorrador() {
        var crudo = null;
        try { crudo = localStorage.getItem(BORRADOR); } catch (e) { return; }
        if (!crudo) return;

        var d;
        try { d = JSON.parse(crudo); } catch (e) { return; }

        $('#cliente').value = d.cliente || '';
        $('#telefono').value = d.telefono || '';
        $('#email').value = d.email || '';
        $('#direccion').value = d.direccion || '';
        $('#observaciones').value = d.observaciones || '';
        (d.items || []).forEach(agregarItem);
    }

    function cargarEdicion(c) {
        $('#cliente').value = c.cliente || '';
        $('#telefono').value = c.telefono || '';
        $('#email').value = c.email || '';
        $('#direccion').value = c.direccion || '';
        $('#observaciones').value = c.observaciones || '';

        (c.items || []).forEach(function (it, n) {
            agregarItem({
                id: n + 1,
                descripcion: it.descripcion || '',
                precio: it.precio || 0,
                fotos: (it.fotos || []).map(function (nombre) {
                    return {
                        estado: 'ok', existente: true, nombre: nombre,
                        preview: 'fotos/' + c.id + '/' + nombre, token: ''
                    };
                })
            });
        });
    }

    function limpiar() {
        try { localStorage.removeItem(BORRADOR); } catch (e) {}
        items = [];
        cont.innerHTML = '';
        ['cliente', 'telefono', 'email', 'direccion', 'observaciones'].forEach(function (id) {
            $('#' + id).value = '';
        });
        actualizar();
        // Deja una reparacion vacia lista: si no, la pantalla queda sin nada
        // que llenar y hay que apretar "Agregar" antes de poder escribir.
        agregarItem();
    }

    /* ---------- envio ---------- */

    function generar() {
        var base = datosFormulario();
        var validos = items.filter(function (i) { return i.descripcion.trim() && i.precio > 0; });
        if (!validos.length) return;

        var perdidas = 0;
        var lista = validos.map(function (i) {
            var refs = [];
            i.fotos.forEach(function (f) {
                if (f.existente) refs.push({ t: 'existente', nombre: f.nombre });
                else if (f.estado === 'ok') refs.push({ t: 'nueva', token: f.token });
                else perdidas++;
            });
            return { descripcion: i.descripcion.trim(), precio: i.precio, fotos: refs };
        });

        if (perdidas && !confirm(perdidas + ' foto(s) no alcanzaron a subir y no se incluirán. ¿Guardar igual?')) return;

        var datos = new FormData();
        if (editandoId) datos.append('id', editandoId);
        datos.append('cliente', base.cliente);
        datos.append('telefono', base.telefono);
        datos.append('email', base.email);
        datos.append('direccion', base.direccion);
        datos.append('observaciones', base.observaciones);
        datos.append('items', JSON.stringify(lista));

        $('#cargando').hidden = false;
        $('#cargando-texto').textContent = editandoId ? 'Guardando cambios...' : 'Guardando cotización...';

        fetch('guardar.php', { method: 'POST', body: datos })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                $('#cargando').hidden = true;
                if (!res.ok) throw new Error(res.error || 'No se pudo guardar');
                mostrarResultado(res, base, validos);
            })
            .catch(function (e) {
                $('#cargando').hidden = true;
                alert('No se pudo guardar la cotización. ' + e.message +
                      ' Revisa la señal e intenta de nuevo: lo que escribiste no se pierde.');
            });
    }

    function mostrarResultado(res, base, validos) {
        var url = res.url;

        $('#modal-titulo').textContent = res.editado ? 'Cotización corregida' : 'Cotización lista';
        $('#modal-sub').textContent = validos.length + ' reparación' +
            (validos.length === 1 ? '' : 'es') + ' · ' + pesos(total());
        $('#enlace').value = url;
        $('#btn-ver').href = url;

        // WhatsApp solo admite texto en el enlace: las fotos van en la pagina
        var lineas = ['*Cotización de reparaciones - Bigcleans*'];
        if (base.direccion) lineas.push(base.direccion);
        lineas.push('');
        validos.forEach(function (i) {
            lineas.push('• ' + i.descripcion.trim() + ': ' + pesos(i.precio));
        });
        lineas.push('');
        lineas.push('*Total: ' + pesos(total()) + '*');
        if (base.observaciones) {
            lineas.push('');
            lineas.push(base.observaciones);
        }
        lineas.push('');
        lineas.push('Detalle con fotos: ' + url);

        $('#btn-wa').href = 'https://wa.me/' + telefonoWa(base.telefono) +
            '?text=' + encodeURIComponent(lineas.join('\n'));

        var btnMail = $('#btn-mail');
        btnMail.disabled = !base.email;
        btnMail.textContent = base.email ? 'Enviar por correo' : 'Sin correo del cliente';

        btnMail.onclick = function () {
            btnMail.disabled = true;
            btnMail.textContent = 'Enviando...';
            fetch('enviar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(res.id)
            })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    var nota = $('#modal-nota');
                    nota.className = 'modal-nota ' + (r.ok ? 'ok' : 'mal');
                    nota.textContent = r.ok ? 'Correo enviado a ' + base.email : (r.error || 'No se pudo enviar');
                    btnMail.textContent = r.ok ? 'Correo enviado' : 'Reintentar';
                    btnMail.disabled = !!r.ok;
                })
                .catch(function () {
                    $('#modal-nota').className = 'modal-nota mal';
                    $('#modal-nota').textContent = 'No se pudo enviar el correo';
                    btnMail.textContent = 'Reintentar';
                    btnMail.disabled = false;
                });
        };

        $('#modal').hidden = false;
    }

    /* ---------- arranque ---------- */

    $('#agregar').addEventListener('click', function () { agregarItem(); });
    $('#generar').addEventListener('click', generar);

    $('#copiar').addEventListener('click', function () {
        var campo = $('#enlace');
        campo.select();
        campo.setSelectionRange(0, 99999);
        var listo = function () { $('#copiar').textContent = 'Copiado'; };
        if (navigator.clipboard) navigator.clipboard.writeText(campo.value).then(listo, listo);
        else { document.execCommand('copy'); listo(); }
    });

    $('#cerrar').addEventListener('click', function () {
        if (editandoId) { window.location.href = 'historial.php'; return; }
        $('#modal').hidden = true;
        $('#modal-nota').textContent = '';
        $('#copiar').textContent = 'Copiar';
        limpiar();
    });

    ['cliente', 'telefono', 'email', 'direccion', 'observaciones'].forEach(function (id) {
        $('#' + id).addEventListener('input', guardarBorrador);
    });

    // Evita perder el trabajo por un toque accidental en "atras"
    window.addEventListener('beforeunload', function (e) {
        if (items.length && $('#modal').hidden) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    if (CFG.editando) cargarEdicion(CFG.editando);
    else cargarBorrador();
    if (!items.length) agregarItem();
})();
