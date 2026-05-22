<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Rest;

use OxyAI\Oxygen\Codex\PageContextService;
use OxyAI\Oxygen\Codex\PromptInstructionService;
use OxyAI\Oxygen\Conversion\ConverterKernelAdapter;
use OxyAI\Oxygen\Oxygen\OxygenPageMutationService;
use OxyAI\Oxygen\Security\CapabilityService;
use OxyAI\Oxygen\Source\SourceBundle;
use WP_REST_Request;

final class CodexController
{
    use ResponseFactory;

    public function __construct(
        private readonly CapabilityService $capabilities,
        private readonly PromptInstructionService $instructions,
        private readonly PageContextService $pages,
        private readonly ConverterKernelAdapter $converter,
        private readonly OxygenPageMutationService $mutations
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('oxyai/v1', '/codex/instructions', [
                'methods' => 'GET',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn () => $this->ok(['success' => true, 'instructions' => $this->instructions->getInstructions()]),
            ]);

            register_rest_route('oxyai/v1', '/codex/pages', [
                'methods' => 'GET',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn (WP_REST_Request $request) => $this->ok([
                    'success' => true,
                    'pages' => $this->pages->listPages((string) $request->get_param('search')),
                ]),
            ]);

            register_rest_route('oxyai/v1', '/codex/page/(?P<id>\d+)', [
                'methods' => 'GET',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn (WP_REST_Request $request) => $this->ok($this->pages->getContext((int) $request['id'])),
            ]);

            register_rest_route('oxyai/v1', '/codex/page/(?P<id>\d+)/handoff', [
                [
                    'methods' => 'POST',
                    'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                    'callback' => fn (WP_REST_Request $request) => $this->ok($this->pages->stagePayload((int) $request['id'], (array) $request->get_json_params())),
                ],
                [
                    'methods' => 'DELETE',
                    'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                    'callback' => function (WP_REST_Request $request) {
                        $this->pages->clearHandoff((int) $request['id']);
                        return $this->ok(['success' => true]);
                    },
                ],
            ]);

            register_rest_route('oxyai/v1', '/codex/page/(?P<id>\d+)/tree', [
                'methods' => 'GET',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn (WP_REST_Request $request) => $this->tree($request),
            ]);

            register_rest_route('oxyai/v1', '/codex/page/(?P<id>\d+)/apply', [
                'methods' => 'POST',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn (WP_REST_Request $request) => $this->apply($request),
            ]);

            register_rest_route('oxyai/v1', '/codex/page/(?P<id>\d+)/backups', [
                'methods' => 'GET',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn (WP_REST_Request $request) => $this->ok([
                    'success' => true,
                    'backups' => $this->mutations->listBackups((int) $request['id']),
                ]),
            ]);

            register_rest_route('oxyai/v1', '/codex/page/(?P<id>\d+)/restore', [
                'methods' => 'POST',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn (WP_REST_Request $request) => $this->restore($request),
            ]);
        });
    }

    public function tree(WP_REST_Request $request)
    {
        $result = $this->mutations->getTree((int) $request['id']);
        return is_wp_error($result) ? $this->error($result) : $this->ok($result);
    }

    public function apply(WP_REST_Request $request)
    {
        $input = (array) $request->get_json_params();
        $postId = (int) $request['id'];

        $oxygen = $this->oxygenPayload($input);
        if (is_wp_error($oxygen)) {
            return $this->error($oxygen);
        }

        $result = $this->mutations->applyOxygen($postId, $oxygen, $input);
        if (is_wp_error($result)) {
            return $this->error($result);
        }

        return $this->ok($result);
    }

    public function restore(WP_REST_Request $request)
    {
        $input = (array) $request->get_json_params();
        $backupId = is_scalar($input['backupId'] ?? null) ? (string) $input['backupId'] : '';
        $result = $this->mutations->restoreBackup((int) $request['id'], $backupId);

        return is_wp_error($result) ? $this->error($result) : $this->ok($result);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|\WP_Error
     */
    private function oxygenPayload(array $input)
    {
        if (isset($input['oxygen']) && is_array($input['oxygen'])) {
            return $input['oxygen'];
        }

        if (isset($input['rawJson']) || isset($input['json']) || isset($input['element']) || isset($input['documentTree'])) {
            return $input;
        }

        $converted = $this->converter->convert(
            SourceBundle::fromArray($input),
            is_array($input['options'] ?? null) ? $input['options'] : []
        );

        if (is_wp_error($converted)) {
            return $converted;
        }

        return is_array($converted['oxygen'] ?? null) ? $converted['oxygen'] : [];
    }
}
