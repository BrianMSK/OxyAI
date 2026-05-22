<?php

declare(strict_types=1);

namespace OxyHtmlConverter\Services;

/**
 * Builds a single converter-safe HTML document from separate source fields.
 */
class SourceBundleNormalizer
{
    /**
     * @param array{html?: mixed, css?: mixed, js?: mixed} $input
     */
    public function fromRequest(array $input): string
    {
        $html = $this->stringValue($input['html'] ?? '');
        $css = $this->stringValue($input['css'] ?? '');
        $js = $this->stringValue($input['js'] ?? '');

        return $this->combine($html, $css, $js);
    }

    public function combine(string $html, string $css = '', string $js = ''): string
    {
        $parts = [];

        if (trim($css) !== '') {
            $parts[] = "<style data-oxy-html-converter-source=\"css\">\n" . trim($css) . "\n</style>";
        }

        $parts[] = trim($html);

        if (trim($js) !== '') {
            $parts[] = "<script data-oxy-html-converter-source=\"js\">\n" . $this->escapeScriptEndTags(trim($js)) . "\n</script>";
        }

        return trim(implode("\n\n", array_filter($parts, static fn (string $part): bool => trim($part) !== '')));
    }

    /**
     * @param mixed $value
     */
    private function stringValue($value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private function escapeScriptEndTags(string $js): string
    {
        return (string) preg_replace('/<\/script/i', '<\/script', $js);
    }
}
