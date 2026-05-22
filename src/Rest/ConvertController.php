<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Rest;

use OxyAI\Oxygen\Conversion\ConverterKernelAdapter;
use OxyAI\Oxygen\Security\CapabilityService;
use OxyAI\Oxygen\Source\SourceBundle;
use WP_REST_Request;

final class ConvertController
{
    use ResponseFactory;

    public function __construct(
        private readonly CapabilityService $capabilities,
        private readonly ConverterKernelAdapter $converter
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('oxyai/v1', '/preview', [
                'methods' => 'POST',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn (WP_REST_Request $request) => $this->preview($request),
            ]);

            register_rest_route('oxyai/v1', '/convert', [
                'methods' => 'POST',
                'permission_callback' => fn (): bool => $this->capabilities->canUseRest(),
                'callback' => fn (WP_REST_Request $request) => $this->convert($request),
            ]);
        });
    }

    public function preview(WP_REST_Request $request)
    {
        $result = $this->converter->preview($this->source($request), $this->options($request));
        return is_wp_error($result) ? $this->error($result) : $this->ok($result);
    }

    public function convert(WP_REST_Request $request)
    {
        $result = $this->converter->convert($this->source($request), $this->options($request));
        return is_wp_error($result) ? $this->error($result) : $this->ok($result);
    }

    private function source(WP_REST_Request $request): SourceBundle
    {
        return SourceBundle::fromArray([
            'html' => $request->get_param('html'),
            'css' => $request->get_param('css'),
            'js' => $request->get_param('js'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(WP_REST_Request $request): array
    {
        $options = $request->get_param('options');
        return is_array($options) ? $options : [];
    }
}
