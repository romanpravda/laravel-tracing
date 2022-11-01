<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Services;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use Romanpravda\Laravel\Tracing\Interfaces\ClientTracingServiceInterface;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;

final class ClientTracingService implements ClientTracingServiceInterface
{
    /**
     * ClientTracingService constructor.
     *
     * @param \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface $tracingService
     */
    public function __construct(
        private readonly TracingServiceInterface $tracingService = new NoopTracingService(),
    )
    {
    }

    /**
     * Creating new span for client.
     *
     * @param string $name
     *
     * @return void
     */
    public function startSpan(string $name): void
    {
        $this->tracingService->startSpan($name, Context::getCurrent(), SpanKind::KIND_CLIENT);
    }

    /**
     * Inject current span context into headers.
     *
     * @param array $headers
     *
     * @return array
     */
    public function injectSpanContextIntoHeaders(array $headers = []): array
    {
        $context = $this->tracingService->getCurrentSpan()?->getCurrent()->storeInContext(Context::getCurrent());
        if (!is_null($context)) {
            $this->tracingService->inject($context, $headers);
        }

        return $headers;
    }

    /**
     * Adding minor service name to current span.
     *
     * @param string $name
     *
     * @return void
     */
    public function addMinorServiceNameToCurrentSpan(string $name): void
    {
        $this->tracingService->getCurrentSpan()?->getCurrent()->setAttribute('service.minor', $name);
    }

    /**
     * Adding request headers to current span.
     *
     * @param array $headers
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addRequestHeadersToCurrentSpan(array $headers): void
    {
        $this->tracingService->getCurrentSpan()?->getCurrent()->setAttribute('request.http.headers', json_encode($headers, JSON_THROW_ON_ERROR));
    }

    /**
     * Adding request query to current span.
     *
     * @param array $query
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addRequestQueryToCurrentSpan(array $query): void
    {
        $this->tracingService->getCurrentSpan()?->getCurrent()->setAttribute('request.http.query', json_encode($query, JSON_THROW_ON_ERROR));
    }

    /**
     * Adding request input to current span.
     *
     * @param array $input
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addRequestInputToCurrentSpan(array $input): void
    {
        $this->tracingService->getCurrentSpan()?->getCurrent()->setAttribute('request.http.input', json_encode($input, JSON_THROW_ON_ERROR));
    }

    /**
     * Adding response status code to current span.
     *
     * @param int $statusCode
     *
     * @return void
     */
    public function addResponseStatusCodeToCurrentSpan(int $statusCode): void
    {
        $this->tracingService->getCurrentSpan()?->getCurrent()->setAttribute('response.http.status_code', $statusCode);
    }

    /**
     * Adding response headers to current span.
     *
     * @param array $headers
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addResponseHeadersToCurrentSpan(array $headers): void
    {
        $this->tracingService->getCurrentSpan()?->getCurrent()->setAttribute('response.http.headers', json_encode($headers, JSON_THROW_ON_ERROR));
    }

    /**
     * Adding raw response to current span.
     *
     * @param array $rawResponse
     *
     * @return void
     *
     * @throws \JsonException
     */
    public function addRawResponseToCurrentSpan(array $rawResponse): void
    {
        $this->tracingService->getCurrentSpan()?->getCurrent()->setAttribute('response.http.raw', json_encode($rawResponse, JSON_THROW_ON_ERROR));
    }

    /**
     * Stopping span for client.
     *
     * @return void
     */
    public function stopSpan(): void
    {
        $this->tracingService->endCurrentSpan();
    }
}