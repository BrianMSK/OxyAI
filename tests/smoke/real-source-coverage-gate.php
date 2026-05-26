<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$gate = $root . '/tools/css-mapping/real-source-coverage-gate.php';

$output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($gate));
assert(is_string($output) && $output !== '');

assert(str_contains($output, '# Real Source CSS Coverage Gate'));

if (str_contains($output, '| Breakdance Elements + Forms | SKIP |')) {
    assert(str_contains($output, 'Merge gate: **FAIL**'));
    echo "real-source-coverage-gate-skip\n";
    exit(0);
}

assert(str_contains($output, '| Breakdance Elements + Forms | PASS | yes | 134 | 1086 | 0 | 0 | 0 | 0 |'));
assert(str_contains($output, '| Oxygen Core | PASS | yes | 21 | 30 | 0 | 0 | 0 | 0 |'));
assert(str_contains($output, 'Merge gate: **PASS**'));

$jsonOutput = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($gate) . ' --json');
assert(is_string($jsonOutput) && $jsonOutput !== '');

$json = json_decode($jsonOutput, true);
assert(is_array($json));
assert(($json['isComplete'] ?? null) === true);
assert(($json['mergeGate'] ?? null) === 'PASS');
assert(($json['inventories'][0]['name'] ?? null) === 'Breakdance Elements + Forms');
assert(($json['inventories'][0]['status'] ?? null) === 'PASS');
assert(($json['inventories'][0]['uncovered'] ?? null) === 0);
assert(($json['inventories'][1]['name'] ?? null) === 'Oxygen Core');
assert(($json['inventories'][1]['needs'] ?? null) === 0);

echo "real-source-coverage-gate-ok\n";
