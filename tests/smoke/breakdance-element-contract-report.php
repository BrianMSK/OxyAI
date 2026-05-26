<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manifest = $root . '/config/css-mapping/breakdance-coverage-manifest.json';
$fixture = $root . '/tests/fixtures/breakdance-contracts/sample-contracts.json';
$validator = $root . '/tools/css-mapping/validate-breakdance-coverage.php';
$backlog = $root . '/tools/css-mapping/element-contract-backlog.php';
$report = $root . '/tools/css-mapping/element-contract-report.php';

$coverageCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator) . ' ' . escapeshellarg($manifest) . ' ' . escapeshellarg($fixture);
$coverage = shell_exec($coverageCommand);
assert(is_string($coverage) && $coverage !== '');

$coverageFile = tempnam(sys_get_temp_dir(), 'oxyai-coverage-');
assert(is_string($coverageFile));
file_put_contents($coverageFile, $coverage);

$backlogCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($backlog) . ' ' . escapeshellarg($coverageFile);
$backlogOutput = shell_exec($backlogCommand);
@unlink($coverageFile);
assert(is_string($backlogOutput) && $backlogOutput !== '');

$backlogFile = tempnam(sys_get_temp_dir(), 'oxyai-backlog-');
assert(is_string($backlogFile));
file_put_contents($backlogFile, $backlogOutput);

$reportCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($report) . ' --limit=1 ' . escapeshellarg($backlogFile);
$output = shell_exec($reportCommand);
@unlink($backlogFile);
assert(is_string($output) && $output !== '');

assert(str_contains($output, '# Element-Specific CSS Contract Backlog'));
assert(str_contains($output, '| Element contracts | 1 |'));
assert(str_contains($output, '## Products List'));
assert(str_contains($output, 'Element: `EssentialElements\\WooProductsList`'));
assert(str_contains($output, '`design.not_yet_classified`'));
assert(str_contains($output, '`fill` in `%%SELECTOR%% svg`'));
assert(str_contains($output, '- [ ] add JSON-shape smoke test for the element contract'));

echo "breakdance-element-contract-report-ok\n";
