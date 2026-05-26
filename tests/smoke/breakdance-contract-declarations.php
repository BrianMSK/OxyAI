<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$fixtureRoot = $root . '/tests/fixtures/breakdance-source/sample-elements';
$script = $root . '/tools/css-mapping/extract-breakdance-contracts.php';

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($fixtureRoot);
$output = shell_exec($command);
assert(is_string($output) && $output !== '');

$inventory = json_decode($output, true);
assert(is_array($inventory));
assert(($inventory['elementCount'] ?? null) === 1);

$element = $inventory['elements'][0] ?? null;
assert(is_array($element));
assert(($element['class'] ?? null) === 'EssentialElements\\SampleCard');
assert(in_array('design.card.spacing.padding.top.style', $element['cssDesignPaths'] ?? [], true));

$declarations = $element['cssDeclarations'] ?? [];
assert(is_array($declarations));
assert(count($declarations) === 4);

$byProperty = [];
foreach ($declarations as $declaration) {
    assert(is_array($declaration));
    $byProperty[$declaration['property']] = $declaration;
}

assert(($byProperty['width']['designPaths'][0] ?? null) === 'design.size.width.style');
assert(($byProperty['background-color']['designPaths'][0] ?? null) === 'design.card.background');
assert(($byProperty['--sample-padding-top']['designPaths'][0] ?? null) === 'design.card.spacing.padding.top.style');
assert(($byProperty['--sample-padding-top']['isCustomProperty'] ?? null) === true);
assert(($byProperty['color']['contentPaths'][0] ?? null) === 'content.title.color');

$macroCalls = $element['cssMacroCalls'] ?? [];
assert(is_array($macroCalls));
assert(count($macroCalls) === 2);
assert(($macroCalls[0]['macro'] ?? null) === 'spacing_padding_all');
assert(($macroCalls[0]['designPaths'][0] ?? null) === 'design.card.spacing');
assert(($macroCalls[1]['macro'] ?? null) === 'typography');
assert(($macroCalls[1]['designPaths'][0] ?? null) === 'design.title.typography');

echo "breakdance-contract-declarations-ok\n";
