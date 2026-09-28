<?php
/**
 * CoreTT - Lint de todos los archivos PHP.
 *
 * Reemplaza el "find backend tests -name '*.php' -exec php -l" del
 * composer.json, que solo funcionaba en sistemas POSIX y fallaba en
 * Windows (que es donde se desarrolla este proyecto).
 *
 * Uso: php tests/lint.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo disponible por consola');
}

$root  = dirname(__DIR__);
$paths = [
    $root . '/backend',
    $root . '/tests',
];

$files = [];
foreach ($paths as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

if (!$files) {
    fwrite(STDERR, "No se encontraron archivos PHP.\n");
    exit(1);
}

$failed = 0;
foreach ($files as $file) {
    $output = [];
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
    $relative = substr($file, strlen($root) + 1);

    if ($status === 0) {
        echo "[OK]   $relative\n";
    } else {
        $failed++;
        echo "[FAIL] $relative\n";
        foreach ($output as $line) {
            echo '       ' . $line . "\n";
        }
    }
}

echo "\n" . count($files) . " archivo(s) revisados, $failed con errores.\n";
exit($failed > 0 ? 1 : 0);
