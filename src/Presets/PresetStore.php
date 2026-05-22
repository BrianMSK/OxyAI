<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Presets;

final class PresetStore
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = get_option(OXYAI_OXYGEN_PRESETS_OPTION, []);
        if (!is_array($stored) || $stored === []) {
            return $this->defaults();
        }

        return array_values(array_filter($stored, 'is_array'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        foreach ($this->all() as $preset) {
            if (($preset['slug'] ?? '') === $slug) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $presets
     */
    public function save(array $presets): void
    {
        update_option(OXYAI_OXYGEN_PRESETS_OPTION, array_values(array_filter($presets, 'is_array')), false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaults(): array
    {
        return [
            [
                'slug' => 'clean-saas',
                'name' => 'Clean SaaS',
                'description' => 'Readable B2B SaaS sections with restrained spacing and high contrast.',
                'instructions' => 'Use clean semantic HTML, neutral color foundations, strong hierarchy, compact cards, and class-based CSS.',
            ],
            [
                'slug' => 'premium-service',
                'name' => 'Premium Service',
                'description' => 'Editorial service-business sections with polished typography and clear CTAs.',
                'instructions' => 'Use a premium editorial layout, strong image/content balance, refined typography, and accessible buttons.',
            ],
            [
                'slug' => 'conversion-landing',
                'name' => 'Conversion Landing',
                'description' => 'Landing-page blocks optimized for offers, benefits, and lead capture.',
                'instructions' => 'Use concise copy zones, benefit grids, CTA repetition, trust elements, and mobile-first sections.',
            ],
        ];
    }
}
