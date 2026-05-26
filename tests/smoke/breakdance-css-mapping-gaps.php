<?php

declare(strict_types=1);

use OxyHtmlConverter\ElementTypes;
use OxyHtmlConverter\Services\ConversionAuditBuilder;
use OxyHtmlConverter\StyleExtractor;

$root = dirname(__DIR__, 2);

require $root . '/vendor/oxygen-html-converter/src/ElementTypes.php';
require $root . '/vendor/oxygen-html-converter/src/StyleExtractor.php';
require $root . '/vendor/oxygen-html-converter/src/Services/ConversionAuditBuilder.php';

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}

$extractor = new StyleExtractor();

$columnsDesign = $extractor->toOxygenProperties([
    'width' => '1280px',
    'margin' => '0 auto',
    'justify-content' => 'center',
], ElementTypes::ESSENTIAL_COLUMNS);

$columnsMargin = $columnsDesign['container']['margin']['breakpoint_base'] ?? null;
assert(is_array($columnsMargin));
assert(($columnsMargin['top']['style'] ?? null) === '0px');
assert(($columnsMargin['bottom']['style'] ?? null) === '0px');
assert(!array_key_exists('left', $columnsMargin));
assert(!array_key_exists('right', $columnsMargin));
assert(!isset($columnsDesign['layout']['justify_content']));
assert(($columnsDesign['size']['width']['breakpoint_base']['style'] ?? null) === '1280px');
assert($extractor->supportsDeclarationsFully(['margin' => '0 auto'], ElementTypes::ESSENTIAL_COLUMNS) === false);
assert($extractor->supportsDeclarationsFully(['margin-left' => 'auto'], ElementTypes::ESSENTIAL_COLUMN) === false);
assert($extractor->supportsDeclarationsFully(['justify-content' => 'center'], ElementTypes::ESSENTIAL_COLUMNS) === false);

$columnDesign = $extractor->toOxygenProperties([
    'align-items' => 'center',
], ElementTypes::ESSENTIAL_COLUMN);

assert(($columnDesign['layout']['align_items']['breakpoint_base'] ?? null) === 'center');
assert(($columnDesign['layout']['align']['breakpoint_base'] ?? null) === 'center');
assert(($columnDesign['layout']['vertical_align']['breakpoint_base'] ?? null) === 'center');

$partialColumnAudit = (new ConversionAuditBuilder())->build([
    'stats' => [
        'elements' => 1,
        'warnings' => [],
        'errors' => [],
        'info' => [],
    ],
    'element' => [
        'data' => [
            'type' => ElementTypes::ESSENTIAL_COLUMN,
            'properties' => [
                'design' => [
                    'layout' => [
                        'align_items' => [
                            'breakpoint_base' => 'center',
                        ],
                    ],
                ],
            ],
        ],
        'children' => [],
    ],
], []);

assert(in_array(
    'Breakdance partial alignment: EssentialElements\\Column only compiles alignment reliably when align_items, align, and vertical_align are written together.',
    $partialColumnAudit['diagnostics']['warnings'],
    true
));

$deadWriteAudit = (new ConversionAuditBuilder())->build([
    'stats' => [
        'elements' => 1,
        'warnings' => [],
        'errors' => [],
        'info' => [],
    ],
    'element' => [
        'data' => [
            'type' => ElementTypes::ESSENTIAL_COLUMNS,
            'properties' => [
                'design' => [
                    'container' => [
                        'margin' => [
                            'breakpoint_base' => [
                                'left' => 'auto',
                                'right' => 'auto',
                            ],
                        ],
                    ],
                    'layout' => [
                        'justify_content' => [
                            'breakpoint_base' => 'center',
                        ],
                    ],
                ],
            ],
        ],
        'children' => [],
    ],
], []);

assert(in_array(
    'Breakdance dead write: container.margin left/right "auto" on EssentialElements\\Columns/Column persists in JSON but does not compile. Use an OxygenElements\\Container wrapper or center the parent Column with the full alignment bundle.',
    $deadWriteAudit['diagnostics']['warnings'],
    true
));
assert(in_array(
    'Breakdance dead write: layout.justify_content on EssentialElements\\Columns persists in JSON but does not compile. Use an OxygenElements\\Container outer wrapper or move alignment to the child Column.',
    $deadWriteAudit['diagnostics']['warnings'],
    true
));

echo "breakdance-css-mapping-gaps-ok\n";
