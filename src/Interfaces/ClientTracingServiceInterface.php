<?php

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
     * @param array $headers
     *
     * @return array
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
     * @param array $headers
     *
     * @return void
     */
    public function addRequestHeadersToCurrentSpan(array $headers): void;

    /**
     * Adding request query to current span.
     *
     * @param array $query
     *
     * @return void
     */
    public function addRequestQueryToCurrentSpan(array $query): void;

    /**
     * Adding request input to current span.
     *
     * @param array $input
     *
     * @return void
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
     * @param array $headers
     *
     * @return void
     */
    public function addResponseHeadersToCurrentSpan(array $headers): void;

    /**
     * Adding raw response to current span.
     *
     * @param array $rawResponse
     *
     * @return void
     */
    public function addRawResponseToCurrentSpan(array $rawResponse): void;

    /**
     * Stopping span for client.
     *
     * @return void
     */
    public function stopSpan(): void;
}