<?php
/**
 * Datos de la cuenta de correo, sacados de cPanel:
 *   Cuentas de correo -> contacto@bigcleans.cl -> Conectar dispositivos
 *
 * Copiar como correo-config.php y completar la contraseña.
 * correo-config.php NO se versiona: el repositorio es publico.
 */

const SMTP_HOST    = 'mail.bigcleans.cl';
const SMTP_PUERTO  = 465;                      // 465 con SSL, 587 con STARTTLS
const SMTP_USUARIO = 'contacto@bigcleans.cl';
const SMTP_CLAVE   = 'PONER_LA_CONTRASENA';
const SMTP_NOMBRE  = 'Bigcleans';

define('CORREO_CONFIGURADO', true);
