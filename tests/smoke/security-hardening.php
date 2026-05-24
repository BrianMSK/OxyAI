<?php

declare(strict_types=1);

use OxyHtmlConverter\Services\InteractionDetector;

require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/Services/InteractionDetector.php';

/**
 * @param array<int, array<string, mixed>> $attributes
 * @return array<string, string>
 */
function oxyai_smoke_index_attributes(array $attributes): array
{
    $byName = [];
    foreach ($attributes as $attribute) {
        assert(is_array($attribute));
        $byName[(string) ($attribute['name'] ?? '')] = (string) ($attribute['value'] ?? '');
    }

    return $byName;
}

function oxyai_smoke_sanitize(\DOMElement $node): array
{
    $element = ['data' => ['properties' => []]];
    (new InteractionDetector())->processCustomAttributes($node, $element);

    return oxyai_smoke_index_attributes(
        $element['data']['properties']['settings']['advanced']['attributes'] ?? []
    );
}

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->loadHTML(
    '<form>'
    . '<button id="b1" formaction="javascript:alert(1)" formmethod="TRACE" formtarget="popup" ping="https://attacker.test/p" data-safe="ok">b1</button>'
    . '<button id="b3" formtarget="_BLANK">b3</button>'
    . '<button id="b4" formtarget="_evil">b4</button>'
    . '</form>',
    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
);

$buttons = [];
foreach ($dom->getElementsByTagName('button') as $btn) {
    assert($btn instanceof DOMElement);
    $buttons[$btn->getAttribute('id')] = $btn;
}

// Inject a control-char payload programmatically so it survives HTML parsing intact.
$smuggled = $dom->createElement('button');
$smuggled->setAttribute('id', 'b2');
$smuggled->setAttribute('formaction', "java\nscript:alert(1)");
$buttons['b2'] = $smuggled;

// b1: javascript: scheme blocked, TRACE method dropped, popup is a valid named context,
// ping is always stripped, data-* passes through.
$b1 = oxyai_smoke_sanitize($buttons['b1']);
assert(($b1['formaction'] ?? null) === '#', 'javascript: scheme must be rewritten to #');
assert(!array_key_exists('formmethod', $b1), 'TRACE method must be dropped');
assert(($b1['formtarget'] ?? null) === 'popup', 'named browsing context "popup" must be preserved');
assert(!array_key_exists('ping', $b1), 'ping must be dropped');
assert(($b1['data-safe'] ?? null) === 'ok', 'data-* must pass through');

// b2: newline-smuggled javascript: scheme must still be neutralized.
$b2 = oxyai_smoke_sanitize($buttons['b2']);
assert(($b2['formaction'] ?? null) === '#', 'control-char smuggled javascript: must be rewritten to #');

// b3: keyword targets are case-insensitive and normalize to lowercase.
$b3 = oxyai_smoke_sanitize($buttons['b3']);
assert(($b3['formtarget'] ?? null) === '_blank', '_BLANK must normalize to _blank');

// b4: reserved _-prefixed names other than the four keywords must be rejected.
$b4 = oxyai_smoke_sanitize($buttons['b4']);
assert(!array_key_exists('formtarget', $b4), 'reserved _-prefixed target must be dropped');

echo "security-hardening-ok\n";
