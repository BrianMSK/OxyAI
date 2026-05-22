<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Builder;

final class BuilderInsertionService
{
    /**
     * @param array<string, mixed> $oxygen
     * @return array<string, mixed>
     */
    public function prepareInsertPayload(array $oxygen): array
    {
        return [
            'mode' => 'builder-paste',
            'json' => (string) ($oxygen['rawJson'] ?? ''),
            'element' => $oxygen['element'] ?? null,
            'documentTree' => $oxygen['documentTree'] ?? null,
            'audit' => $oxygen['audit'] ?? [],
            'instructions' => [
                __('Open the Oxygen builder and use the OxyAI panel to insert this payload directly.', 'oxyai-oxygen'),
                __('Remote clients receive payloads; direct mutation requires an authenticated browser builder session.', 'oxyai-oxygen'),
            ],
        ];
    }
}
