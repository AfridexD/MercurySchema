<?php
/**
 * Builds build/unlimited-schema-<version>.zip, leaving out everything listed
 * in .distignore. Needs only PHP with the zip extension.
 *
 *   docker compose run --rm tests php bin/build.php
 */

$root = dirname(__DIR__);
preg_match("/define\('UNLIMITED_SCHEMA_VERSION', '([^']+)'\)/", file_get_contents("$root/unlimited-schema.php"), $m);
$version = $m[1] ?? 'dev';

$ignore = array_filter(array_map('trim', file("$root/.distignore")), static fn($l) => $l !== '' && $l[0] !== '#');
$ignored = static function (string $rel) use ($ignore): bool {
    foreach ($ignore as $pattern) {
        $anchored = $pattern[0] === '/';
        $p = ltrim($pattern, '/');
        if ($rel === $p || strpos($rel, "$p/") === 0) {
            return true;
        }
        if (!$anchored && (basename($rel) === $p || strpos("/$rel/", "/$p/") !== false)) {
            return true;
        }
    }
    return false;
};

@mkdir("$root/build");
$zipPath = "$root/build/unlimited-schema-$version.zip";
@unlink($zipPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create $zipPath\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$count = 0;
$bytes = 0;
foreach ($files as $file) {
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($ignored($rel)) {
        continue;
    }
    $zip->addFile($file->getPathname(), "unlimited-schema/$rel");
    $count++;
    $bytes += $file->getSize();
}
$zip->close();

printf("%s: %d files, %.1f KB unpacked, %.1f KB zipped\n", basename($zipPath), $count, $bytes / 1024, filesize($zipPath) / 1024);
