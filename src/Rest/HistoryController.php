<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Rest;

use OxyAI\Oxygen\History\HistoryStore;
use OxyAI\Oxygen\Presets\PresetStore;
use OxyAI\Oxygen\Security\CapabilityService;
use WP_REST_Request;

final class HistoryController
{
    use ResponseFactory;

    public function __construct(
        private readonly CapabilityService $capabilities,
        private readonly HistoryStore $history,
        private readonly PresetStore $presets
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('oxyai/v1', '/history', [
                [
                    'methods' => 'GET',
                    'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                    'callback' => fn () => $this->ok(['success' => true, 'history' => $this->history->all()]),
                ],
                [
                    'methods' => 'DELETE',
                    'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                    'callback' => function () {
                        $this->history->clear();
                        return $this->ok(['success' => true]);
                    },
                ],
            ]);

            register_rest_route('oxyai/v1', '/presets', [
                'methods' => 'GET',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn () => $this->ok(['success' => true, 'presets' => $this->presets->all()]),
            ]);

            register_rest_route('oxyai/v1', '/presets', [
                'methods' => 'POST',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn (WP_REST_Request $request) => $this->savePresets($request),
            ]);
        });
    }

    public function savePresets(WP_REST_Request $request)
    {
        $presets = $request->get_param('presets');
        $this->presets->save(is_array($presets) ? $presets : []);
        return $this->ok(['success' => true, 'presets' => $this->presets->all()]);
    }
}
