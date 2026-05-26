<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manifestPath = $root . '/config/css-mapping/breakdance-coverage-manifest.json';
$fixture = $root . '/tests/fixtures/breakdance-contracts/sample-contracts.json';
$validator = $root . '/tools/css-mapping/validate-breakdance-coverage.php';

$manifest = json_decode((string) file_get_contents($manifestPath), true);
assert(is_array($manifest));

$manifest['elementRules'][] = [
    'class' => 'EssentialElements\\WooProductsList',
    'match' => 'exact',
    'path' => 'design.not_yet_classified',
    'status' => 'element-specific-contract',
    'stripSafe' => false,
];

$tmpManifest = tempnam(sys_get_temp_dir(), 'oxyai-element-rules-');
assert(is_string($tmpManifest));
file_put_contents($tmpManifest, json_encode($manifest));

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator) . ' ' . escapeshellarg($tmpManifest) . ' ' . escapeshellarg($fixture);
$output = shell_exec($command);
@unlink($tmpManifest);
assert(is_string($output) && $output !== '');

$summary = json_decode($output, true);
assert(is_array($summary));
assert(($summary['gapCounts']['uncovered'] ?? null) === 0);
assert(($summary['gapCounts']['needs-element-specific-mapper'] ?? null) === 0);
assert(($summary['statusCounts']['element-specific-contract'] ?? null) === 1);

$pathsByName = [];
foreach ($summary['paths'] as $row) {
    $pathsByName[$row['path']] = $row;
}

assert(($pathsByName['design.not_yet_classified']['status'] ?? null) === 'element-specific-contract');
assert(($pathsByName['design.not_yet_classified']['rulePath'] ?? null) === 'design.not_yet_classified');

echo "breakdance-element-rules-ok\n";
