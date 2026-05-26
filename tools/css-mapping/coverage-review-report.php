<?php

declare(strict_types=1);

/**
 * Render validate-breakdance-coverage.php output as a PR-reviewable Markdown
 * gate. The JSON validator remains the machine source of truth; this script
 * makes the same evidence readable for reviewers and MCP agents.
 *
 * Usage:
 *   php tools/css-mapping/validate-breakdance-coverage.php <manifest> <contracts.json> \
 *     | php tools/css-mapping/coverage-review-report.php -
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php coverage-review-report.php <coverage-json>\n");
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
if (!is_array($coverage) || !is_array($coverage['paths'] ?? null) || !is_array($coverage['elements'] ?? null)) {
    fwrite(STDERR, "Invalid coverage JSON; run validate-breakdance-coverage.php without --summary-only\n");
    exit(2);
}

$gapPaths = gapPaths($coverage['paths']);
$topPrefixes = topGapPrefixes($gapPaths, 20);
$topElements = topGapElements($coverage['elements'], 20);
$gapCounts = is_array($coverage['gapCounts'] ?? null) ? $coverage['gapCounts'] : [];
$cssDeclarationPropertyGapCounts = is_array($coverage['cssDeclarationPropertyGapCounts'] ?? null) ? $coverage['cssDeclarationPropertyGapCounts'] : [];
$cssMacroGapCounts = is_array($coverage['cssMacroGapCounts'] ?? null) ? $coverage['cssMacroGapCounts'] : [];
$statusCounts = is_array($coverage['statusCounts'] ?? null) ? $coverage['statusCounts'] : [];
$cssDeclarationProperties = is_array($coverage['cssDeclarationProperties'] ?? null) ? $coverage['cssDeclarationProperties'] : [];
$cssDeclarationPropertyCoverage = is_array($coverage['cssDeclarationPropertyCoverage'] ?? null) ? $coverage['cssDeclarationPropertyCoverage'] : [];
$cssMacroCoverage = is_array($coverage['cssMacroCoverage'] ?? null) ? $coverage['cssMacroCoverage'] : [];
$isComplete = ($coverage['isComplete'] ?? false) === true;

$lines = [];
$lines[] = '# CSS Mapping Coverage Review';
$lines[] = '';
$lines[] = '| Gate | Result |';
$lines[] = '| --- | --- |';
$lines[] = '| Complete | ' . ($isComplete ? 'yes' : 'no') . ' |';
$lines[] = '| Elements scanned | ' . scalar($coverage['elementCount'] ?? 0) . ' |';
$lines[] = '| Unique CSS design paths | ' . scalar($coverage['uniqueCssDesignPathCount'] ?? 0) . ' |';
$lines[] = '| CSS declarations scanned | ' . scalar($coverage['cssDeclarationCount'] ?? 0) . ' |';
$lines[] = '| Unique CSS declaration properties | ' . scalar($coverage['uniqueCssDeclarationPropertyCount'] ?? 0) . ' |';
$lines[] = '| CSS macro calls scanned | ' . scalar($coverage['cssMacroCallCount'] ?? 0) . ' |';
$lines[] = '| Uncovered paths | ' . scalar($gapCounts['uncovered'] ?? 0) . ' |';
$lines[] = '| Needs element-specific mapper | ' . scalar($gapCounts['needs-element-specific-mapper'] ?? 0) . ' |';
$lines[] = '| Strip-safe without proof | ' . scalar($gapCounts['strip-safe-without-proof'] ?? 0) . ' |';
$lines[] = '| Unknown CSS declaration properties | ' . scalar($cssDeclarationPropertyGapCounts['unknown-css-property'] ?? 0) . ' |';
$lines[] = '| Unknown CSS macro calls | ' . scalar($cssMacroGapCounts['unknown-css-macro'] ?? 0) . ' |';
$lines[] = '';
$lines[] = $isComplete
    ? 'Merge gate: **PASS**. Every discovered design path has a completed coverage status and every strip-safe rule has proof metadata.'
    : 'Merge gate: **FAIL** for the 100% coverage objective. Keep this goal open until the gaps below are mapped, fallback-classified with proof, or intentionally covered by element-specific mappers.';
$lines[] = '';
$lines[] = '## Status Counts';
$lines[] = '';
$lines[] = '| Status | Paths |';
$lines[] = '| --- | ---: |';
foreach (sortedCounts($statusCounts) as $status => $count) {
    $lines[] = '| `' . escapePipes((string) $status) . '` | ' . scalar($count) . ' |';
}

$lines[] = '';
$lines[] = '## Top CSS Declaration Properties';
$lines[] = '';
if ($cssDeclarationProperties === []) {
    $lines[] = 'No direct CSS declarations were found in the inventory.';
} else {
    $lines[] = '| CSS property | Declarations |';
    $lines[] = '| --- | ---: |';
    foreach (topCounts($cssDeclarationProperties, 30) as $property => $count) {
        $lines[] = '| `' . escapePipes((string) $property) . '` | ' . scalar($count) . ' |';
    }
}

$lines[] = '';
$lines[] = '## Completion Criteria';
$lines[] = '';
foreach (($coverage['completionCriteria'] ?? []) as $criterion) {
    if (is_string($criterion) && $criterion !== '') {
        $lines[] = '- ' . $criterion;
    }
}

$lines[] = '';
$lines[] = '## CSS Declaration Property Coverage';
$lines[] = '';
if ($cssDeclarationPropertyCoverage === []) {
    $lines[] = 'No CSS declaration property coverage rows were found.';
} else {
    $lines[] = '| CSS property | Status | Declarations | Sample elements |';
    $lines[] = '| --- | --- | ---: | --- |';
    foreach (topCssPropertyCoverageRows($cssDeclarationPropertyCoverage, 40) as $row) {
        $samples = array_values(array_filter(array_map('strval', $row['sampleElements'] ?? [])));
        $lines[] = '| `' . escapePipes((string) ($row['property'] ?? '')) . '` | `' . escapePipes((string) ($row['status'] ?? '')) . '` | ' . scalar($row['declarationCount'] ?? 0) . ' | ' . escapePipes(implode(', ', $samples)) . ' |';
    }
}

$lines[] = '';
$lines[] = '## CSS Macro Coverage';
$lines[] = '';
if ($cssMacroCoverage === []) {
    $lines[] = 'No CSS macro coverage rows were found.';
} else {
    $lines[] = '| Macro | Status | Calls |';
    $lines[] = '| --- | --- | ---: |';
    foreach (topCssMacroCoverageRows($cssMacroCoverage, 40) as $row) {
        $lines[] = '| `' . escapePipes((string) ($row['macro'] ?? '')) . '` | `' . escapePipes((string) ($row['status'] ?? '')) . '` | ' . scalar($row['callCount'] ?? 0) . ' |';
    }
}

$lines[] = '';
$lines[] = '## Top Gap Prefixes';
$lines[] = '';
if ($topPrefixes === []) {
    $lines[] = 'No gap prefixes remain.';
} else {
    $lines[] = '| Prefix | Gap paths | Uncovered | Needs mapper |';
    $lines[] = '| --- | ---: | ---: | ---: |';
    foreach ($topPrefixes as $row) {
        $lines[] = '| `' . escapePipes($row['prefix']) . '` | ' . $row['total'] . ' | ' . $row['uncovered'] . ' | ' . $row['needs'] . ' |';
    }
}

$lines[] = '';
$lines[] = '## Top Gap Elements';
$lines[] = '';
if ($topElements === []) {
    $lines[] = 'No element-specific mapper gaps remain.';
} else {
    $lines[] = '| Element | Name | Gap paths | Sample gaps |';
    $lines[] = '| --- | --- | ---: | --- |';
    foreach ($topElements as $row) {
        $lines[] = '| `' . escapePipes($row['class']) . '` | ' . escapePipes($row['name']) . ' | ' . $row['count'] . ' | ' . escapePipes(implode(', ', $row['samples'])) . ' |';
    }
}

$lines[] = '';
$lines[] = '## First Gap Paths';
$lines[] = '';
if ($gapPaths === []) {
    $lines[] = 'No gap paths remain.';
} else {
    $lines[] = '| Path | Status | Sample elements |';
    $lines[] = '| --- | --- | --- |';
    foreach (array_slice($gapPaths, 0, 50) as $row) {
        $samples = array_values(array_filter(array_map('strval', $row['sampleElements'] ?? [])));
        $lines[] = '| `' . escapePipes((string) $row['path']) . '` | `' . escapePipes((string) $row['status']) . '` | ' . escapePipes(implode(', ', $samples)) . ' |';
    }
}

echo implode("\n", $lines) . "\n";

/**
 * @param array<int, mixed> $paths
 * @return array<int, array<string, mixed>>
 */
function gapPaths(array $paths): array
{
    $rows = [];
    foreach ($paths as $row) {
        if (!is_array($row)) {
            continue;
        }

        $status = (string) ($row['status'] ?? '');
        $stripSafeWithoutProof = ($row['stripSafe'] ?? false) === true && ($row['hasProof'] ?? false) !== true;
        if (!in_array($status, ['uncovered', 'needs-element-specific-mapper'], true) && !$stripSafeWithoutProof) {
            continue;
        }

        $rows[] = $row;
    }

    usort($rows, static fn (array $a, array $b): int => [(string) ($a['status'] ?? ''), (string) ($a['path'] ?? '')] <=> [(string) ($b['status'] ?? ''), (string) ($b['path'] ?? '')]);

    return $rows;
}

/**
 * @param array<int, array<string, mixed>> $gapPaths
 * @return array<int, array{prefix:string,total:int,uncovered:int,needs:int}>
 */
function topGapPrefixes(array $gapPaths, int $limit): array
{
    $counts = [];
    foreach ($gapPaths as $row) {
        $path = (string) ($row['path'] ?? '');
        $status = (string) ($row['status'] ?? '');
        foreach (prefixes($path) as $prefix) {
            $counts[$prefix]['total'] = ($counts[$prefix]['total'] ?? 0) + 1;
            if ($status === 'uncovered') {
                $counts[$prefix]['uncovered'] = ($counts[$prefix]['uncovered'] ?? 0) + 1;
            }
            if ($status === 'needs-element-specific-mapper') {
                $counts[$prefix]['needs'] = ($counts[$prefix]['needs'] ?? 0) + 1;
            }
        }
    }

    $rows = [];
    foreach ($counts as $prefix => $row) {
        $rows[] = [
            'prefix' => $prefix,
            'total' => (int) ($row['total'] ?? 0),
            'uncovered' => (int) ($row['uncovered'] ?? 0),
            'needs' => (int) ($row['needs'] ?? 0),
        ];
    }

    usort($rows, static fn (array $a, array $b): int => [$b['total'], $a['prefix']] <=> [$a['total'], $b['prefix']]);

    return array_slice($rows, 0, $limit);
}

/**
 * @param array<int, mixed> $elements
 * @return array<int, array{class:string,name:string,count:int,samples:array<int, string>}>
 */
function topGapElements(array $elements, int $limit): array
{
    $rows = [];
    foreach ($elements as $element) {
        if (!is_array($element)) {
            continue;
        }

        $count = (int) ($element['coverageGapCount'] ?? 0);
        if ($count <= 0) {
            continue;
        }

        $gaps = array_values(array_filter(array_map('strval', $element['coverageGaps'] ?? [])));
        $rows[] = [
            'class' => (string) ($element['class'] ?? ''),
            'name' => (string) ($element['name'] ?? ''),
            'count' => $count,
            'samples' => array_slice($gaps, 0, 5),
        ];
    }

    usort($rows, static fn (array $a, array $b): int => [$b['count'], $a['class']] <=> [$a['count'], $b['class']]);

    return array_slice($rows, 0, $limit);
}

/**
 * @return array<int, string>
 */
function prefixes(string $path): array
{
    $parts = explode('.', $path);
    $prefixes = [];
    for ($length = 2; $length <= min(count($parts), 4); $length++) {
        $prefixes[] = implode('.', array_slice($parts, 0, $length));
    }

    return $prefixes;
}

/**
 * @param array<string, mixed> $counts
 * @return array<string, mixed>
 */
function sortedCounts(array $counts): array
{
    ksort($counts);

    return $counts;
}

/**
 * @param array<string, mixed> $counts
 * @return array<string, mixed>
 */
function topCounts(array $counts, int $limit): array
{
    arsort($counts);

    return array_slice($counts, 0, $limit, true);
}

/**
 * @param array<int, mixed> $rows
 * @return array<int, array<string, mixed>>
 */
function topCssPropertyCoverageRows(array $rows, int $limit): array
{
    $normalized = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $normalized[] = $row;
        }
    }

    $rank = [
        'unknown-css-property' => 0,
        'recognized-but-fallback-only' => 1,
        'css-custom-property-runtime' => 2,
        'shared-mapper-recognized' => 3,
    ];

    usort($normalized, static function (array $a, array $b) use ($rank): int {
        $aStatus = (string) ($a['status'] ?? '');
        $bStatus = (string) ($b['status'] ?? '');

        return [
            $rank[$aStatus] ?? 9,
            -(int) ($a['declarationCount'] ?? 0),
            (string) ($a['property'] ?? ''),
        ] <=> [
            $rank[$bStatus] ?? 9,
            -(int) ($b['declarationCount'] ?? 0),
            (string) ($b['property'] ?? ''),
        ];
    });

    return array_slice($normalized, 0, $limit);
}

/**
 * @param array<int, mixed> $rows
 * @return array<int, array<string, mixed>>
 */
function topCssMacroCoverageRows(array $rows, int $limit): array
{
    $normalized = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $normalized[] = $row;
        }
    }

    $rank = [
        'unknown-css-macro' => 0,
        'element-specific-macro-contract' => 1,
        'fallback-or-runtime-macro-contract' => 2,
        'shared-mapper-family' => 3,
    ];

    usort($normalized, static function (array $a, array $b) use ($rank): int {
        $aStatus = (string) ($a['status'] ?? '');
        $bStatus = (string) ($b['status'] ?? '');

        return [
            $rank[$aStatus] ?? 9,
            -(int) ($a['callCount'] ?? 0),
            (string) ($a['macro'] ?? ''),
        ] <=> [
            $rank[$bStatus] ?? 9,
            -(int) ($b['callCount'] ?? 0),
            (string) ($b['macro'] ?? ''),
        ];
    });

    return array_slice($normalized, 0, $limit);
}

/**
 * @param mixed $value
 */
function scalar($value): string
{
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
