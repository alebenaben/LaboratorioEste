<?php
/** Página sin menú (ingreso, errores sin sesión). */
?><!doctype html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Integral del Este</title>
<link rel="icon" href="/assets/logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap">
<link rel="stylesheet" href="/assets/app.css?v=<?= \App\Domain\Version::actual()['v'] ?>">
<link rel="stylesheet" href="/assets/extra.css?v=<?= \App\Domain\Version::actual()['v'] ?>">
</head>
<body>
<?= $contenido ?>
<div id="layer2"></div>
<script src="/assets/app.js?v=<?= \App\Domain\Version::actual()['v'] ?>"></script>
</body>
</html>
