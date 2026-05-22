<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Security;

use OxyAI\Oxygen\Settings\SettingsRepository;
use WP_REST_Request;

final class CapabilityService
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function requiredCapability(): string
    {
        return (string) apply_filters('oxyai_oxygen_required_capability', 'manage_options');
    }

    public function canUse(): bool
    {
        return current_user_can($this->requiredCapability());
    }

    public function canUseRest(): bool
    {
        return $this->canUse();
    }

    public function canUseMcp(WP_REST_Request $request): bool
    {
        if ($this->canUse()) {
            return true;
        }

        $configuredToken = $this->settings->get('mcp_token', '');
        if (!is_string($configuredToken) || $configuredToken === '') {
            return false;
        }

        $requestToken = (string) $request->get_header('x-oxyai-token');
        if ($requestToken === '') {
            $authorization = (string) $request->get_header('authorization');
            if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
                $requestToken = trim($matches[1]);
            }
        }
        if ($requestToken === '') {
            $requestToken = is_scalar($request->get_param('oxyai_token')) ? (string) $request->get_param('oxyai_token') : '';
        }

        return hash_equals($configuredToken, $requestToken);
    }
}
