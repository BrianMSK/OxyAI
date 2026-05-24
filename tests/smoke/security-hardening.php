<?php

declare(strict_types=1);

use OxyHtmlConverter\Services\InteractionDetector;

require_once __DIR__ . '/../../vendor/oxygen-html-converter/src/Services/InteractionDetector.php';

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->loadHTML(
    '<button formaction="javascript:alert(1)" formmethod="TRACE" formtarget="popup" ping="https://attacker.test/p" data-safe="ok">Send</button>',
    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
);

$button = $dom->getElementsByTagName('button')->item(0);
assert($button instanceof DOMElement);

$element = [
    'data' => [
        'properties' => [],
    ],
];

(new InteractionDetector())->processCustomAttributes($button, $element);

$attributes = $element['data']['properties']['settings']['advanced']['attributes'] ?? [];
assert(is_array($attributes));

$byName = [];
foreach ($attributes as $attribute) {
    assert(is_array($attribute));
    $byName[(string) ($attribute['name'] ?? '')] = (string) ($attribute['value'] ?? '');
}

assert(($byName['formaction'] ?? null) === '#');
assert(!array_key_exists('formmethod', $byName));
assert(!array_key_exists('formtarget', $byName));
assert(!array_key_exists('ping', $byName));
assert(($byName['data-safe'] ?? null) === 'ok');

echo "security-hardening-ok\n";
