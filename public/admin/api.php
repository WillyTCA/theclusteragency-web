<?php
/** API del panel del blog. Solo con sesión; las escrituras exigen el token CSRF. */
declare(strict_types=1);
require __DIR__ . '/_lib.php';

if (!web_usuario()) web_json_salir(['ok' => false, 'error' => 'Sin sesión'], 401);
$op = (string)($_GET['op'] ?? '');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post) {
    $tok = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(web_csrf(), $tok)) web_json_salir(['ok' => false, 'error' => 'Token inválido, recarga la página'], 403);
}
$cuerpo = $post && stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'json') !== false ? (json_decode((string)file_get_contents('php://input'), true) ?: []) : [];
$cats = web_categorias();

switch ($op) {
case 'listar':
    $l = array_map(fn($a) => array_intersect_key($a, array_flip(['slug','titulo','categoria','estado','fecha','actualizado'])), web_art_listar());
    web_json_salir(['ok' => true, 'articulos' => $l, 'categorias' => $cats]);

case 'leer':
    $a = web_art_leer((string)($_GET['slug'] ?? ''));
    $a ? web_json_salir(['ok' => true, 'articulo' => $a]) : web_json_salir(['ok' => false, 'error' => 'No existe'], 404);

case 'guardar':
    if (!$post) web_json_salir(['ok' => false, 'error' => 'POST'], 405);
    $slug = trim((string)($cuerpo['slug'] ?? ''));
    $orig = trim((string)($cuerpo['original'] ?? ''));
    $titulo = trim((string)($cuerpo['titulo'] ?? ''));
    $cat = (string)($cuerpo['categoria'] ?? '');
    $estado = ($cuerpo['estado'] ?? '') === 'publicado' ? 'publicado' : 'borrador';
    $fecha = (string)($cuerpo['fecha'] ?? '');
    if (!web_slug_ok($slug)) web_json_salir(['ok' => false, 'error' => 'URL (slug) no válida: solo minúsculas, números y guiones'], 400);
    if ($titulo === '' || mb_strlen($titulo) > 200) web_json_salir(['ok' => false, 'error' => 'Falta el título (máx. 200)'], 400);
    if (!isset($cats[$cat])) web_json_salir(['ok' => false, 'error' => 'Categoría no válida'], 400);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !checkdate((int)substr($fecha, 5, 2), (int)substr($fecha, 8, 2), (int)substr($fecha, 0, 4))) $fecha = date('Y-m-d');
    $desc = mb_substr(trim((string)($cuerpo['descripcion'] ?? '')), 0, 300);
    $img = trim((string)($cuerpo['imagen'] ?? ''));
    if ($img !== '' && (strpos($img, '..') !== false || !preg_match('#^[A-Za-z0-9/_.-]+\.(jpg|jpeg|png|webp|gif)$#', $img))) web_json_salir(['ok' => false, 'error' => 'Imagen no válida: usa el botón Subir'], 400);
    $html = web_limpiar_html((string)($cuerpo['html'] ?? ''));
    if ($estado === 'publicado' && ($html === '' || $desc === '')) web_json_salir(['ok' => false, 'error' => 'Para publicar hacen falta descripción y contenido'], 400);
    $dest = web_art_dir() . "/$slug.json";
    if ($orig !== '' && !web_slug_ok($orig)) web_json_salir(['ok' => false, 'error' => 'Original no válido'], 400);
    if (($orig === '' || $orig !== $slug) && is_file($dest)) web_json_salir(['ok' => false, 'error' => 'Ya existe un artículo con esa URL'], 409);
    $art = ['slug' => $slug, 'titulo' => $titulo, 'categoria' => $cat, 'descripcion' => $desc, 'imagen' => $img, 'html' => $html,
            'fecha' => $fecha, 'estado' => $estado, 'autor' => web_usuario(), 'actualizado' => date('c')];
    if (!web_json_guardar($dest, $art)) web_json_salir(['ok' => false, 'error' => 'No se pudo guardar'], 500);
    if ($orig !== '' && $orig !== $slug && is_file(web_art_dir() . "/$orig.json")) @rename(web_art_dir() . "/$orig.json", web_dir('papelera') . "/$orig-renombrado-" . time() . '.json');
    $url = web_base() . "/$cat/$slug/";
    web_json_salir(['ok' => true, 'articulo' => $art, 'url' => $url]);

case 'borrar':
    if (!$post) web_json_salir(['ok' => false, 'error' => 'POST'], 405);
    $slug = (string)($cuerpo['slug'] ?? '');
    if (!web_slug_ok($slug) || !is_file(web_art_dir() . "/$slug.json")) web_json_salir(['ok' => false, 'error' => 'No existe'], 404);
    @rename(web_art_dir() . "/$slug.json", web_dir('papelera') . "/$slug-" . time() . '.json');
    web_json_salir(['ok' => true]);

case 'subir':
    if (!$post || empty($_FILES['foto'])) web_json_salir(['ok' => false, 'error' => 'Falta la foto'], 400);
    $f = $_FILES['foto'];
    if ($f['error'] !== UPLOAD_ERR_OK) web_json_salir(['ok' => false, 'error' => 'Error al recibir la foto (código ' . $f['error'] . '; el servidor limita el tamaño)'], 400);
    if ($f['size'] > WEB_MAX_FOTO) web_json_salir(['ok' => false, 'error' => 'La foto pesa más de 6 MB: comprímela'], 400);
    $mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($mimes[$mime]) || @getimagesize($f['tmp_name']) === false) web_json_salir(['ok' => false, 'error' => 'Solo JPG, PNG, WebP o GIF'], 400);
    $rel = date('Y') . '/' . date('m');
    $dir = dirname(__DIR__) . "/uploads/$rel";
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) web_json_salir(['ok' => false, 'error' => 'No se pudo crear la carpeta de fotos'], 500);
    $nom = bin2hex(random_bytes(8)) . '.' . $mimes[$mime];
    if (!move_uploaded_file($f['tmp_name'], "$dir/$nom")) web_json_salir(['ok' => false, 'error' => 'No se pudo guardar la foto'], 500);
    @chmod("$dir/$nom", 0644);
    web_json_salir(['ok' => true, 'ruta' => "$rel/$nom", 'url' => web_base() . "/uploads/$rel/$nom"]);

default:
    web_json_salir(['ok' => false, 'error' => 'Operación desconocida'], 400);
}
