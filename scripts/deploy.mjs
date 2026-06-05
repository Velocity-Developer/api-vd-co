import { execFileSync } from 'node:child_process';
import { existsSync, lstatSync, mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const dist = join(root, '.dist');
const packageRoot = join(dist, 'package');
const laravelRoot = join(packageRoot, 'laravel');
const publicHtmlRoot = join(packageRoot, 'public_html');
const zipPath = join(dist, 'apico-production-update.zip');
const includeVendor = parseIncludeVendorOption();

const laravelEntries = [
    'app',
    'bootstrap',
    'config',
    'database',
    'public',
    'resources',
    'routes',
    'storage',
    'artisan',
    'composer.json',
    'composer.lock',
];

const publicExcludes = new Set(['hot', 'storage', 'fonts-manifest.dev.json']);
const databaseExcludes = new Set(['database.sqlite']);
const bootstrapCacheKeeps = new Set(['.gitignore']);

function parseIncludeVendorOption() {
    const args = process.argv.slice(2);

    if (args.includes('--with-vendor')) {
        return true;
    }

    if (args.includes('--no-vendor') || args.includes('--without-vendor')) {
        return false;
    }

    return true;
}

function shouldSkip(sourcePath, relativePath) {
    const normalizedPath = relativePath.replaceAll('\\', '/');
    const name = basename(sourcePath);

    if (normalizedPath.startsWith('public/') && publicExcludes.has(name)) {
        return true;
    }

    if (normalizedPath.startsWith('database/') && databaseExcludes.has(name)) {
        return true;
    }

    if (normalizedPath.startsWith('bootstrap/cache/') && !bootstrapCacheKeeps.has(name)) {
        return true;
    }

    if (normalizedPath.startsWith('storage/') && !lstatSync(sourcePath).isDirectory() && !name.startsWith('.git')) {
        return true;
    }

    return false;
}

function copyEntry(sourcePath, destinationPath, relativePath = '') {
    if (!existsSync(sourcePath) || shouldSkip(sourcePath, relativePath)) {
        return;
    }

    const stats = lstatSync(sourcePath);

    if (stats.isSymbolicLink()) {
        return;
    }

    if (stats.isDirectory()) {
        mkdirSync(destinationPath, { recursive: true });

        for (const entry of readdirSync(sourcePath)) {
            copyEntry(
                join(sourcePath, entry),
                join(destinationPath, entry),
                relativePath ? join(relativePath, entry) : entry,
            );
        }

        return;
    }

    mkdirSync(dirname(destinationPath), { recursive: true });
    writeFileSync(destinationPath, readFileSync(sourcePath));
}

function rewritePublicIndex() {
    const indexPath = join(publicHtmlRoot, 'index.php');
    const indexContent = readFileSync(indexPath, 'utf8')
        .replace("__DIR__.'/../storage/framework/maintenance.php'", "__DIR__.'/../laravel/storage/framework/maintenance.php'")
        .replace("__DIR__.'/../vendor/autoload.php'", "__DIR__.'/../laravel/vendor/autoload.php'")
        .replace("__DIR__.'/../bootstrap/app.php'", "__DIR__.'/../laravel/bootstrap/app.php'");

    writeFileSync(indexPath, indexContent);
}

function zipPackage() {
    rmSync(zipPath, { force: true });

    execFileSync(
        'tar.exe',
        [
            '-a',
            '-cf',
            zipPath,
            '-C',
            packageRoot,
            'laravel',
            'public_html',
        ],
        { stdio: 'inherit' },
    );
}

rmSync(packageRoot, { force: true, recursive: true });
mkdirSync(laravelRoot, { recursive: true });
mkdirSync(publicHtmlRoot, { recursive: true });

for (const entry of laravelEntries) {
    copyEntry(join(root, entry), join(laravelRoot, entry), entry);
}

if (includeVendor) {
    copyEntry(join(root, 'vendor'), join(laravelRoot, 'vendor'), 'vendor');
}

for (const entry of readdirSync(join(root, 'public'))) {
    if (publicExcludes.has(entry)) {
        continue;
    }

    copyEntry(join(root, 'public', entry), join(publicHtmlRoot, entry), entry);
}

rewritePublicIndex();
zipPackage();

console.log(`Deploy package created: ${zipPath}`);
console.log(`Vendor included: ${includeVendor ? 'yes' : 'no'}`);
