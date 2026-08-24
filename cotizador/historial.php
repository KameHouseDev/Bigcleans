<?php
/**
 * Listado de cotizaciones. Sin esta pantalla, una cotizacion creada solo se
 * puede recuperar por su enlace: si se cierra WhatsApp sin enviarlo, queda
 * perdida entre los archivos.
 */
require __DIR__ . '/arranque.php';

iniciar_sesion();

if (!autorizado()) {
    header('Location: index.php');
    exit;
}

// Borrado. Va por POST y con testigo de sesion: por GET, cualquier
// precarga del navegador o un enlace compartido podria borrar sin querer.
if (!empty($_POST['borrar'])) {
    $id = (string)$_POST['borrar'];
    $testigo = (string)($_POST['testigo'] ?? '');

    if (id_valido($id) && hash_equals($_SESSION['testigo'] ?? '', $testigo)) {
        @unlink(DIR_DATOS . '/' . $id . '.json');
        $dirFotos = DIR_FOTOS . '/' . $id;
        if (is_dir($dirFotos)) {
            foreach (glob($dirFotos . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dirFotos);
        }
    }
    header('Location: historial.php' . (!empty($_POST['q']) ? '?q=' . urlencode($_POST['q']) : ''));
    exit;
}

if (empty($_SESSION['testigo'])) {
    $_SESSION['testigo'] = bin2hex(random_bytes(16));
}

// Borrado automatico, si esta configurado en config.php
if (defined('BORRAR_TRAS_MESES') && BORRAR_TRAS_MESES > 0) {
    $corte = strtotime('-' . BORRAR_TRAS_MESES . ' months');
    foreach (glob(DIR_DATOS . '/*.json') ?: [] as $f) {
        $c = json_decode(@file_get_contents($f), true);
        if (!is_array($c) || empty($c['fecha']) || strtotime($c['fecha']) >= $corte) continue;
        @unlink($f);
        $dir = DIR_FOTOS . '/' . $c['id'];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $x) @unlink($x);
            @rmdir($dir);
        }
    }
}

$cots = [];
foreach (glob(DIR_DATOS . '/*.json') ?: [] as $f) {
    $c = json_decode(@file_get_contents($f), true);
    if (is_array($c) && !empty($c['id'])) $cots[] = $c;
}

// Por defecto solo el ultimo mes: con el tiempo la lista se vuelve larga y lo
// que se busca casi siempre es reciente. Nada se borra, solo se oculta.
$todas = !empty($_GET['todas']);
$antiguas = 0;
if (!$todas && HISTORIAL_DIAS > 0) {
    $corte = strtotime('-' . HISTORIAL_DIAS . ' days');
    $antes = count($cots);
    $cots = array_values(array_filter($cots, function ($c) use ($corte) {
        return strtotime($c['fecha'] ?? '') >= $corte;
    }));
    $antiguas = $antes - count($cots);
}
usort($cots, fn($a, $b) => strcmp($b['fecha'] ?? '', $a['fecha'] ?? ''));

$buscar = trim((string)($_GET['q'] ?? ''));
if ($buscar !== '') {
    $cots = array_filter($cots, function ($c) use ($buscar) {
        $heno = mb_strtolower(($c['cliente'] ?? '') . ' ' . ($c['direccion'] ?? '') . ' ' . ($c['telefono'] ?? ''));
        return mb_strpos($heno, mb_strtolower($buscar)) !== false;
    });
}

$suma = array_sum(array_column($cots, 'total'));
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Historial | <?= EMPRESA ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="robots" content="noindex, nofollow" />
<meta name="theme-color" content="#0000ff" />
<link rel="icon" href="../img/favicon_bigcleans.png?v=2" />
<link href="https://fonts.googleapis.com/css?family=Montserrat:600,700|Open+Sans:400,600" rel="stylesheet" />
<link rel="stylesheet" href="cotizador.css?v=6" />
</head>
<body>

<header class="barra">
    <img src="../img/Logo bigcleans.png?v=2" alt="<?= EMPRESA ?>" />
    <span>Historial</span>
    <a class="salir" href="index.php">Nueva</a>
</header>

<main class="app">

    <form class="buscador" method="get">
        <input type="search" name="q" value="<?= $e($buscar) ?>" placeholder="Buscar por cliente, dirección o teléfono" />
        <?php if ($buscar !== ''): ?><a class="limpiar" href="historial.php">&times;</a><?php endif; ?>
    </form>

    <p class="resumen">
        <?= count($cots) ?> cotizaci<?= count($cots) === 1 ? 'ón' : 'ones' ?> · <?= pesos($suma) ?>
        <?php if (!$todas && HISTORIAL_DIAS > 0): ?>
            · últimos <?= HISTORIAL_DIAS ?> días
        <?php endif; ?>
    </p>

    <?php if ($antiguas): ?>
        <p class="resumen"><a class="ver-todas" href="historial.php?todas=1<?= $buscar !== '' ? '&amp;q=' . urlencode($buscar) : '' ?>">Ver <?= $antiguas ?> más antigua<?= $antiguas === 1 ? '' : 's' ?></a></p>
    <?php elseif ($todas): ?>
        <p class="resumen"><a class="ver-todas" href="historial.php">Ver solo el último mes</a></p>
    <?php endif; ?>

    <?php if (!count($cots)): ?>
        <div class="tarjeta vacio">
            <p><?= $buscar !== '' ? 'No hay resultados para esa búsqueda.' : 'Todavía no hay cotizaciones.' ?></p>
            <a class="btn-agregar" href="index.php">Crear la primera</a>
        </div>
    <?php endif; ?>

    <?php foreach ($cots as $c):
        $f = new DateTime($c['fecha']);
        $fotos = [];
        // Ojo con el nombre: $f ya guarda la fecha de la cotizacion
        foreach ($c['items'] as $it) {
            foreach (fotos_de($it) as $foto) $fotos[] = $foto;
        }
        $nfotos = count($fotos);
    ?>
    <article class="fila-cot">
        <div class="fila-cab">
            <div>
                <h3><?= $e($c['cliente'] ?: '(sin nombre)') ?></h3>
                <p class="fila-dir"><?= $e($c['direccion'] ?: 'sin dirección') ?></p>
            </div>
            <strong class="fila-total"><?= pesos($c['total']) ?></strong>
        </div>
        <p class="fila-meta">
            <?= $f->format('d/m/Y H:i') ?>
            · <?= count($c['items']) ?> reparaci<?= count($c['items']) === 1 ? 'ón' : 'ones' ?>
            · <?= $nfotos ?> foto<?= $nfotos === 1 ? '' : 's' ?>
            <?php if (!empty($c['enviado'])): ?><span class="tag ok">enviada</span><?php endif; ?>
            <?php if (!empty($c['actualizada'])): ?><span class="tag">corregida</span><?php endif; ?>
        </p>
        <?php if ($nfotos): ?>
        <div class="fila-fotos">
            <?php foreach (array_slice($fotos, 0, 4) as $miniatura): ?>
                <img loading="lazy" src="fotos/<?= $e($c['id']) ?>/<?= $e(ruta_miniatura($c['id'], $miniatura)) ?>" alt="" />
            <?php endforeach; ?>
            <?php if ($nfotos > 4): ?><span class="mas">+<?= $nfotos - 4 ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="fila-acciones">
            <a href="ver.php?id=<?= $e($c['id']) ?>" target="_blank" rel="noopener">Ver</a>
            <a href="index.php?editar=<?= $e($c['id']) ?>">Corregir</a>
            <?php if (!empty($c['telefono'])): ?>
                <a href="https://wa.me/<?= $e(preg_replace('/\D/', '', $c['telefono'])) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif; ?>
            <form method="post" class="borrar-form"
                  onsubmit="return confirm('¿Borrar esta cotización y sus fotos? No se puede deshacer.');">
                <input type="hidden" name="borrar" value="<?= $e($c['id']) ?>" />
                <input type="hidden" name="testigo" value="<?= $e($_SESSION['testigo']) ?>" />
                <input type="hidden" name="q" value="<?= $e($buscar) ?>" />
                <button type="submit" title="Borrar">Borrar</button>
            </form>
        </div>
    </article>
    <?php endforeach; ?>

    <div class="espaciador"></div>
</main>

</body>
</html>
