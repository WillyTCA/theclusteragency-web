<?php
/** Un artículo: /nueva/<categoria>/<slug>/ (la reescritura de ../.htaccess llega aquí con ?c= y ?s=). */
declare(strict_types=1);
require dirname(__DIR__) . '/admin/_lib.php';
header('X-Robots-Tag: noindex, nofollow');
$base = web_base(); $cats = web_categorias();
$c = (string)($_GET['c'] ?? ''); $s = (string)($_GET['s'] ?? '');
$a = (isset($cats[$c]) && web_slug_ok($s)) ? web_art_leer($s) : null;
if (!$a || ($a['estado'] ?? '') !== 'publicado' || $a['categoria'] !== $c) {
    http_response_code(404); header('Content-Type: text/html; charset=utf-8');
    echo web_plantilla('No encontrado · The Cluster Agency', '', web_origen() . $base . '/blog/', '', '<h1>No encontrado</h1><p><a href="' . web_esc($base) . '/blog/">Ir al blog</a></p>');
    exit;
}
$url = web_origen() . $base . '/' . $c . '/' . $s . '/';
$img = !empty($a['imagen']) ? web_origen() . $base . '/uploads/' . $a['imagen'] : '';
$ld = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $a['titulo'], 'description' => $a['descripcion'] ?? '',
       'datePublished' => $a['fecha'], 'dateModified' => substr((string)($a['actualizado'] ?? $a['fecha']), 0, 10), 'mainEntityOfPage' => $url,
       'author' => ['@type' => 'Organization', 'name' => 'The Cluster Agency'],
       'publisher' => ['@type' => 'Organization', 'name' => 'The Cluster Agency']];
if ($img) $ld['image'] = $img;
$head = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
if ($img) $head .= '<meta property="og:image" content="' . web_esc($img) . '">';
$head .= '<meta property="og:title" content="' . web_esc($a['titulo']) . '"><meta property="og:type" content="article">';
$h = '<article><p><a href="' . web_esc($base) . '/blog/?cat=' . web_esc($c) . '">' . web_esc($cats[$c]) . '</a> · <time datetime="' . web_esc($a['fecha']) . '">' . web_esc($a['fecha']) . '</time></p>'
   . '<h1>' . web_esc($a['titulo']) . '</h1>'
   . ($img ? '<img src="' . web_esc($img) . '" alt="" style="max-width:100%;height:auto">' : '')
   . $a['html'] . '</article>';
header('Content-Type: text/html; charset=utf-8');
echo web_plantilla($a['titulo'] . ' | The Cluster Agency', $a['descripcion'] ?? '', $url, $head, $h);
