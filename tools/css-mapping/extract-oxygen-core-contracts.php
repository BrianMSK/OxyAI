<?php

declare(strict_types=1);

/**
 * Build a machine-readable inventory from Oxygen 6 core element sources.
 *
 * Usage:
 *   php tools/css-mapping/extract-oxygen-core-contracts.php \
 *     "C:\Users\Denis\Downloads\oxygen-6.1.0-beta.4\oxygen"
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php extract-oxygen-core-contracts.php <oxygen-plugin-root>\n");
    exit(2);
}

$root = rtrim((string) $argv[1], "\\/");
if (!is_dir($root)) {
    fwrite(STDERR, "Missing Oxygen root: {$root}\n");
    exit(2);
}

$elementsRoot = $root . DIRECTORY_SEPARATOR . 'subplugins' . DIRECTORY_SEPARATOR . 'oxygen-elements' . DIRECTORY_SEPARATOR . 'elements';
if (!is_dir($elementsRoot)) {
    fwrite(STDERR, "Missing Oxygen elements root: {$elementsRoot}\n");
    exit(2);
}

$elements = [];
foreach (glob($elementsRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $elementDir) {
    $elementPhp = $elementDir . DIRECTORY_SEPARATOR . 'element.php';
    if (!is_file($elementPhp)) {
        continue;
    }

    $elementSource = readFileString($elementPhp);
    $cssTwig = readOptionalFile($elementDir . DIRECTORY_SEPARATOR . 'css.twig');
    $htmlTwig = readOptionalFile($elementDir . DIRECTORY_SEPARATOR . 'html.twig');
    $defaultCss = readOptionalFile($elementDir . DIRECTORY_SEPARATOR . 'default.css');
    $class = extractClassName($elementSource);
    if ($class === null) {
        continue;
    }

    $sourceBlob = implode("\n", array_filter([$elementSource, $cssTwig, $htmlTwig], static fn ($value): bool => is_string($value)));

    $elements[] = [
        'class' => $class,
        'name' => extractStaticStringReturn($elementSource, 'name'),
        'className' => extractStaticStringReturn($elementSource, 'className'),
        'category' => extractStaticStringReturn($elementSource, 'category'),
        'availableIn' => extractAvailableIn($elementSource),
        'sourceDir' => normalizePath($elementDir),
        'files' => [
            'element' => normalizePath($elementPhp),
            'css' => $cssTwig !== null ? normalizePath($elementDir . DIRECTORY_SEPARATOR . 'css.twig') : null,
            'html' => $htmlTwig !== null ? normalizePath($elementDir . DIRECTORY_SEPARATOR . 'html.twig') : null,
            'defaultCss' => $defaultCss !== null ? normalizePath($elementDir . DIRECTORY_SEPARATOR . 'default.css') : null,
        ],
        'controlSlugs' => extractControlSlugs($elementSource),
        'cssDesignPaths' => extractPropertyPaths($cssTwig ?? '', 'design'),
        'cssContentPaths' => extractPropertyPaths($cssTwig ?? '', 'content'),
        'htmlContentPaths' => extractPropertyPaths($htmlTwig ?? '', 'content'),
        'htmlDesignPaths' => extractPropertyPaths($htmlTwig ?? '', 'design'),
        'sourceDesignPaths' => extractPropertyPaths($sourceBlob, 'design'),
        'sourceContentPaths' => extractPropertyPaths($sourceBlob, 'content'),
        'sourceSettingsPaths' => extractPropertyPaths($sourceBlob, 'settings'),
        'affectedCssProperties' => extractAffectedCssMappings($sourceBlob),
        'whitelistedPropertyPaths' => extractReturnedStringArrayForMethod($elementSource, 'propertyPathsToWhitelistInFlatProps'),
        'ssrPropertyPaths' => extractReturnedStringArrayForMethod($elementSource, 'propertyPathsToSsrElementWhenValueChanges'),
        'defaultCssSelectors' => extractDefaultCssSelectors($defaultCss ?? ''),
    ];
}

usort($elements, static fn (array $a, array $b): int => strcmp((string) $a['class'], (string) $b['class']));

$universalFiles = [
    $root . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'classes-selectors' . DIRECTORY_SEPARATOR . 'controls.php',
    $root . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR . 'elements-helpers.php',
    $root . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR . 'universal-controls' . DIRECTORY_SEPARATOR . 'advanced.php',
    $root . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR . 'universal-controls' . DIRECTORY_SEPARATOR . 'hide-by-breakpoint.php',
    $root . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR . 'universal-controls' . DIRECTORY_SEPARATOR . 'html.php',
];

$universalSource = '';
$existingUniversalFiles = [];
foreach ($universalFiles as $file) {
    if (!is_file($file)) {
        continue;
    }
    $existingUniversalFiles[] = normalizePath($file);
    $universalSource .= "\n" . readFileString($file);
}

echo json_encode([
    'generatedAt' => gmdate('c'),
    'source' => 'oxygen-core',
    'root' => normalizePath($root),
    'elementCount' => count($elements),
    'universal' => [
        'files' => $existingUniversalFiles,
        'designPaths' => extractPropertyPaths($universalSource, 'design'),
        'contentPaths' => extractPropertyPaths($universalSource, 'content'),
        'settingsPaths' => extractPropertyPaths($universalSource, 'settings'),
        'affectedCssProperties' => extractAffectedCssMappings($universalSource),
        'presetSectionRefs' => extractPresetSectionRefs($universalSource),
    ],
    'elements' => $elements,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function readOptionalFile(string $path): ?string
{
    return is_file($path) ? readFileString($path) : null;
}

function readFileString(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    return $contents;
}

function extractClassName(string $source): ?string
{
    if (!preg_match('/namespace\s+([^;]+);.*?class\s+([A-Za-z0-9_]+)/s', $source, $matches)) {
        return null;
    }

    return trim($matches[1]) . '\\' . trim($matches[2]);
}

function extractStaticStringReturn(string $source, string $method): ?string
{
    $pattern = '/static\s+function\s+' . preg_quote($method, '/') . '\s*\(\)\s*\{.*?return\s+[\'"]([^\'"]+)[\'"]\s*;/s';
    if (!preg_match($pattern, $source, $matches)) {
        return null;
    }

    return $matches[1];
}

/**
 * @return array<int, string>
 */
function extractAvailableIn(string $source): array
{
    if (!preg_match('/static\s+function\s+availableIn\s*\(\)\s*\{.*?return\s+\[(.*?)\]\s*;/s', $source, $matches)) {
        return [];
    }

    preg_match_all('/[\'"]([^\'"]+)[\'"]/', $matches[1], $items);

    return array_values(array_unique($items[1] ?? []));
}

/**
 * @return array<int, string>
 */
function extractControlSlugs(string $source): array
{
    preg_match_all('/\bc\s*\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches);

    return array_values(array_unique($matches[1] ?? []));
}

/**
 * @return array<int, string>
 */
function extractPropertyPaths(string $source, string $root): array
{
    if ($source === '') {
        return [];
    }

    preg_match_all('/\b' . preg_quote($root, '/') . '((?:\.[A-Za-z_][A-Za-z0-9_]*|\.\[\])*)+/', $source, $matches);

    return array_values(array_unique($matches[0] ?? []));
}

/**
 * @return array<int, array{cssProperty:string,affectedPropertyPath:string}>
 */
function extractAffectedCssMappings(string $source): array
{
    if ($source === '') {
        return [];
    }

    preg_match_all(
        '/[\'"]cssProperty[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"].{0,240}?[\'"]affectedPropertyPath[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/s',
        $source,
        $matches,
        PREG_SET_ORDER
    );

    $rows = [];
    foreach ($matches as $match) {
        $rows[] = [
            'cssProperty' => $match[1],
            'affectedPropertyPath' => $match[2],
        ];
    }

    return uniqueRows($rows);
}

/**
 * @return array<int, string>
 */
function extractReturnedStringArrayForMethod(string $source, string $method): array
{
    $pattern = '/static\s+function\s+' . preg_quote($method, '/') . '\s*\(\)\s*\{.*?return\s+\[(.*?)\]\s*;/s';
    if (!preg_match($pattern, $source, $matches)) {
        return [];
    }

    preg_match_all('/[\'"]([^\'"]+)[\'"]/', $matches[1], $items);

    return array_values(array_unique($items[1] ?? []));
}

/**
 * @return array<int, string>
 */
function extractDefaultCssSelectors(string $source): array
{
    if ($source === '') {
        return [];
    }

    preg_match_all('/(^|})\s*([^{}]+)\s*\{/m', $source, $matches);
    $selectors = [];

    foreach ($matches[2] ?? [] as $selector) {
        $selector = trim((string) $selector);
        if ($selector !== '') {
            $selectors[] = $selector;
        }
    }

    return array_values(array_unique($selectors));
}

/**
 * @return array<int, string>
 */
function extractPresetSectionRefs(string $source): array
{
    preg_match_all('/getPresetSection\s*\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches);

    return array_values(array_unique(array_map(
        static fn (string $ref): string => str_replace('\\\\', '\\', $ref),
        $matches[1] ?? []
    )));
}

/**
 * @param array<int, array<string, string>> $rows
 * @return array<int, array<string, string>>
 */
function uniqueRows(array $rows): array
{
    $seen = [];
    $unique = [];
    foreach ($rows as $row) {
        $key = json_encode($row);
        if (!is_string($key) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $row;
    }

    return $unique;
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}
