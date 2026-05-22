<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Ai;

use OxyAI\Oxygen\Source\SourceBundle;
use WP_Error;

final class StructuredOutputValidator
{
    /**
     * @return SourceBundle|WP_Error
     */
    public function validateJson(string $json)
    {
        $decoded = json_decode(trim($json), true);
        if (!is_array($decoded)) {
            return new WP_Error('oxyai_ai_invalid_json', __('AI response was not valid JSON.', 'oxyai-oxygen'), ['status' => 502]);
        }

        return $this->validateArray($decoded);
    }

    /**
     * @param array<string, mixed> $decoded
     * @return SourceBundle|WP_Error
     */
    public function validateArray(array $decoded)
    {
        if (!isset($decoded['html']) || !is_string($decoded['html']) || trim($decoded['html']) === '') {
            return new WP_Error('oxyai_ai_missing_html', __('AI response did not include HTML.', 'oxyai-oxygen'), ['status' => 502]);
        }

        foreach (['css', 'js'] as $optional) {
            if (isset($decoded[$optional]) && !is_string($decoded[$optional])) {
                return new WP_Error('oxyai_ai_invalid_source_field', sprintf(__('AI response field "%s" must be a string.', 'oxyai-oxygen'), $optional), ['status' => 502]);
            }
        }

        return SourceBundle::fromArray($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['html', 'css', 'js', 'meta', 'warnings'],
            'properties' => [
                'html' => ['type' => 'string'],
                'css' => ['type' => 'string'],
                'js' => ['type' => 'string'],
                'warnings' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'meta' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'properties' => [
                        'page_type' => ['type' => 'string'],
                        'root_selector' => ['type' => 'string'],
                        'notes' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
