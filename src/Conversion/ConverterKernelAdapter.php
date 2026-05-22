<?php

declare(strict_types=1);

namespace OxyAI\Oxygen\Conversion;

use OxyAI\Oxygen\Source\SourceBundle;
use OxyAI\Oxygen\Source\SourceBundleNormalizer;
use OxyHtmlConverter\Services\ConversionAuditBuilder;
use OxyHtmlConverter\Services\ConvertPayloadBuilder;
use OxyHtmlConverter\Services\OxygenDocumentTree;
use OxyHtmlConverter\Services\TreeBuilderFactory;
use OxyHtmlConverter\Validation\OutputValidator;
use WP_Error;

final class ConverterKernelAdapter
{
    private readonly SourceBundleNormalizer $sourceNormalizer;
    private readonly ConversionOptions $optionsNormalizer;
    private readonly OxygenPayloadAdapter $payloadAdapter;

    public function __construct()
    {
        $this->sourceNormalizer = new SourceBundleNormalizer();
        $this->optionsNormalizer = new ConversionOptions();
        $this->payloadAdapter = new OxygenPayloadAdapter();
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>|WP_Error
     */
    public function convert(SourceBundle $source, array $options = [])
    {
        if ($source->isEmpty()) {
            return new WP_Error('oxyai_empty_html', __('HTML is required before conversion.', 'oxyai-oxygen'), ['status' => 400]);
        }

        $normalizedOptions = $this->optionsNormalizer->normalize($options);
        $converterHtml = $this->sourceNormalizer->toConverterHtml($source);

        if (strlen($converterHtml) > 1048576) {
            return new WP_Error('oxyai_source_too_large', __('Source bundle is too large. The limit is 1MB per conversion.', 'oxyai-oxygen'), ['status' => 413]);
        }

        try {
            $builderFactory = new TreeBuilderFactory();
            $builder = $builderFactory->createForConvert($normalizedOptions, $converterHtml);
            $result = $builder->convert($converterHtml);

            if (empty($result['success'])) {
                return new WP_Error(
                    'oxyai_conversion_failed',
                    (string) ($result['error'] ?? __('Conversion failed.', 'oxyai-oxygen')),
                    ['status' => 400, 'errors' => $result['errors'] ?? []]
                );
            }

            $payloadBuilder = new ConvertPayloadBuilder(
                new OxygenDocumentTree(),
                new ConversionAuditBuilder(),
                new OutputValidator()
            );
            $payload = $payloadBuilder->build($result, $normalizedOptions);

            if (empty($payload['success'])) {
                return new WP_Error(
                    'oxyai_payload_invalid',
                    (string) ($payload['data']['message'] ?? __('Converted output failed builder validation.', 'oxyai-oxygen')),
                    ['status' => (int) ($payload['status'] ?? 422), 'payload' => $payload['data'] ?? []]
                );
            }

            return [
                'success' => true,
                'source' => $source->toArray(),
                'options' => $normalizedOptions,
                'oxygen' => $this->payloadAdapter->shape($payload, $normalizedOptions),
            ];
        } catch (\Throwable $e) {
            do_action('oxyai_oxygen_conversion_exception', $e, $source, $normalizedOptions);
            return new WP_Error('oxyai_conversion_exception', __('Conversion failed due to an internal error.', 'oxyai-oxygen'), ['status' => 500]);
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>|WP_Error
     */
    public function preview(SourceBundle $source, array $options = [])
    {
        $converted = $this->convert($source, $options);
        if (is_wp_error($converted)) {
            return $converted;
        }

        $oxygen = is_array($converted['oxygen'] ?? null) ? $converted['oxygen'] : [];
        $summary = $this->summarizeElementTree(is_array($oxygen['element'] ?? null) ? $oxygen['element'] : []);

        return [
            'success' => true,
            'source' => $source->toArray(),
            'summary' => $summary,
            'audit' => $oxygen['audit'] ?? [],
            'customClasses' => $oxygen['customClasses'] ?? [],
            'extractedCssLength' => strlen((string) ($oxygen['extractedCss'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>
     */
    private function summarizeElementTree(array $element): array
    {
        $types = [];
        $count = $this->collectTypes($element, $types);

        return [
            'elementCount' => $count,
            'byType' => $types,
        ];
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, int> $types
     */
    private function collectTypes(array $element, array &$types): int
    {
        if ($element === []) {
            return 0;
        }

        $type = (string) ($element['data']['type'] ?? 'unknown');
        $types[$type] = ($types[$type] ?? 0) + 1;
        $count = 1;

        foreach (($element['children'] ?? []) as $child) {
            if (is_array($child)) {
                $count += $this->collectTypes($child, $types);
            }
        }

        return $count;
    }
}
