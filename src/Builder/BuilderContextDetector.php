<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Builder;

final class BuilderContextDetector
{
    public function isOxygenActive(): bool
    {
        return (
            defined('__BREAKDANCE_PLUGIN_FILE__')
            && defined('BREAKDANCE_MODE')
            && BREAKDANCE_MODE === 'oxygen'
        ) || defined('CT_VERSION') || class_exists('\\OxygenElements\\Container');
    }

    public function isBuilderRequest(): bool
    {
        $oxygen = isset($_GET['oxygen']) ? sanitize_text_field(wp_unslash((string) $_GET['oxygen'])) : '';
        $breakdance = isset($_GET['breakdance']) ? sanitize_text_field(wp_unslash((string) $_GET['breakdance'])) : '';

        return $oxygen === 'builder'
            || $breakdance === 'builder'
            || isset($_GET['ct_builder'])
            || !empty($_GET['breakdance_iframe'])
            || !empty($_GET['oxygen_iframe'])
            || !empty($_GET['breakdance_gutenberg_iframe'])
            || (defined('OXYGEN_IFRAME') && OXYGEN_IFRAME);
    }
}
