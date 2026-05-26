<?php

declare(strict_types=1);

/**
 * Build a machine-readable inventory from Breakdance Elements for Oxygen
 * source folders. This is intentionally static: it reads element.php,
 * css.twig, html.twig, and default files without booting WordPress.
 *
 * Usage:
 *   php tools/css-mapping/extract-breakdance-contracts.php \
 *     "C:\Users\Denis\Downloads\breakdance-elements-for-oxygen-1.0.0 (1)\breakdance-elements-for-oxygen\elements" \
 *     "C:\Users\Denis\Downloads\breakdance-forms-for-oxygen-0.3.0 (1)\breakdance-forms-for-oxygen\elements"
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php extract-breakdance-contracts.php <elements-root> [<elements-root>...]\n");
    exit(2);
}

$contracts = [];

foreach (array_slice($argv, 1) as $root) {
    $root = rtrim((string) $root, "\\/");
    if (!is_dir($root)) {
        fwrite(STDERR, "Missing elements root: {$root}\n");
        exit(2);
    }

    foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $elementDir) {
        $elementPhp = $elementDir . DIRECTORY_SEPARATOR . 'element.php';
        if (!is_file($elementPhp)) {
            continue;
        }

        $elementSource = readFileString($elementPhp);
        $cssTwig = readOptionalFile($elementDir . DIRECTORY_SEPARATOR . 'css.twig');
        $htmlTwig = readOptionalFile($elementDir . DIRECTORY_SEPARATOR . 'html.twig');
        $defaultCss = readOptionalFile($elementDir . DIRECTORY_SEPARATOR . 'default.css');
        $cssTwigSource = $cssTwig ?? '';

        $class = extractClassName($elementSource);
        if ($class === null) {
            continue;
        }

        $contracts[] = [
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
            'cssDesignPaths' => extractTwigPropertyPaths($cssTwigSource, 'design'),
            'cssContentPaths' => extractTwigPropertyPaths($cssTwigSource, 'content'),
            'cssDeclarations' => extractCssDeclarations($cssTwigSource),
            'cssMacroCalls' => extractCssMacroCalls($cssTwigSource),
            'htmlContentPaths' => extractTwigPropertyPaths($htmlTwig ?? '', 'content'),
            'htmlDesignPaths' => extractTwigPropertyPaths($htmlTwig ?? '', 'design'),
            'defaultCssSelectors' => extractDefaultCssSelectors($defaultCss ?? ''),
        ];
    }
}

usort($contracts, static fn (array $a, array $b): int => strcmp((string) $a['class'], (string) $b['class']));

echo json_encode([
    'generatedAt' => gmdate('c'),
    'elementCount' => count($contracts),
    'elements' => $contracts,
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
function extractTwigPropertyPaths(string $source, string $root): array
{
    if ($source === '') {
        return [];
    }

    $aliases = extractTwigAliases($source);
    preg_match_all('/\b' . preg_quote($root, '/') . '((?:\.[A-Za-z_][A-Za-z0-9_]*)+)/', $source, $matches);
    $paths = [];

    foreach ($matches[0] ?? [] as $match) {
        $paths[] = $match;
    }

    foreach ($aliases as $alias => $basePath) {
        if (!str_starts_with($basePath, $root . '.')) {
            continue;
        }

        preg_match_all('/\b' . preg_quote($alias, '/') . '((?:\.[A-Za-z_][A-Za-z0-9_]*)+)/', $source, $aliasMatches);
        foreach ($aliasMatches[0] ?? [] as $aliasMatch) {
            $paths[] = $basePath . substr($aliasMatch, strlen($alias));
        }
    }

    return array_values(array_unique($paths));
}

/**
 * @return array<int, array<string, mixed>>
 */
function extractCssDeclarations(string $source): array
{
    if ($source === '') {
        return [];
    }

    $aliases = extractTwigAliases($source);
    $rows = [];
    $currentSelector = null;
    $lines = preg_split('/\R/', $source) ?: [];

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }

        if (preg_match('/\{\s*$/', $trimmed) === 1 && !str_starts_with($trimmed, '{%') && !str_starts_with($trimmed, '{{')) {
            $selector = trim(substr($trimmed, 0, strpos($trimmed, '{')));
            if ($selector !== '') {
                $currentSelector = $selector;
            }
        }

        if ($trimmed === '}' && !str_starts_with($trimmed, '{%') && !str_starts_with($trimmed, '{{')) {
            if ($trimmed === '}' || str_ends_with($trimmed, '}')) {
                $currentSelector = null;
            }
        }

        if (!preg_match('/^\s*(-{0,2}[A-Za-z_][A-Za-z0-9_-]*)\s*:\s*(.+?);?\s*$/', $line, $matches)) {
            continue;
        }

        $property = strtolower($matches[1]);
        $value = trim($matches[2]);
        if ($value === '' || $value === '{') {
            continue;
        }

        $rows[] = [
            'selector' => $currentSelector,
            'property' => $property,
            'value' => $value,
            'designPaths' => extractTwigValuePaths($value, 'design', $aliases),
            'contentPaths' => extractTwigValuePaths($value, 'content', $aliases),
            'isCustomProperty' => str_starts_with($property, '--'),
        ];
    }

    return $rows;
}

/**
 * @return array<int, array<string, mixed>>
 */
function extractCssMacroCalls(string $source): array
{
    if ($source === '') {
        return [];
    }

    $aliases = extractTwigAliases($source);
    preg_match_all('/\{\{\s*macros\.([A-Za-z_][A-Za-z0-9_]*)\s*\((.*?)\)\s*\}\}/s', $source, $matches, PREG_SET_ORDER);
    $rows = [];

    foreach ($matches as $match) {
        $arguments = trim((string) ($match[2] ?? ''));
        $rows[] = [
            'macro' => (string) $match[1],
            'arguments' => $arguments,
            'designPaths' => extractTwigValuePaths($arguments, 'design', $aliases),
            'contentPaths' => extractTwigValuePaths($arguments, 'content', $aliases),
        ];
    }

    return $rows;
}

/**
 * @return array<string, string>
 */
function extractTwigAliases(string $source): array
{
    preg_match_all('/\{%\s*set\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*((?:design|content)(?:\.[A-Za-z_][A-Za-z0-9_]*)+)\s*%\}/', $source, $matches, PREG_SET_ORDER);
    $aliases = [];

    foreach ($matches as $match) {
        $aliases[(string) $match[1]] = (string) $match[2];
    }

    return $aliases;
}

/**
 * @param array<string, string> $aliases
 * @return array<int, string>
 */
function extractTwigValuePaths(string $value, string $root, array $aliases): array
{
    preg_match_all('/\b' . preg_quote($root, '/') . '((?:\.[A-Za-z_][A-Za-z0-9_]*)+)/', $value, $matches);
    $paths = [];

    foreach ($matches[0] ?? [] as $match) {
        $paths[] = $match;
    }

    foreach ($aliases as $alias => $basePath) {
        if (!str_starts_with($basePath, $root . '.')) {
            continue;
        }

        preg_match_all('/\b' . preg_quote($alias, '/') . '((?:\.[A-Za-z_][A-Za-z0-9_]*)+)/', $value, $aliasMatches);
        foreach ($aliasMatches[0] ?? [] as $aliasMatch) {
            $paths[] = $basePath . substr($aliasMatch, strlen($alias));
        }
    }

    return array_values(array_unique($paths));
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

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}
