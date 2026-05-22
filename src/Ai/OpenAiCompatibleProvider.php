<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Ai;

use OxyAI\Oxygen\Settings\SettingsRepository;
use WP_Error;

final class OpenAiCompatibleProvider extends AbstractHttpProvider implements ProviderInterface
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly PromptCompiler $promptCompiler,
        private readonly StructuredOutputValidator $validator
    ) {
    }

    public function generate(array $input)
    {
        $endpoint = rtrim((string) $this->settings->get('compatible_endpoint', ''), '/');
        if ($endpoint === '') {
            return new WP_Error('oxyai_missing_compatible_endpoint', __('OpenAI-compatible endpoint is not configured.', 'oxyai-oxygen'), ['status' => 400]);
        }

        $prompt = $this->promptCompiler->compile($input);
        $headers = [];
        $apiKey = $this->settings->getSecret('compatible_api_key');
        if ($apiKey !== '') {
            $headers['authorization'] = 'Bearer ' . $apiKey;
        }

        $response = $this->postJson($endpoint . '/v1/chat/completions', [
            'model' => (string) $this->settings->get('compatible_model', 'local-model'),
            'messages' => [
                ['role' => 'system', 'content' => $prompt['system']],
                ['role' => 'user', 'content' => $prompt['user'] . "\n\nReturn a JSON object with html, css, js, meta, and warnings."],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ], $headers);

        if (is_wp_error($response)) {
            return $response;
        }

        $content = (string) ($response['choices'][0]['message']['content'] ?? '');
        return $this->validator->validateJson($content);
    }
}
