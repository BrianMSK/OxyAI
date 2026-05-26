<?php

declare(strict_types=1);

/**
 * Validate an Oxygen/Breakdance contract inventory against an explicit coverage
 * manifest. This is the review gate for moving toward 100% property coverage:
 * every css.twig/universal design path must have a manifest rule before it can
 * be treated as intentionally mapped, intentionally fallback-only, or still gap.
 *
 * Usage:
 *   php tools/css-mapping/validate-breakdance-coverage.php <manifest-json> <contracts-json>
 *   php tools/css-mapping/validate-breakdance-coverage.php --summary-only <manifest-json> <contracts-json>
 */

$summaryOnly = false;
$args = array_slice($argv, 1);
if (in_array('--summary-only', $args, true)) {
    $summaryOnly = true;
    $args = array_values(array_filter($args, static fn (string $arg): bool => $arg !== '--summary-only'));
}

if (count($args) !== 2) {
    fwrite(STDERR, "Usage: php validate-breakdance-coverage.php [--summary-only] <manifest-json> <contracts-json>\n");
    exit(2);
}

[$manifestPath, $inventoryPath] = $args;
$manifest = readJsonFile($manifestPath, 'coverage manifest');
$inventoryInput = $inventoryPath === '-'
    ? stream_get_contents(STDIN)
    : file_get_contents($inventoryPath);
if ($inventoryInput === false) {
    fwrite(STDERR, "Unable to read {$inventoryPath}\n");
    exit(2);
}

$inventory = json_decode($inventoryInput, true);
if (!is_array($inventory) || !is_array($inventory['elements'] ?? null)) {
    fwrite(STDERR, "Invalid contract inventory JSON\n");
    exit(2);
}

$rules = $manifest['rules'] ?? null;
if (!is_array($rules)) {
    fwrite(STDERR, "Coverage manifest must contain a rules array\n");
    exit(2);
}
$elementRules = is_array($manifest['elementRules'] ?? null) ? $manifest['elementRules'] : [];

$validStatuses = array_keys(is_array($manifest['statuses'] ?? null) ? $manifest['statuses'] : []);
$pathRows = [];
$elementRows = [];
$cssDeclarationPropertyCounts = [];
$cssDeclarationPropertyElements = [];
$cssDeclarationRows = [];
$cssMacroCounts = [];
$cssMacroRows = [];
$cssPropertyCoverage = cssPropertyCoverageConfig($manifest);
$cssMacroCoverage = cssMacroCoverageConfig($manifest);

foreach ($inventory['elements'] as $element) {
    if (!is_array($element)) {
        continue;
    }

    $class = (string) ($element['class'] ?? '');
    $cssDesignPaths = array_values(array_unique(array_filter(array_map('strval', $element['cssDesignPaths'] ?? []))));
    $cssDeclarations = is_array($element['cssDeclarations'] ?? null) ? $element['cssDeclarations'] : [];
    $cssMacroCalls = is_array($element['cssMacroCalls'] ?? null) ? $element['cssMacroCalls'] : [];
    $elementGaps = [];

    foreach ($cssDesignPaths as $path) {
        $rule = findRule($path, $rules, $class, $elementRules);
        $status = is_array($rule) ? (string) ($rule['status'] ?? '') : 'uncovered';
        if ($status === '' || ($validStatuses !== [] && !in_array($status, $validStatuses, true))) {
            $status = 'uncovered';
        }

        $pathRows[$path]['path'] = $path;
        $pathRows[$path]['statuses'][$status] = true;
        $pathRows[$path]['rulePaths'][(string) (is_array($rule) ? ($rule['path'] ?? null) : '')] = true;
        $pathRows[$path]['stripSafe'] = ($pathRows[$path]['stripSafe'] ?? false) || (is_array($rule) ? (bool) ($rule['stripSafe'] ?? false) : false);
        $pathRows[$path]['hasProof'] = ($pathRows[$path]['hasProof'] ?? false) || (is_array($rule) && hasProof($rule));
        $pathRows[$path]['elements'][$class] = true;

        $isGap = false;
        if ($status === 'uncovered') {
            $isGap = true;
        }
        if ($status === 'needs-element-specific-mapper') {
            $isGap = true;
        }
        if (is_array($rule) && ($rule['stripSafe'] ?? false) === true && !hasProof($rule)) {
            $isGap = true;
        }

        if ($isGap) {
            $elementGaps[] = $path;
        }
    }

    foreach ($cssDeclarations as $declaration) {
        if (!is_array($declaration)) {
            continue;
        }

        $property = (string) ($declaration['property'] ?? '');
        if ($property === '') {
            continue;
        }

        $cssDeclarationPropertyCounts[$property] = ($cssDeclarationPropertyCounts[$property] ?? 0) + 1;
        $cssDeclarationPropertyElements[$property][$class] = true;
        $cssDeclarationRows[] = [
            'element' => $class,
            'property' => $property,
            'selector' => $declaration['selector'] ?? null,
            'designPaths' => array_values(array_filter(array_map('strval', $declaration['designPaths'] ?? []))),
            'contentPaths' => array_values(array_filter(array_map('strval', $declaration['contentPaths'] ?? []))),
            'isCustomProperty' => (bool) ($declaration['isCustomProperty'] ?? false),
        ];
    }

    foreach ($cssMacroCalls as $macroCall) {
        if (!is_array($macroCall)) {
            continue;
        }

        $macro = (string) ($macroCall['macro'] ?? '');
        if ($macro !== '') {
            $cssMacroCounts[$macro] = ($cssMacroCounts[$macro] ?? 0) + 1;
            $cssMacroRows[] = [
                'element' => $class,
                'macro' => $macro,
                'arguments' => $macroCall['arguments'] ?? null,
                'designPaths' => array_values(array_filter(array_map('strval', $macroCall['designPaths'] ?? []))),
                'contentPaths' => array_values(array_filter(array_map('strval', $macroCall['contentPaths'] ?? []))),
            ];
        }
    }

    $elementRows[] = [
        'class' => $class,
        'name' => $element['name'] ?? null,
        'category' => $element['category'] ?? null,
        'cssDesignPathCount' => count($cssDesignPaths),
        'cssDeclarationCount' => count($cssDeclarations),
        'cssMacroCallCount' => count($cssMacroCalls),
        'coverageGapCount' => count($elementGaps),
        'coverageGaps' => $elementGaps,
    ];
}

$universal = is_array($inventory['universal'] ?? null) ? $inventory['universal'] : [];
foreach (array_values(array_unique(array_filter(array_map('strval', $universal['designPaths'] ?? [])))) as $path) {
    addPathOccurrence($pathRows, $path, 'universal', $rules);
}
foreach (($universal['affectedCssProperties'] ?? []) as $mapping) {
    if (!is_array($mapping)) {
        continue;
    }
    $path = normalizeManifestPath((string) ($mapping['affectedPropertyPath'] ?? ''));
    if ($path !== '') {
        addPathOccurrence($pathRows, $path, 'universal:' . (string) ($mapping['cssProperty'] ?? 'cssProperty'), $rules);
    }
}

ksort($pathRows);

$statusCounts = [];
$gapCounts = [
    'uncovered' => 0,
    'needs-element-specific-mapper' => 0,
    'strip-safe-without-proof' => 0,
];
$paths = [];
foreach ($pathRows as $row) {
    $status = aggregatePathStatus(is_array($row['statuses'] ?? null) ? array_keys($row['statuses']) : []);
    $row['status'] = $status;
    $rulePaths = array_values(array_filter(array_keys(is_array($row['rulePaths'] ?? null) ? $row['rulePaths'] : [])));
    sort($rulePaths);
    $row['rulePath'] = $rulePaths[0] ?? null;

    $status = (string) $row['status'];
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    if ($status === 'uncovered') {
        $gapCounts['uncovered']++;
    }
    if ($status === 'needs-element-specific-mapper') {
        $gapCounts['needs-element-specific-mapper']++;
    }
    if (($row['stripSafe'] ?? false) === true && ($row['hasProof'] ?? false) !== true) {
        $gapCounts['strip-safe-without-proof']++;
    }

    $elements = array_keys($row['elements']);
    sort($elements);
    $paths[] = [
        'path' => $row['path'],
        'status' => $row['status'],
        'rulePath' => $row['rulePath'],
        'stripSafe' => $row['stripSafe'] ?? null,
        'elementCount' => count($elements),
        'sampleElements' => array_slice($elements, 0, 8),
    ];
}

usort($elementRows, static fn (array $a, array $b): int => [(int) $b['coverageGapCount'], (string) $a['class']] <=> [(int) $a['coverageGapCount'], (string) $b['class']]);

$cssDeclarationPropertyGapCounts = cssDeclarationPropertyGapCounts($cssDeclarationPropertyCounts, $cssPropertyCoverage);
$cssMacroGapCounts = cssMacroGapCounts($cssMacroCounts, $cssMacroCoverage);

$summary = [
    'generatedAt' => gmdate('c'),
    'manifestVersion' => $manifest['version'] ?? null,
    'elementCount' => count($elementRows),
    'uniqueCssDesignPathCount' => count($paths),
    'cssDeclarationCount' => count($cssDeclarationRows),
    'uniqueCssDeclarationPropertyCount' => count($cssDeclarationPropertyCounts),
    'cssMacroCallCount' => array_sum($cssMacroCounts),
    'statusCounts' => (object) $statusCounts,
    'gapCounts' => (object) $gapCounts,
    'cssDeclarationPropertyGapCounts' => (object) $cssDeclarationPropertyGapCounts,
    'cssMacroGapCounts' => (object) $cssMacroGapCounts,
    'isComplete' => array_sum($gapCounts) === 0
        && array_sum($cssDeclarationPropertyGapCounts) === 0
        && array_sum($cssMacroGapCounts) === 0,
    'completionCriteria' => [
        'no uncovered css.twig or universal design paths',
        'no paths left as needs-element-specific-mapper',
        'no stripSafe=true rule without explicit compile proof metadata',
        'no unknown direct CSS declaration properties',
        'no unknown css.twig macro calls',
    ],
];

if (!$summaryOnly) {
    $summary['paths'] = $paths;
    $summary['elements'] = $elementRows;
    ksort($cssDeclarationPropertyCounts);
    arsort($cssMacroCounts);
    $summary['cssDeclarationProperties'] = $cssDeclarationPropertyCounts;
    $summary['cssDeclarationPropertyCoverage'] = cssDeclarationPropertyRows(
        $cssDeclarationPropertyCounts,
        $cssDeclarationPropertyElements,
        $cssPropertyCoverage
    );
    $summary['cssDeclarations'] = $cssDeclarationRows;
    $summary['cssMacroCalls'] = $cssMacroCounts;
    $summary['cssMacroRows'] = $cssMacroRows;
    $summary['cssMacroCoverage'] = cssMacroRows($cssMacroCounts, $cssMacroCoverage);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

/**
 * @return array<string, mixed>
 */
function readJsonFile(string $path, string $label): array
{
    $input = file_get_contents($path);
    if ($input === false) {
        fwrite(STDERR, "Unable to read {$label}: {$path}\n");
        exit(2);
    }

    $decoded = json_decode($input, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Invalid {$label} JSON: {$path}\n");
        exit(2);
    }

    return $decoded;
}

/**
 * @param array<int, mixed> $rules
 * @return array<string, mixed>|null
 */
function findRule(string $path, array $rules, ?string $class = null, array $elementRules = []): ?array
{
    $elementRule = $class !== null ? findElementRule($class, $path, $elementRules) : null;
    if ($elementRule !== null) {
        return $elementRule;
    }

    return findPathRule($path, $rules);
}

/**
 * @param array<int, mixed> $rules
 * @return array<string, mixed>|null
 */
function findElementRule(string $class, string $path, array $rules): ?array
{
    $best = null;
    $bestLength = -1;

    foreach ($rules as $rule) {
        if (!is_array($rule) || (string) ($rule['class'] ?? '') !== $class) {
            continue;
        }

        $rulePath = (string) ($rule['path'] ?? '');
        if ($rulePath === '') {
            continue;
        }

        $match = (string) ($rule['match'] ?? 'exact');
        $matches = $match === 'prefix'
            ? ($path === $rulePath || str_starts_with($path, $rulePath . '.'))
            : $path === $rulePath;

        if ($matches && strlen($rulePath) > $bestLength) {
            $best = $rule;
            $bestLength = strlen($rulePath);
        }
    }

    return $best;
}

/**
 * @param array<int, mixed> $rules
 * @return array<string, mixed>|null
 */
function findPathRule(string $path, array $rules): ?array
{
    $best = null;
    $bestLength = -1;

    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }

        $rulePath = (string) ($rule['path'] ?? '');
        if ($rulePath === '') {
            continue;
        }

        $match = (string) ($rule['match'] ?? 'exact');
        $matches = $match === 'prefix'
            ? ($path === $rulePath || str_starts_with($path, $rulePath . '.'))
            : $path === $rulePath;

        if ($matches && strlen($rulePath) > $bestLength) {
            $best = $rule;
            $bestLength = strlen($rulePath);
        }
    }

    return $best;
}

/**
 * @param array<int, string> $statuses
 */
function aggregatePathStatus(array $statuses): string
{
    foreach (['uncovered', 'needs-element-specific-mapper'] as $gapStatus) {
        if (in_array($gapStatus, $statuses, true)) {
            return $gapStatus;
        }
    }

    foreach (['requires-css-fallback', 'element-specific-contract', 'native-with-guardrails', 'native-shared-mapper', 'content-or-render-runtime'] as $status) {
        if (in_array($status, $statuses, true)) {
            return $status;
        }
    }

    return $statuses[0] ?? 'uncovered';
}

/**
 * @param array<string, mixed> $rule
 */
function hasProof(array $rule): bool
{
    $proof = $rule['proof'] ?? null;
    if (!is_array($proof)) {
        return false;
    }

    return is_string($proof['jsonShapeTest'] ?? null)
        && $proof['jsonShapeTest'] !== ''
        && is_string($proof['compiledCssTest'] ?? null)
        && $proof['compiledCssTest'] !== '';
}

/**
 * @param array<string, array<string, mixed>> $pathRows
 */
function addPathOccurrence(array &$pathRows, string $path, string $source, array $rules = []): void
{
    $path = normalizeManifestPath($path);
    if ($path === '') {
        return;
    }

    $pathRows[$path]['path'] = $path;
    $pathRows[$path]['elements'][$source] = true;

    $rule = findPathRule($path, $rules);
    $status = is_array($rule) ? (string) ($rule['status'] ?? '') : 'uncovered';
    if ($status === '') {
        $status = 'uncovered';
    }

    $pathRows[$path]['statuses'][$status] = true;
    $pathRows[$path]['rulePaths'][(string) (is_array($rule) ? ($rule['path'] ?? null) : '')] = true;
    $pathRows[$path]['stripSafe'] = ($pathRows[$path]['stripSafe'] ?? false) || (is_array($rule) ? (bool) ($rule['stripSafe'] ?? false) : false);
    $pathRows[$path]['hasProof'] = ($pathRows[$path]['hasProof'] ?? false) || (is_array($rule) && hasProof($rule));
}

function normalizeManifestPath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    $path = str_replace('%%BREAKPOINT%%', 'breakpoint_base', $path);

    return preg_replace('/\.+/', '.', $path) ?? $path;
}

/**
 * @param array<string, mixed> $manifest
 * @return array{recognized: array<string, true>, fallbackOnly: array<string, true>, customPropertyStatus: string}
 */
function cssPropertyCoverageConfig(array $manifest): array
{
    $config = is_array($manifest['cssDeclarationPropertyCoverage'] ?? null)
        ? $manifest['cssDeclarationPropertyCoverage']
        : [];

    return [
        'recognized' => array_fill_keys(array_values(array_filter(array_map('strval', $config['recognizedBySharedMapper'] ?? []))), true),
        'fallbackOnly' => array_fill_keys(array_values(array_filter(array_map('strval', $config['recognizedButFallbackOnly'] ?? []))), true),
        'customPropertyStatus' => (string) ($config['customPropertyStatus'] ?? 'css-custom-property-runtime'),
    ];
}

/**
 * @param array<string, int> $propertyCounts
 * @param array{recognized: array<string, true>, fallbackOnly: array<string, true>, customPropertyStatus: string} $coverage
 * @return array<string, int>
 */
function cssDeclarationPropertyGapCounts(array $propertyCounts, array $coverage): array
{
    $counts = [
        'unknown-css-property' => 0,
    ];

    foreach ($propertyCounts as $property => $_count) {
        if (cssDeclarationPropertyStatus((string) $property, $coverage) === 'unknown-css-property') {
            $counts['unknown-css-property']++;
        }
    }

    return $counts;
}

/**
 * @param array<string, int> $propertyCounts
 * @param array<string, array<string, true>> $propertyElements
 * @param array{recognized: array<string, true>, fallbackOnly: array<string, true>, customPropertyStatus: string} $coverage
 * @return array<int, array<string, mixed>>
 */
function cssDeclarationPropertyRows(array $propertyCounts, array $propertyElements, array $coverage): array
{
    $rows = [];

    foreach ($propertyCounts as $property => $count) {
        $elements = array_keys($propertyElements[$property] ?? []);
        sort($elements);
        $rows[] = [
            'property' => $property,
            'status' => cssDeclarationPropertyStatus((string) $property, $coverage),
            'declarationCount' => (int) $count,
            'elementCount' => count($elements),
            'sampleElements' => array_slice($elements, 0, 8),
        ];
    }

    usort($rows, static fn (array $a, array $b): int => [(string) $a['status'], (string) $a['property']] <=> [(string) $b['status'], (string) $b['property']]);

    return $rows;
}

/**
 * @param array{recognized: array<string, true>, fallbackOnly: array<string, true>, customPropertyStatus: string} $coverage
 */
function cssDeclarationPropertyStatus(string $property, array $coverage): string
{
    if (str_starts_with($property, '--')) {
        return $coverage['customPropertyStatus'];
    }

    if (isset($coverage['fallbackOnly'][$property])) {
        return 'recognized-but-fallback-only';
    }

    if (isset($coverage['recognized'][$property])) {
        return 'shared-mapper-recognized';
    }

    return 'unknown-css-property';
}

/**
 * @param array<string, mixed> $manifest
 * @return array{shared: array<string, true>, elementSpecific: array<string, true>, fallbackRuntime: array<string, true>}
 */
function cssMacroCoverageConfig(array $manifest): array
{
    $config = is_array($manifest['cssMacroCoverage'] ?? null)
        ? $manifest['cssMacroCoverage']
        : [];

    return [
        'shared' => array_fill_keys(array_values(array_filter(array_map('strval', $config['sharedMapperFamilies'] ?? []))), true),
        'elementSpecific' => array_fill_keys(array_values(array_filter(array_map('strval', $config['elementSpecificContracts'] ?? []))), true),
        'fallbackRuntime' => array_fill_keys(array_values(array_filter(array_map('strval', $config['fallbackOrRuntimeContracts'] ?? []))), true),
    ];
}

/**
 * @param array<string, int> $macroCounts
 * @param array{shared: array<string, true>, elementSpecific: array<string, true>, fallbackRuntime: array<string, true>} $coverage
 * @return array<string, int>
 */
function cssMacroGapCounts(array $macroCounts, array $coverage): array
{
    $counts = [
        'unknown-css-macro' => 0,
    ];

    foreach ($macroCounts as $macro => $_count) {
        if (cssMacroStatus((string) $macro, $coverage) === 'unknown-css-macro') {
            $counts['unknown-css-macro']++;
        }
    }

    return $counts;
}

/**
 * @param array<string, int> $macroCounts
 * @param array{shared: array<string, true>, elementSpecific: array<string, true>, fallbackRuntime: array<string, true>} $coverage
 * @return array<int, array<string, mixed>>
 */
function cssMacroRows(array $macroCounts, array $coverage): array
{
    $rows = [];

    foreach ($macroCounts as $macro => $count) {
        $rows[] = [
            'macro' => $macro,
            'status' => cssMacroStatus((string) $macro, $coverage),
            'callCount' => (int) $count,
        ];
    }

    usort($rows, static fn (array $a, array $b): int => [(string) $a['status'], (string) $a['macro']] <=> [(string) $b['status'], (string) $b['macro']]);

    return $rows;
}

/**
 * @param array{shared: array<string, true>, elementSpecific: array<string, true>, fallbackRuntime: array<string, true>} $coverage
 */
function cssMacroStatus(string $macro, array $coverage): string
{
    if (isset($coverage['shared'][$macro])) {
        return 'shared-mapper-family';
    }

    if (isset($coverage['elementSpecific'][$macro])) {
        return 'element-specific-macro-contract';
    }

    if (isset($coverage['fallbackRuntime'][$macro])) {
        return 'fallback-or-runtime-macro-contract';
    }

    return 'unknown-css-macro';
}
