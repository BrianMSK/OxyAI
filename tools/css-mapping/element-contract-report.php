<?php

declare(strict_types=1);

/**
 * Render element-contract-backlog.php output as Markdown for PR review.
 *
 * Usage:
 *   php tools/css-mapping/element-contract-backlog.php <coverage-json> \
 *     | php tools/css-mapping/element-contract-report.php --limit=10 -
 */

$limit = 10;
$args = array_slice($argv, 1);
foreach ($args as $index => $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, strlen('--limit=')));
        unset($args[$index]);
    }
}
$args = array_values($args);

if (count($args) !== 1) {
    fwrite(STDERR, "Usage: php element-contract-report.php [--limit=N] <element-backlog-json>\n");
    exit(2);
}

$input = $args[0] === '-'
    ? stream_get_contents(STDIN)
    : file_get_contents($args[0]);
if ($input === false) {
    fwrite(STDERR, "Unable to read {$args[0]}\n");
    exit(2);
}

$backlog = json_decode($input, true);
if (!is_array($backlog) || !is_array($backlog['contracts'] ?? null)) {
    fwrite(STDERR, "Invalid element backlog JSON\n");
    exit(2);
}

$contracts = array_values(array_filter($backlog['contracts'], 'is_array'));
$sourceSummary = is_array($backlog['sourceSummary'] ?? null) ? $backlog['sourceSummary'] : [];
$gapCounts = is_array($sourceSummary['gapCounts'] ?? null) ? $sourceSummary['gapCounts'] : [];

$lines = [];
$lines[] = '# Element-Specific CSS Contract Backlog';
$lines[] = '';
$lines[] = '| Metric | Value |';
$lines[] = '| --- | ---: |';
$lines[] = '| Element contracts | ' . scalar($backlog['contractCount'] ?? count($contracts)) . ' |';
$lines[] = '| Source elements | ' . scalar($sourceSummary['elementCount'] ?? '') . ' |';
$lines[] = '| Unique CSS design paths | ' . scalar($sourceSummary['uniqueCssDesignPathCount'] ?? '') . ' |';
$lines[] = '| Needs element-specific mapper | ' . scalar($gapCounts['needs-element-specific-mapper'] ?? '') . ' |';
$lines[] = '| Uncovered paths | ' . scalar($gapCounts['uncovered'] ?? '') . ' |';
$lines[] = '';
$lines[] = 'These contracts are not complete mapper implementations. Each item is a reviewed work packet that still needs classification, JSON-shape tests, and compile/render proof before any CSS fallback can be stripped.';

foreach (array_slice($contracts, 0, $limit) as $contract) {
    $lines[] = '';
    $lines[] = '## ' . heading((string) ($contract['name'] ?? $contract['element'] ?? 'Element'));
    $lines[] = '';
    $lines[] = '- Element: `' . escapeInline((string) ($contract['element'] ?? '')) . '`';
    $lines[] = '- Gap paths: ' . scalar($contract['gapPathCount'] ?? count($contract['gapPaths'] ?? []));
    $lines[] = '- Recommended contract: ' . (string) ($contract['recommendedContract'] ?? '');

    $gapPaths = array_values(array_filter(array_map('strval', $contract['gapPaths'] ?? [])));
    $lines[] = '';
    $lines[] = 'Top gap paths:';
    foreach (array_slice($gapPaths, 0, 12) as $path) {
        $lines[] = '- `' . escapeInline($path) . '`';
    }
    if (count($gapPaths) > 12) {
        $lines[] = '- ... +' . (count($gapPaths) - 12) . ' more';
    }

    $declarations = array_values(array_filter($contract['cssDeclarationsTouchingGaps'] ?? [], 'is_array'));
    $macros = array_values(array_filter($contract['cssMacrosTouchingGaps'] ?? [], 'is_array'));

    $lines[] = '';
    $lines[] = 'CSS declarations touching gaps:';
    if ($declarations === []) {
        $lines[] = '- none detected';
    } else {
        foreach (array_slice($declarations, 0, 8) as $row) {
            $touches = implode(', ', array_slice(array_values(array_filter(array_map('strval', $row['touchesGapPaths'] ?? []))), 0, 3));
            $selector = (string) ($row['selector'] ?? '');
            $lines[] = '- `' . escapeInline((string) ($row['property'] ?? '')) . '` in `' . escapeInline($selector) . '` -> ' . escapeInline($touches);
        }
        if (count($declarations) > 8) {
            $lines[] = '- ... +' . (count($declarations) - 8) . ' more';
        }
    }

    $lines[] = '';
    $lines[] = 'CSS macros touching gaps:';
    if ($macros === []) {
        $lines[] = '- none detected';
    } else {
        foreach (array_slice($macros, 0, 8) as $row) {
            $touches = implode(', ', array_slice(array_values(array_filter(array_map('strval', $row['touchesGapPaths'] ?? []))), 0, 3));
            $lines[] = '- `' . escapeInline((string) ($row['macro'] ?? '')) . '` -> ' . escapeInline($touches);
        }
        if (count($macros) > 8) {
            $lines[] = '- ... +' . (count($macros) - 8) . ' more';
        }
    }

    $lines[] = '';
    $lines[] = 'Completion checklist:';
    foreach (($contract['completionChecklist'] ?? []) as $item) {
        if (is_string($item) && $item !== '') {
            $lines[] = '- [ ] ' . $item;
        }
    }
}

echo implode("\n", $lines) . "\n";

/**
 * @param mixed $value
 */
function scalar($value): string
{
    if (is_scalar($value)) {
        return (string) $value;
    }

    return '';
}

function escapeInline(string $value): string
{
    return str_replace('`', '\\`', $value);
}

function heading(string $value): string
{
    $value = trim($value);
    return $value !== '' ? $value : 'Element';
}
