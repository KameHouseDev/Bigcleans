<?php
/**
 * Cotizacion tal como la ve el cliente.
 * Publica a proposito: se abre desde el enlace de WhatsApp sin clave.
 * Lo que la protege es el id aleatorio, no adivinable.
 */
require __DIR__ . '/config.php';

$id = $_GET['id'] ?? '';

// Sin esta validacion, un id como "../../config" leeria otros archivos
if (!id_valido($id)) {
    http_response_code(404);
    exit('Cotización no encontrada.');
}

$archivo = DIR_DATOS . '/' . $id . '.json';
if (!is_file($archivo)) {
    http_response_code(404);
    exit('Cotización no encontrada.');
}

$c = json_decode(file_get_contents($archivo), true);
if (!is_array($c)) {
    http_response_code(500);
    exit('No se pudo leer la cotización.');
}

$fecha = new DateTime($c['fecha']);
$corregida = !empty($c['actualizada']) ? new DateTime($c['actualizada']) : null;
$vence = (clone $fecha)->modify('+' . VALIDEZ_DIAS . ' days');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Cotización de reparaciones | <?= EMPRESA ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<meta name="theme-color" content="#0000ff" />
<link rel="icon" href="../img/favicon_bigcleans.png" />
<link href="https://fonts.googleapis.com/css?family=Montserrat:600,700|Open+Sans:400,600" rel="stylesheet" />
<style>
* { box-sizing: border-box; }
body { margin: 0; background: #0a0a0a; color: #c8c8c8; font-family: "Open Sans", sans-serif; }
.hoja { max-width: 760px; margin: 0 auto; padding: 26px 18px 60px; }

.cab { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-bottom: 20px; border-bottom: 1px solid #222; margin-bottom: 24px; }
.cab img { width: 168px; }
.cab .meta { text-align: right; font-size: 12.5px; color: #7a7a7a; line-height: 1.7; }

h1 { font-family: Montserrat; color: #fff; font-size: 23px; margin: 0 0 6px; }
.sub { color: #7a7a7a; font-size: 14px; margin: 0 0 26px; }

.datos { background: #111; border: 1px solid #1e1e1e; border-radius: 10px; padding: 16px 18px; margin-bottom: 26px; font-size: 14.5px; line-height: 1.9; }
.datos b { color: #fff; font-weight: 600; }

.item { display: flex; gap: 16px; padding: 16px 0; border-bottom: 1px solid #1a1a1a; }
.item:last-of-type { border-bottom: 0; }
.item-n { flex: 0 0 26px; height: 26px; border-radius: 50%; background: #0000ff; color: #fff; font-size: 13px; font-weight: 600; display: flex; align-items: center; justify-content: center; }
.item-foto { flex: 0 0 116px; display: flex; flex-wrap: wrap; gap: 6px; }
.item-foto img { width: 116px; height: 116px; object-fit: cover; border-radius: 8px; border: 1px solid #262626; display: block; cursor: zoom-in; }
.corregida { color: #d9a441; }
.subtotales { margin-top: 18px; padding: 14px 20px; background: #0d0d0d; border: 1px solid #1a1a1a; border-radius: 10px 10px 0 0; font-size: 14.5px; }
.subtotales div { display: flex; justify-content: space-between; padding: 3px 0; }
.subtotales div span:last-child { color: #fff; }
.subtotales + .total { margin-top: 0; border-radius: 0 0 10px 10px; border-top: 0; }
.item-cuerpo { flex: 1; min-width: 0; }
.item-desc { color: #e4e4e4; font-size: 15px; line-height: 1.65; margin: 0 0 8px; }
.item-precio { font-family: Montserrat; color: #fff; font-size: 17px; font-weight: 600; }

.total { display: flex; align-items: center; justify-content: space-between; margin-top: 22px; padding: 20px; background: #111; border: 1px solid #1e1e1e; border-radius: 10px; }
.total span { font-family: Montserrat; font-size: 15px; color: #b8b8b8; }
.total strong { font-family: Montserrat; font-size: 27px; color: #fff; }

.obs { margin-top: 22px; padding: 16px 18px; background: #101010; border-left: 3px solid #0000ff; border-radius: 0 8px 8px 0; font-size: 14.5px; line-height: 1.8; }

.acciones { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 30px; }
.acciones a, .acciones button { flex: 1; min-width: 190px; padding: 15px; border: 0; border-radius: 8px; font-family: Montserrat; font-size: 14.5px; font-weight: 600; text-align: center; text-decoration: none; cursor: pointer; }
.wa { background: #25d366; color: #fff; }
.tel { background: transparent; border: 1px solid #333 !important; color: #d8d8d8; }
.imprimir { background: #0000ff; color: #fff; }

.pie { margin-top: 34px; padding-top: 20px; border-top: 1px solid #1e1e1e; font-size: 12.5px; color: #6a6a6a; line-height: 1.8; text-align: center; }

.lupa { position: fixed; inset: 0; background: rgba(0,0,0,.94); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 50; cursor: zoom-out; }
.lupa img { max-width: 100%; max-height: 100%; border-radius: 6px; }
.lupa.abierta { display: flex; }

@media (max-width: 560px) {
    .cab { flex-direction: column; align-items: flex-start; }
    .cab .meta { text-align: left; }
    .item { flex-wrap: wrap; }
    .item-foto { flex-basis: 100%; }
    .item-foto img { width: calc(50% - 3px); height: 160px; }
    .item-foto img:only-child { width: 100%; height: 210px; }
}

/* Version para imprimir o guardar como PDF */
@media print {
    body { background: #fff; color: #000; }
    .hoja { max-width: none; padding: 0; }
    .acciones, .lupa { display: none !important; }
    h1, .total strong, .total span, .datos b, .item-desc, .item-precio { color: #000; }
    .datos, .total, .obs { background: #f4f4f4; border-color: #ddd; }
    .item-n { background: #000; }
    .item-foto img { border-color: #ccc; }
    .cab, .item, .pie { border-color: #ddd; }
}
</style>
</head>
<body>

<div class="hoja">

    <div class="cab">
        <img src="../img/Logo bigcleans.png" alt="<?= EMPRESA ?>" />
        <div class="meta">
            N° <?= $e(strtoupper(substr($id, 0, 6))) ?><br />
            <?= $fecha->format('d/m/Y') ?><br />
            Válida hasta el <?= $vence->format('d/m/Y') ?>
            <?php if ($corregida): ?><br /><span class="corregida">Actualizada el <?= $corregida->format('d/m/Y H:i') ?></span><?php endif; ?>
        </div>
    </div>

    <h1>Cotización de reparaciones</h1>
    <p class="sub">Detalle de los trabajos detectados durante la visita.</p>

    <?php if ($c['cliente'] || $c['direccion']): ?>
    <div class="datos">
        <?php if ($c['cliente']): ?><div><b>Cliente:</b> <?= $e($c['cliente']) ?></div><?php endif; ?>
        <?php if ($c['direccion']): ?><div><b>Dirección:</b> <?= $e($c['direccion']) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($c['items'] as $i => $item): ?>
    <div class="item">
        <div class="item-n"><?= $i + 1 ?></div>
        <?php $fotos = fotos_de($item); if (count($fotos)): ?>
        <div class="item-foto">
            <?php foreach ($fotos as $foto): ?>
            <img loading="lazy" src="fotos/<?= $e($id) ?>/<?= $e($foto) ?>" alt="Reparación <?= $i + 1 ?>" />
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="item-cuerpo">
            <p class="item-desc"><?= nl2br($e($item['descripcion'])) ?></p>
            <div class="item-precio"><?= pesos($item['precio']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php list($neto, $iva, $tot) = desglose((int)$c['total']); ?>
    <?php if ($iva > 0): ?>
    <div class="subtotales">
        <div><span>Neto</span><span><?= pesos($neto) ?></span></div>
        <div><span>IVA (<?= IVA_PORCENTAJE ?>%)</span><span><?= pesos($iva) ?></span></div>
    </div>
    <?php endif; ?>
    <div class="total">
        <span>Total</span>
        <strong><?= pesos($tot) ?></strong>
    </div>

    <?php if ($c['observaciones']): ?>
    <div class="obs"><?= nl2br($e($c['observaciones'])) ?></div>
    <?php endif; ?>

    <div class="acciones">
        <a class="wa" href="https://wa.me/<?= TELEFONO_WA ?>?text=<?= rawurlencode('Hola! Es por la cotización N° ' . strtoupper(substr($id, 0, 6))) ?>" target="_blank" rel="noopener">Responder por WhatsApp</a>
        <a class="tel" href="tel:<?= str_replace(' ', '', TELEFONO) ?>">Llamar</a>
        <button class="imprimir" type="button" onclick="window.print()">Guardar como PDF</button>
    </div>

    <div class="pie">
        <?= EMPRESA ?> · <?= TELEFONO ?> · <?= CORREO_OFICINA ?><br />
        Valores en pesos chilenos. Cotización válida por <?= VALIDEZ_DIAS ?> días.
    </div>

</div>

<div class="lupa" id="lupa"><img alt="" /></div>

<script>
    // Tocar una foto la abre en grande: en el celular las miniaturas no se ven
    var lupa = document.getElementById('lupa');
    document.querySelectorAll('.item-foto img').forEach(function (img) {
        img.addEventListener('click', function () {
            lupa.querySelector('img').src = img.src;
            lupa.classList.add('abierta');
        });
    });
    lupa.addEventListener('click', function () { lupa.classList.remove('abierta'); });
</script>

</body>
</html>
