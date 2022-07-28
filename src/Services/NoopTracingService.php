<?php

namespace Romanpravda\Laravel\Tracing\Services;

use OpenTracing\NoopSpan;
use OpenTracing\SpanContext as SpanContextInterface;
use Romanpravda\Laravel\Tracing\Interfaces\SpanInterface;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;
use Romanpravda\Laravel\Tracing\Span\Span;

class NoopTracingService implements TracingServiceInterface
{
    /**
     * Starting span for trace.
     *
     * @param string $spanName
     * @param int $spanKind
     * @param \OpenTracing\SpanContext|null $parent
     * @param int|null $timestamp
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface
     */
    public function startSpan(string $spanName, int $spanKind, ?SpanContextInterface $parent = null, ?int $timestamp = null): SpanInterface
    {
        return new Span(new NoopSpan());
    }

    /**
     * Check for current span existence.
     *
     * @return bool
     */
    public function hasCurrentSpan(): bool
    {
        return false;
    }

    /**
     * Retrieving current span.
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    public function getCurrentSpan(): ?SpanInterface
    {
        return null;
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
    }

    /**
     * Writing context to given data.
     *
     * @param \OpenTracing\SpanContext $spanContext
     * @param string $format
     * @param $carrier
     *
     * @return void
     */
    public function inject(SpanContextInterface $spanContext, string $format, &$carrier): void
    {
    }

    /**
     * Retrieving context from given data.
     *
     * @param string $format
     *
     * @param $carrier
     *
     * @return \OpenTracing\SpanContext|null
     */
    public function extract(string $format, $carrier): ?SpanContextInterface
    {
        return null;
    }

    /**
     * Setting span's status.
     *
     * @param int $spanStatus
     *
     * @return void
     */
    public function setCurrentSpanStatus(int $spanStatus): void
    {
    }

    /**
     * Stopping current trace.
     *
     * @return void
     */
    public function stop(): void
    {
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
        return $headers;
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
        return $input;
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
        return '';
    }
}