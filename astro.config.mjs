import { defineConfig } from 'astro/config';

// URL de pruebas: https://theclusteragency.com/nueva/ (carpeta aparte; WordPress no se toca).
// El día de salida se quita `base` y la web pasa a la raíz.
export default defineConfig({
  site: 'https://theclusteragency.com',
  base: '/nueva',
  trailingSlash: 'always',
  build: { format: 'directory' },
});
