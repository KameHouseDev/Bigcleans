<?php
/**
 * Punto de entrada comun.
 *
 * config.php no se versiona porque lleva el PIN, asi que en un servidor recien
 * desplegado no existe todavia. Sin esta comprobacion, PHP tira un error fatal
 * que ademas revela la ruta absoluta del servidor, y no dice que hay que hacer.
 */

if (!is_file(__DIR__ . '/config.php')) {
    if (defined('RESPUESTA_JSON')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Falta config.php en el servidor. Copialo desde config.example.php.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="utf-8" />
    <title>Cotizador sin configurar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" />
    <style>
        body { margin:0; background:#0a0a0a; color:#c8c8c8; font-family:system-ui,sans-serif;
               display:flex; align-items:center; justify-content:center; min-height:100vh; padding:24px; }
        .caja { max-width:460px; background:#111; border:1px solid #262626; border-radius:12px; padding:30px 26px; }
        h1 { color:#fff; font-size:19px; margin:0 0 14px; }
        p { line-height:1.7; font-size:14.5px; margin:0 0 14px; }
        ol { line-height:1.9; font-size:14.5px; padding-left:20px; margin:0; }
        code { background:#1c1c1c; padding:2px 6px; border-radius:4px; color:#8ab4ff; font-size:13.5px; }
    </style>
    </head>
    <body>
        <div class="caja">
            <h1>Falta configurar el cotizador</h1>
            <p>El archivo <code>config.php</code> no existe en el servidor. No viene
               en el repositorio porque contiene el PIN de acceso.</p>
            <ol>
                <li>Copia <code>config.example.php</code> como <code>config.php</code></li>
                <li>Cambia el PIN, que viene como <code>CAMBIAR</code></li>
                <li>Da permiso de escritura a <code>datos/</code> y <code>fotos/</code></li>
            </ol>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require __DIR__ . '/config.php';
