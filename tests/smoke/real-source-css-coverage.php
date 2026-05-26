<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manifest = $root . '/config/css-mapping/breakdance-coverage-manifest.json';
$validator = $root . '/tools/css-mapping/validate-breakdance-coverage.php';
$breakdanceExtractor = $root . '/tools/css-mapping/extract-breakdance-contracts.php';
$oxygenExtractor = $root . '/tools/css-mapping/extract-oxygen-core-contracts.php';

$breakdanceElements = sourcePath('OXYAI_BREAKDANCE_ELEMENTS_DIR');
$breakdanceForms = sourcePath('OXYAI_BREAKDANCE_FORMS_DIR');
$oxygenCore = sourcePath('OXYAI_OXYGEN_CORE_DIR');

if (
    $breakdanceElements === ''
    || $breakdanceForms === ''
    || $oxygenCore === ''
    || !is_dir($breakdanceElements)
    || !is_dir($breakdanceForms)
    || !is_dir($oxygenCore)
) {
    echo "real-source-css-coverage-skip\n";
    exit(0);
}

$breakdanceCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($breakdanceExtractor)
    . ' ' . escapeshellarg($breakdanceElements)
    . ' ' . escapeshellarg($breakdanceForms)
    . ' | ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator)
    . ' --summary-only ' . escapeshellarg($manifest) . ' -';
$breakdanceOutput = shell_exec($breakdanceCommand);
expectTrue(is_string($breakdanceOutput) && $breakdanceOutput !== '', '$breakdanceOutput is empty');

$breakdance = json_decode((string) $breakdanceOutput, true);
expectTrue(is_array($breakdance), '$breakdance JSON did not decode to an array');
expectTrue(is_int($breakdance['elementCount'] ?? null) && (int) $breakdance['elementCount'] > 0, '$breakdance.elementCount must be a positive integer');
expectTrue(is_int($breakdance['uniqueCssDesignPathCount'] ?? null) && (int) $breakdance['uniqueCssDesignPathCount'] > 0, '$breakdance.uniqueCssDesignPathCount must be a positive integer');
expectTrue(($breakdance['gapCounts']['uncovered'] ?? null) === 0, '$breakdance.gapCounts.uncovered must be 0');
expectTrue(($breakdance['gapCounts']['needs-element-specific-mapper'] ?? null) === 0, '$breakdance.gapCounts.needs-element-specific-mapper must be 0');
expectTrue(($breakdance['cssDeclarationPropertyGapCounts']['unknown-css-property'] ?? null) === 0, '$breakdance.cssDeclarationPropertyGapCounts.unknown-css-property must be 0');
expectTrue(($breakdance['cssMacroGapCounts']['unknown-css-macro'] ?? null) === 0, '$breakdance.cssMacroGapCounts.unknown-css-macro must be 0');
expectTrue(($breakdance['isComplete'] ?? null) === true, '$breakdance.isComplete must be true');

$oxygenCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($oxygenExtractor)
    . ' ' . escapeshellarg($oxygenCore)
    . ' | ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator)
    . ' --summary-only ' . escapeshellarg($manifest) . ' -';
$oxygenOutput = shell_exec($oxygenCommand);
expectTrue(is_string($oxygenOutput) && $oxygenOutput !== '', '$oxygenOutput is empty');

$oxygen = json_decode((string) $oxygenOutput, true);
expectTrue(is_array($oxygen), '$oxygen JSON did not decode to an array');
expectTrue(is_int($oxygen['elementCount'] ?? null) && (int) $oxygen['elementCount'] > 0, '$oxygen.elementCount must be a positive integer');
expectTrue(is_int($oxygen['uniqueCssDesignPathCount'] ?? null) && (int) $oxygen['uniqueCssDesignPathCount'] > 0, '$oxygen.uniqueCssDesignPathCount must be a positive integer');
expectTrue(($oxygen['gapCounts']['uncovered'] ?? null) === 0, '$oxygen.gapCounts.uncovered must be 0');
expectTrue(($oxygen['gapCounts']['needs-element-specific-mapper'] ?? null) === 0, '$oxygen.gapCounts.needs-element-specific-mapper must be 0');
expectTrue(($oxygen['isComplete'] ?? null) === true, '$oxygen.isComplete must be true');

echo "real-source-css-coverage-ok\n";

function sourcePath(string $envName): string
{
    $value = getenv($envName);
    if (is_string($value) && trim($value) !== '') {
        return $value;
    }

    return '';
}

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
