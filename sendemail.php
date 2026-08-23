<?php
/**
 * Formulario de contacto del sitio.
 *
 * Antes llamaba a mail() sin mirar el resultado y redirigia siempre a
 * gracias.html: el visitante leia "Gracias por tu mensaje" aunque el correo
 * nunca hubiera salido, y nadie se enteraba. El hosting rechaza mail(), asi
 * que probablemente se perdieron consultas.
 *
 * Ahora envia por SMTP y solo agradece si el servidor acepto el mensaje.
 */
require_once __DIR__ . '/correo.php';

$nombre  = trim((string)($_POST['nombre'] ?? ''));
$correo  = trim((string)($_POST['email'] ?? ''));
$asunto  = trim((string)($_POST['asunto'] ?? ''));
$mensaje = trim((string)($_POST['mensaje'] ?? ''));

if ($nombre === '' || $mensaje === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.html#contact');
    exit;
}

$cuerpo = "Nuevo mensaje desde el formulario del sitio

"
    . "Nombre: " . $nombre . "
"
    . "Email : " . $correo . "
"
    . "Asunto: " . ($asunto ?: '(sin asunto)') . "

"
    . $mensaje . "

"
    . "Recibido el " . date('d/m/Y H:i') . "
";

// El remitente es la cuenta del dominio; el correo del visitante va en
// responder-a, asi el servidor no lo marca como suplantacion.
list($ok, $detalle) = enviar_correo(
    'contacto@bigcleans.cl',
    'Consulta de ' . $nombre . ($asunto ? ' - ' . $asunto : ''),
    $cuerpo,
    $correo
);

if ($ok) {
    header('Location: gracias.html');
    exit;
}

// Si fallo, se registra y se avisa: nunca mas un "gracias" en falso
@error_log('[contacto] no se pudo enviar: ' . $detalle);
header('Location: index.html?error=envio#contact');
