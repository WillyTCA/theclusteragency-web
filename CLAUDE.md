# The Cluster Agency · web nueva — instrucciones de trabajo

Proyecto APARTE de `cluster-ops-suite`. Aquí no aplican las reglas, auditorías ni el
despliegue de la suite, y no se toca nada de ella.
Léelo entero antes de tocar nada.

## Qué es esto

La web nueva de theclusteragency.com. Hoy el dominio es **WordPress** (tema Kenozoik,
WPBakery, AIOSEO) y **sigue siéndolo hasta el día de salida**. Esta web se construye en una
carpeta aparte del hosting, **`https://theclusteragency.com/nueva/`** (sin subdominio), con `noindex`. WordPress ignora esa carpeta
porque existe físicamente; el `.htaccess` de `/nueva/` solo vale dentro de `/nueva/`.

Stack: **Astro 5** (estático, sin servidor), blog en Markdown (`src/content/blog/`),
GSAP + Lenis para los efectos. Se sube por SFTP (hosting PHP, sin Node).

**Blog con panel propio (PHP, sin build):** `/nueva/admin/` (login 3 intentos / 15 min, CSRF, HTML limpiado, fotos validadas).
Los artículos viven FUERA de la web, en `~/web-data/articulos/*.json`, y se pintan con la plantilla compilada por Astro
(`src/pages/plantilla-blog.astro` → `plantilla-blog/index.html`, marcadores `@@TITLE@@ @@DESC@@ @@CANON@@ @@HEAD@@ @@CONTENT@@`).
Si cambias esos marcadores, cambia también `public/admin/_lib.php` (`web_plantilla`). Publicar un artículo NO requiere deploy.
Usuarios: `./deploy.sh usuario NOMBRE`. Las fotos van a `nueva/uploads/` y `deploy.sh` nunca las toca ni las borra.

## Reglas que no se saltan

**1. No tocar WordPress ni el dominio principal sin orden explícita de Willy.**
Nada de DNS, plugins, ajustes ni contenido del sitio actual. El cambio de destino del dominio
es el día de salida y lo decide Willy.

**2. No perder SEO.** `docs/urls-*.txt` es el inventario de URLs actuales (blog, páginas,
clientes, categorías). Cada URL actual o se conserva idéntica o tiene redirección 301 en
`.htaccess` (plantilla de producción en `docs/htaccess-produccion.md`). El sitemap, los
títulos y las descripciones se llevan tal cual.

**3. Mide, no supongas.** Si dices "probado", aclara cómo (build local, navegador
headless, móvil real...). Un build que pasa no prueba el despliegue real.

**4. FAQ con schema siempre.** El componente de FAQ pinta las preguntas y genera el
JSON-LD `FAQPage` desde los MISMOS datos. La respuesta va en el HTML aunque el acordeón esté
cerrado. Nunca escribir el schema a mano aparte.

**5. Si pides algo y no lo haces, dilo.** Al final de cada tanda: qué se hizo, qué no, y qué
tiene que probar Willy.

**6. Sube la versión en cada cambio.** `VERSION` en `src/config.ts`, formato `AAAA-MM-DD.N`.
Se muestra en el pie de la web. Sin esto nadie sabe si el servidor tiene lo último.

**7. No desplegar sin que Willy lo diga.** `./deploy.sh` (SFTP, mismo método que la suite) sube cada archivo a un temporal,
verifica el SHA-256, guarda lo anterior en `~/deploy-prev-web/` fuera de la web y `./deploy.sh volver` lo deshace.
No sube `admin/config.php`, no borra `uploads/` ni `~/web-data/`. Variables: ver cabecera del script.

**8. Accesibilidad y rendimiento.** Respetar `prefers-reduced-motion`; menú y FAQ con teclado;
el sonido del menú nunca suena solo (solo tras clic, con botón de silencio, apagado en
móvil); vídeos comprimidos y con carga diferida. Inspirarse en otras webs, nunca copiar su
código, tipografías ni vídeos.

**9. Secretos fuera de git.** Claves, contraseñas y `.env` no se suben. Las contraseñas del panel se piden por teclado y se guardan con hash.

## Banderas de funciones (`src/config.ts`)

- `mostrarCasos = false`: carrusel de casos de éxito, construido pero oculto hasta tener los vídeos.
- `noindex = true`: mientras sea URL de pruebas. Se pone en `false` SOLO el día de salida.

## Cómo trabajar

```bash
npm install
npm run dev      # local
npm run build    # genera dist/
```

Antes de entregar: `npm run build` sin errores y revisión en navegador.
El backend se prueba en local con `php -S` (login, bloqueo, guardado, sanitizado, subida, página pública); eso NO prueba
el Apache real del hosting (`.htaccess`, límites de subida de PHP): hay que comprobarlo con `./deploy.sh comprobar`.

## Estado

Ver `README.md` (qué hay hecho y qué falta).
