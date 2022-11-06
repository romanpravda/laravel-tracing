<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OpenTelemetry\API\Trace\SpanContextKey;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Romanpravda\Laravel\Tracing\Interfaces\SpanInterface;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;
use Romanpravda\Laravel\Tracing\Span;

final class TracingService implements TracingServiceInterface
{
    /**
     * Base tracer service.
     *
     * @var \OpenTelemetry\API\Trace\TracerInterface
     */
    private TracerInterface $tracer;

    /**
     * The current span.
     *
     * @var \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    private ?SpanInterface $currentSpan = null;

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
        /** @var string $serviceName */
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
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface
     */
    public function startSpan(string $spanName, Context $context, int $spanKind, ?float $timestamp = null): SpanInterface
    {
        $currentSpan = $this->getCurrentSpan();

        $spanBuilder = $this->tracer->spanBuilder($spanName)->setSpanKind($spanKind);
        if (!is_null($timestamp)) {
            $spanBuilder->setStartTimestamp((int) $timestamp);
        }
        if (!is_null($currentSpan)) {
            $parentContext = $currentSpan->getCurrent()->storeInContext($context);
            $spanBuilder->setParent($parentContext);
        } elseif (is_null($context->get(SpanContextKey::instance()))) {
            $spanBuilder->setNoParent();
        } else {
            $spanBuilder->setParent($context);
        }
        $baseSpan = $spanBuilder->startSpan();

        /** @var string $serviceName */
        $serviceName = Arr::get($this->tracingConfig, 'service-name', 'jaeger');
        $baseSpan->setAttribute('service.major', $serviceName);

        $span = new Span($baseSpan, $currentSpan);
        $this->currentSpan = $span;

        return $span;
    }

    /**
     * Check for current span existence.
     *
     * @return bool
     */
    public function hasCurrentSpan(): bool
    {
        return !is_null($this->currentSpan);
    }

    /**
     * Retrieving current span.
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    public function getCurrentSpan(): ?SpanInterface
    {
        return $this->currentSpan;
    }

    /**
     * Ending current span.
     *
     * @param int|null $timestamp
     *
     * @return void
     */
    public function endCurrentSpan(?int $timestamp = null): void
    {
        if (!$this->hasCurrentSpan()) {
            return;
        }

        $parentSpan = $this->getCurrentSpan()?->getParent();
        $this->getCurrentSpan()?->getCurrent()->end($timestamp);
        $this->currentSpan = $parentSpan;
    }

    /**
     * Writing context to given data.
     *
     * @param \OpenTelemetry\Context\Context $context
     * @param array<string, array<int, string|null>|string|null> $carrier
     *
     * @return void
     */
    public function inject(Context $context, array &$carrier): void
    {
        $this->propagator->inject($carrier, null, $context);
    }

    /**
     * Retrieving context from given data.
     *
     * @param array<string, array<int, string|null>|string|null> $carrier
     *
     * @return \OpenTelemetry\Context\Context
     */
    public function extract(array $carrier): Context
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
        while ($this->hasCurrentSpan()) {
            $this->endCurrentSpan();
        }
    }

    /**
     * Filtering and hiding headers.
     *
     * @param array<string, array<int, string|null>|string|null> $headers
     *
     * @return array<string, array<int, string|null>|string|null>
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
     * @param array<string, array<int, string|null>|string|null> $headers
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
        /** @var array<string> $allowedHeaders */
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
        /** @var array<string> $sensitiveHeaders */
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
        /** @var array<string> $sensitiveInput */
        $sensitiveInput = Arr::get($this->tracingConfig, 'middleware.sensitive_input');

        $normalizedInput = array_map('strtolower', $sensitiveInput);

        $input->transform(function ($value, $name) use ($normalizedInput) {
            return in_array($name, $normalizedInput, true) ? ['This value is hidden because it contains sensitive info'] : $value;
        });

        return $input;
    }
}