# theclusteragency-web

Web nueva de The Cluster Agency (Astro). Proyecto separado de `cluster-ops-suite`.

## Estado (versión en `src/config.ts`)

- [x] Inventario del sitio actual en `docs/` (155 entradas de blog, 11 páginas, 13 clientes, 12 categorías)
- [x] Esqueleto Astro, layout con `noindex`, versión visible, FAQ con schema `FAQPage`
- [x] Sitio bajo `https://theclusteragency.com/nueva/` (`base` de Astro, `.htaccess` propio, `noindex`)
- [x] `deploy.sh` (SFTP, hash por archivo, copia previa, volver, usuario, comprobar) — **sin probar contra el servidor**
- [x] Panel del blog en `/nueva/admin/` + blog público en `/nueva/blog/` (probado en local con PHP)
- [ ] Migrar contenido de la home y páginas de servicio
- [ ] Migrar blog a Markdown (con las mismas URLs)
- [ ] Menú sandwich + sonido, YES/NO, efectos de scroll, footer
- [ ] Carrusel de casos (oculto con `mostrarCasos`)
- [ ] Primer despliegue real a `/nueva/` + crear usuario del panel (`./deploy.sh usuario NOMBRE`)
- [ ] Importador de los 155 artículos de WordPress al formato del panel
- [ ] Sitemap del blog nuevo y redirecciones 301 de salida

Ver `docs/PLAN-SALIDA.md` para la URL de pruebas y el día de salida.
