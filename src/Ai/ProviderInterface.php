<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Ai;

use OxyAI\Oxygen\Source\SourceBundle;
use WP_Error;

interface ProviderInterface
{
    /**
     * @param array<string, mixed> $input
     * @return SourceBundle|WP_Error
     */
    public function generate(array $input);
}
