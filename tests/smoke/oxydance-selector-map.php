<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$fixture = $root . '/tests/fixtures/oxydance/sample-builder-ai.js';
$script = $root . '/tools/css-mapping/extract-oxydance-selector-map.php';

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($fixture);
$output = shell_exec($command);
assert(is_string($output) && $output !== '');

$result = json_decode($output, true);
assert(is_array($result));
assert(($result['mappingCount'] ?? null) === 5);
assert(($result['mappings'][0]['from'] ?? null) === 'typography.typography.custom.customTypography.fontSize');
assert(($result['mappings'][0]['to'] ?? null) === 'typography.font_size');
assert(($result['mappings'][2]['status'] ?? null) === 'post-process');
assert(isset($result['specialCases']['background.layers']));
assert(isset($result['specialCases']['custom_css.css']));

echo "oxydance-selector-map-ok\n";
