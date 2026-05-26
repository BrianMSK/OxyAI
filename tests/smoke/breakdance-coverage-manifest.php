<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manifest = $root . '/config/css-mapping/breakdance-coverage-manifest.json';
$fixture = $root . '/tests/fixtures/breakdance-contracts/sample-contracts.json';
$script = $root . '/tools/css-mapping/validate-breakdance-coverage.php';

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' --summary-only ' . escapeshellarg($manifest) . ' ' . escapeshellarg($fixture);
$output = shell_exec($command);
assert(is_string($output) && $output !== '');

$summary = json_decode($output, true);
assert(is_array($summary));
assert(($summary['manifestVersion'] ?? null) === 1);
assert($summary['elementCount'] === 3);
assert($summary['uniqueCssDesignPathCount'] === 11);
assert(($summary['statusCounts']['native-shared-mapper'] ?? null) === 4);
assert(($summary['statusCounts']['native-with-guardrails'] ?? null) === 4);
assert(($summary['statusCounts']['requires-css-fallback'] ?? null) === 2);
assert(($summary['statusCounts']['uncovered'] ?? null) === 1);
assert(($summary['gapCounts']['uncovered'] ?? null) === 1);
assert(($summary['gapCounts']['needs-element-specific-mapper'] ?? null) === 0);
assert(($summary['gapCounts']['strip-safe-without-proof'] ?? null) === 0);
assert(($summary['cssDeclarationCount'] ?? null) === 4);
assert(($summary['uniqueCssDeclarationPropertyCount'] ?? null) === 4);
assert(($summary['cssMacroCallCount'] ?? null) === 2);
assert(($summary['cssDeclarationPropertyGapCounts']['unknown-css-property'] ?? null) === 1);
assert(($summary['cssMacroGapCounts']['unknown-css-macro'] ?? null) === 1);
assert($summary['isComplete'] === false);
assert(!array_key_exists('paths', $summary));
assert(!array_key_exists('elements', $summary));

$fullCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($manifest) . ' ' . escapeshellarg($fixture);
$fullOutput = shell_exec($fullCommand);
assert(is_string($fullOutput) && $fullOutput !== '');

$full = json_decode($fullOutput, true);
assert(is_array($full));

$pathsByName = [];
foreach ($full['paths'] as $row) {
    $pathsByName[$row['path']] = $row;
}

assert(($pathsByName['design.not_yet_classified']['status'] ?? null) === 'uncovered');
assert(($pathsByName['design.layout.slider']['rulePath'] ?? null) === 'design.layout');
assert(($full['cssDeclarationProperties']['color'] ?? null) === 1);
assert(($full['cssDeclarationProperties']['fill'] ?? null) === 1);
assert(($full['cssDeclarationProperties']['width'] ?? null) === 1);
assert(($full['cssMacroCalls']['spacing_margin_y'] ?? null) === 1);
assert(($full['cssMacroCalls']['notKnownMacro'] ?? null) === 1);

$propertyRowsByName = [];
foreach ($full['cssDeclarationPropertyCoverage'] as $row) {
    $propertyRowsByName[$row['property']] = $row;
}
assert(($propertyRowsByName['fill']['status'] ?? null) === 'recognized-but-fallback-only');
assert(($propertyRowsByName['made-up-prop']['status'] ?? null) === 'unknown-css-property');
assert(($propertyRowsByName['width']['status'] ?? null) === 'shared-mapper-recognized');

$macroRowsByName = [];
foreach ($full['cssMacroCoverage'] as $row) {
    $macroRowsByName[$row['macro']] = $row;
}
assert(($macroRowsByName['spacing_margin_y']['status'] ?? null) === 'shared-mapper-family');
assert(($macroRowsByName['notKnownMacro']['status'] ?? null) === 'unknown-css-macro');

echo "breakdance-coverage-manifest-ok\n";
