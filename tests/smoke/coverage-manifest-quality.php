<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manifest = $root . '/config/css-mapping/breakdance-coverage-manifest.json';
$validator = $root . '/tools/css-mapping/validate-coverage-manifest.php';

$output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator) . ' ' . escapeshellarg($manifest));
assert(is_string($output) && trim($output) === 'coverage-manifest-quality-ok');

echo "coverage-manifest-quality-ok\n";
