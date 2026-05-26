<?php

declare(strict_types=1);

/**
 * Run the CSS coverage manifest gate against the real downloaded source trees.
 *
 * Optional environment variables:
 * - OXYAI_BREAKDANCE_ELEMENTS_DIR
 * - OXYAI_BREAKDANCE_FORMS_DIR
 * - OXYAI_OXYGEN_CORE_DIR
 *
 * Usage:
 *   php tools/css-mapping/real-source-coverage-gate.php [--json]
 */

$jsonOutput = in_array('--json', array_slice($argv, 1), true);

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

$rows = [];
$rows[] = coverageRow(
    'Breakdance Elements + Forms',
    [$breakdanceElements, $breakdanceForms],
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($breakdanceExtractor)
        . ' ' . escapeshellarg($breakdanceElements)
        . ' ' . escapeshellarg($breakdanceForms)
        . ' | ' . validatorCommand($validator, $manifest)
);
$rows[] = coverageRow(
    'Oxygen Core',
    [$oxygenCore],
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($oxygenExtractor)
        . ' ' . escapeshellarg($oxygenCore)
        . ' | ' . validatorCommand($validator, $manifest)
);

$allRunnable = every($rows, static fn (array $row): bool => $row['status'] !== 'SKIP');
$allComplete = every($rows, static fn (array $row): bool => $row['complete'] === true);
$isComplete = $allRunnable && $allComplete;

if ($jsonOutput) {
    echo json_encode([
        'generatedAt' => gmdate('c'),
        'isComplete' => $isComplete,
        'mergeGate' => $isComplete ? 'PASS' : 'FAIL',
        'inventories' => $rows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit($isComplete ? 0 : 1);
}

$lines = [];
$lines[] = '# Real Source CSS Coverage Gate';
$lines[] = '';
$lines[] = '| Inventory | Status | Complete | Elements | Design paths | Uncovered | Needs mapper | Unknown CSS props | Unknown macros |';
$lines[] = '| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';
foreach ($rows as $row) {
    $lines[] = '| ' . escapePipes($row['name']) . ' | ' . $row['status'] . ' | ' . yesNo($row['complete']) . ' | ' . scalar($row['elements']) . ' | ' . scalar($row['paths']) . ' | ' . scalar($row['uncovered']) . ' | ' . scalar($row['needs']) . ' | ' . scalar($row['unknownProps']) . ' | ' . scalar($row['unknownMacros']) . ' |';
}
$lines[] = '';
$lines[] = $isComplete
    ? 'Merge gate: **PASS**. Real Oxygen, Breakdance Elements, and Breakdance Forms sources satisfy the coverage manifest.'
    : 'Merge gate: **FAIL**. Run with all source directories available and eliminate every uncovered, needs-mapper, unknown-property, and unknown-macro row.';

echo implode("\n", $lines) . "\n";
exit($isComplete ? 0 : 1);

/**
 * @param array<int, string> $requiredDirs
 * @return array<string, mixed>
 */
function coverageRow(string $name, array $requiredDirs, string $command): array
{
    foreach ($requiredDirs as $dir) {
        if (!is_dir($dir)) {
            return [
                'name' => $name,
                'status' => 'SKIP',
                'complete' => false,
                'elements' => null,
                'paths' => null,
                'uncovered' => null,
                'needs' => null,
                'unknownProps' => null,
                'unknownMacros' => null,
            ];
        }
    }

    $output = shell_exec($command);
    $summary = is_string($output) ? json_decode($output, true) : null;
    if (!is_array($summary)) {
        return [
            'name' => $name,
            'status' => 'ERROR',
            'complete' => false,
            'elements' => null,
            'paths' => null,
            'uncovered' => null,
            'needs' => null,
            'unknownProps' => null,
            'unknownMacros' => null,
        ];
    }

    $complete = ($summary['isComplete'] ?? false) === true;

    return [
        'name' => $name,
        'status' => $complete ? 'PASS' : 'FAIL',
        'complete' => $complete,
        'elements' => $summary['elementCount'] ?? null,
        'paths' => $summary['uniqueCssDesignPathCount'] ?? null,
        'uncovered' => $summary['gapCounts']['uncovered'] ?? null,
        'needs' => $summary['gapCounts']['needs-element-specific-mapper'] ?? null,
        'unknownProps' => $summary['cssDeclarationPropertyGapCounts']['unknown-css-property'] ?? null,
        'unknownMacros' => $summary['cssMacroGapCounts']['unknown-css-macro'] ?? null,
    ];
}

function validatorCommand(string $validator, string $manifest): string
{
    return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator)
        . ' --summary-only ' . escapeshellarg($manifest) . ' -';
}

function sourcePath(string $envName, string $default): string
{
    $value = getenv($envName);
    if (is_string($value) && trim($value) !== '') {
        return $value;
    }

    return $default;
}

/**
 * @template T
 * @param array<int, T> $items
 * @param callable(T): bool $predicate
 */
function every(array $items, callable $predicate): bool
{
    foreach ($items as $item) {
        if (!$predicate($item)) {
            return false;
        }
    }

    return true;
}

function yesNo(bool $value): string
{
    return $value ? 'yes' : 'no';
}

/**
 * @param mixed $value
 */
function scalar($value): string
{
    if ($value === null) {
        return '-';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    return '';
}

function escapePipes(string $value): string
{
    return str_replace('|', '\\|', $value);
}
