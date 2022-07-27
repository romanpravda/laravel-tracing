<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Interfaces;

use OpenTelemetry\API\Trace\SpanInterface;
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
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    public function startSpan(string $spanName, Context $context, int $spanKind, ?float $timestamp = null): SpanInterface;

    /**
     * Check for root span's existence.
     *
     * @return bool
     */
    public function hasRootSpan(): bool;

    /**
     * Retrieving root span.
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface|null
     */
    public function getRootSpan(): ?SpanInterface;

    /**
     * Writing context to given data.
     *
     * @param \OpenTelemetry\Context\Context $context
     * @param $carrier
     *
     * @return void
     */
    public function inject(Context $context, &$carrier): void;

    /**
     * Retrieving context from given data.
     *
     * @param $carrier
     *
     * @return \OpenTelemetry\Context\Context
     */
    public function extract($carrier): Context;

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