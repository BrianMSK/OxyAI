<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manifest = $root . '/config/css-mapping/breakdance-coverage-manifest.json';
$fixture = $root . '/tests/fixtures/breakdance-contracts/sample-contracts.json';
$validator = $root . '/tools/css-mapping/validate-breakdance-coverage.php';
$cluster = $root . '/tools/css-mapping/cluster-coverage-gaps.php';

$coverageCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator) . ' ' . escapeshellarg($manifest) . ' ' . escapeshellarg($fixture);
$coverage = shell_exec($coverageCommand);
assert(is_string($coverage) && $coverage !== '');

$tmp = tempnam(sys_get_temp_dir(), 'oxyai-coverage-');
assert(is_string($tmp));
file_put_contents($tmp, $coverage);

$clusterCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($cluster) . ' ' . escapeshellarg($tmp);
$output = shell_exec($clusterCommand);
@unlink($tmp);
assert(is_string($output) && $output !== '');

$summary = json_decode($output, true);
assert(is_array($summary));
assert(($summary['sourceSummary']['elementCount'] ?? null) === 3);
assert(($summary['gapStatusCounts']['uncovered'] ?? null) === 1);
assert(($summary['uncoveredPaths'][0]['path'] ?? null) === 'design.not_yet_classified');
assert(($summary['topGapPrefixes'][0]['name'] ?? null) === 'design.not_yet_classified');

echo "breakdance-coverage-clusters-ok\n";
