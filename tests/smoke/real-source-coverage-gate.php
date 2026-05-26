<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$gate = $root . '/tools/css-mapping/real-source-coverage-gate.php';

$output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($gate));
expectTrue(is_string($output) && $output !== '', '$output is empty');

expectTrue(str_contains((string) $output, '# Real Source CSS Coverage Gate'), '$output is missing the gate heading');

$hasAnySkip = preg_match('/\|\s*SKIP\s*\|/', (string) $output) === 1;
if ($hasAnySkip) {
    expectTrue(str_contains((string) $output, 'Merge gate: **FAIL**'), '$output must report FAIL when any inventory is SKIP');
    echo "real-source-coverage-gate-skip\n";
    exit(0);
}

expectTrue(
    str_contains((string) $output, '| Breakdance Elements + Forms | PASS | yes |'),
    '$output is missing the Breakdance PASS row'
);
expectTrue(
    str_contains((string) $output, '| Oxygen Core | PASS | yes |'),
    '$output is missing the Oxygen PASS row'
);
expectTrue(str_contains((string) $output, 'Merge gate: **PASS**'), '$output must report PASS when no inventory is SKIP');

$jsonOutput = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($gate) . ' --json');
expectTrue(is_string($jsonOutput) && $jsonOutput !== '', '$jsonOutput is empty');

$json = json_decode((string) $jsonOutput, true);
expectTrue(is_array($json), '$json did not decode to an array');
expectTrue(($json['isComplete'] ?? null) === true, '$json.isComplete must be true');
expectTrue(($json['mergeGate'] ?? null) === 'PASS', '$json.mergeGate must be PASS');

$inventoriesByName = [];
foreach (($json['inventories'] ?? []) as $inventory) {
    if (is_array($inventory) && isset($inventory['name']) && is_string($inventory['name'])) {
        $inventoriesByName[$inventory['name']] = $inventory;
    }
}

$breakdance = $inventoriesByName['Breakdance Elements + Forms'] ?? null;
expectTrue(is_array($breakdance), '$inventoriesByName[Breakdance Elements + Forms] missing');
expectTrue(($breakdance['status'] ?? null) === 'PASS', 'Breakdance Elements + Forms status must be PASS');
expectTrue(($breakdance['uncovered'] ?? null) === 0, 'Breakdance Elements + Forms uncovered must be 0');

$oxygen = $inventoriesByName['Oxygen Core'] ?? null;
expectTrue(is_array($oxygen), '$inventoriesByName[Oxygen Core] missing');
expectTrue(($oxygen['status'] ?? null) === 'PASS', 'Oxygen Core status must be PASS');
expectTrue(($oxygen['needs'] ?? null) === 0, 'Oxygen Core needs must be 0');

echo "real-source-coverage-gate-ok\n";

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
