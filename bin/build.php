<?php
/**
 * Builds build/mercury-schema-<version>.zip, leaving out everything listed
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
$zipPath = "$root/build/mercury-schema-$version.zip";
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
    $ext = pathinfo($rel, PATHINFO_EXTENSION);
    if ($ext === 'js' || $ext === 'css') {
        $compact = compact_asset((string) file_get_contents($file->getPathname()), $ext);
        $zip->addFromString("mercury-schema/$rel", $compact);
        $bytes += strlen($compact);
        printf("  %s: %.1f KB -> %.1f KB\n", $rel, $file->getSize() / 1024, strlen($compact) / 1024);
    } else {
        $zip->addFile($file->getPathname(), "mercury-schema/$rel");
        $bytes += $file->getSize();
    }
    $count++;
}
$zip->close();

printf("%s: %d files, %.1f KB unpacked, %.1f KB zipped\n", basename($zipPath), $count, $bytes / 1024, filesize($zipPath) / 1024);

/**
 * Conservative compaction: drop indentation, blank lines and comment-only
 * lines, but keep every line break so JavaScript semantics cannot change.
 */
function compact_asset(string $source, string $ext): string
{
    if ($ext === 'css') {
        $source = preg_replace('#/\*.*?\*/#s', '', $source);
    }
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $source) as $line) {
        $line = trim($line);
        if ($line === '' || ($ext === 'js' && (strpos($line, '//') === 0 || strpos($line, '* ') === 0 || $line === '*' || $line === '/**' || $line === '*/'))) {
            continue;
        }
        $out[] = $line;
    }
    return implode("\n", $out) . "\n";
}
