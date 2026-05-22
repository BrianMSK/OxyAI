<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Ai;

use OxyAI\Oxygen\Presets\PresetStore;

final class PromptCompiler
{
    public function __construct(private readonly PresetStore $presets)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function compile(array $input): array
    {
        $presetSlug = is_string($input['preset'] ?? null) ? (string) $input['preset'] : '';
        $preset = $presetSlug !== '' ? $this->presets->find($presetSlug) : null;
        $presetInstructions = is_array($preset) ? (string) ($preset['instructions'] ?? '') : '';
        $context = is_array($input['context'] ?? null) ? wp_json_encode($input['context']) : '';

        $system = implode("\n", array_filter([
            'You generate code for conversion into native Oxygen Builder 6 elements.',
            'Return only JSON matching the requested schema. Do not include markdown fences.',
            'Use one logical HTML root when possible.',
            'Prefer semantic HTML and class-based CSS.',
            'Keep JavaScript minimal and progressive.',
            'Do not include dynamic WordPress bindings, loops, forms, or server code.',
            'Avoid framework-specific directives unless explicitly requested.',
            $presetInstructions !== '' ? 'Preset instructions: ' . $presetInstructions : '',
        ]));

        $user = (string) ($input['prompt'] ?? '');
        if ($context !== '') {
            $user .= "\n\nExisting subtree/context JSON:\n" . $context;
        }

        return [
            'system' => $system,
            'user' => $user,
        ];
    }
}
