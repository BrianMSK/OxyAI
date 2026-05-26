<?php

declare(strict_types=1);

/**
 * Extract the Oxygen selector path map embedded in OxyDance Pilot's builder JS.
 *
 * Usage:
 *   php tools/css-mapping/extract-oxydance-selector-map.php \
 *     "<path-to-oxydance-builder-ai.js>"
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php extract-oxydance-selector-map.php <builder-ai.js>\n");
    exit(2);
}

$path = (string) $argv[1];
$source = file_get_contents($path);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$path}\n");
    exit(2);
}

if (!preg_match('/\b(?:var|let|const)\s+PATH_MAP\s*=\s*\{(.*?)\}\s*;/s', $source, $matches)) {
    fwrite(STDERR, "Unable to find PATH_MAP in {$path}\n");
    exit(2);
}

$body = $matches[1];
preg_match_all('/[\'"]([^\'"]+)[\'"]\s*:\s*[\'"]([^\'"]+)[\'"]/', $body, $entries, PREG_SET_ORDER);

$mappings = [];
foreach ($entries as $entry) {
    $mappings[] = [
        'from' => $entry[1],
        'to' => $entry[2],
        'status' => $entry[2] === '__flex_wrap__' ? 'post-process' : 'direct',
    ];
}

$specialCases = [
    'effects.box_shadow.shadows' => 'Convert raw shadow objects to Oxygen array entries with x/y/blur/spread length objects, color, position, disabled.',
    'background.layers' => 'Convert Breakdance background layers to Oxygen background.backgrounds, including image size, repeat, attachment, blend, and position normalization.',
    'layout.flex_wrap' => 'Merge flex_wrap into layout.flex_direction because Oxygen emits flex-flow from flex_direction.',
    'effects.transition' => 'Normalize custom transition property names and timing_function to easing; drop malformed transition entries.',
    'effects.opacity' => 'Convert CSS 0-1 opacity scale to Oxygen 0-100 scale.',
    'custom_css.css' => 'Replace %%SELECTOR%% with Oxygen :selector in custom CSS.',
];

echo json_encode([
    'generatedAt' => gmdate('c'),
    'source' => str_replace('\\', '/', $path),
    'mappingCount' => count($mappings),
    'mappings' => $mappings,
    'specialCases' => $specialCases,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
