<?php

declare(strict_types=1);

use OxyHtmlConverter\Services\ClassStrategyService;
use OxyHtmlConverter\Services\InteractionDetector;

require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/Services/InteractionDetector.php';
require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/Services/ClassStrategyService.php';
require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/TreeBuilder.php';

// Use a throwing helper instead of PHP's assert(), which can be compiled out
// when zend.assertions is -1 — that would let logic regressions print "ok"
// silently. RuntimeException makes failures hard, regardless of php.ini.
function oxyai_smoke_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param array<int, array<string, mixed>> $attributes
 * @return array<string, string>
 */
function oxyai_smoke_index_attributes(array $attributes): array
{
    $byName = [];
    foreach ($attributes as $attribute) {
        oxyai_smoke_assert(is_array($attribute), 'attribute entry must be an array');
        $byName[(string) ($attribute['name'] ?? '')] = (string) ($attribute['value'] ?? '');
    }

    return $byName;
}

function oxyai_smoke_sanitize(DOMElement $node): array
{
    $element = ['data' => ['properties' => []]];
    (new InteractionDetector())->processCustomAttributes($node, $element);

    return oxyai_smoke_index_attributes(
        $element['data']['properties']['settings']['advanced']['attributes'] ?? []
    );
}

function oxyai_smoke_invoke_private(object $object, string $method, array $args): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $args);
}

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->loadHTML(
    '<form>'
    . '<button id="b1" formaction="javascript:alert(1)" formmethod="TRACE" formtarget="popup" ping="https://attacker.test/p" data-safe="ok">b1</button>'
    . '<button id="b3" formtarget="_BLANK">b3</button>'
    . '<button id="b4" formtarget="_evil">b4</button>'
    . '<input  id="b5" value="  hello  " placeholder="  pad  " data-keep="  spaced  ">'
    . '</form>',
    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
);

$buttons = [];
foreach ($dom->getElementsByTagName('button') as $btn) {
    oxyai_smoke_assert($btn instanceof DOMElement, 'button node must be a DOMElement');
    $buttons[$btn->getAttribute('id')] = $btn;
}

$input = $dom->getElementsByTagName('input')->item(0);
oxyai_smoke_assert($input instanceof DOMElement, 'input node must be a DOMElement');

// Inject a control-char payload programmatically so it survives HTML parsing intact.
$smuggled = $dom->createElement('button');
$smuggled->setAttribute('id', 'b2');
$smuggled->setAttribute('formaction', "java\nscript:alert(1)");
$buttons['b2'] = $smuggled;

// b1: javascript: scheme blocked, TRACE method dropped, popup is a valid named context,
// ping is always stripped, data-* passes through.
$b1 = oxyai_smoke_sanitize($buttons['b1']);
oxyai_smoke_assert(($b1['formaction'] ?? null) === '#', 'javascript: scheme must be rewritten to #');
oxyai_smoke_assert(!array_key_exists('formmethod', $b1), 'TRACE method must be dropped');
oxyai_smoke_assert(($b1['formtarget'] ?? null) === 'popup', 'named browsing context "popup" must be preserved');
oxyai_smoke_assert(!array_key_exists('ping', $b1), 'ping must be dropped');
oxyai_smoke_assert(($b1['data-safe'] ?? null) === 'ok', 'data-* must pass through');

// b2: newline-smuggled javascript: scheme must still be neutralized.
$b2 = oxyai_smoke_sanitize($buttons['b2']);
oxyai_smoke_assert(($b2['formaction'] ?? null) === '#', 'control-char smuggled javascript: must be rewritten to #');

// b3: keyword targets are case-insensitive and normalize to lowercase.
$b3 = oxyai_smoke_sanitize($buttons['b3']);
oxyai_smoke_assert(($b3['formtarget'] ?? null) === '_blank', '_BLANK must normalize to _blank');

// b4: reserved _-prefixed names other than the four keywords must be rejected.
$b4 = oxyai_smoke_sanitize($buttons['b4']);
oxyai_smoke_assert(!array_key_exists('formtarget', $b4), 'reserved _-prefixed target must be dropped');

// b5: preserved attributes that hit the generic fallback (value, placeholder, data-*)
// must keep boundary whitespace — those are user-visible defaults.
$b5 = oxyai_smoke_sanitize($input);
oxyai_smoke_assert(($b5['value'] ?? null) === '  hello  ', 'value boundary whitespace must be preserved');
oxyai_smoke_assert(($b5['placeholder'] ?? null) === '  pad  ', 'placeholder boundary whitespace must be preserved');
oxyai_smoke_assert(($b5['data-keep'] ?? null) === '  spaced  ', 'data-* boundary whitespace must be preserved');

// Class tokens: Tailwind arbitrary-value utilities containing quotes must survive.
$classService = (new ReflectionClass(ClassStrategyService::class))->newInstanceWithoutConstructor();
$tailwindContent = "before:content-['_↗']";
$tailwindAttr = 'data-[state=open]:bg-blue-500';
$malicious = "evil<script";

oxyai_smoke_assert(
    oxyai_smoke_invoke_private($classService, 'sanitizeClassToken', [$tailwindContent]) === $tailwindContent,
    'Tailwind quoted content utility must survive'
);
oxyai_smoke_assert(
    oxyai_smoke_invoke_private($classService, 'sanitizeClassToken', [$tailwindAttr]) === $tailwindAttr,
    'Tailwind arbitrary variant must survive'
);
oxyai_smoke_assert(
    oxyai_smoke_invoke_private($classService, 'sanitizeClassToken', [$malicious]) === null,
    'class token containing < must be dropped'
);

// Ids: valid but unusual ids (with =) must pass through unchanged so anchor href
// fragments and JS getElementById references keep matching. Hostile ids drop entirely.
$treeBuilder = (new ReflectionClass('OxyHtmlConverter\\TreeBuilder'))->newInstanceWithoutConstructor();
oxyai_smoke_assert(
    oxyai_smoke_invoke_private($treeBuilder, 'sanitizeHtmlId', ['section=1']) === 'section=1',
    'id with `=` must be preserved verbatim'
);
oxyai_smoke_assert(
    oxyai_smoke_invoke_private($treeBuilder, 'sanitizeHtmlId', ['safe:id']) === 'safe:id',
    'id with `:` must be preserved verbatim'
);
oxyai_smoke_assert(
    oxyai_smoke_invoke_private($treeBuilder, 'sanitizeHtmlId', ['bad id']) === '',
    'id with whitespace must be dropped'
);
oxyai_smoke_assert(
    oxyai_smoke_invoke_private($treeBuilder, 'sanitizeHtmlId', ['"><x']) === '',
    'id with quote/angle must be dropped'
);

echo "security-hardening-ok\n";
