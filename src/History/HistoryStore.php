<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\History;

use OxyAI\Oxygen\Settings\SettingsRepository;

final class HistoryStore
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('history_enabled', false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $history = get_option(OXYAI_OXYGEN_HISTORY_OPTION, []);
        return is_array($history) ? array_values(array_filter($history, 'is_array')) : [];
    }

    /**
     * @param array<string, mixed> $record
     */
    public function add(array $record): void
    {
        if (!$this->enabled()) {
            return;
        }

        $history = $this->all();
        array_unshift($history, array_merge([
            'id' => wp_generate_uuid4(),
            'createdAt' => gmdate('c'),
        ], $this->redact($record)));

        update_option(OXYAI_OXYGEN_HISTORY_OPTION, array_slice($history, 0, 50), false);
    }

    public function clear(): void
    {
        delete_option(OXYAI_OXYGEN_HISTORY_OPTION);
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function redact(array $record): array
    {
        unset($record['apiKey'], $record['secret'], $record['headers']);
        return $record;
    }
}
