<?php
/**
 * Cotizador de reparaciones en terreno.
 * Pantalla privada: se entra con PIN y queda la sesion abierta en el telefono.
 */
require __DIR__ . '/arranque.php';

iniciar_sesion();

if (isset($_GET['salir'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    // Freno simple a la fuerza bruta: tras 5 intentos hay que esperar un minuto
    $_SESSION['intentos'] = ($_SESSION['intentos'] ?? 0) + 1;
    $_SESSION['ultimo_intento'] = time();

    if ($_SESSION['intentos'] > 5 && time() - ($_SESSION['bloqueo'] ?? 0) < 60) {
        $error = 'Demasiados intentos. Espera un minuto.';
    } elseif (hash_equals(COTIZADOR_PIN, (string)$_POST['pin'])) {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['intentos'] = 0;
        // Vuelve a la pagina que se pidio antes del login. Se acepta solo una
        // lista conocida: con un destino libre esto seria un salto abierto.
        $destinos = ['revisar.php', 'historial.php'];
        $volver = in_array($_POST['volver'] ?? '', $destinos, true) ? $_POST['volver'] : 'index.php';
        header('Location: ' . $volver);
        exit;
    } else {
        usleep(400000);
        if ($_SESSION['intentos'] > 5) $_SESSION['bloqueo'] = time();
        $error = 'PIN incorrecto.';
    }
}

$autorizado = autorizado();

// Modo correccion: se carga una cotizacion existente en el formulario
$editando = null;
if ($autorizado && !empty($_GET['editar']) && id_valido($_GET['editar'])) {
    $f = DIR_DATOS . '/' . $_GET['editar'] . '.json';
    if (is_file($f)) {
        $editando = json_decode(file_get_contents($f), true);
        foreach ($editando['items'] as $i => $it) {
            $editando['items'][$i]['fotos'] = fotos_de($it);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Cotizador de reparaciones | <?= EMPRESA ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="robots" content="noindex, nofollow" />
<meta name="theme-color" content="#0000ff" />
<link rel="icon" href="../img/favicon_bigcleans.png" />
<link href="https://fonts.googleapis.com/css?family=Montserrat:600,700|Open+Sans:400,600" rel="stylesheet" />
<link rel="stylesheet" href="cotizador.css?v=3" />
</head>
<body>

<?php if (!$autorizado): ?>

    <div class="acceso">
        <img class="acceso-logo" src="../img/Logo bigcleans.png?v=2" alt="<?= EMPRESA ?>" />
        <h1>Cotizador de reparaciones</h1>
        <form method="post" autocomplete="off">
            <?php if (!empty($_GET['volver'])): ?>
                <input type="hidden" name="volver" value="<?= htmlspecialchars($_GET['volver'], ENT_QUOTES) ?>" />
            <?php endif; ?>
            <label for="pin">PIN de acceso</label>
            <input type="password" id="pin" name="pin" inputmode="numeric" required autofocus />
            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <button type="submit">Entrar</button>
        </form>
    </div>

<?php else: ?>

    <header class="barra">
        <img src="../img/Logo bigcleans.png?v=2" alt="<?= EMPRESA ?>" />
        <span><?= $editando ? 'Corrigiendo' : 'Cotizador' ?></span>
        <a class="salir" href="historial.php">Historial</a>
        <a class="salir" href="revisar.php">Revisar</a>
        <a class="salir" href="?salir=1" title="Cerrar sesión">Salir</a>
    </header>

    <main class="app" id="app">

        <?php if ($editando): ?>
        <div class="aviso-editar">
            Estás corrigiendo una cotización ya creada. Conserva el mismo enlace,
            y al cliente le aparecerá la fecha de actualización.
        </div>
        <?php endif; ?>

        <!-- ===== Paso 1: datos del cliente ===== -->
        <section class="tarjeta">
            <h2>Datos del cliente</h2>
            <div class="campo">
                <label for="cliente">Nombre</label>
                <input type="text" id="cliente" placeholder="Ej: Juan Pérez" />
            </div>
            <div class="campo">
                <label for="telefono">WhatsApp</label>
                <input type="tel" id="telefono" inputmode="tel" placeholder="+56 9 1234 5678" />
                <small>Para enviarle la cotización por WhatsApp.</small>
            </div>
            <div class="campo">
                <label for="email">Correo <span class="opc">(opcional)</span></label>
                <input type="email" id="email" inputmode="email" placeholder="cliente@correo.cl" />
            </div>
            <div class="campo">
                <label for="direccion">Dirección / departamento</label>
                <input type="text" id="direccion" placeholder="Ej: Av. Providencia 1234, depto 802" />
            </div>
        </section>

        <!-- ===== Paso 2: items ===== -->
        <section class="tarjeta">
            <h2>Reparaciones <span class="contador" id="contador">0</span></h2>
            <div id="items"></div>
            <button class="btn-agregar" type="button" id="agregar">+ Agregar reparación</button>
            <p class="aviso" id="aviso-max" hidden>Máximo <?= MAX_ITEMS ?> reparaciones por cotización.</p>
        </section>

        <!-- ===== Paso 3: observaciones ===== -->
        <section class="tarjeta">
            <h2>Observaciones <span class="opc">(opcional)</span></h2>
            <textarea id="observaciones" rows="3" placeholder="Ej: los materiales están incluidos. Plazo estimado: 2 días."></textarea>
        </section>

        <div class="espaciador"></div>
    </main>

    <!-- ===== Barra fija con el total ===== -->
    <div class="totalbar">
        <div class="totalbar-monto">
            <span>Total</span>
            <strong id="total">$0</strong>
        </div>
        <button type="button" id="generar" disabled><?= $editando ? 'Guardar cambios' : 'Generar cotización' ?></button>
    </div>

    <!-- ===== Pantalla de resultado ===== -->
    <div class="modal" id="modal" hidden>
        <div class="modal-caja">
            <h2 id="modal-titulo">Cotización lista</h2>
            <p class="modal-sub" id="modal-sub"></p>

            <div class="enlace-caja">
                <input type="text" id="enlace" readonly />
                <button type="button" id="copiar">Copiar</button>
            </div>

            <a class="btn-wa" id="btn-wa" target="_blank" rel="noopener">
                <span>Enviar por WhatsApp</span>
            </a>
            <button class="btn-mail" type="button" id="btn-mail">Enviar por correo</button>
            <a class="btn-ver" id="btn-ver" target="_blank" rel="noopener">Ver la cotización</a>

            <p class="modal-nota" id="modal-nota"></p>
            <button class="btn-cerrar" type="button" id="cerrar">Nueva cotización</button>
        </div>
    </div>

    <div class="cargando" id="cargando" hidden>
        <div class="spinner"></div>
        <p id="cargando-texto">Guardando…</p>
    </div>

    <template id="plantilla-item">
        <article class="item">
            <button class="item-borrar" type="button" title="Quitar">&times;</button>
            <div class="item-fotos">
                <div class="tira"></div>
                <label class="agregar-foto">
                    <input type="file" accept="image/*" capture="environment" hidden />
                    <span class="icono">📷</span>
                    <span class="rotulo">Foto</span>
                </label>
            </div>
            <div class="item-datos">
                <textarea class="item-desc" rows="2" placeholder="¿Qué hay que reparar?"></textarea>
                <div class="item-precio">
                    <span>$</span>
                    <input type="text" inputmode="numeric" class="item-monto" placeholder="0" />
                </div>
            </div>
        </article>
    </template>

    <script>
        window.COTIZADOR = {
            maxFotosItem: <?= MAX_FOTOS_ITEM ?>,
            maxItems: <?= MAX_ITEMS ?>,
            editando: <?= $editando ? json_encode($editando, JSON_UNESCAPED_UNICODE) : 'null' ?>
        };
    </script>
    <script src="cotizador.js?v=3"></script>

<?php endif; ?>

</body>
</html>
