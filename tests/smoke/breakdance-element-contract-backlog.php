<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manifest = $root . '/config/css-mapping/breakdance-coverage-manifest.json';
$fixture = $root . '/tests/fixtures/breakdance-contracts/sample-contracts.json';
$validator = $root . '/tools/css-mapping/validate-breakdance-coverage.php';
$backlog = $root . '/tools/css-mapping/element-contract-backlog.php';

$coverageCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator) . ' ' . escapeshellarg($manifest) . ' ' . escapeshellarg($fixture);
$coverage = shell_exec($coverageCommand);
assert(is_string($coverage) && $coverage !== '');

$tmp = tempnam(sys_get_temp_dir(), 'oxyai-element-backlog-');
assert(is_string($tmp));
file_put_contents($tmp, $coverage);

$backlogCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($backlog) . ' ' . escapeshellarg($tmp);
$output = shell_exec($backlogCommand);
@unlink($tmp);
assert(is_string($output) && $output !== '');

$summary = json_decode($output, true);
assert(is_array($summary));
assert(($summary['contractCount'] ?? null) === 1);

$contract = $summary['contracts'][0] ?? null;
assert(is_array($contract));
assert(($contract['element'] ?? null) === 'EssentialElements\\WooProductsList');
assert(($contract['gapPathCount'] ?? null) === 1);
assert(($contract['gapPaths'][0] ?? null) === 'design.not_yet_classified');
assert(($contract['cssDeclarationsTouchingGaps'][0]['property'] ?? null) === 'fill');
assert(($contract['cssDeclarationsTouchingGaps'][0]['touchesGapPaths'][0] ?? null) === 'design.not_yet_classified');
assert(($contract['recommendedContract'] ?? null) === 'element-specific declaration mapper contract');
assert(in_array('add JSON-shape smoke test for the element contract', $contract['completionChecklist'] ?? [], true));

echo "breakdance-element-contract-backlog-ok\n";
