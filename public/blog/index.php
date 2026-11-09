<?php
/** Listado del blog: /nueva/blog/ y /nueva/blog/?cat=slug&p=2 — lee los artículos publicados. */
declare(strict_types=1);
require dirname(__DIR__) . '/admin/_lib.php';
header('X-Robots-Tag: noindex, nofollow');
$base = web_base(); $cats = web_categorias();
$cat = (string)($_GET['cat'] ?? ''); if (!isset($cats[$cat])) $cat = '';
$todos = array_values(array_filter(web_art_listar(true), fn($a) => $cat === '' || $a['categoria'] === $cat));
$por = 12; $pag = max(1, (int)($_GET['p'] ?? 1)); $paginas = max(1, (int)ceil(count($todos) / $por));
if ($pag > $paginas) $pag = $paginas;
$h = '<h1>Blog</h1><nav aria-label="Categorías"><a href="' . web_esc($base) . '/blog/">Todas</a>';
foreach ($cats as $k => $v) $h .= ' · <a href="' . web_esc($base . '/blog/?cat=' . $k) . '">' . web_esc($v) . '</a>';
$h .= '</nav>';
if (!$todos) $h .= '<p>Aún no hay artículos publicados.</p>';
foreach (array_slice($todos, ($pag - 1) * $por, $por) as $a) {
    $u = web_esc($base . '/' . $a['categoria'] . '/' . $a['slug'] . '/');
    $h .= '<article style="margin:1.5rem 0">';
    if (!empty($a['imagen'])) $h .= '<a href="' . $u . '"><img src="' . web_esc($base . '/uploads/' . $a['imagen']) . '" alt="" loading="lazy" style="max-width:100%;height:auto"></a>';
    $h .= '<h2><a href="' . $u . '">' . web_esc($a['titulo']) . '</a></h2><p>' . web_esc($a['descripcion'] ?? '') . '</p></article>';
}
if ($paginas > 1) {
    $h .= '<nav aria-label="Páginas">';
    for ($i = 1; $i <= $paginas; $i++) $h .= ($i === $pag ? "<strong>$i</strong> " : '<a href="' . web_esc($base . '/blog/?' . http_build_query(array_filter(['cat' => $cat, 'p' => $i > 1 ? $i : null]))) . "\">$i</a> ");
    $h .= '</nav>';
}
header('Content-Type: text/html; charset=utf-8');
echo web_plantilla('Blog · The Cluster Agency', 'Artículos de marketing digital, redes sociales, SEO y desarrollo web.', web_origen() . $base . '/blog/', '', $h);
