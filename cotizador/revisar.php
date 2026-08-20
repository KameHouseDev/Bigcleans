<?php
/**
 * Diagnostico de instalacion del cotizador.
 *
 * Se abre en el navegador despues de subir los archivos y dice exactamente
 * que falta. Evita tener que ir probando a ciegas.
 *
 * Si config.php ya existe, exige el PIN: los resultados revelan rutas y
 * estado del servidor. Si todavia no existe, se puede ver sin clave, porque
 * en ese punto aun no hay nada que proteger.
 */

$hayConfig = is_file(__DIR__ . '/config.php');

if ($hayConfig) {
    require __DIR__ . '/config.php';
    iniciar_sesion();
    if (!autorizado()) {
        header('Location: index.php');
        exit;
    }
}

$pruebas = [];

/** Registra un resultado. $estado: ok | aviso | falla */
function revisar(string $titulo, string $estado, string $detalle, string $arreglo = ''): void {
    global $pruebas;
    $pruebas[] = compact('titulo', 'estado', 'detalle', 'arreglo');
}

// --- PHP ---
revisar(
    'Versión de PHP',
    version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'falla',
    PHP_VERSION,
    'El cotizador necesita PHP 7.4 o superior. Cámbialo en el panel del hosting.'
);

// --- configuracion ---
if (!$hayConfig) {
    revisar('Archivo config.php', 'falla', 'No existe',
        'Copia config.example.php como config.php en esta misma carpeta.');
} else {
    revisar('Archivo config.php', 'ok', 'Presente');

    $pinDebil = in_array(COTIZADOR_PIN, ['CAMBIAR', '2468', '1234', '0000'], true);
    revisar('PIN de acceso', $pinDebil ? 'falla' : 'ok',
        $pinDebil ? 'Sigue siendo el de ejemplo' : 'Personalizado (' . strlen(COTIZADOR_PIN) . ' caracteres)',
        'Cambia COTIZADOR_PIN en config.php. Con el PIN de ejemplo cualquiera puede entrar.');

    revisar('IVA', 'ok', IVA_PORCENTAJE > 0
        ? 'Se desglosa neto + IVA ' . IVA_PORCENTAJE . '%'
        : 'Los precios se muestran como valores finales');
}

// --- carpetas ---
foreach ([['datos', DIR_DATOS ?? __DIR__ . '/datos'], ['fotos', DIR_FOTOS ?? __DIR__ . '/fotos']] as [$nombre, $ruta]) {
    if (!is_dir($ruta)) {
        revisar('Carpeta ' . $nombre, 'falla', 'No existe',
            'Crea la carpeta ' . $nombre . '/ dentro de cotizador/');
    } elseif (!is_writable($ruta)) {
        revisar('Carpeta ' . $nombre, 'falla', 'Sin permiso de escritura',
            'Dale permisos 755 (o 775) a cotizador/' . $nombre . '/. Sin esto no se puede guardar nada.');
    } else {
        $n = count(glob($ruta . '/*') ?: []);
        revisar('Carpeta ' . $nombre, 'ok', 'Se puede escribir · ' . $n . ' elemento(s)');
    }
}

// --- proteccion de los datos ---
if ($hayConfig) {
    $prueba = DIR_DATOS . '/prueba_' . bin2hex(random_bytes(4)) . '.json';
    $escrito = @file_put_contents($prueba, '{"prueba":true}') !== false;

    if ($escrito && function_exists('curl_init')) {
        $url = url_base() . '/datos/' . basename($prueba);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
                                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_NOBODY => true]);
        curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($codigo === 403 || $codigo === 404) {
            revisar('Protección de los datos', 'ok',
                'Los archivos de clientes no se pueden abrir desde el navegador (HTTP ' . $codigo . ')');
        } elseif ($codigo === 200) {
            revisar('Protección de los datos', 'falla',
                'Los datos de los clientes son accesibles desde internet',
                'El .htaccess de datos/ no está funcionando. Si el servidor usa Apache 2.2, ' .
                'reemplaza "Require all denied" por "Order allow,deny" y "Deny from all".');
        } else {
            revisar('Protección de los datos', 'aviso',
                'No se pudo comprobar (HTTP ' . $codigo . ')',
                'Abre a mano ' . $url . ' — no debería mostrarte el contenido.');
        }
    } elseif ($escrito) {
        revisar('Protección de los datos', 'aviso', 'No se pudo comprobar: falta cURL en el servidor',
            'Abre a mano cotizador/datos/ en el navegador. No debe listar ni mostrar archivos.');
    }
    @unlink($prueba);
}

// --- imagenes ---
revisar('Lectura de imágenes', function_exists('getimagesize') ? 'ok' : 'falla',
    function_exists('getimagesize') ? 'getimagesize disponible' : 'getimagesize no disponible',
    'Sin esta función no se puede validar que las fotos sean imágenes reales.');

$subidas = (int)ini_get('max_file_uploads');
revisar('Subida de archivos', 'ok',
    'Hasta ' . ini_get('upload_max_filesize') . ' por archivo · ' . $subidas . ' archivos por envío',
    $subidas < 5 ? 'Es bajo, pero el cotizador sube las fotos de a una, así que no le afecta.' : '');

// --- sesiones ---
revisar('Sesiones', session_status() === PHP_SESSION_ACTIVE || function_exists('session_start') ? 'ok' : 'falla',
    'Duración configurada: ' . ($hayConfig ? SESION_HORAS . ' horas' : 'sin configurar'),
    'Sin sesiones no funciona el acceso con PIN.');

// --- cifrado ---
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
revisar('Conexión cifrada (HTTPS)', $https ? 'ok' : 'aviso',
    $https ? 'El sitio va por HTTPS' : 'El sitio va por HTTP sin cifrar',
    'Sin HTTPS, el PIN y los datos de los clientes viajan en texto plano. ' .
    'La mayoría de los hostings ofrecen un certificado gratis con Let\'s Encrypt.');

// --- correo ---
$correo = $_GET['correo'] ?? '';
if ($correo === '1' && $hayConfig) {
    $t = microtime(true);
    $ok = @mail(CORREO_OFICINA, 'Prueba del cotizador - ' . EMPRESA,
        "Si recibes este correo, el envio desde el cotizador funciona.\n\n" . date('d/m/Y H:i'),
        'From: ' . CORREO_DESDE . "\r\nContent-Type: text/plain; charset=UTF-8");
    revisar('Envío de correo', $ok ? 'ok' : 'falla',
        $ok ? 'Enviado a ' . CORREO_OFICINA . ' en ' . round((microtime(true) - $t) * 1000) . ' ms — revisa la bandeja'
            : 'El servidor rechazó el envío',
        'El cotizador funciona igual por WhatsApp. Para el correo, pide al hosting los datos SMTP.');
} else {
    revisar('Envío de correo', 'aviso', 'Sin comprobar',
        'Es la única prueba que envía algo de verdad, por eso va aparte.');
}

$fallas = count(array_filter($pruebas, fn($p) => $p['estado'] === 'falla'));
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Diagnóstico del cotizador</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<style>
* { box-sizing: border-box; }
body { margin:0; background:#0a0a0a; color:#c8c8c8; font-family:system-ui,-apple-system,sans-serif; padding:26px 16px 60px; }
.hoja { max-width:640px; margin:0 auto; }
h1 { color:#fff; font-size:21px; margin:0 0 6px; }
.sub { color:#7a7a7a; font-size:14px; margin:0 0 24px; }
.banda { padding:16px 18px; border-radius:10px; margin-bottom:22px; font-size:15px; line-height:1.6; }
.banda.bien { background:rgba(74,222,128,.08); border:1px solid rgba(74,222,128,.3); color:#4ade80; }
.banda.mal  { background:rgba(255,107,107,.08); border:1px solid rgba(255,107,107,.3); color:#ff6b6b; }
.p { display:flex; gap:12px; padding:14px 0; border-bottom:1px solid #1a1a1a; }
.ico { flex:0 0 22px; font-size:15px; line-height:1.5; }
.cuerpo { flex:1; min-width:0; }
.tit { color:#fff; font-size:14.5px; font-weight:600; margin-bottom:3px; }
.det { font-size:13.5px; color:#8a8a8a; }
.arr { font-size:13px; color:#d9a441; margin-top:7px; line-height:1.6; }
.ok .ico { color:#4ade80; } .falla .ico { color:#ff6b6b; } .aviso .ico { color:#d9a441; }
.pie { margin-top:26px; display:flex; gap:10px; flex-wrap:wrap; }
.pie a { flex:1; min-width:170px; text-align:center; padding:13px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; }
.azul { background:#0000ff; color:#fff; } .gris { background:transparent; border:1px solid #333; color:#c8c8c8; }
code { background:#1c1c1c; padding:2px 6px; border-radius:4px; color:#8ab4ff; font-size:13px; }
</style>
</head>
<body>
<div class="hoja">

    <h1>Diagnóstico del cotizador</h1>
    <p class="sub">Comprueba que el servidor tenga todo lo necesario.</p>

    <?php if ($fallas === 0): ?>
        <div class="banda bien">Todo en orden. El cotizador está listo para usarse.</div>
    <?php else: ?>
        <div class="banda mal">Hay <?= $fallas ?> punto<?= $fallas === 1 ? '' : 's' ?> que resolver antes de usarlo. Están marcados abajo.</div>
    <?php endif; ?>

    <?php foreach ($pruebas as $p): ?>
    <div class="p <?= $e($p['estado']) ?>">
        <div class="ico"><?= $p['estado'] === 'ok' ? '&#10003;' : ($p['estado'] === 'falla' ? '&#10007;' : '!') ?></div>
        <div class="cuerpo">
            <div class="tit"><?= $e($p['titulo']) ?></div>
            <div class="det"><?= $e($p['detalle']) ?></div>
            <?php if ($p['arreglo'] && $p['estado'] !== 'ok'): ?>
                <div class="arr"><?= $e($p['arreglo']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="pie">
        <?php if ($hayConfig): ?><a class="gris" href="?correo=1">Probar el envío de correo</a><?php endif; ?>
        <a class="azul" href="index.php">Ir al cotizador</a>
    </div>

</div>
</body>
</html>
