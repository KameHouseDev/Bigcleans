<?php
/**
 * Envia la cotizacion por correo al cliente.
 * El correo lleva el detalle y el enlace: las fotos viven en la pagina, no
 * como adjuntos, para no chocar con los limites de tamaño de los servidores.
 */
define('RESPUESTA_JSON', true);
require __DIR__ . '/arranque.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

function salir(array $d, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['auth'])) {
    salir(['ok' => false, 'error' => 'Sesión expirada'], 403);
}

$id = $_POST['id'] ?? '';
if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
    salir(['ok' => false, 'error' => 'Cotización inválida'], 400);
}

$archivo = DIR_DATOS . '/' . $id . '.json';
if (!is_file($archivo)) {
    salir(['ok' => false, 'error' => 'Cotización no encontrada'], 404);
}

$c = json_decode(file_get_contents($archivo), true);
$para = filter_var($c['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$para) {
    salir(['ok' => false, 'error' => 'El cliente no tiene correo registrado'], 400);
}

$url = url_base() . '/ver.php?id=' . $id;

$cuerpo = "Hola" . ($c['cliente'] ? ' ' . $c['cliente'] : '') . ",\n\n"
    . "Te enviamos la cotización de las reparaciones detectadas"
    . ($c['direccion'] ? ' en ' . $c['direccion'] : '') . ".\n\n";

foreach ($c['items'] as $i => $item) {
    $cuerpo .= ($i + 1) . '. ' . $item['descripcion'] . ' - ' . pesos($item['precio']) . "\n";
}

$cuerpo .= "\nTOTAL: " . pesos($c['total']) . "\n\n";

if (!empty($c['observaciones'])) {
    $cuerpo .= $c['observaciones'] . "\n\n";
}

$cuerpo .= "Puedes ver el detalle con las fotos de cada reparación aquí:\n" . $url . "\n\n"
    . "La cotización es válida por " . VALIDEZ_DIAS . " días.\n"
    . "Cualquier duda, respóndenos este correo o escríbenos al " . TELEFONO . ".\n\n"
    . EMPRESA . "\n" . SITIO . "\n";

require_once __DIR__ . '/../correo.php';

list($ok, $detalle) = enviar_correo(
    $para,
    'Cotización de reparaciones ' . pesos($c['total']) . ' - ' . EMPRESA,
    $cuerpo,
    CORREO_OFICINA,      // responder-a
    CORREO_OFICINA       // copia oculta para la oficina
);

if (!$ok) {
    salir(['ok' => false, 'error' => 'No se pudo enviar: ' . $detalle]);
}

$c['enviado_en'] = date('c');
file_put_contents($archivo, json_encode($c, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

salir(['ok' => true]);
