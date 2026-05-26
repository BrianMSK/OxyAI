<?php

declare(strict_types=1);

/**
 * Cluster validate-breakdance-coverage.php output into reviewable gap buckets.
 *
 * Usage:
 *   php tools/css-mapping/validate-breakdance-coverage.php <manifest> <contracts.json> \
 *     | php tools/css-mapping/cluster-coverage-gaps.php -
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php cluster-coverage-gaps.php <coverage-json>\n");
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
if (!is_array($coverage) || !is_array($coverage['paths'] ?? null)) {
    fwrite(STDERR, "Invalid coverage JSON\n");
    exit(2);
}

$gapStatuses = ['uncovered', 'needs-element-specific-mapper'];
$prefixCounts = [];
$elementCounts = [];
$statusCounts = [];
$uncoveredPaths = [];

foreach ($coverage['paths'] as $pathRow) {
    if (!is_array($pathRow)) {
        continue;
    }

    $status = (string) ($pathRow['status'] ?? '');
    if (!in_array($status, $gapStatuses, true)) {
        continue;
    }

    $path = (string) ($pathRow['path'] ?? '');
    if ($path === '') {
        continue;
    }

    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    if ($status === 'uncovered') {
        $uncoveredPaths[] = [
            'path' => $path,
            'sampleElements' => $pathRow['sampleElements'] ?? [],
        ];
    }
    foreach (prefixes($path) as $prefix) {
        $prefixCounts[$prefix][$status] = ($prefixCounts[$prefix][$status] ?? 0) + 1;
        $prefixCounts[$prefix]['total'] = ($prefixCounts[$prefix]['total'] ?? 0) + 1;
    }

    foreach (($pathRow['sampleElements'] ?? []) as $element) {
        $element = (string) $element;
        if ($element === '') {
            continue;
        }
        $elementCounts[$element][$status] = ($elementCounts[$element][$status] ?? 0) + 1;
        $elementCounts[$element]['total'] = ($elementCounts[$element]['total'] ?? 0) + 1;
    }
}

$prefixRows = rowsFromCounts($prefixCounts, 80);
$elementRows = rowsFromCounts($elementCounts, 40);

echo json_encode([
    'generatedAt' => gmdate('c'),
    'sourceSummary' => [
        'elementCount' => $coverage['elementCount'] ?? null,
        'uniqueCssDesignPathCount' => $coverage['uniqueCssDesignPathCount'] ?? null,
        'gapCounts' => $coverage['gapCounts'] ?? null,
        'isComplete' => $coverage['isComplete'] ?? null,
    ],
    'gapStatusCounts' => (object) $statusCounts,
    'uncoveredPaths' => $uncoveredPaths,
    'topGapPrefixes' => $prefixRows,
    'topSampleElements' => $elementRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

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
 * @param array<string, array<string, int>> $counts
 * @return array<int, array<string, mixed>>
 */
function rowsFromCounts(array $counts, int $limit): array
{
    $rows = [];
    foreach ($counts as $name => $row) {
        $rows[] = [
            'name' => $name,
            'total' => $row['total'] ?? 0,
            'uncovered' => $row['uncovered'] ?? 0,
            'needs-element-specific-mapper' => $row['needs-element-specific-mapper'] ?? 0,
        ];
    }

    usort($rows, static fn (array $a, array $b): int => [(int) $b['total'], (string) $a['name']] <=> [(int) $a['total'], (string) $b['name']]);

    return array_slice($rows, 0, $limit);
}
