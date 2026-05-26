<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manifest = $root . '/config/css-mapping/breakdance-coverage-manifest.json';
$fixture = $root . '/tests/fixtures/breakdance-contracts/sample-contracts.json';
$validator = $root . '/tools/css-mapping/validate-breakdance-coverage.php';
$reporter = $root . '/tools/css-mapping/coverage-review-report.php';

$coverageCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator) . ' ' . escapeshellarg($manifest) . ' ' . escapeshellarg($fixture);
$coverage = shell_exec($coverageCommand);
assert(is_string($coverage) && $coverage !== '');

$tmp = tempnam(sys_get_temp_dir(), 'oxyai-coverage-review-');
assert(is_string($tmp));
file_put_contents($tmp, $coverage);

$reportCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($reporter) . ' ' . escapeshellarg($tmp);
$output = shell_exec($reportCommand);
@unlink($tmp);
assert(is_string($output) && $output !== '');

assert(str_contains($output, '# CSS Mapping Coverage Review'));
assert(str_contains($output, 'Merge gate: **FAIL**'));
assert(str_contains($output, '| CSS declarations scanned | 4 |'));
assert(str_contains($output, '| Unique CSS declaration properties | 4 |'));
assert(str_contains($output, '| Unknown CSS declaration properties | 1 |'));
assert(str_contains($output, '| Unknown CSS macro calls | 1 |'));
assert(str_contains($output, '| `made-up-prop` | `unknown-css-property` | 1 |'));
assert(str_contains($output, '| `fill` | `recognized-but-fallback-only` | 1 |'));
assert(str_contains($output, '| `notKnownMacro` | `unknown-css-macro` | 1 |'));
assert(str_contains($output, '| `spacing_margin_y` | `shared-mapper-family` | 1 |'));
assert(str_contains($output, '| `width` | 1 |'));
assert(str_contains($output, '| Uncovered paths | 1 |'));
assert(str_contains($output, '| `design.not_yet_classified` | `uncovered` |'));
assert(str_contains($output, '| `EssentialElements\\WooProductsList` | Products List | 1 |'));

echo "breakdance-coverage-review-report-ok\n";
