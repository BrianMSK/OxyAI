<?php

declare(strict_types=1);

namespace OxyHtmlConverter\Services;

class ConversionAuditBuilder
{
    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function build(array $result, array $options): array
    {
        $stats = is_array($result['stats'] ?? null) ? $result['stats'] : [];
        $warnings = array_values(array_unique(array_merge(
            $this->normalizeMessages($stats['warnings'] ?? []),
            $this->detectBreakdanceDeadWriteWarnings($result['element'] ?? null)
        )));
        $errors = $this->normalizeMessages($stats['errors'] ?? []);
        $info = $this->normalizeMessages($stats['info'] ?? []);
        $validationErrors = $this->normalizeMessages($result['validationErrors'] ?? []);
        $validationWarnings = $this->normalizeMessages($result['validationWarnings'] ?? []);
        $iconLibraries = $this->normalizeMessages($result['detectedIconLibraries'] ?? []);
        $headLinkCount = is_array($result['headLinkElements'] ?? null) ? count($result['headLinkElements']) : 0;
        $headScriptCount = is_array($result['headScriptElements'] ?? null) ? count($result['headScriptElements']) : 0;
        $iconScriptCount = is_array($result['iconScriptElements'] ?? null) ? count($result['iconScriptElements']) : 0;
        $customClasses = is_array($result['customClasses'] ?? null) ? $result['customClasses'] : [];
        $preserveStyleBlockCss = (bool) ($result['preserveStyleBlockCss'] ?? true);
        $redistributedCssSelectors = is_array($result['redistributedCssSelectors'] ?? null)
            ? array_values($result['redistributedCssSelectors'])
            : [];
        $retainedCssSelectors = is_array($result['retainedCssSelectors'] ?? null)
            ? array_values($result['retainedCssSelectors'])
            : [];

        $stripped = [];
        if (!empty($options['safeMode'])) {
            $stripped[] = 'Scripts, event handlers, and external head assets were removed by Safe Mode.';
        }

        $followUp = [];
        if ($validationErrors) {
            $followUp[] = 'Converted output failed builder validation. Review the reported issues before importing.';
        }
        if ($warnings) {
            $followUp[] = 'Review conversion warnings for unsupported or partially transformed constructs.';
        }
        if (!empty($customClasses)) {
            $followUp[] = 'Verify residual custom classes and CSS fallbacks on the frontend.';
        }
        if (!$preserveStyleBlockCss && $retainedCssSelectors !== []) {
            $followUp[] = sprintf(
                '%d CSS selector fallback rule(s) were retained because their native mapping cannot yet be safely stripped for the target Oxygen element type. Do not assume mapCssToProperties removes every CssCode rule.',
                count($retainedCssSelectors)
            );
        }

        $audit = [
            'summary' => [
                'elements' => (int) ($stats['elements'] ?? 0),
                'tailwindClasses' => (int) ($stats['tailwindClasses'] ?? 0),
                'customClasses' => (int) ($stats['customClasses'] ?? 0),
                'warningsCount' => count($warnings),
                'errorsCount' => count($errors) + count($validationErrors),
                'headLinkCount' => $headLinkCount,
                'headScriptCount' => $headScriptCount,
                'iconScriptCount' => $iconScriptCount,
                'hasExtractedCss' => trim((string) ($result['extractedCss'] ?? '')) !== '',
            ],
            'preserved' => [
                'customClasses' => array_values(array_map('strval', $customClasses)),
                'iconLibraries' => $iconLibraries,
                'headAssets' => [
                    'links' => $headLinkCount,
                    'scripts' => $headScriptCount,
                    'iconScripts' => $iconScriptCount,
                ],
            ],
            'transformed' => [
                'wrapInContainer' => !empty($options['wrapInContainer']),
                'includeCssElement' => !empty($options['includeCssElement']),
                'inlineStyles' => !empty($options['inlineStyles']),
                'safeMode' => !empty($options['safeMode']),
                'preserveStyleBlockCss' => $preserveStyleBlockCss,
                'redistributedCssSelectors' => $redistributedCssSelectors,
                'retainedCssSelectors' => $retainedCssSelectors,
                'info' => $info,
            ],
            'stripped' => $stripped,
            'followUp' => array_values(array_unique(array_merge($followUp, $validationWarnings))),
            'diagnostics' => [
                'warnings' => $warnings,
                'errors' => $errors,
                'validationErrors' => $validationErrors,
                'validationWarnings' => $validationWarnings,
            ],
        ];

        return (array) apply_filters('oxy_html_converter_conversion_audit', $audit, $result, $options);
    }

    /**
     * @param mixed $messages
     * @return array<int, string>
     */
    private function normalizeMessages($messages): array
    {
        if (!is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (!is_scalar($message)) {
                continue;
            }

            $value = trim((string) $message);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param mixed $node
     * @return array<int, string>
     */
    private function detectBreakdanceDeadWriteWarnings($node): array
    {
        if (!is_array($node)) {
            return [];
        }

        $warnings = [];
        $this->collectBreakdanceDeadWriteWarnings($node, $warnings);

        return array_values(array_unique($warnings));
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $warnings
     */
    private function collectBreakdanceDeadWriteWarnings(array $node, array &$warnings): void
    {
        $type = (string) ($node['data']['type'] ?? '');
        $design = $node['data']['properties']['design'] ?? [];
        $design = is_array($design) ? $design : [];

        if (
            in_array($type, ['EssentialElements\\Columns', 'EssentialElements\\Column'], true)
            && $this->hasHorizontalAutoMargin($design)
        ) {
            $warnings[] = 'Breakdance dead write: container.margin left/right "auto" on EssentialElements\\Columns/Column persists in JSON but does not compile. Use an OxygenElements\\Container wrapper or center the parent Column with the full alignment bundle.';
        }

        if ($type === 'EssentialElements\\Columns' && $this->pathExists($design, ['layout', 'justify_content'])) {
            $warnings[] = 'Breakdance dead write: layout.justify_content on EssentialElements\\Columns persists in JSON but does not compile. Use an OxygenElements\\Container outer wrapper or move alignment to the child Column.';
        }

        if (
            $type === 'EssentialElements\\Column'
            && $this->pathExists($design, ['layout', 'align_items'])
            && (!$this->pathExists($design, ['layout', 'align']) || !$this->pathExists($design, ['layout', 'vertical_align']))
        ) {
            $warnings[] = 'Breakdance partial alignment: EssentialElements\\Column only compiles alignment reliably when align_items, align, and vertical_align are written together.';
        }

        foreach (($node['children'] ?? []) as $child) {
            if (is_array($child)) {
                $this->collectBreakdanceDeadWriteWarnings($child, $warnings);
            }
        }
    }

    /**
     * @param array<string, mixed> $design
     */
    private function hasHorizontalAutoMargin(array $design): bool
    {
        $margin = $design['container']['margin'] ?? null;
        if (!is_array($margin)) {
            return false;
        }

        foreach ($margin as $breakpointValue) {
            if (!is_array($breakpointValue)) {
                continue;
            }

            foreach (['left', 'right'] as $side) {
                if (isset($breakpointValue[$side]) && strtolower(trim((string) $breakpointValue[$side])) === 'auto') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $array
     * @param array<int, string> $path
     */
    private function pathExists(array $array, array $path): bool
    {
        $current = $array;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }
}
