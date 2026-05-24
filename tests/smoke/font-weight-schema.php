<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/vendor/oxygen-html-converter/src/ElementTypes.php';
require $root . '/vendor/oxygen-html-converter/src/StyleExtractor.php';

use OxyHtmlConverter\StyleExtractor;

$extractor = new StyleExtractor();

$numeric = $extractor->toOxygenProperties(['font-weight' => '700']);
$numericWeight = $numeric['typography']['font_weight']['breakpoint_base'] ?? null;

assert($numericWeight === 700);
assert(str_contains(json_encode($numeric), '"breakpoint_base":700'));
assert(!str_contains(json_encode($numeric), '"breakpoint_base":"700"'));

$keywordCases = [
    'normal' => 400,
    'bold' => 700,
];

foreach ($keywordCases as $keyword => $expected) {
    $design = $extractor->toOxygenProperties(['font-weight' => $keyword]);
    assert(($design['typography']['font_weight']['breakpoint_base'] ?? null) === $expected);
}

$relative = $extractor->toOxygenProperties(['font-weight' => 'lighter']);
assert(($relative['typography']['font_weight']['breakpoint_base'] ?? null) === 'lighter');

$cssWide = $extractor->toOxygenProperties(['font-weight' => 'revert-layer']);
assert(($cssWide['typography']['font_weight']['breakpoint_base'] ?? null) === 'revert-layer');

$variable = $extractor->toOxygenProperties(['font-weight' => 'var(--BrandWeight)']);
assert(($variable['typography']['font_weight']['breakpoint_base'] ?? null) === 'var(--BrandWeight)');
assert(!str_contains(json_encode($variable), 'brandweight'));

echo "font-weight-schema-ok\n";
