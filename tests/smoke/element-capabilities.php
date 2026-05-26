<?php

declare(strict_types=1);

use OxyAI\Oxygen\Codex\OxygenElementCapabilityService;
use OxyHtmlConverter\ElementTypes;

require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/ElementTypes.php';
require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/Services/BuilderContractService.php';
require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/Services/BuilderElementCatalogService.php';
require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/Services/EnvironmentService.php';
require_once __DIR__ . '/../../src/Codex/OxygenElementCapabilityService.php';

$service = new OxygenElementCapabilityService();
$all = $service->all();

assert(isset($all['breakdanceFormsForOxygen']));
assert(isset($all['cssMappingCoveragePolicy']));
assert(($all['cssMappingCoveragePolicy']['sourceOfTruth'] ?? '') === 'config/css-mapping/breakdance-coverage-manifest.json plus live list_oxygen_element_capabilities output for the current site.');
assert(isset($all['cssMappingCoveragePolicy']['coverageHarness']['oxygenInventory']));
assert(isset($all['cssMappingCoveragePolicy']['coverageHarness']['realSourceSmoke']));
assert(isset($all['cssMappingCoveragePolicy']['coverageHarness']['realSourceReviewGate']));
assert(isset($all['cssMappingCoveragePolicy']['statuses']['element-specific-contract']));
assert(isset($all['cssMappingCoveragePolicy']['statuses']['needs-element-specific-mapper']));
assert(in_array('compiled CSS or rendered page proof', $all['cssMappingCoveragePolicy']['stripSafeRequires'] ?? [], true));
assert(array_key_exists('safeHandAuthoredTargets', $all['breakdanceFormsForOxygen']));
assert(isset($all['breakdanceFormsForOxygen']['safeHandAuthoredTargets'][ElementTypes::ESSENTIAL_FORM_BUILDER]));
assert(isset($all['selectorCompilerSupport']['knownNativeSelectorGaps']));
assert(($all['selectorCompilerSupport']['nativeResponsiveMapping']['@media (max-width:1119px)'] ?? '') === 'breakpoint_tablet_landscape');
assert(str_contains(
    $all['selectorCompilerSupport']['nativeResponsiveSelectorScope'],
    'direct single-class selectors'
));
assert(in_array(
    'Use percentage widths or keep a CSS fallback for layouts that require wrapping, CSS grid, complex media queries, or flex item growth/shrink behavior.',
    $all['selectorCompilerSupport']['knownNativeSelectorGaps'],
    true
));

$formBuilder = $service->all(ElementTypes::ESSENTIAL_FORM_BUILDER);
assert(count($formBuilder['elements']) === 1);
assert($formBuilder['elements'][0]['type'] === ElementTypes::ESSENTIAL_FORM_BUILDER);
assert(($formBuilder['elements'][0]['requiresBreakdanceFormsForOxygen'] ?? false) === true);
assert(in_array('content.form.fields[].type', $formBuilder['elements'][0]['requiredContentPaths'], true));
assert(in_array('content.actions.actions', $formBuilder['elements'][0]['requiredContentPaths'], true));

$contracts = $all['breakdanceFormsForOxygen']['contractStatuses'] ?? [];
assert(in_array('content.form.fields[].type', $contracts['formBuilder']['details']['requiredContentPaths'] ?? [], true));
assert(in_array('content.form.fields[].label', $contracts['formBuilder']['details']['requiredContentPaths'] ?? [], true));
assert(in_array('content.form.submit_text', $contracts['formBuilder']['details']['requiredContentPaths'] ?? [], true));
assert(in_array('content.actions.actions', $contracts['formBuilder']['details']['requiredContentPaths'] ?? [], true));

echo "element-capabilities-ok\n";
