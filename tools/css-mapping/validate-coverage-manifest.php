<?php

declare(strict_types=1);

/**
 * Validate the CSS coverage manifest itself, independent of any source
 * inventory. This catches vague or malformed future rules before they can make
 * the source coverage gate look better than the review evidence deserves.
 *
 * Usage:
 *   php tools/css-mapping/validate-coverage-manifest.php config/css-mapping/breakdance-coverage-manifest.json
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php validate-coverage-manifest.php <manifest-json>\n");
    exit(2);
}

$input = file_get_contents($argv[1]);
if ($input === false) {
    fwrite(STDERR, "Unable to read manifest: {$argv[1]}\n");
    exit(2);
}

$manifest = json_decode($input, true);
if (!is_array($manifest)) {
    fwrite(STDERR, "Invalid manifest JSON: {$argv[1]}\n");
    exit(2);
}

$validStatuses = array_fill_keys(array_keys(is_array($manifest['statuses'] ?? null) ? $manifest['statuses'] : []), true);
$errors = [];

if (($manifest['version'] ?? null) !== 1) {
    $errors[] = 'Manifest version must be 1.';
}

validateRuleList('elementRules', is_array($manifest['elementRules'] ?? null) ? $manifest['elementRules'] : [], $validStatuses, true, $errors);
validateRuleList('rules', is_array($manifest['rules'] ?? null) ? $manifest['rules'] : [], $validStatuses, false, $errors);
validateCssPropertyCoverage(is_array($manifest['cssDeclarationPropertyCoverage'] ?? null) ? $manifest['cssDeclarationPropertyCoverage'] : [], $errors);
validateCssMacroCoverage(is_array($manifest['cssMacroCoverage'] ?? null) ? $manifest['cssMacroCoverage'] : [], $errors);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

echo "coverage-manifest-quality-ok\n";

/**
 * @param array<int, mixed> $rules
 * @param array<string, true> $validStatuses
 * @param array<int, string> $errors
 */
function validateRuleList(string $field, array $rules, array $validStatuses, bool $isElementRule, array &$errors): void
{
    foreach ($rules as $index => $rule) {
        $label = "{$field}[{$index}]";
        if (!is_array($rule)) {
            $errors[] = "{$label} must be an object.";
            continue;
        }

        $match = (string) ($rule['match'] ?? 'exact');
        if (!in_array($match, ['exact', 'prefix'], true)) {
            $errors[] = "{$label}.match must be exact or prefix.";
        }

        $path = trim((string) ($rule['path'] ?? ''));
        if ($path === '') {
            $errors[] = "{$label}.path must be non-empty.";
        }

        $status = (string) ($rule['status'] ?? '');
        if ($status === '' || !isset($validStatuses[$status])) {
            $errors[] = "{$label}.status must be declared in manifest.statuses.";
        }

        if (!array_key_exists('stripSafe', $rule) || !is_bool($rule['stripSafe'])) {
            $errors[] = "{$label}.stripSafe must be a boolean.";
        }

        if ($isElementRule) {
            $class = trim((string) ($rule['class'] ?? ''));
            if ($class === '') {
                $errors[] = "{$label}.class must be non-empty.";
            }
        }

        if ($status === 'element-specific-contract' && trim((string) ($rule['reason'] ?? '')) === '') {
            $errors[] = "{$label}.reason is required for element-specific contracts.";
        }

        if (($rule['stripSafe'] ?? false) === true && !hasProof($rule)) {
            $errors[] = "{$label} is stripSafe=true but lacks JSON-shape and compiled/render proof metadata.";
        }
    }
}

/**
 * @param array<string, mixed> $coverage
 * @param array<int, string> $errors
 */
function validateCssPropertyCoverage(array $coverage, array &$errors): void
{
    foreach (['recognizedBySharedMapper', 'recognizedButFallbackOnly'] as $field) {
        $values = $coverage[$field] ?? null;
        if (!is_array($values)) {
            $errors[] = "cssDeclarationPropertyCoverage.{$field} must be an array.";
            continue;
        }

        foreach ($values as $index => $property) {
            if (!is_string($property) || trim($property) === '') {
                $errors[] = "cssDeclarationPropertyCoverage.{$field}[{$index}] must be a non-empty string.";
            }
        }
    }
}

/**
 * @param array<string, mixed> $coverage
 * @param array<int, string> $errors
 */
function validateCssMacroCoverage(array $coverage, array &$errors): void
{
    foreach (['sharedMapperFamilies', 'elementSpecificContracts', 'fallbackOrRuntimeContracts'] as $field) {
        $values = $coverage[$field] ?? null;
        if (!is_array($values)) {
            $errors[] = "cssMacroCoverage.{$field} must be an array.";
            continue;
        }

        foreach ($values as $index => $macro) {
            if (!is_string($macro) || trim($macro) === '') {
                $errors[] = "cssMacroCoverage.{$field}[{$index}] must be a non-empty string.";
            }
        }
    }
}

/**
 * @param array<string, mixed> $rule
 */
function hasProof(array $rule): bool
{
    $proof = $rule['proof'] ?? null;
    if (!is_array($proof)) {
        return false;
    }

    return trim((string) ($proof['jsonShapeTest'] ?? '')) !== ''
        && (
            trim((string) ($proof['compiledCssTest'] ?? '')) !== ''
            || trim((string) ($proof['renderedPageTest'] ?? '')) !== ''
        );
}
