import { mkdirSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const dist = join(root, '.dist');

rmSync(dist, { force: true, recursive: true });
mkdirSync(dist, { recursive: true });

console.log(`Cleaned dist folder: ${dist}`);
