<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OpenTelemetry\API\Trace\SpanContextKey;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;

final class TracingService implements TracingServiceInterface
{
    /**
     * Base tracer service.
     *
     * @var \OpenTelemetry\API\Trace\TracerInterface
     */
    private TracerInterface $tracer;

    /**
     * The root span.
     *
     * @var \OpenTelemetry\API\Trace\SpanInterface|null
     */
    private ?SpanInterface $rootSpan = null;

    /**
     * TracingService constructor.
     *
     * @param \OpenTelemetry\API\Trace\TracerProviderInterface $tracerProvider
     * @param \OpenTelemetry\Context\Propagation\TextMapPropagatorInterface $propagator
     * @param array $tracingConfig
     */
    public function __construct(
        private readonly TracerProviderInterface $tracerProvider,
        private readonly TextMapPropagatorInterface $propagator,
        private readonly array $tracingConfig = [],
    ) {
        $serviceName = Arr::get($this->tracingConfig, 'service-name', 'jaeger');

        $this->tracer = $this->tracerProvider->getTracer($serviceName);
    }

    /**
     * Starting span for trace.
     *
     * @param string $spanName
     * @param \OpenTelemetry\Context\Context $context
     * @param int $spanKind
     * @param float|null $timestamp
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    public function startSpan(string $spanName, Context $context, int $spanKind, ?float $timestamp = null): SpanInterface
    {
        $spanBuilder = $this->tracer->spanBuilder($spanName)->setSpanKind($spanKind);
        if (!is_null($timestamp)) {
            $spanBuilder->setStartTimestamp((int) $timestamp);
        }
        if (is_null($context->get(SpanContextKey::instance()))) {
            $spanBuilder->setNoParent();
        } else {
            $spanBuilder->setParent($context);
        }
        $span = $spanBuilder->startSpan();
        $span->setAttribute('service.major', Arr::get($this->tracingConfig, 'service-name', 'jaeger'));

        if (!$this->hasRootSpan()) {
            $this->rootSpan = $span;
        }

        return $span;
    }

    /**
     * Check for root span's existence.
     *
     * @return bool
     */
    public function hasRootSpan(): bool
    {
        return !is_null($this->rootSpan);
    }

    /**
     * Retrieving root span.
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface|null
     */
    public function getRootSpan(): ?SpanInterface
    {
        return $this->rootSpan;
    }

    /**
     * Writing context to given data.
     *
     * @param \OpenTelemetry\Context\Context $context
     * @param $carrier
     *
     * @return void
     */
    public function inject(Context $context, &$carrier): void
    {
        $this->propagator->inject($carrier, null, $context);
    }

    /**
     * Retrieving context from given data.
     *
     * @param $carrier
     *
     * @return \OpenTelemetry\Context\Context
     */
    public function extract($carrier): Context
    {
        return $this->propagator->extract($carrier);
    }

    /**
     * Stopping current trace.
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->hasRootSpan()) {
            $this->rootSpan->end();
            $this->rootSpan = null;
        }
    }

    /**
     * Filtering and hiding headers.
     *
     * @param array $headers
     *
     * @return array
     */
    public function filterHeaders(array $headers = []): array
    {
        return $this->hideSensitiveHeaders($this->filterAllowedHeaders(collect($headers)))->all();
    }

    /**
     * Filtering and hiding input data.
     *
     * @param array $input
     *
     * @return array
     */
    public function filterInput(array $input = []): array
    {
        return $this->hideSensitiveInput(collect($input))->all();
    }

    /**
     * Transforming headers.
     *
     * @param array $headers
     *
     * @return string
     */
    public function transformedHeaders(array $headers = []): string
    {
        if (!$headers) {
            return '';
        }

        ksort($headers);
        $max = max(array_map('strlen', array_keys($headers))) + 1;

        $content = '';
        foreach ($headers as $name => $values) {
            $name = implode('-', array_map('ucfirst', explode('-', $name)));

            if (is_array($values)) {
                foreach ($values as $value) {
                    $content .= sprintf("%-{$max}s %s\r\n", $name.':', $value);
                }
            } else {
                $content .= sprintf("%-{$max}s %s\r\n", $name.':', $values);
            }
        }

        return $content;
    }

    /**
     * Filtering away not allowed headers.
     *
     * @param \Illuminate\Support\Collection $headers
     *
     * @return \Illuminate\Support\Collection
     */
    private function filterAllowedHeaders(Collection $headers): Collection
    {
        $allowedHeaders = Arr::get($this->tracingConfig, 'middleware.allowed_headers');

        if (in_array('*', $allowedHeaders, true)) {
            return $headers;
        }

        $normalizedHeaders = array_map('strtolower', $allowedHeaders);

        return $headers->filter(function ($value, $name) use ($normalizedHeaders) {
            return in_array($name, $normalizedHeaders, true);
        });
    }

    /**
     * Hiding sensitive data in headers' values.
     *
     * @param \Illuminate\Support\Collection $headers
     *
     * @return \Illuminate\Support\Collection
     */
    private function hideSensitiveHeaders(Collection $headers): Collection
    {
        $sensitiveHeaders = Arr::get($this->tracingConfig, 'middleware.sensitive_headers');

        $normalizedHeaders = array_map('strtolower', $sensitiveHeaders);

        $headers->transform(function ($value, $name) use ($normalizedHeaders) {
            return in_array($name, $normalizedHeaders, true) ? ['This value is hidden because it contains sensitive info'] : $value;
        });

        return $headers;
    }

    /**
     * Hiding sensitive data in input data.
     *
     * @param \Illuminate\Support\Collection $input
     *
     * @return \Illuminate\Support\Collection
     */
    private function hideSensitiveInput(Collection $input): Collection
    {
        $sensitiveInput = Arr::get($this->tracingConfig, 'middleware.sensitive_input');

        $normalizedInput = array_map('strtolower', $sensitiveInput);

        $input->transform(function ($value, $name) use ($normalizedInput) {
            return in_array($name, $normalizedInput, true) ? ['This value is hidden because it contains sensitive info'] : $value;
        });

        return $input;
    }
}