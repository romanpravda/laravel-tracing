<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Interfaces;

use OpenTelemetry\Context\Context;

interface TracingServiceInterface
{
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
    public function startSpan(string $spanName, Context $context, int $spanKind, ?float $timestamp = null): SpanInterface;

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
     * @param int|null $timestamp
     *
     * @return void
     */
    public function endCurrentSpan(?int $timestamp = null): void;

    /**
     * Writing context to given data.
     *
     * @param \OpenTelemetry\Context\Context $context
     * @param array<string, array<int, string|null>|string|null> $carrier
     *
     * @return void
     */
    public function inject(Context $context, array &$carrier): void;

    /**
     * Retrieving context from given data.
     *
     * @param array<string, array<int, string|null>|string|null> $carrier
     *
     * @return \OpenTelemetry\Context\Context
     */
    public function extract(array $carrier): Context;

    /**
     * Stopping current trace.
     *
     * @return void
     */
    public function stop(): void;

    /**
     * Filtering and hiding headers.
     *
     * @param array<string, array<int, string|null>|string|null> $headers
     *
     * @return array<string, array<int, string|null>|string|null>
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
     * @param array<string, array<int, string|null>|string|null> $headers
     *
     * @return string
     */
    public function transformedHeaders(array $headers = []): string;
}