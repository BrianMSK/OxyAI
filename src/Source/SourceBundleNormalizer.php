<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Source;

final class SourceBundleNormalizer
{
    public function toConverterHtml(SourceBundle $source): string
    {
        $parts = [];

        if (trim($source->css) !== '') {
            $parts[] = "<style data-oxyai-source=\"css\">\n" . trim($source->css) . "\n</style>";
        }

        $parts[] = trim($source->html);

        if (trim($source->js) !== '') {
            $parts[] = "<script data-oxyai-source=\"js\">\n" . $this->escapeScriptEndTags(trim($source->js)) . "\n</script>";
        }

        return trim(implode("\n\n", array_filter($parts, static fn (string $part): bool => trim($part) !== '')));
    }

    private function escapeScriptEndTags(string $js): string
    {
        return (string) preg_replace('/<\/script/i', '<\/script', $js);
    }
}
