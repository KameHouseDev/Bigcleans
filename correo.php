<?php
/**
 * Envio de correo del sitio, por SMTP autenticado.
 *
 * El hosting rechaza mail(), asi que se habla SMTP directo contra el servidor
 * de correo del dominio. Se escribe a mano en vez de traer una libreria para
 * no sumar dependencias: son unas pocas ordenes de texto sobre un socket.
 *
 * Las credenciales viven en correo-config.php, que no se versiona.
 * Lo usan tanto el formulario de contacto como el cotizador.
 */

if (!defined('CORREO_CONFIGURADO')) {
    $cfg = __DIR__ . '/correo-config.php';
    if (is_file($cfg)) require_once $cfg;
}

/**
 * Envia un correo.
 *
 * @return array{0:bool,1:string} [exito, detalle]
 */
function enviar_correo(string $para, string $asunto, string $cuerpo,
                       string $responderA = '', string $copiaOculta = ''): array {

    // Sin configuracion SMTP se intenta mail(), por si el servidor si lo permite
    if (!defined('SMTP_HOST') || SMTP_HOST === '') {
        $cab = 'From: ' . correo_remitente() . "\r\n"
             . ($responderA ? 'Reply-To: ' . $responderA . "\r\n" : '')
             . ($copiaOculta ? 'Bcc: ' . $copiaOculta . "\r\n" : '')
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit";
        $ok = @mail($para, correo_asunto($asunto), $cuerpo, $cab, '-f' . SMTP_USUARIO);
        return [$ok, $ok ? 'Enviado con mail()' : 'mail() fue rechazado y no hay SMTP configurado'];
    }

    $destinos = array_filter(array_map('trim', array_merge([$para], $copiaOculta ? [$copiaOculta] : [])));
    return smtp_entregar($destinos, $para, $asunto, $cuerpo, $responderA);
}

function correo_remitente(): string {
    $nombre = defined('SMTP_NOMBRE') ? SMTP_NOMBRE : '';
    $dir = defined('SMTP_USUARIO') ? SMTP_USUARIO : '';
    return $nombre ? correo_cabecera($nombre) . ' <' . $dir . '>' : $dir;
}

/** Codifica una cabecera con acentos segun RFC 2047, si hace falta. */
function correo_cabecera(string $texto): string {
    return preg_match('/[^\x20-\x7E]/', $texto)
        ? '=?UTF-8?B?' . base64_encode($texto) . '?='
        : $texto;
}

function correo_asunto(string $s): string {
    return correo_cabecera($s);
}

/** Lee una respuesta del servidor, incluidas las de varias lineas (250-...). */
function smtp_leer($conexion): string {
    $salida = '';
    while (($linea = fgets($conexion, 515)) !== false) {
        $salida .= $linea;
        // La ultima linea trae un espacio en la cuarta posicion; las intermedias, un guion
        if (strlen($linea) < 4 || $linea[3] !== '-') break;
    }
    return $salida;
}

/** Envia una orden y comprueba el codigo esperado. */
function smtp_orden($conexion, string $orden, string $esperado, array &$traza): bool {
    if ($orden !== '') {
        fwrite($conexion, $orden . "\r\n");
        // No se registran las credenciales en la traza
        $traza[] = '> ' . (preg_match('/^[A-Za-z0-9+\/=]{12,}$/', $orden) ? '(credencial)' : $orden);
    }
    $r = smtp_leer($conexion);
    $traza[] = '< ' . trim($r);
    return str_starts_with(trim($r), $esperado);
}

function smtp_entregar(array $destinos, string $para, string $asunto,
                       string $cuerpo, string $responderA): array {
    $traza = [];
    $puerto = defined('SMTP_PUERTO') ? (int)SMTP_PUERTO : 465;
    $seguro = $puerto === 465;                       // 465 cifra desde el saludo; 587 con STARTTLS
    $destino = ($seguro ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . $puerto;

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $conexion = @stream_socket_client($destino, $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT, $ctx);

    if (!$conexion) {
        return [false, 'No se pudo conectar a ' . SMTP_HOST . ':' . $puerto . ' — ' . $errstr];
    }
    stream_set_timeout($conexion, 15);

    $fin = function (string $motivo) use ($conexion, &$traza) {
        @fwrite($conexion, "QUIT\r\n");
        @fclose($conexion);
        return [false, $motivo . "\n" . implode("\n", array_slice($traza, -6))];
    };

    if (!smtp_orden($conexion, '', '220', $traza)) return $fin('El servidor no saludó');

    $yo = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!smtp_orden($conexion, 'EHLO ' . $yo, '250', $traza)) return $fin('EHLO rechazado');

    if (!$seguro) {
        if (!smtp_orden($conexion, 'STARTTLS', '220', $traza)) return $fin('STARTTLS rechazado');
        if (!@stream_socket_enable_crypto($conexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fin('No se pudo cifrar la conexión');
        }
        if (!smtp_orden($conexion, 'EHLO ' . $yo, '250', $traza)) return $fin('EHLO tras STARTTLS rechazado');
    }

    if (!smtp_orden($conexion, 'AUTH LOGIN', '334', $traza)) return $fin('El servidor no acepta AUTH LOGIN');
    if (!smtp_orden($conexion, base64_encode(SMTP_USUARIO), '334', $traza)) return $fin('Usuario rechazado');
    if (!smtp_orden($conexion, base64_encode(SMTP_CLAVE), '235', $traza)) {
        return $fin('Contraseña rechazada. Revisa SMTP_CLAVE en correo-config.php');
    }

    if (!smtp_orden($conexion, 'MAIL FROM:<' . SMTP_USUARIO . '>', '250', $traza)) return $fin('MAIL FROM rechazado');

    $aceptados = 0;
    foreach ($destinos as $d) {
        if (smtp_orden($conexion, 'RCPT TO:<' . $d . '>', '250', $traza)) $aceptados++;
    }
    if (!$aceptados) return $fin('Ningún destinatario fue aceptado');

    if (!smtp_orden($conexion, 'DATA', '354', $traza)) return $fin('DATA rechazado');

    $cabeceras = [
        'From: ' . correo_remitente(),
        'To: ' . $para,
        'Subject: ' . correo_asunto($asunto),
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . SMTP_HOST . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($responderA) $cabeceras[] = 'Reply-To: ' . $responderA;

    // Las lineas que empiezan con punto se duplican: un punto solo cierra el mensaje
    $texto = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $cuerpo));
    $texto = str_replace("\n", "\r\n", $texto);

    fwrite($conexion, implode("\r\n", $cabeceras) . "\r\n\r\n" . $texto . "\r\n.\r\n");
    $traza[] = '> (mensaje)';

    if (!smtp_orden($conexion, '', '250', $traza)) return $fin('El servidor no aceptó el mensaje');

    @fwrite($conexion, "QUIT\r\n");
    @fclose($conexion);

    return [true, 'Entregado a ' . $aceptados . ' destinatario(s) por ' . SMTP_HOST];
}
