<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Rest;

use WP_Error;
use WP_REST_Response;

trait ResponseFactory
{
    /**
     * @param mixed $data
     */
    private function ok($data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response($data, $status);
    }

    private function error(WP_Error $error): WP_REST_Response
    {
        $data = $error->get_error_data();
        $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 500;

        return new WP_REST_Response([
            'success' => false,
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data' => $data,
        ], $status);
    }
}
