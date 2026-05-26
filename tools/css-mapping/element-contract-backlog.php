<?php

declare(strict_types=1);

/**
 * Build an element-specific mapper backlog from full coverage JSON.
 *
 * Usage:
 *   php tools/css-mapping/validate-breakdance-coverage.php <manifest> <contracts.json> \
 *     | php tools/css-mapping/element-contract-backlog.php -
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php element-contract-backlog.php <coverage-json>\n");
    exit(2);
}

$input = $argv[1] === '-'
    ? stream_get_contents(STDIN)
    : file_get_contents($argv[1]);
if ($input === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(2);
}

$coverage = json_decode($input, true);
if (!is_array($coverage) || !is_array($coverage['elements'] ?? null)) {
    fwrite(STDERR, "Invalid coverage JSON; run validate-breakdance-coverage.php without --summary-only\n");
    exit(2);
}

$declarationsByElement = groupRowsByElement(is_array($coverage['cssDeclarations'] ?? null) ? $coverage['cssDeclarations'] : []);
$macrosByElement = groupRowsByElement(is_array($coverage['cssMacroRows'] ?? null) ? $coverage['cssMacroRows'] : []);
$contracts = [];

foreach ($coverage['elements'] as $element) {
    if (!is_array($element)) {
        continue;
    }

    $gapCount = (int) ($element['coverageGapCount'] ?? 0);
    if ($gapCount <= 0) {
        continue;
    }

    $class = (string) ($element['class'] ?? '');
    if ($class === '') {
        continue;
    }

    $gapPaths = array_values(array_filter(array_map('strval', $element['coverageGaps'] ?? [])));
    $declarations = relatedRows($declarationsByElement[$class] ?? [], $gapPaths);
    $macros = relatedRows($macrosByElement[$class] ?? [], $gapPaths);

    $contracts[] = [
        'element' => $class,
        'name' => $element['name'] ?? null,
        'category' => $element['category'] ?? null,
        'gapPathCount' => $gapCount,
        'gapPaths' => $gapPaths,
        'cssDeclarationsTouchingGaps' => $declarations,
        'cssMacrosTouchingGaps' => $macros,
        'recommendedContract' => recommendContract($gapPaths, $declarations, $macros),
        'completionChecklist' => [
            'classify every gapPath as native mapper, element-specific mapper, content/runtime behavior, or explicit fallback',
            'document CSS declaration or macro source for every mapped path',
            'add JSON-shape smoke test for the element contract',
            'add compiled CSS or rendered page proof before stripSafe=true',
            'verify conversion audit has no retained/dead-write declarations for any strip-safe path',
        ],
    ];
}

usort($contracts, static fn (array $a, array $b): int => [(int) $b['gapPathCount'], (string) $a['element']] <=> [(int) $a['gapPathCount'], (string) $b['element']]);

echo json_encode([
    'generatedAt' => gmdate('c'),
    'sourceSummary' => [
        'elementCount' => $coverage['elementCount'] ?? null,
        'uniqueCssDesignPathCount' => $coverage['uniqueCssDesignPathCount'] ?? null,
        'gapCounts' => $coverage['gapCounts'] ?? null,
        'isComplete' => $coverage['isComplete'] ?? null,
    ],
    'contractCount' => count($contracts),
    'contracts' => $contracts,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

/**
 * @param array<int, mixed> $rows
 * @return array<string, array<int, array<string, mixed>>>
 */
function groupRowsByElement(array $rows): array
{
    $grouped = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $element = (string) ($row['element'] ?? '');
        if ($element === '') {
            continue;
        }

        $grouped[$element][] = $row;
    }

    return $grouped;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param array<int, string> $gapPaths
 * @return array<int, array<string, mixed>>
 */
function relatedRows(array $rows, array $gapPaths): array
{
    $related = [];
    foreach ($rows as $row) {
        $paths = array_values(array_filter(array_map('strval', $row['designPaths'] ?? [])));
        $touching = [];
        foreach ($paths as $path) {
            foreach ($gapPaths as $gapPath) {
                if (pathsRelated($path, $gapPath)) {
                    $touching[] = $gapPath;
                }
            }
        }

        if ($touching === []) {
            continue;
        }

        $row['touchesGapPaths'] = array_values(array_unique($touching));
        $related[] = $row;
    }

    return $related;
}

function pathsRelated(string $path, string $gapPath): bool
{
    return $path === $gapPath
        || str_starts_with($path, $gapPath . '.')
        || str_starts_with($gapPath, $path . '.');
}

/**
 * @param array<int, string> $gapPaths
 * @param array<int, array<string, mixed>> $declarations
 * @param array<int, array<string, mixed>> $macros
 */
function recommendContract(array $gapPaths, array $declarations, array $macros): string
{
    foreach ($gapPaths as $path) {
        if (str_contains($path, '.hover') || str_ends_with($path, '_hover') || str_contains($path, '_hover.')) {
            return 'element-specific selector/fallback contract with hover-state proof';
        }
    }

    if ($macros !== []) {
        return 'element-specific macro contract';
    }

    if ($declarations !== []) {
        return 'element-specific declaration mapper contract';
    }

    return 'element-specific runtime/content contract';
}
