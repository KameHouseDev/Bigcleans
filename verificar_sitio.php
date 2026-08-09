<?php
// CONFIGURACIÓN
$url = "https://bigcleans.cl"; // Cambia esto por tu URL real
$correo_destino = "bigclean84@gmail.com"; // Cambia esto por tu correo

// VERIFICACIÓN
$timeout = 10;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ENVÍO DE ALERTA SI HAY ERROR
if ($httpcode !== 200) {
    $asunto = "⚠️ Sitio no disponible";
    $mensaje = "El sitio $url no está respondiendo correctamente. Código HTTP: $httpcode";
    mail($correo_destino, $asunto, $mensaje);
}
?>
