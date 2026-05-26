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
expectTrue(is_array($columnsMargin), '$columnsMargin must be an array');
expectTrue(($columnsMargin['top']['style'] ?? null) === '0px', '$columnsMargin.top.style must be 0px');
expectTrue(($columnsMargin['bottom']['style'] ?? null) === '0px', '$columnsMargin.bottom.style must be 0px');
expectTrue(!array_key_exists('left', $columnsMargin), '$columnsMargin.left must not be present');
expectTrue(!array_key_exists('right', $columnsMargin), '$columnsMargin.right must not be present');
expectTrue(!isset($columnsDesign['layout']['justify_content']), '$columnsDesign.layout.justify_content must not be present');
expectTrue(($columnsDesign['size']['width']['breakpoint_base']['style'] ?? null) === '1280px', '$columnsDesign.size.width.breakpoint_base.style must be 1280px');
expectTrue($extractor->supportsDeclarationsFully(['margin' => '0 auto'], ElementTypes::ESSENTIAL_COLUMNS) === false, '$extractor->supportsDeclarationsFully(margin:0 auto) must be false');
expectTrue($extractor->supportsDeclarationsFully(['margin-left' => 'auto'], ElementTypes::ESSENTIAL_COLUMN) === false, '$extractor->supportsDeclarationsFully(margin-left:auto) must be false');
expectTrue($extractor->supportsDeclarationsFully(['justify-content' => 'center'], ElementTypes::ESSENTIAL_COLUMNS) === false, '$extractor->supportsDeclarationsFully(justify-content:center on Columns) must be false');

$columnDesign = $extractor->toOxygenProperties([
    'align-items' => 'center',
], ElementTypes::ESSENTIAL_COLUMN);

expectTrue(($columnDesign['layout']['align_items']['breakpoint_base'] ?? null) === 'center', '$columnDesign.layout.align_items must be center');
expectTrue(($columnDesign['layout']['align']['breakpoint_base'] ?? null) === 'center', '$columnDesign.layout.align must be center');
expectTrue(($columnDesign['layout']['vertical_align']['breakpoint_base'] ?? null) === 'center', '$columnDesign.layout.vertical_align must be center');

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

expectTrue(
    in_array(
        'Breakdance partial alignment: EssentialElements\\Column only compiles alignment reliably when align_items, align, and vertical_align are written together.',
        $partialColumnAudit['diagnostics']['warnings'],
        true
    ),
    '$partialColumnAudit must include the partial-alignment warning'
);

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

expectTrue(
    in_array(
        'Breakdance dead write: container.margin left/right "auto" on EssentialElements\\Columns/Column persists in JSON but does not compile. Use an OxygenElements\\Container wrapper or center the parent Column with the full alignment bundle.',
        $deadWriteAudit['diagnostics']['warnings'],
        true
    ),
    '$deadWriteAudit must include the container.margin auto warning'
);
expectTrue(
    in_array(
        'Breakdance dead write: layout.justify_content on EssentialElements\\Columns persists in JSON but does not compile. Use an OxygenElements\\Container outer wrapper or move alignment to the child Column.',
        $deadWriteAudit['diagnostics']['warnings'],
        true
    ),
    '$deadWriteAudit must include the layout.justify_content warning'
);

$structuredAuto = (new ConversionAuditBuilder())->build([
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
                    'container' => [
                        'margin' => [
                            'breakpoint_base' => [
                                'left' => [
                                    'style' => 'auto',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'children' => [],
    ],
], []);

expectTrue(
    in_array(
        'Breakdance dead write: container.margin left/right "auto" on EssentialElements\\Columns/Column persists in JSON but does not compile. Use an OxygenElements\\Container wrapper or center the parent Column with the full alignment bundle.',
        $structuredAuto['diagnostics']['warnings'],
        true
    ),
    '$structuredAuto must detect array-shaped auto margins'
);

echo "breakdance-css-mapping-gaps-ok\n";

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
