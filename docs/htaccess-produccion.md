# .htaccess de producción (borrador, NO aplicar hasta el día de salida)

Hoy WordPress sirve en la raíz estas URLs (ver `docs/urls-*.txt`):

- Páginas: `/`, `/blog/`, `/desarrollo-web/`, `/desarrollo-magento/`, `/agencia-*/`, `/seo-geo-en-mexico/`, `/auditoria-gratuita/`, `/aviso-de-privacidad/`...
- Artículos: `/<categoria>/<slug>/` (68 en `noticias-agencia`, 63 en `marketing-online`, 12 en `redes-sociales`...)
- Clientes: `/clientes/<slug>/` · Categorías: `/categoria/<slug>/` · Portfolio: `/portfolio-category/<slug>/`

## Plan
1. Mantener las mismas URLs: el blog nuevo ya usa `/<categoria>/<slug>/`, solo cambia el prefijo `/nueva`.
2. Para cada URL de `docs/urls-*.txt` sin equivalente exacto, una línea `Redirect 301 /vieja/ /nueva-url/`.
3. Conservar `/wp-content/uploads/...` mientras haya páginas externas enlazándolas, o redirigir a las fotos nuevas.
4. Feeds (`/feed/`) y `sitemap.xml`: mantener el sitemap con las mismas URLs.

Este archivo se completa cuando se migre cada sección; no copiar a producción sin revisar.
