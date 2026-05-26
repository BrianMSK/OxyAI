<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$fixtureRoot = $root . '/tests/fixtures/breakdance-source/sample-elements';
$script = $root . '/tools/css-mapping/extract-breakdance-contracts.php';

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($fixtureRoot);
$output = shell_exec($command);
expectTrue(is_string($output) && $output !== '', '$output is empty');

$inventory = json_decode((string) $output, true);
expectTrue(is_array($inventory), '$inventory JSON did not decode to an array');
expectTrue(($inventory['elementCount'] ?? null) === 1, '$inventory.elementCount must be 1');

$element = $inventory['elements'][0] ?? null;
expectTrue(is_array($element), '$element[0] is missing');
expectTrue(($element['class'] ?? null) === 'EssentialElements\\SampleCard', '$element.class must be EssentialElements\\SampleCard');
expectTrue(
    in_array('design.card.spacing.padding.top.style', $element['cssDesignPaths'] ?? [], true),
    '$element.cssDesignPaths must include design.card.spacing.padding.top.style'
);

$declarations = $element['cssDeclarations'] ?? [];
expectTrue(is_array($declarations), '$declarations must be an array');
expectTrue(count($declarations) === 4, '$declarations must contain exactly 4 entries');

$byProperty = [];
foreach ($declarations as $declaration) {
    expectTrue(is_array($declaration), '$declaration entry must be an array');
    $byProperty[$declaration['property']] = $declaration;
}

expectTrue(($byProperty['width']['designPaths'][0] ?? null) === 'design.size.width.style', '$byProperty.width.designPaths[0] mismatch');
expectTrue(($byProperty['background-color']['designPaths'][0] ?? null) === 'design.card.background', '$byProperty.background-color.designPaths[0] mismatch');
expectTrue(($byProperty['--sample-padding-top']['designPaths'][0] ?? null) === 'design.card.spacing.padding.top.style', '$byProperty.--sample-padding-top.designPaths[0] mismatch');
expectTrue(($byProperty['--sample-padding-top']['isCustomProperty'] ?? null) === true, '$byProperty.--sample-padding-top.isCustomProperty must be true');
expectTrue(($byProperty['color']['contentPaths'][0] ?? null) === 'content.title.color', '$byProperty.color.contentPaths[0] mismatch');

$macroCalls = $element['cssMacroCalls'] ?? [];
expectTrue(is_array($macroCalls), '$macroCalls must be an array');
expectTrue(count($macroCalls) === 2, '$macroCalls must contain exactly 2 entries');
expectTrue(($macroCalls[0]['macro'] ?? null) === 'spacing_padding_all', '$macroCalls[0].macro must be spacing_padding_all');
expectTrue(($macroCalls[0]['designPaths'][0] ?? null) === 'design.card.spacing', '$macroCalls[0].designPaths[0] mismatch');
expectTrue(($macroCalls[1]['macro'] ?? null) === 'typography', '$macroCalls[1].macro must be typography');
expectTrue(($macroCalls[1]['designPaths'][0] ?? null) === 'design.title.typography', '$macroCalls[1].designPaths[0] mismatch');

echo "breakdance-contract-declarations-ok\n";

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
