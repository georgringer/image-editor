import { build } from 'esbuild';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');

await build({
  entryPoints: [resolve(here, 'entry.mjs')],
  bundle: true,
  minify: true,
  format: 'iife',
  // Filerobot uses process.env.NODE_ENV internally (React); define it for the browser.
  define: { 'process.env.NODE_ENV': '"production"' },
  loader: { '.js': 'jsx' },
  outfile: resolve(root, 'Resources/Public/JavaScript/Vendor/filerobot-image-editor.bundle.js'),
  legalComments: 'none',
  logLevel: 'info',
});

console.log('Filerobot bundle built.');
