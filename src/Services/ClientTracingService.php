<?php

namespace Romanpravda\Laravel\Tracing\Services;

use Romanpravda\Laravel\Tracing\Interfaces\ClientTracingServiceInterface;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;
use Romanpravda\Laravel\Tracing\Span\SpanKind;

class ClientTracingService implements ClientTracingServiceInterface
{
    /**
     * Base tracing service.
     *
     * @var \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface
     */
    private $tracingService;

    /**
     * ClientTracingService constructor.
     *
     * @param \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface|null $tracingService
     */
    public function __construct(?TracingServiceInterface $tracingService = null)
    {
        $this->tracingService = $tracingService ?? new NoopTracingService();
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
        $this->tracingService->startSpan($name, SpanKind::KIND_CLIENT);
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
        $context = $this->tracingService->hasCurrentSpan() ? $this->tracingService->getCurrentSpan()->getCurrent()->getContext() : null;
        $this->tracingService->inject($context, \OpenTracing\Formats\TEXT_MAP, $headers);

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
        $this->tracingService->getCurrentSpan()->getCurrent()->setTag('service.minor', $name);
    }

    /**
     * Adding request headers to current span.
     *
     * @param array $headers
     *
     * @return void
     */
    public function addRequestHeadersToCurrentSpan(array $headers): void
    {
        if ($this->tracingService->hasCurrentSpan()) {
            $this->tracingService->getCurrentSpan()->getCurrent()->setTag('request.http.headers', json_encode($headers));
        }
    }

    /**
     * Adding request query to current span.
     *
     * @param array $query
     *
     * @return void
     */
    public function addRequestQueryToCurrentSpan(array $query): void
    {
        if ($this->tracingService->hasCurrentSpan()) {
            $this->tracingService->getCurrentSpan()->getCurrent()->setTag('request.http.query', json_encode($query));
        }
    }

    /**
     * Adding request input to current span.
     *
     * @param array $input
     *
     * @return void
     */
    public function addRequestInputToCurrentSpan(array $input): void
    {
        if ($this->tracingService->hasCurrentSpan()) {
            $this->tracingService->getCurrentSpan()->getCurrent()->setTag('request.http.input', json_encode($input));
        }
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
        if ($this->tracingService->hasCurrentSpan()) {
            $this->tracingService->getCurrentSpan()->getCurrent()->setTag('response.http.status_code', $statusCode);
        }
    }

    /**
     * Adding response headers to current span.
     *
     * @param array $headers
     *
     * @return void
     */
    public function addResponseHeadersToCurrentSpan(array $headers): void
    {
        if ($this->tracingService->hasCurrentSpan()) {
            $this->tracingService->getCurrentSpan()->getCurrent()->setTag('response.http.headers', json_encode($headers));
        }
    }

    /**
     * Adding raw response to current span.
     *
     * @param array $rawResponse
     *
     * @return void
     */
    public function addRawResponseToCurrentSpan(array $rawResponse): void
    {
        if ($this->tracingService->hasCurrentSpan()) {
            $this->tracingService->getCurrentSpan()->getCurrent()->setTag('response.http.raw', json_encode($rawResponse));
        }
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