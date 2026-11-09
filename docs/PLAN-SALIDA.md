# URL de pruebas y salida a producción

## Ahora (sin afectar a WordPress)
1. Crear el subdominio `nueva.theclusteragency.com` en el hosting, apuntando a una carpeta
   aparte (p. ej. `public_html/nueva`). No toca el dominio principal.
2. Crear la clave: `htpasswd -c ~/.htpasswds/web-nueva Cluster` y poner la ruta absoluta en
   `public/.htaccess`.
3. Definir `TCA_SSH_USER`, `TCA_SSH_HOST`, `TCA_WEB_DIR` y subir con `./deploy.sh`.
Protección triple contra indexado: Basic Auth, `X-Robots-Tag`, `<meta robots>` y `robots.txt`.

## Día de salida (solo con orden de Willy)
1. Inventario `docs/urls-*.txt` cubierto: cada URL existe o tiene 301.
2. `noindex = false`, `site` en `astro.config.mjs` a `https://theclusteragency.com`, quitar
   Basic Auth y sustituir `robots.txt` (conservar `Sitemap:`).
3. Sitemap con las mismas URLs; mismos títulos y descripciones.
4. Copia completa de WordPress (archivos + BD) antes de cambiar el destino del dominio.
5. Cambiar el destino del dominio; vigilar Search Console y 404 los primeros días.
