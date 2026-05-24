<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/vendor/oxygen-html-converter/src/ElementTypes.php';
require $root . '/vendor/oxygen-html-converter/src/StyleExtractor.php';

use OxyHtmlConverter\StyleExtractor;

$extractor = new StyleExtractor();

// CSS function values (min/clamp/calc) on size properties used to fall through
// normalizeLength as raw strings, which Oxygen's IO-TS schema then rejected
// on builder load. They must now be dropped entirely.
$design = $extractor->toOxygenProperties([
    'width' => 'min(100% - 48px, 1280px)',
    'max-width' => 'clamp(320px, 80vw, 1280px)',
    'height' => 'calc(100vh - 80px)',
    'min-height' => 'fit-content',
]);

$json = (string) json_encode($design);
assert(!str_contains($json, 'min(100%'));
assert(!str_contains($json, 'clamp('));
assert(!str_contains($json, 'calc('));
assert(!str_contains($json, 'fit-content'));
assert(!isset($design['size']['width']));
assert(!isset($design['size']['max_width']));
assert(!isset($design['size']['height']));
assert(!isset($design['size']['min_height']));

// `auto` stays in the small keyword whitelist and still serialises as a string.
$auto = $extractor->toOxygenProperties(['width' => 'auto']);
assert(($auto['size']['width']['breakpoint_base'] ?? null) === 'auto');

// Parseable number+unit values still produce the structured shape.
$structured = $extractor->toOxygenProperties(['width' => '320px']);
assert(($structured['size']['width']['breakpoint_base']['number'] ?? null) === 320);
assert(($structured['size']['width']['breakpoint_base']['unit'] ?? null) === 'px');

// Unitless line-height (e.g. raw 1.75) is not a parseable length — it must be
// dropped so the builder's typography.line_height decoder does not see a bare
// string where it expects `{number, unit, style}`.
$lineHeight = $extractor->toOxygenProperties([
    'line-height' => '1.75',
    'letter-spacing' => 'var(--track)',
    'font-size' => 'clamp(34px, 4vw, 54px)',
]);
$lineHeightJson = (string) json_encode($lineHeight);
assert(!isset($lineHeight['typography']['line_height']));
assert(!isset($lineHeight['typography']['letter_spacing']));
assert(!isset($lineHeight['typography']['font_size']));
assert(!str_contains($lineHeightJson, '1.75'));
assert(!str_contains($lineHeightJson, 'clamp('));
assert(!str_contains($lineHeightJson, '--track'));

// Shorthand padding with one unparseable side keeps the parseable ones intact
// and skips the bad side — instead of writing a corrupted `top:"calc(..)"`.
$padding = $extractor->toOxygenProperties([
    'padding' => 'calc(2vw + 4px) 16px 24px 12px',
]);
$paddingBox = $padding['container']['padding']['breakpoint_base'] ?? null;
assert(is_array($paddingBox));
assert(!isset($paddingBox['top']));
assert(($paddingBox['right']['style'] ?? null) === '16px');
assert(($paddingBox['bottom']['style'] ?? null) === '24px');
assert(($paddingBox['left']['style'] ?? null) === '12px');

// Border-radius with calc on one corner: bad corner skipped, good corners stored.
$radius = $extractor->toOxygenProperties([
    'border-radius' => 'calc(50% - 2px) 8px 8px 8px',
]);
$radiusBox = $radius['container']['borders']['radius']['breakpoint_base'] ?? null;
assert(is_array($radiusBox));
assert(!isset($radiusBox['topLeft']));
assert(($radiusBox['topRight']['style'] ?? null) === '8px');
assert(($radiusBox['bottomRight']['style'] ?? null) === '8px');
assert(($radiusBox['bottomLeft']['style'] ?? null) === '8px');

echo "iots-length-sanitization-ok\n";
