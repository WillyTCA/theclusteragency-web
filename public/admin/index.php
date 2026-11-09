<?php
/** Panel del blog: login (3 intentos, 15 min de bloqueo) y editor de artículos. */
declare(strict_types=1);
require __DIR__ . '/_lib.php';
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-src 'self'; base-uri 'none'; form-action 'self'");
header('X-Frame-Options: SAMEORIGIN'); header('Referrer-Policy: same-origin');
web_sesion();

$msg = '';
if (($_GET['salir'] ?? '') === '1') { $_SESSION = []; session_destroy(); header('Location: ./'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !web_usuario()) {
    $espera = web_bloqueado();
    if ($espera > 0) { $msg = 'Demasiados intentos. Espera ' . ceil($espera / 60) . ' min.'; }
    else {
        $u = web_verificar(trim((string)($_POST['usuario'] ?? '')), (string)($_POST['clave'] ?? ''));
        if ($u) { web_ok_login(); session_regenerate_id(true); $_SESSION['usuario'] = $u; header('Location: ./'); exit; }
        web_fallo(); $rest = WEB_INTENTOS - ((int)(web_json_leer(web_data() . '/intentos.json')[web_ip()]['n'] ?? 1));
        $msg = $rest > 0 ? "Usuario o contraseña incorrectos. Te quedan $rest intento(s)." : 'Demasiados intentos. Bloqueado 15 min.';
    }
}
$usuario = web_usuario();
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Blog · panel</title>
<style>
:root{color-scheme:light}*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,sans-serif;background:#fff;color:#111}
header{display:flex;gap:1rem;align-items:center;padding:.8rem 1rem;border-bottom:1px solid #ddd}header b{flex:1}
main{max-width:70rem;margin:0 auto;padding:1rem}input,select,textarea,button{font:inherit;padding:.5rem;border:1px solid #bbb;border-radius:6px;background:#fff;color:#111}
button{cursor:pointer;background:#111;color:#fff;border-color:#111}button.sec{background:#fff;color:#111;border-color:#bbb}button.rojo{background:#b00020;border-color:#b00020}
label{display:block;margin:.8rem 0 .2rem;font-weight:600}input[type=text],input[type=date],select,textarea{width:100%}textarea{min-height:18rem;font-family:ui-monospace,monospace;font-size:13px}
table{width:100%;border-collapse:collapse}td,th{padding:.5rem;border-bottom:1px solid #eee;text-align:left}.fila{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center}
.est{font-size:12px;padding:2px 8px;border-radius:99px;background:#eee}.est.pub{background:#d8f3dc}.err{color:#b00020}.ok{color:#116611}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:1rem}@media(max-width:800px){.cols{grid-template-columns:1fr}}iframe{width:100%;min-height:24rem;border:1px solid #ddd;border-radius:6px}
.login{max-width:22rem;margin:4rem auto}.mini{font-size:13px;color:#555}
</style></head><body>
<?php if (!$usuario): ?>
<main class="login"><h1>Panel del blog</h1>
<?php if ($msg): ?><p class="err"><?= web_esc($msg) ?></p><?php endif; ?>
<form method="post" autocomplete="on"><label for="u">Usuario</label><input id="u" name="usuario" type="text" required autofocus>
<label for="c">Contraseña</label><input id="c" name="clave" type="password" required><p><button>Entrar</button></p></form></main>
<?php else: ?>
<header><b>Blog · The Cluster Agency</b><span class="mini">Hola, <?= web_esc($usuario) ?></span><a href="?salir=1">Salir</a></header>
<main id="app"></main>
<script>
const CSRF=<?= json_encode(web_csrf()) ?>, BASE=<?= json_encode(web_base()) ?>;
const $=(s,r=document)=>r.querySelector(s);
function el(t,a={},...h){const e=document.createElement(t);for(const k in a){if(k==='class')e.className=a[k];else if(k.startsWith('on'))e.addEventListener(k.slice(2),a[k]);else e.setAttribute(k,a[k])}h.flat().forEach(x=>e.append(x));return e}
async function api(op,body,form){const o={method:body||form?'POST':'GET',headers:{'X-CSRF-Token':CSRF}};
 if(form)o.body=form;else if(body){o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)}
 const r=await fetch('api.php?op='+op,o);const j=await r.json().catch(()=>({ok:false,error:'Respuesta no válida ('+r.status+')'}));return j}
const slugify=s=>s.normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,100);
let cats={};
async function lista(){const j=await api('listar');const app=$('#app');app.replaceChildren();
 if(!j.ok){app.append(el('p',{class:'err'},j.error));return}
 cats=j.categorias;
 app.append(el('div',{class:'fila'},el('button',{onclick:()=>editor(null)},'+ Nuevo artículo'),el('span',{class:'mini'},j.articulos.length+' artículos')));
 const t=el('table',{},el('tr',{},...['Título','Categoría','Fecha','Estado',''].map(x=>el('th',{},x))));
 j.articulos.forEach(a=>t.append(el('tr',{},el('td',{},a.titulo),el('td',{},cats[a.categoria]||a.categoria),el('td',{},a.fecha||''),
  el('td',{},el('span',{class:'est'+(a.estado==='publicado'?' pub':'')},a.estado)),
  el('td',{},el('button',{class:'sec',onclick:()=>editor(a.slug)},'Editar')))));
 app.append(t)}
async function editor(slug){let a={slug:'',titulo:'',categoria:Object.keys(cats)[0],descripcion:'',imagen:'',html:'',fecha:new Date().toISOString().slice(0,10),estado:'borrador'};
 if(slug){const j=await api('leer&slug='+encodeURIComponent(slug));if(!j.ok){alert(j.error);return}a=j.articulo}
 const orig=slug||'';const app=$('#app');app.replaceChildren();
 const f={};const campo=(n,l,node)=>{f[n]=node;return el('div',{},el('label',{for:'f_'+n},l),node)};
 const tit=el('input',{type:'text',id:'f_titulo',maxlength:200});tit.value=a.titulo;
 const sl=el('input',{type:'text',id:'f_slug',maxlength:120});sl.value=a.slug;let tocado=!!slug;
 sl.addEventListener('input',()=>tocado=true);tit.addEventListener('input',()=>{if(!tocado)sl.value=slugify(tit.value)});
 const cat=el('select',{id:'f_cat'});Object.entries(cats).forEach(([k,v])=>{const o=el('option',{value:k},v);if(k===a.categoria)o.selected=true;cat.append(o)});
 const fe=el('input',{type:'date',id:'f_fecha'});fe.value=a.fecha;
 const de=el('input',{type:'text',id:'f_desc',maxlength:300});de.value=a.descripcion;
 const im=el('input',{type:'text',id:'f_img',readonly:'readonly',placeholder:'Sin foto destacada'});im.value=a.imagen;
 const prev=el('img',{alt:'',style:'max-height:8rem;display:block;margin:.4rem 0'});const pimg=()=>{prev.style.display=im.value?'block':'none';if(im.value)prev.src=BASE+'/uploads/'+im.value};pimg();
 const file=el('input',{type:'file',accept:'image/jpeg,image/png,image/webp,image/gif'});
 const st=el('p',{class:'mini'});
 const subir=async(cb)=>{if(!file.files[0])return;st.textContent='Subiendo…';const fd=new FormData();fd.append('foto',file.files[0]);const j=await api('subir',null,fd);
  if(!j.ok){st.className='err';st.textContent=j.error;return}st.className='ok';st.textContent='Foto subida';cb(j);file.value=''};
 const ta=el('textarea',{id:'f_html',spellcheck:'false'});ta.value=a.html;
 const pv=el('iframe',{sandbox:'',title:'Vista previa'});const actualizar=()=>{pv.srcdoc='<meta charset=utf-8><style>body{font:16px/1.6 system-ui;max-width:42rem;margin:1rem auto;padding:0 1rem}img{max-width:100%}</style>'+ta.value.replace(/<script[\s\S]*?<\/script>/gi,'')};
 ta.addEventListener('input',actualizar);actualizar();
 const guardar=async(estado)=>{st.className='mini';st.textContent='Guardando…';
  const j=await api('guardar',{original:orig,slug:sl.value.trim(),titulo:tit.value,categoria:cat.value,fecha:fe.value,descripcion:de.value,imagen:im.value,html:ta.value,estado});
  if(!j.ok){st.className='err';st.textContent=j.error;return}st.className='ok';
  st.replaceChildren(estado==='publicado'?'Publicado: ':'Borrador guardado. ',el('a',{href:j.url,target:'_blank',rel:'noopener'},j.url));a=j.articulo;
  sl.value=a.slug;ta.value=a.html;actualizar();return j};
 app.append(el('p',{},el('button',{class:'sec',onclick:lista},'← Volver')),
  campo('titulo','Título',tit),campo('slug','URL (slug) — theclusteragency.com/…/categoria/slug/',sl),
  el('div',{class:'cols'},campo('cat','Categoría',cat),campo('fecha','Fecha',fe)),
  campo('desc','Descripción (meta, ~155 caracteres)',de),
  el('label',{},'Foto destacada'),im,prev,el('div',{class:'fila'},file,el('button',{class:'sec',onclick:()=>subir(j=>{im.value=j.ruta;pimg()})},'Subir como destacada'),
   el('button',{class:'sec',onclick:()=>subir(j=>{const p=ta.selectionStart;ta.setRangeText('<img src="'+j.url+'" alt="">',p,p,'end');actualizar()})},'Subir e insertar en el texto')),st,
  el('div',{class:'cols'},el('div',{},el('label',{for:'f_html'},'Contenido (HTML)'),ta),el('div',{},el('label',{},'Vista previa'),pv)),
  el('p',{class:'mini'},'Se permiten: p, h2–h6, listas, enlaces, imágenes, tablas, citas y vídeos de YouTube/Vimeo. Cualquier script o atributo peligroso se elimina al guardar.'),
  el('div',{class:'fila'},el('button',{onclick:()=>guardar('publicado')},'Publicar'),el('button',{class:'sec',onclick:()=>guardar('borrador')},'Guardar borrador'),
   slug?el('button',{class:'rojo',onclick:async()=>{if(!confirm('¿Mover a la papelera?'))return;const j=await api('borrar',{slug:orig});j.ok?lista():alert(j.error)}},'Borrar'):''))}
lista();
</script>
<?php endif; ?>
</body></html>
