<?php
/**
 * Sube UNA foto y devuelve un token.
 *
 * Se llama apenas se toma la foto, no al final. Asi la subida se reparte
 * durante la visita (util con la señal de un edificio) y nunca se choca con
 * max_file_uploads del servidor, que limita los archivos POR ENVIO: si se
 * pasara, PHP descarta las sobrantes en silencio y la cotizacion se guardaria
 * con fotos faltantes sin avisar.
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
if (!isset($_FILES['foto'])) {
    responder(['ok' => false, 'error' => 'No llegó ninguna foto'], 400);
}

$f = $_FILES['foto'];

if ($f['error'] !== UPLOAD_ERR_OK) {
    $motivos = [
        UPLOAD_ERR_INI_SIZE   => 'La foto excede el tamaño permitido por el servidor',
        UPLOAD_ERR_FORM_SIZE  => 'La foto es demasiado grande',
        UPLOAD_ERR_PARTIAL    => 'La subida se cortó, reintenta',
        UPLOAD_ERR_NO_FILE    => 'No llegó ninguna foto',
        UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal',
        UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir la foto',
    ];
    responder(['ok' => false, 'error' => $motivos[$f['error']] ?? 'Error al subir la foto'], 400);
}

if ($f['size'] > MAX_FOTO_BYTES) {
    responder(['ok' => false, 'error' => 'La foto pesa demasiado'], 400);
}

// getimagesize no necesita GD y confirma que el archivo es realmente una
// imagen, no un script renombrado a .jpg
$info = @getimagesize($f['tmp_name']);
if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
    responder(['ok' => false, 'error' => 'El archivo no es una imagen válida'], 400);
}

if (!is_dir(DIR_TMP) && !mkdir(DIR_TMP, 0775, true)) {
    responder(['ok' => false, 'error' => 'No se pudo crear la carpeta temporal'], 500);
}

limpiar_temporales();

$token = bin2hex(random_bytes(10));
$destino = DIR_TMP . '/' . $token . '.jpg';

if (!move_uploaded_file($f['tmp_name'], $destino)) {
    responder(['ok' => false, 'error' => 'No se pudo guardar la foto'], 500);
}

// La miniatura viaja junto a la foto: la usa el historial, que si no tendria
// que descargar la imagen completa para un cuadro de 54 px.
if (isset($_FILES['mini']) && $_FILES['mini']['error'] === UPLOAD_ERR_OK) {
    $infoMini = @getimagesize($_FILES['mini']['tmp_name']);
    if ($infoMini !== false && $infoMini[2] === IMAGETYPE_JPEG) {
        @move_uploaded_file($_FILES['mini']['tmp_name'], DIR_TMP . '/' . $token . '-mini.jpg');
    }
}

responder([
    'ok'    => true,
    'token' => $token,
    'url'   => 'fotos/_tmp/' . $token . '.jpg',
]);
