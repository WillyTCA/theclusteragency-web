import { defineConfig } from 'astro/config';

// URL de pruebas. El día de salida a producción cambia a https://theclusteragency.com
export default defineConfig({
  site: 'https://nueva.theclusteragency.com',
  trailingSlash: 'always',
  build: { format: 'directory' },
});
