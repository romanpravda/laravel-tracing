<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Interfaces;

interface ClientTracingServiceInterface
{
    /**
     * Creating new span for client.
     *
     * @param string $name
     *
     * @return void
     */
    public function startSpan(string $name): void;

    /**
     * Inject current span context into headers.
     *
     * @param array<string, array<int, string|null>|string|null> $headers
     *
     * @return array<string, array<int, string|null>|string|null>
     */
    public function injectSpanContextIntoHeaders(array $headers = []): array;

    /**
     * Adding minor service name to current span.
     *
     * @param string $name
     *
     * @return void
     */
    public function addMinorServiceNameToCurrentSpan(string $name): void;

    /**
     * Adding request headers to current span.
     *
     * @param array<string, array<int, string|null>|string|null> $headers
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addRequestHeadersToCurrentSpan(array $headers): void;

    /**
     * Adding request query to current span.
     *
     * @param array<string, scalar|array> $query
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addRequestQueryToCurrentSpan(array $query): void;

    /**
     * Adding request input to current span.
     *
     * @param array<string, scalar|array> $input
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addRequestInputToCurrentSpan(array $input): void;

    /**
     * Adding response status code to current span.
     *
     * @param int $statusCode
     *
     * @return void
     */
    public function addResponseStatusCodeToCurrentSpan(int $statusCode): void;

    /**
     * Adding response headers to current span.
     *
     * @param array<string, array<int, string|null>|string|null> $headers
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addResponseHeadersToCurrentSpan(array $headers): void;

    /**
     * Adding raw response to current span.
     *
     * @param array<string, scalar|array> $rawResponse
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addRawResponseToCurrentSpan(array $rawResponse): void;

    /**
     * Stopping span for client.
     *
     * @return void
     */
    public function stopSpan(): void;
}