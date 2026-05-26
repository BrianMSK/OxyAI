<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$fixture = $root . '/tests/fixtures/oxygen-core/sample-root';
$script = $root . '/tools/css-mapping/extract-oxygen-core-contracts.php';

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($fixture);
$output = shell_exec($command);
assert(is_string($output) && $output !== '');

$inventory = json_decode($output, true);
assert(is_array($inventory));
assert(($inventory['source'] ?? null) === 'oxygen-core');
assert(($inventory['elementCount'] ?? null) === 1);
assert(count($inventory['universal']['files'] ?? []) === 5);
assert(in_array('settings.advanced.wrapper.background', $inventory['universal']['settingsPaths'] ?? [], true));
assert(($inventory['universal']['affectedCssProperties'][0]['cssProperty'] ?? null) === 'padding-left');
assert(in_array('EssentialElements\\background', $inventory['universal']['presetSectionRefs'] ?? [], true));

$element = $inventory['elements'][0] ?? null;
assert(is_array($element));
assert(($element['class'] ?? null) === 'OxygenElements\\Sample');
assert(($element['name'] ?? null) === 'Sample');
assert(($element['className'] ?? null) === 'oxy-sample');
assert(in_array('design.size.width.style', $element['cssDesignPaths'] ?? [], true));
assert(in_array('design.typography.color', $element['cssDesignPaths'] ?? [], true));
assert(in_array('content.content.text', $element['htmlContentPaths'] ?? [], true));
assert(($element['affectedCssProperties'][0]['affectedPropertyPath'] ?? null) === 'design.spacing.margin_top.%%BREAKPOINT%%');
assert(in_array('content.content.text', $element['whitelistedPropertyPaths'] ?? [], true));
assert(in_array('.oxy-sample', $element['defaultCssSelectors'] ?? [], true));

echo "oxygen-core-contracts-ok\n";
