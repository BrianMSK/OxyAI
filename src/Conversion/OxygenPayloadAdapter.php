<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Conversion;

final class OxygenPayloadAdapter
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function shape(array $payload, array $options): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (!empty($options['useSelectors'])) {
            if (!isset($data['audit']) || !is_array($data['audit'])) {
                $data['audit'] = [];
            }
            if (!isset($data['audit']['followUp']) || !is_array($data['audit']['followUp'])) {
                $data['audit']['followUp'] = [];
            }
            $data['audit']['followUp'][] = __('Selector-library mode is enabled: direct class selector styles are registered as Oxygen selector properties for editor visibility; complex selectors, pseudo states, media queries, and unsupported CSS remain in CSS Code.', 'oxyai-oxygen');
        }

        return [
            'element' => $data['element'] ?? null,
            'documentTree' => $data['documentTree'] ?? null,
            'documentJson' => $data['documentJson'] ?? null,
            'cssElement' => $data['cssElement'] ?? null,
            'extractedCss' => $data['extractedCss'] ?? '',
            'customClasses' => $data['customClasses'] ?? [],
            'stats' => $data['stats'] ?? [],
            'audit' => $this->normalizeAudit($data['audit'] ?? []),
            'rawJson' => $data['json'] ?? '',
        ];
    }

    /**
     * @param mixed $audit
     * @return array<string, mixed>
     */
    public function normalizeAudit($audit): array
    {
        $audit = is_array($audit) ? $audit : [];

        return [
            'summary' => $this->arrayMap($audit['summary'] ?? []),
            'preserved' => $this->arrayMap($audit['preserved'] ?? []),
            'transformed' => $this->arrayMap($audit['transformed'] ?? []),
            'stripped' => $this->stringList($audit['stripped'] ?? []),
            'followUp' => $this->stringList($audit['followUp'] ?? []),
            'diagnostics' => $this->arrayMap($audit['diagnostics'] ?? []),
        ];
    }

    /**
     * @param mixed $value
     * @return array<mixed>
     */
    private function arrayMap($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $normalized[$key] = $this->arrayMap($item);
                continue;
            }

            if (is_scalar($item) || $item === null) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function stringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): string => is_scalar($item) ? (string) $item : '',
            $value
        )));
    }
}
