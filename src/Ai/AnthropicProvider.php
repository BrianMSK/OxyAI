<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Ai;

use OxyAI\Oxygen\Settings\SettingsRepository;
use WP_Error;

final class AnthropicProvider extends AbstractHttpProvider implements ProviderInterface
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly PromptCompiler $promptCompiler,
        private readonly StructuredOutputValidator $validator
    ) {
    }

    public function generate(array $input)
    {
        $apiKey = $this->settings->getSecret('anthropic_api_key');
        if ($apiKey === '') {
            return new WP_Error('oxyai_missing_anthropic_key', __('Anthropic API key is not configured.', 'oxyai-oxygen'), ['status' => 400]);
        }

        $prompt = $this->promptCompiler->compile($input);
        $body = [
            'model' => (string) $this->settings->get('anthropic_model', 'claude-opus-4-1-20250805'),
            'max_tokens' => 4096,
            'system' => $prompt['system'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt['user']],
            ],
            'tools' => [[
                'name' => 'emit_source_bundle',
                'description' => 'Return HTML, CSS, and JavaScript for Oxygen conversion.',
                'input_schema' => $this->validator->jsonSchema(),
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => 'emit_source_bundle'],
        ];

        $response = $this->postJson('https://api.anthropic.com/v1/messages', $body, [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        foreach (($response['content'] ?? []) as $content) {
            if (is_array($content) && ($content['type'] ?? '') === 'tool_use' && is_array($content['input'] ?? null)) {
                return $this->validator->validateArray($content['input']);
            }
        }

        return $this->validator->validateJson($this->extractText($response));
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractText(array $response): string
    {
        foreach (($response['content'] ?? []) as $content) {
            if (is_array($content) && ($content['type'] ?? '') === 'text' && is_string($content['text'] ?? null)) {
                return (string) $content['text'];
            }
        }

        return '';
    }
}
