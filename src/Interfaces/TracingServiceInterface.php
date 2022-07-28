<?php

namespace Romanpravda\Laravel\Tracing\Interfaces;

use OpenTracing\SpanContext as SpanContextInterface;

interface TracingServiceInterface
{
    /**
     * Starting span for trace.
     *
     * @param string $spanName
     * @param int $spanKind
     * @param \OpenTracing\SpanContext|null $parent
     * @param float|null $timestamp
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface
     */
    public function startSpan(string $spanName, int $spanKind, ?SpanContextInterface $parent = null, ?float $timestamp = null): SpanInterface;

    /**
     * Check for current span existence.
     *
     * @return bool
     */
    public function hasCurrentSpan(): bool;

    /**
     * Retrieving current span.
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    public function getCurrentSpan(): ?SpanInterface;

    /**
     * Ending current span.
     *
     * @param float|null $timestamp
     *
     * @return void
     */
    public function endCurrentSpan(?float $timestamp = null): void;

    /**
     * Writing context to given data.
     *
     * @param \OpenTracing\SpanContext $spanContext
     * @param string $format
     * @param $carrier
     *
     * @return void
     */
    public function inject(SpanContextInterface $spanContext, string $format, &$carrier): void;

    /**
     * Retrieving context from given data.
     *
     * @param string $format
     *
     * @param $carrier
     *
     * @return \OpenTracing\SpanContext|null
     */
    public function extract(string $format, $carrier): ?SpanContextInterface;

    /**
     * Stopping current trace.
     *
     * @return void
     */
    public function stop(): void;

    /**
     * Filtering and hiding headers.
     *
     * @param array $headers
     *
     * @return array
     */
    public function filterHeaders(array $headers = []): array;

    /**
     * Filtering and hiding input data.
     *
     * @param array $input
     *
     * @return array
     */
    public function filterInput(array $input = []): array;

    /**
     * Transforming headers.
     *
     * @param array $headers
     *
     * @return string
     */
    public function transformedHeaders(array $headers = []): string;
}