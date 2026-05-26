<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manifest = $root . '/config/css-mapping/breakdance-coverage-manifest.json';
$validator = $root . '/tools/css-mapping/validate-breakdance-coverage.php';
$breakdanceExtractor = $root . '/tools/css-mapping/extract-breakdance-contracts.php';
$oxygenExtractor = $root . '/tools/css-mapping/extract-oxygen-core-contracts.php';

$breakdanceElements = sourcePath(
    'OXYAI_BREAKDANCE_ELEMENTS_DIR',
    'C:\\Users\\Denis\\Downloads\\breakdance-elements-for-oxygen-1.0.0 (1)\\breakdance-elements-for-oxygen\\elements'
);
$breakdanceForms = sourcePath(
    'OXYAI_BREAKDANCE_FORMS_DIR',
    'C:\\Users\\Denis\\Downloads\\breakdance-forms-for-oxygen-0.3.0 (1)\\breakdance-forms-for-oxygen\\elements'
);
$oxygenCore = sourcePath(
    'OXYAI_OXYGEN_CORE_DIR',
    'C:\\Users\\Denis\\Downloads\\oxygen-6.1.0-beta.4\\oxygen'
);

if (!is_dir($breakdanceElements) || !is_dir($breakdanceForms) || !is_dir($oxygenCore)) {
    echo "real-source-css-coverage-skip\n";
    exit(0);
}

$breakdanceCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($breakdanceExtractor)
    . ' ' . escapeshellarg($breakdanceElements)
    . ' ' . escapeshellarg($breakdanceForms)
    . ' | ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator)
    . ' --summary-only ' . escapeshellarg($manifest) . ' -';
$breakdanceOutput = shell_exec($breakdanceCommand);
assert(is_string($breakdanceOutput) && $breakdanceOutput !== '');

$breakdance = json_decode($breakdanceOutput, true);
assert(is_array($breakdance));
assert(($breakdance['elementCount'] ?? null) === 134);
assert(($breakdance['uniqueCssDesignPathCount'] ?? null) === 1086);
assert(($breakdance['gapCounts']['uncovered'] ?? null) === 0);
assert(($breakdance['gapCounts']['needs-element-specific-mapper'] ?? null) === 0);
assert(($breakdance['cssDeclarationPropertyGapCounts']['unknown-css-property'] ?? null) === 0);
assert(($breakdance['cssMacroGapCounts']['unknown-css-macro'] ?? null) === 0);
assert(($breakdance['isComplete'] ?? null) === true);

$oxygenCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($oxygenExtractor)
    . ' ' . escapeshellarg($oxygenCore)
    . ' | ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator)
    . ' --summary-only ' . escapeshellarg($manifest) . ' -';
$oxygenOutput = shell_exec($oxygenCommand);
assert(is_string($oxygenOutput) && $oxygenOutput !== '');

$oxygen = json_decode($oxygenOutput, true);
assert(is_array($oxygen));
assert(($oxygen['elementCount'] ?? null) === 21);
assert(($oxygen['uniqueCssDesignPathCount'] ?? null) === 30);
assert(($oxygen['gapCounts']['uncovered'] ?? null) === 0);
assert(($oxygen['gapCounts']['needs-element-specific-mapper'] ?? null) === 0);
assert(($oxygen['isComplete'] ?? null) === true);

echo "real-source-css-coverage-ok\n";

function sourcePath(string $envName, string $default): string
{
    $value = getenv($envName);
    if (is_string($value) && trim($value) !== '') {
        return $value;
    }

    return $default;
}
