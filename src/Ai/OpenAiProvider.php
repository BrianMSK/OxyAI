<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Ai;

use OxyAI\Oxygen\Settings\SettingsRepository;
use WP_Error;

final class OpenAiProvider extends AbstractHttpProvider implements ProviderInterface
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly PromptCompiler $promptCompiler,
        private readonly StructuredOutputValidator $validator
    ) {
    }

    public function generate(array $input)
    {
        $apiKey = $this->settings->getSecret('openai_api_key');
        if ($apiKey === '') {
            return new WP_Error('oxyai_missing_openai_key', __('OpenAI API key is not configured.', 'oxyai-oxygen'), ['status' => 400]);
        }

        $prompt = $this->promptCompiler->compile($input);
        $body = [
            'model' => (string) $this->settings->get('openai_model', 'gpt-5.2'),
            'input' => [
                ['role' => 'system', 'content' => $prompt['system']],
                ['role' => 'user', 'content' => $prompt['user']],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'oxyai_source_bundle',
                    'strict' => true,
                    'schema' => $this->validator->jsonSchema(),
                ],
            ],
        ];

        $response = $this->postJson('https://api.openai.com/v1/responses', $body, [
            'authorization' => 'Bearer ' . $apiKey,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        $text = $this->extractText($response);
        return $this->validator->validateJson($text);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractText(array $response): string
    {
        if (is_string($response['output_text'] ?? null)) {
            return (string) $response['output_text'];
        }

        foreach (($response['output'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (($item['content'] ?? []) as $content) {
                if (is_array($content) && is_string($content['text'] ?? null)) {
                    return (string) $content['text'];
                }
            }
        }

        return '';
    }
}
