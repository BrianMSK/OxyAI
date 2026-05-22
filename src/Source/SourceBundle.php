<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Source;

final class SourceBundle
{
    /**
     * @param array<int, string> $warnings
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $html,
        public readonly string $css = '',
        public readonly string $js = '',
        public readonly array $warnings = [],
        public readonly array $meta = []
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            html: self::stringValue($input['html'] ?? ''),
            css: self::stringValue($input['css'] ?? ''),
            js: self::stringValue($input['js'] ?? ''),
            warnings: self::stringList($input['warnings'] ?? []),
            meta: is_array($input['meta'] ?? null) ? $input['meta'] : []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'html' => $this->html,
            'css' => $this->css,
            'js' => $this->js,
            'warnings' => $this->warnings,
            'meta' => $this->meta,
        ];
    }

    public function isEmpty(): bool
    {
        return trim($this->html) === '';
    }

    /**
     * @param mixed $value
     */
    private static function stringValue($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private static function stringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): string => is_scalar($item) ? trim((string) $item) : '',
            $value
        )));
    }
}
