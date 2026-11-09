# The Cluster Agency · web nueva — instrucciones de trabajo

Proyecto APARTE de `cluster-ops-suite`. Aquí no aplican las reglas, auditorías ni el
despliegue de la suite, y no se toca nada de ella.
Léelo entero antes de tocar nada.

## Qué es esto

La web nueva de theclusteragency.com. Hoy el dominio es **WordPress** (tema Kenozoik,
WPBakery, AIOSEO) y **sigue siéndolo hasta el día de salida**. Esta web se construye en una
URL de pruebas (`nueva.theclusteragency.com`) con `noindex` y contraseña.

Stack: **Astro 5** (estático, sin servidor), blog en Markdown (`src/content/blog/`),
GSAP + Lenis para los efectos. Se sube por SFTP (hosting PHP, sin Node).

## Reglas que no se saltan

**1. No tocar WordPress ni el dominio principal sin orden explícita de Willy.**
Nada de DNS, plugins, ajustes ni contenido del sitio actual. El cambio de destino del dominio
es el día de salida y lo decide Willy.

**2. No perder SEO.** `docs/urls-*.txt` es el inventario de URLs actuales (blog, páginas,
clientes, categorías). Cada URL actual o se conserva idéntica o tiene redirección 301 en
`public/.htaccess` (plantilla de producción en `docs/htaccess-produccion.md`). El sitemap, los
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

**7. No desplegar sin que Willy lo diga.** `./deploy.sh` sube a un temporal, verifica el hash,
guarda lo anterior fuera de la web y `./deploy.sh volver` lo deshace.

**8. Accesibilidad y rendimiento.** Respetar `prefers-reduced-motion`; menú y FAQ con teclado;
el sonido del menú nunca suena solo (solo tras clic, con botón de silencio, apagado en
móvil); vídeos comprimidos y con carga diferida. Inspirarse en otras webs, nunca copiar su
código, tipografías ni vídeos.

**9. Secretos fuera de git.** Claves, contraseñas y `.env` no se suben.

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

## Estado

Ver `README.md` (qué hay hecho y qué falta).
