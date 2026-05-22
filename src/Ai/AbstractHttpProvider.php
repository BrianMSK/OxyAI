<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Ai;

use WP_Error;

abstract class AbstractHttpProvider
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>|WP_Error
     */
    protected function postJson(string $url, array $body, array $headers)
    {
        $response = wp_remote_post($url, [
            'timeout' => 60,
            'headers' => array_merge(['content-type' => 'application/json'], $headers),
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);

        if ($status < 200 || $status >= 300) {
            return new WP_Error('oxyai_provider_http_error', __('AI provider request failed.', 'oxyai-oxygen'), [
                'status' => $status,
                'body' => is_array($decoded) ? $decoded : $raw,
            ]);
        }

        if (!is_array($decoded)) {
            return new WP_Error('oxyai_provider_invalid_response', __('AI provider returned invalid JSON.', 'oxyai-oxygen'), ['status' => 502]);
        }

        return $decoded;
    }
}
