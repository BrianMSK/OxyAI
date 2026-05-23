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
            'summary' => is_array($audit['summary'] ?? null) ? $audit['summary'] : [],
            'preserved' => $this->stringList($audit['preserved'] ?? []),
            'transformed' => $this->stringList($audit['transformed'] ?? []),
            'stripped' => $this->stringList($audit['stripped'] ?? []),
            'followUp' => $this->stringList($audit['followUp'] ?? []),
        ];
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
