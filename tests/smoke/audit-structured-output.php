<?php

declare(strict_types=1);

use OxyAI\Oxygen\Conversion\OxygenPayloadAdapter;
use OxyHtmlConverter\Services\ConversionAuditBuilder;

require_once __DIR__ . '/../../src/Conversion/OxygenPayloadAdapter.php';
require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/Services/ConversionAuditBuilder.php';

if (!function_exists('__')) {
    function __($text, $domain = null)
    {
        return $text;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}

$adapter = new OxygenPayloadAdapter();
$shaped = $adapter->shape([
    'data' => [
        'audit' => [
            'summary' => [
                'elements' => 3,
                'hasExtractedCss' => true,
            ],
            'preserved' => [
                'customClasses' => ['mk-section', 'mk-button'],
                'headAssets' => [
                    'links' => 0,
                    'scripts' => 0,
                ],
            ],
            'transformed' => [
                'wrapInContainer' => true,
                'includeCssElement' => true,
                'preserveStyleBlockCss' => false,
                'redistributedCssSelectors' => ['.native-button'],
                'retainedCssSelectors' => ['.mk-references-section', '.mk-references-grid'],
            ],
            'diagnostics' => [
                'warnings' => ['Check retained fallback CSS.'],
                'errors' => [],
            ],
            'stripped' => [],
            'followUp' => ['Verify frontend render.'],
        ],
    ],
], ['useSelectors' => true]);

$audit = $shaped['audit'];

assert($audit['summary']['elements'] === 3);
assert($audit['summary']['hasExtractedCss'] === true);
assert($audit['preserved']['customClasses'] === ['mk-section', 'mk-button']);
assert($audit['preserved']['headAssets']['links'] === 0);
assert($audit['transformed']['wrapInContainer'] === true);
assert($audit['transformed']['preserveStyleBlockCss'] === false);
assert($audit['transformed']['redistributedCssSelectors'] === ['.native-button']);
assert($audit['transformed']['retainedCssSelectors'] === ['.mk-references-section', '.mk-references-grid']);
assert($audit['diagnostics']['warnings'] === ['Check retained fallback CSS.']);
assert(in_array('Verify frontend render.', $audit['followUp'], true));
assert(in_array(
    'Selector-library mode is enabled: direct class selector styles are registered as Oxygen selector properties for editor visibility; complex selectors, pseudo states, media queries, and unsupported CSS remain in CSS Code.',
    $audit['followUp'],
    true
));

$builder = new ConversionAuditBuilder();
$sourceAudit = $builder->build([
    'stats' => [
        'elements' => 2,
        'customClasses' => 1,
    ],
    'customClasses' => ['mk-references-section'],
    'extractedCss' => '.mk-references-section{padding:90px 0}',
    'preserveStyleBlockCss' => false,
    'redistributedCssSelectors' => [],
    'retainedCssSelectors' => ['.mk-references-section'],
], [
    'wrapInContainer' => true,
    'includeCssElement' => true,
    'inlineStyles' => true,
    'safeMode' => false,
]);

assert($sourceAudit['transformed']['preserveStyleBlockCss'] === false);
assert($sourceAudit['transformed']['redistributedCssSelectors'] === []);
assert($sourceAudit['transformed']['retainedCssSelectors'] === ['.mk-references-section']);
assert(in_array(
    '1 CSS selector fallback rule(s) were retained because their native mapping cannot yet be safely stripped for the target Oxygen element type. Do not assume mapCssToProperties removes every CssCode rule.',
    $sourceAudit['followUp'],
    true
));

echo "audit-structured-output-ok\n";
