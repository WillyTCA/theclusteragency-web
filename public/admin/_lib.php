<?php
/**
 * The Cluster Agency · web nueva — librería del backend del blog.
 * Independiente de la suite. Nada de lo que guarda vive dentro de la web salvo las fotos:
 *   ~/web-data/articulos/*.json   artículos        ~/web-data/usuarios.json   usuarios (bcrypt)
 *   ~/web-data/papelera/          borrados         ~/web-data/sesiones/       sesiones
 *   ~/web-data/intentos.json      límite de intentos
 */
declare(strict_types=1);

const WEB_INTENTOS = 3;          // fallos por IP antes de bloquear
const WEB_BLOQUEO  = 900;        // 15 min
const WEB_MAX_FOTO = 6 * 1024 * 1024;

function web_home(): string {
    $h = getenv('HOME');
    if (!$h && function_exists('posix_getpwuid')) { $p = posix_getpwuid(posix_getuid()); $h = $p['dir'] ?? ''; }
    return rtrim((string)$h, '/');
}
function web_cfg(): array {
    static $c = null;
    if ($c === null) { $f = __DIR__ . '/config.php'; $c = is_file($f) ? (array)(require $f) : []; }
    return $c;
}
function web_data(): string {
    $d = getenv('TCA_WEB_DATA') ?: (web_cfg()['data_dir'] ?? (web_home() . '/web-data'));
    return rtrim($d, '/');
}
function web_dir(string $sub): string {
    $d = web_data() . '/' . $sub;
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}
/** Carpeta pública de la web nueva en la URL (p. ej. /nueva). */
function web_base(): string { return rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/nueva/admin/x.php')), '/'); }
function web_origen(): string {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'theclusteragency.com');
}

/** Categorías del WordPress actual (slug => nombre). Se conservan para no romper URLs. */
function web_categorias(): array {
    return [
        'noticias-agencia' => 'Noticias de la agencia',
        'marketing-online' => 'Marketing online',
        'redes-sociales' => 'Redes sociales',
        'universos-visuales' => 'Universos visuales',
        'emprendedores' => 'Emprendedores',
        'seo' => 'SEO',
        'big-ones' => 'Big ones',
        'movil' => 'Móvil',
        'diseno-web' => 'Diseño web',
        'lesgislacion-de-internet' => 'Legislación de internet',
        'campanas-digitales' => 'Campañas digitales',
        'cine' => 'Cine',
    ];
}
function web_slug_ok(string $s): bool { return strlen($s) <= 120 && (bool)preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $s); }
function web_esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ───────────── JSON en disco ───────────── */
function web_json_leer(string $f): array { $j = json_decode((string)@file_get_contents($f), true); return is_array($j) ? $j : []; }
function web_json_guardar(string $f, array $d): bool {
    $tmp = $f . '.tmp' . getmypid();
    if (@file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
    @chmod($tmp, 0600);
    return @rename($tmp, $f);
}

/* ───────────── Sesión, usuarios, intentos ───────────── */
function web_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
function web_sesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_save_path(web_dir('sesiones'));
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['lifetime' => 0, 'path' => web_base() . '/', 'secure' => web_https(), 'httponly' => true, 'samesite' => 'Strict']);
    session_name('TCAWEB');
    session_start();
}
function web_ip(): string { return (string)($_SERVER['REMOTE_ADDR'] ?? '0'); }
function web_bloqueado(): int {
    $i = web_json_leer(web_data() . '/intentos.json')[web_ip()] ?? null;
    if ($i && ($i['n'] ?? 0) >= WEB_INTENTOS && ($i['t'] ?? 0) + WEB_BLOQUEO > time()) return (int)(($i['t'] + WEB_BLOQUEO) - time());
    return 0;
}
function web_fallo(): void {
    web_dir('');
    $f = web_data() . '/intentos.json'; $d = web_json_leer($f); $ip = web_ip();
    $n = (($d[$ip]['t'] ?? 0) + WEB_BLOQUEO > time()) ? ($d[$ip]['n'] ?? 0) + 1 : 1;
    $d[$ip] = ['n' => $n, 't' => time()];
    web_json_guardar($f, $d);
}
function web_ok_login(): void { $f = web_data() . '/intentos.json'; $d = web_json_leer($f); unset($d[web_ip()]); web_json_guardar($f, $d); }
/** Devuelve el nombre del usuario si usuario+clave son correctos. */
function web_verificar(string $u, string $clave): ?string {
    $us = web_json_leer(web_data() . '/usuarios.json');
    $hash = $us[$u]['hash'] ?? '$2y$10$usesomesillystringforsaltandthingsxxxxxxxxxxxxxxxxxxxxxxxx'; // tiempo constante
    $ok = password_verify($clave, $hash);
    return ($ok && isset($us[$u])) ? $u : null;
}
function web_csrf(): string { web_sesion(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function web_usuario(): ?string { web_sesion(); return $_SESSION['usuario'] ?? null; }
function web_json_salir(array $d, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE); exit;
}

/* ───────────── Artículos ───────────── */
function web_art_dir(): string { return web_dir('articulos'); }
function web_art_leer(string $slug): ?array {
    if (!web_slug_ok($slug)) return null;
    $f = web_art_dir() . "/$slug.json";
    return is_file($f) ? web_json_leer($f) : null;
}
function web_art_listar(bool $solo_publicados = false): array {
    $out = [];
    foreach (glob(web_art_dir() . '/*.json') ?: [] as $f) {
        $a = web_json_leer($f); if (!$a || empty($a['slug'])) continue;
        if ($solo_publicados && ($a['estado'] ?? '') !== 'publicado') continue;
        $out[] = $a;
    }
    usort($out, fn($x, $y) => strcmp($y['fecha'] ?? '', $x['fecha'] ?? '') ?: strcmp($y['slug'], $x['slug']));
    return $out;
}

/* ───────────── Limpieza del HTML del artículo ───────────── */
function web_limpiar_html(string $html): string {
    $ok = ['p','br','h2','h3','h4','h5','h6','ul','ol','li','a','strong','b','em','i','u','s','small','sup','sub','blockquote','img','figure','figcaption',
           'table','thead','tbody','tr','th','td','hr','span','div','code','pre','iframe'];
    $quitar = ['script','style','object','embed','form','input','button','textarea','select','link','meta','base','noscript','svg','math','template','frame','frameset'];
    $attrs = ['*' => ['class','id'], 'a' => ['href','title','target','rel'], 'img' => ['src','alt','width','height','loading','title'],
              'iframe' => ['src','width','height','title','allowfullscreen','loading','frameborder'], 'td' => ['colspan','rowspan'], 'th' => ['colspan','rowspan']];
    $iframes = ['www.youtube.com','www.youtube-nocookie.com','player.vimeo.com'];
    $esUrlOk = function (string $v, bool $solo_http) use ($iframes): bool {
        $v = trim(preg_replace('/[\x00-\x20]+/', '', $v));
        if ($v === '') return true;
        if (preg_match('#^(https?:)?//#i', $v) || preg_match('#^https?:#i', $v)) return true;
        if ($solo_http) return !preg_match('#^[a-z][a-z0-9+.-]*:#i', $v);
        return (bool)preg_match('#^(mailto:|tel:|\#|/|\./|\.\./|[a-z0-9_-])#i', $v) && !preg_match('#^(javascript|data|vbscript):#i', $v);
    };
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="utf-8" ?><div id="tca-raiz">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $raiz = $d->getElementById('tca-raiz') ?: $d->documentElement;
    $walk = function (DOMNode $n) use (&$walk, $ok, $quitar, $attrs, $esUrlOk, $iframes) {
        foreach (iterator_to_array($n->childNodes) as $h) {
            if ($h instanceof DOMComment) { $n->removeChild($h); continue; }
            if (!($h instanceof DOMElement)) continue;
            $t = strtolower($h->tagName);
            if (in_array($t, $quitar, true)) { $n->removeChild($h); continue; }
            $walk($h);
            if (!in_array($t, $ok, true)) { while ($h->firstChild) $n->insertBefore($h->firstChild, $h); $n->removeChild($h); continue; }
            foreach (iterator_to_array($h->attributes) as $a) {
                $nom = strtolower($a->name);
                if (!in_array($nom, array_merge($attrs['*'], $attrs[$t] ?? []), true)) { $h->removeAttribute($a->name); continue; }
                if ($nom === 'href' && !$esUrlOk($a->value, false)) $h->removeAttribute($a->name);
                if ($nom === 'src') {
                    if ($t === 'iframe') {
                        $host = strtolower((string)parse_url((strpos($a->value, '//') === 0 ? 'https:' : '') . $a->value, PHP_URL_HOST));
                        if (!in_array($host, $iframes, true)) { $n->removeChild($h); continue 2; }
                    } elseif (!$esUrlOk($a->value, true)) $h->removeAttribute($a->name);
                }
            }
            if ($t === 'a' && strtolower($h->getAttribute('target')) === '_blank') $h->setAttribute('rel', 'noopener noreferrer');
        }
    };
    $walk($raiz);
    $out = '';
    foreach ($raiz->childNodes as $c) $out .= $d->saveHTML($c);
    return trim($out);
}

/* ───────────── Plantilla (la compila Astro: plantilla-blog/index.html) ───────────── */
function web_plantilla(string $titulo, string $desc, string $canon, string $head, string $contenido): string {
    $f = dirname(__DIR__) . '/plantilla-blog/index.html';
    $t = is_file($f) ? (string)file_get_contents($f) : '';
    if ($t === '') { http_response_code(500); return '<!doctype html><meta charset="utf-8"><p>Falta plantilla-blog/index.html (build de Astro sin subir).</p>'; }
    return strtr($t, ['@@TITLE@@' => web_esc($titulo), '@@DESC@@' => web_esc($desc), '@@CANON@@' => web_esc($canon), '@@HEAD@@' => $head, '@@CONTENT@@' => $contenido]);
}
