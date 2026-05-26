<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$fixture = $root . '/tests/fixtures/breakdance-contracts/sample-contracts.json';
$script = $root . '/tools/css-mapping/summarize-breakdance-contracts.php';

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($fixture);
$output = shell_exec($command);
assert(is_string($output) && $output !== '');

$summary = json_decode($output, true);
assert(is_array($summary));
assert($summary['elementCount'] === 3);
assert($summary['uniqueCssDesignPathCount'] === 11);
assert(($summary['statusCounts']['native-shared-mapper'] ?? null) === 4);
assert(($summary['statusCounts']['native-with-guardrails'] ?? null) === 4);
assert(($summary['statusCounts']['requires-css-fallback'] ?? null) === 2);
assert(($summary['statusCounts']['needs-element-specific-mapper'] ?? null) === 1);

$pathsByName = [];
foreach ($summary['paths'] as $row) {
    $pathsByName[$row['path']] = $row;
}

assert(($pathsByName['design.layout']['status'] ?? null) === 'native-with-guardrails');
assert(($pathsByName['design.elements']['status'] ?? null) === 'requires-css-fallback');
assert(($pathsByName['design.not_yet_classified']['status'] ?? null) === 'needs-element-specific-mapper');

$woo = null;
foreach ($summary['elements'] as $element) {
    if (($element['class'] ?? null) === 'EssentialElements\\WooProductsList') {
        $woo = $element;
        break;
    }
}

assert(is_array($woo));
assert($woo['unmappedCssDesignPaths'] === ['design.not_yet_classified']);

$summaryOnlyCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' --summary-only ' . escapeshellarg($fixture);
$summaryOnlyOutput = shell_exec($summaryOnlyCommand);
assert(is_string($summaryOnlyOutput) && $summaryOnlyOutput !== '');

$summaryOnly = json_decode($summaryOnlyOutput, true);
assert(is_array($summaryOnly));
assert($summaryOnly['elementCount'] === 3);
assert($summaryOnly['uniqueCssDesignPathCount'] === 11);
assert(!array_key_exists('paths', $summaryOnly));
assert(!array_key_exists('elements', $summaryOnly));

echo "breakdance-contract-summary-ok\n";
