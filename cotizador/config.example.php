<?php
/**
 * Plantilla de configuracion. Copiar como config.php y completar.
 * config.php NO se versiona: lleva el PIN.
 * Este archivo lo interpreta PHP, no se sirve como texto.
 */

// ---------------------------------------------------------------------------
// CAMBIAR ANTES DE PUBLICAR
// ---------------------------------------------------------------------------

// PIN de acceso al cotizador
const COTIZADOR_PIN = 'CAMBIAR';

// IVA: 0 = no se muestra (los precios son finales).
//      19 = la cotizacion desglosa neto + IVA + total.
const IVA_PORCENTAJE = 0;

// ---------------------------------------------------------------------------

const CORREO_OFICINA = 'contacto@bigcleans.cl';

// Remitente de los correos. Debe ser del dominio del sitio: si se pone la
// direccion del cliente como remitente, los servidores lo marcan como spam.
const CORREO_DESDE = 'contacto@bigcleans.cl';

const EMPRESA     = 'Bigcleans';
const TELEFONO    = '+56 9 6139 9502';
const TELEFONO_WA = '56961399502';
const SITIO       = 'https://bigcleans.cl';

const VALIDEZ_DIAS   = 15;
const MAX_ITEMS      = 30;
const MAX_FOTOS_ITEM = 4;

// Las fotos se suben de a una, apenas se toman, asi que no chocan con
// max_file_uploads del servidor (20 archivos por envio). Al guardar solo
// viaja el texto y la lista de fotos ya subidas.
const MAX_FOTO_BYTES = 4 * 1024 * 1024;

// Las fotos sueltas de cotizaciones que nunca se guardaron se borran
// despues de estas horas.
const HORAS_TEMPORALES = 12;

// Duracion de la sesion. Sin esto son 24 minutos y se vence mientras se
// recorre el departamento, porque entre el PIN y el envio no hay peticiones.
const SESION_HORAS = 12;

define('DIR_DATOS', __DIR__ . '/datos');
define('DIR_FOTOS', __DIR__ . '/fotos');
define('DIR_TMP',   __DIR__ . '/fotos/_tmp');

/** Abre la sesion con la duracion larga. Llamar antes de tocar $_SESSION. */
function iniciar_sesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $dur = SESION_HORAS * 3600;
    @ini_set('session.gc_maxlifetime', (string)$dur);
    session_set_cookie_params([
        'lifetime' => $dur,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

/** true si la sesion tiene el PIN validado. */
function autorizado(): bool {
    return !empty($_SESSION['auth']);
}

/** Responde JSON y termina. */
function responder(array $d, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

/** URL base del cotizador, para armar el enlace publico de cada cotizacion. */
function url_base(): string {
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    return ($esHttps ? 'https' : 'http') . '://' . $host . $dir;
}

/** Formatea un monto en pesos chilenos: 45000 -> $45.000 */
function pesos($n): string {
    return '$' . number_format((float)$n, 0, ',', '.');
}

/** Devuelve [neto, iva, total] segun la configuracion de IVA. */
function desglose(int $total): array {
    if (IVA_PORCENTAJE <= 0) return [$total, 0, $total];
    $neto = (int)round($total / (1 + IVA_PORCENTAJE / 100));
    return [$neto, $total - $neto, $total];
}

/** Valida el id de una cotizacion. Sin esto un id con ../ leeria otros archivos. */
function id_valido(string $id): bool {
    return (bool)preg_match('/^[a-f0-9]{16}$/', $id);
}

/** Las primeras cotizaciones guardaban una sola foto por item; se normaliza a lista. */
function fotos_de(array $item): array {
    if (!empty($item['fotos']) && is_array($item['fotos'])) return $item['fotos'];
    if (!empty($item['foto'])) return [$item['foto']];
    return [];
}

/** Borra las fotos temporales que quedaron de cotizaciones abandonadas. */
function limpiar_temporales(): void {
    if (!is_dir(DIR_TMP)) return;
    $limite = time() - HORAS_TEMPORALES * 3600;
    foreach (glob(DIR_TMP . '/*.jpg') ?: [] as $f) {
        if (@filemtime($f) < $limite) @unlink($f);
    }
}
