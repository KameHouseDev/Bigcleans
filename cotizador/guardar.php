<?php
/**
 * Crea o actualiza una cotizacion.
 * Las fotos ya fueron subidas por subir.php: aca solo llegan sus tokens,
 * asi que este envio es puro texto y pesa unos pocos KB.
 */
define('RESPUESTA_JSON', true);
require __DIR__ . '/arranque.php';

iniciar_sesion();

if (!autorizado()) {
    responder(['ok' => false, 'error' => 'Sesión expirada, vuelve a entrar con el PIN'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'error' => 'Método no permitido'], 405);
}

$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items) || !count($items)) {
    responder(['ok' => false, 'error' => 'No hay reparaciones que guardar'], 400);
}
if (count($items) > MAX_ITEMS) {
    responder(['ok' => false, 'error' => 'Demasiadas reparaciones'], 400);
}

// Si viene un id, se esta corrigiendo una cotizacion existente y conserva su
// enlace: lo habitual es un precio mal tecleado que se detecta al momento.
$editando = false;
$id = trim((string)($_POST['id'] ?? ''));

if ($id !== '') {
    if (!id_valido($id) || !is_file(DIR_DATOS . '/' . $id . '.json')) {
        responder(['ok' => false, 'error' => 'La cotización que intentas corregir no existe'], 404);
    }
    $editando = true;
} else {
    // Identificador imposible de adivinar: la pagina es publica para que el
    // cliente la abra sin clave, asi que el id es lo unico que la protege.
    $id = bin2hex(random_bytes(8));
}

foreach ([DIR_DATOS, DIR_FOTOS] as $d) {
    if (!is_dir($d) && !mkdir($d, 0775, true)) {
        responder(['ok' => false, 'error' => 'No se pudo crear la carpeta de trabajo'], 500);
    }
}

$dirFotos = DIR_FOTOS . '/' . $id;
if (!is_dir($dirFotos) && !mkdir($dirFotos, 0775, true)) {
    responder(['ok' => false, 'error' => 'No se pudo crear la carpeta de fotos'], 500);
}

$limpios = [];
$total = 0;
$usadas = [];

foreach ($items as $n => $item) {
    $desc = trim((string)($item['descripcion'] ?? ''));
    $precio = (int)($item['precio'] ?? 0);
    if ($desc === '' || $precio <= 0) continue;

    $fotos = [];
    $refs = is_array($item['fotos'] ?? null) ? $item['fotos'] : [];

    foreach (array_slice($refs, 0, MAX_FOTOS_ITEM) as $k => $ref) {
        $tipo = $ref['t'] ?? '';

        if ($tipo === 'existente') {
            // Foto que ya estaba en esta cotizacion: se conserva tal cual
            $nombre = basename((string)($ref['nombre'] ?? ''));
            if ($nombre !== '' && is_file($dirFotos . '/' . $nombre)) {
                $fotos[] = $nombre;
                $usadas[] = $nombre;
            }
        } elseif ($tipo === 'nueva') {
            $token = (string)($ref['token'] ?? '');
            if (!preg_match('/^[a-f0-9]{20}$/', $token)) continue;
            $origen = DIR_TMP . '/' . $token . '.jpg';
            if (!is_file($origen)) continue;
            $nombre = ($n + 1) . '-' . ($k + 1) . '-' . substr($token, 0, 6) . '.jpg';
            if (@rename($origen, $dirFotos . '/' . $nombre)) {
                $fotos[] = $nombre;
                $usadas[] = $nombre;
            }
        }
    }

    $limpios[] = [
        'descripcion' => mb_substr($desc, 0, 400),
        'precio'      => $precio,
        'fotos'       => $fotos,
    ];
    $total += $precio;
}

if (!count($limpios)) {
    if (!$editando) @rmdir($dirFotos);
    responder(['ok' => false, 'error' => 'No hay reparaciones válidas'], 400);
}

// Al corregir, se borran las fotos que quedaron fuera de la nueva version
if ($editando) {
    foreach (glob($dirFotos . '/*.jpg') ?: [] as $f) {
        if (!in_array(basename($f), $usadas, true)) @unlink($f);
    }
}

$archivo = DIR_DATOS . '/' . $id . '.json';
$previo = $editando ? json_decode(@file_get_contents($archivo), true) : null;

$cot = [
    'id'            => $id,
    'fecha'         => $previo['fecha'] ?? date('c'),
    'actualizada'   => $editando ? date('c') : null,
    'cliente'       => mb_substr(trim((string)($_POST['cliente'] ?? '')), 0, 120),
    'telefono'      => mb_substr(trim((string)($_POST['telefono'] ?? '')), 0, 40),
    'email'         => mb_substr(trim((string)($_POST['email'] ?? '')), 0, 120),
    'direccion'     => mb_substr(trim((string)($_POST['direccion'] ?? '')), 0, 200),
    'observaciones' => mb_substr(trim((string)($_POST['observaciones'] ?? '')), 0, 1000),
    'items'         => $limpios,
    'total'         => $total,
    'enviado'       => $previo['enviado'] ?? false,
];

if (file_put_contents($archivo, json_encode($cot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
    responder(['ok' => false, 'error' => 'No se pudo guardar la cotización'], 500);
}

limpiar_temporales();

// Aviso a la oficina, aunque el cliente no tenga correo
if (!$editando) {
    $aviso = "Nueva cotización de reparaciones\n\n"
        . 'Cliente: ' . ($cot['cliente'] ?: '(sin nombre)') . "\n"
        . 'Dirección: ' . ($cot['direccion'] ?: '(sin dirección)') . "\n"
        . 'Teléfono: ' . ($cot['telefono'] ?: '-') . "\n"
        . 'Total: ' . pesos($total) . "\n"
        . 'Reparaciones: ' . count($limpios) . "\n\n"
        . url_base() . '/ver.php?id=' . $id;

    require_once __DIR__ . '/../correo.php';
    enviar_correo(CORREO_OFICINA,
        'Cotización ' . pesos($total) . ' - ' . ($cot['cliente'] ?: 'sin nombre'),
        $aviso);
}

responder([
    'ok'       => true,
    'id'       => $id,
    'editado'  => $editando,
    'url'      => url_base() . '/ver.php?id=' . $id,
]);
