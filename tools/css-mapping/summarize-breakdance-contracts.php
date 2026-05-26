<?php

declare(strict_types=1);

/**
 * Summarize Breakdance contract inventory into a reviewable coverage matrix.
 *
 * Usage:
 *   php tools/css-mapping/extract-breakdance-contracts.php <roots...> > contracts.json
 *   php tools/css-mapping/summarize-breakdance-contracts.php contracts.json
 *   php tools/css-mapping/summarize-breakdance-contracts.php --summary-only contracts.json
 */

$summaryOnly = false;
$args = array_slice($argv, 1);
if (in_array('--summary-only', $args, true)) {
    $summaryOnly = true;
    $args = array_values(array_filter($args, static fn (string $arg): bool => $arg !== '--summary-only'));
}

if (count($args) !== 1) {
    fwrite(STDERR, "Usage: php summarize-breakdance-contracts.php [--summary-only] <contracts-json>\n");
    exit(2);
}

$inputPath = $args[0];
$input = $inputPath === '-'
    ? stream_get_contents(STDIN)
    : file_get_contents($inputPath);
if ($input === false) {
    fwrite(STDERR, "Unable to read {$inputPath}\n");
    exit(2);
}

$inventory = json_decode($input, true);
if (!is_array($inventory) || !is_array($inventory['elements'] ?? null)) {
    fwrite(STDERR, "Invalid contract inventory JSON\n");
    exit(2);
}

$pathRows = [];
$elementRows = [];

foreach ($inventory['elements'] as $element) {
    if (!is_array($element)) {
        continue;
    }

    $class = (string) ($element['class'] ?? '');
    $cssDesignPaths = array_values(array_unique(array_filter(array_map('strval', $element['cssDesignPaths'] ?? []))));
    $htmlContentPaths = array_values(array_unique(array_filter(array_map('strval', $element['htmlContentPaths'] ?? []))));
    $htmlDesignPaths = array_values(array_unique(array_filter(array_map('strval', $element['htmlDesignPaths'] ?? []))));

    $statuses = [];
    foreach ($cssDesignPaths as $path) {
        $status = classifyDesignPath($path);
        $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        $pathRows[$path]['path'] = $path;
        $pathRows[$path]['status'] = $status;
        $pathRows[$path]['elements'][$class] = true;
    }

    $elementRows[] = [
        'class' => $class,
        'name' => $element['name'] ?? null,
        'category' => $element['category'] ?? null,
        'cssDesignPathCount' => count($cssDesignPaths),
        'htmlContentPathCount' => count($htmlContentPaths),
        'htmlDesignPathCount' => count($htmlDesignPaths),
        'cssDesignStatusCounts' => (object) $statuses,
        'unmappedCssDesignPaths' => array_values(array_filter(
            $cssDesignPaths,
            static fn (string $path): bool => classifyDesignPath($path) === 'needs-element-specific-mapper'
        )),
    ];
}

ksort($pathRows);

$statusCounts = [];
$paths = [];
foreach ($pathRows as $path => $row) {
    $status = (string) $row['status'];
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    $elements = array_keys($row['elements']);
    sort($elements);
    $paths[] = [
        'path' => $path,
        'status' => $status,
        'elementCount' => count($elements),
        'sampleElements' => array_slice($elements, 0, 8),
    ];
}

$summary = [
    'generatedAt' => gmdate('c'),
    'elementCount' => count($elementRows),
    'uniqueCssDesignPathCount' => count($paths),
    'statusCounts' => (object) $statusCounts,
    'coverageMeaning' => [
        'native-shared-mapper' => 'Path belongs to a shared Breakdance/Oxygen family that current mapper infrastructure can represent, but still needs per-element compile proof before cssFallbackCanBeStripped.',
        'native-with-guardrails' => 'Path has explicit guardrails because some values persist but do not compile.',
        'content-or-render-runtime' => 'Path affects HTML/content/runtime logic rather than CSS mapping from source CSS.',
        'requires-css-fallback' => 'Path uses nested/pseudo/dynamic element CSS that should keep source CSS unless a dedicated mapper and compile proof exists.',
        'needs-element-specific-mapper' => 'Path is discovered from source contracts and currently has no declared mapping status.',
    ],
];

if (!$summaryOnly) {
    usort($elementRows, static fn (array $a, array $b): int => strcmp((string) $a['class'], (string) $b['class']));
    $summary['paths'] = $paths;
    $summary['elements'] = $elementRows;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function classifyDesignPath(string $path): string
{
    $path = trim($path);

    if ($path === '') {
        return 'needs-element-specific-mapper';
    }

    $nativePrefixes = [
        'design.spacing',
        'design.container.padding',
        'design.container.borders',
        'design.container.width',
        'design.container.min_height',
        'design.container.height',
        'design.size.width',
        'design.size.min_height',
        'design.size.height',
        'design.borders',
        'design.background',
        'design.typography',
        'design.text_colors',
    ];

    foreach ($nativePrefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '.')) {
            return 'native-shared-mapper';
        }
    }

    $guardedPrefixes = [
        'design.layout',
        'design.layout_v2',
        'design.container.margin',
    ];

    foreach ($guardedPrefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '.')) {
            return 'native-with-guardrails';
        }
    }

    $runtimePrefixes = [
        'design.tabs',
        'design.slider',
        'design.icon',
        'design.form',
    ];

    foreach ($runtimePrefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '.')) {
            return 'content-or-render-runtime';
        }
    }

    $fallbackPrefixes = [
        'design.elements',
        'design.product_wrapper',
        'design.filter_bar',
        'design.pagination',
        'design.advanced',
        'design.woo',
        'design.price_filter',
        'design.attribute_filter',
        'design.cart_contents',
        'design.your_order',
        'design.notices',
    ];

    foreach ($fallbackPrefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '.')) {
            return 'requires-css-fallback';
        }
    }

    return 'needs-element-specific-mapper';
}
