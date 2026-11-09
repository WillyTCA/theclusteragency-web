# URL de pruebas y salida a producción

## Ahora (sin afectar a WordPress)
La web nueva vive en una carpeta aparte: **https://theclusteragency.com/nueva/** (`public_html/nueva/`).
No hace falta subdominio. Como la carpeta existe físicamente, WordPress no la procesa.
Antes de la primera subida: confirmar que no existe en WordPress una página con slug `nueva`
(en el sitemap de páginas actual no existe).

1. Variables en `~/.zshrc`: `TCA_SSH_USER` (la misma de la suite). La clave es la misma `~/.ssh/tca_deploy`.
2. `./deploy.sh` (build + subida + comprobaciones).
3. `./deploy.sh usuario Willy` (crea el acceso al panel; `/nueva/admin/`).
4. Opcional: contraseña de navegador para toda la carpeta (ver bloque comentado de `public/.htaccess`).

Protección contra indexado: cabecera `X-Robots-Tag: noindex`, `<meta robots>` y el panel con `noindex`.
(El `robots.txt` de la raíz es de WordPress y no se toca.)

## Publicar artículos (sin tocar código)
`/nueva/admin/` → Nuevo artículo → título, URL, categoría, fecha, descripción, foto destacada y HTML → Publicar.
Aparece en `/nueva/<categoria>/<slug>/` y en `/nueva/blog/`. No hace falta desplegar.

## Día de salida (solo con orden de Willy)
1. Inventario `docs/urls-*.txt` cubierto: cada URL existe o tiene 301 (ver `docs/htaccess-produccion.md`).
2. Traer los artículos nuevos que se hayan publicado en WordPress entretanto.
3. `noindex = false`, quitar `base` y `RewriteBase /nueva/` / `X-Robots-Tag`, `site` correcto.
4. Copia completa de WordPress (archivos + BD) antes de cambiar nada.
5. Mover la web a la raíz según el plan de `docs/htaccess-produccion.md`; vigilar Search Console y 404.
