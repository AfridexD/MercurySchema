<?php
/**
 * Builds build/Mercury-Schema-<version>.zip, leaving out everything listed
 * in .distignore. Needs only PHP with the zip extension.
 *
 *   docker compose run --rm tests php bin/build.php
 */

$root = dirname(__DIR__);
preg_match("/define\('MERCURY_SCHEMA_VERSION', '([^']+)'\)/", file_get_contents("$root/mercury-schema.php"), $m);
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
// The download is named for the brand; the folder inside stays "mercury-schema" (the WordPress slug).
$zipPath = "$root/build/Mercury-Schema-$version.zip";
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
    // Files ship as written: WordPress.org reviewers require readable, unminified code.
    $zip->addFile($file->getPathname(), "mercury-schema/$rel");
    $bytes += $file->getSize();
    $count++;
}
$zip->close();

printf("%s: %d files, %.1f KB unpacked, %.1f KB zipped\n", basename($zipPath), $count, $bytes / 1024, filesize($zipPath) / 1024);

