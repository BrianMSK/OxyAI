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
    'lighter' => 300,
    'bolder' => 700,
];

foreach ($keywordCases as $keyword => $expected) {
    $design = $extractor->toOxygenProperties(['font-weight' => $keyword]);
    assert(($design['typography']['font_weight']['breakpoint_base'] ?? null) === $expected);
}

echo "font-weight-schema-ok\n";
