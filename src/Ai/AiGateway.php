<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Ai;

use OxyAI\Oxygen\Settings\SettingsRepository;
use WP_Error;

final class AiGateway
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly PromptCompiler $promptCompiler,
        private readonly StructuredOutputValidator $validator
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function generate(array $input)
    {
        $provider = $this->provider((string) ($input['provider'] ?? $this->settings->get('provider', 'openai')));
        return $provider->generate($input);
    }

    private function provider(string $provider): ProviderInterface
    {
        return match ($provider) {
            'anthropic' => new AnthropicProvider($this->settings, $this->promptCompiler, $this->validator),
            'compatible' => new OpenAiCompatibleProvider($this->settings, $this->promptCompiler, $this->validator),
            default => new OpenAiProvider($this->settings, $this->promptCompiler, $this->validator),
        };
    }
}
