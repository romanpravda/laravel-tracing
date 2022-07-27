<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Middlewares;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\StatusData;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;

final class TraceRequests
{
    /**
     * Active span.
     *
     * @var \OpenTelemetry\API\Trace\SpanInterface|null
     */
    private ?SpanInterface $span = null;

    /**
     * Active span's scope.
     *
     * @var \OpenTelemetry\Context\ScopeInterface|null
     */
    private ?ScopeInterface $spanScope = null;

    /**
     * TraceRequests constructor.
     *
     * @param \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface $tracingService
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    public function __construct(
        private readonly TracingServiceInterface $tracingService,
        private readonly Repository $config,
    )
    {
    }

    /**
     * Handling request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     *
     * @throws \JsonException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $context = TraceContextPropagator::getInstance()->extract($request->headers->all());
        $span = $this->tracingService->startSpan($request->url(), $context, SpanKind::KIND_SERVER);
        $span->setAttribute('service', 'reports-service');
        $this->parseRequestForSpan($span, $request);
        $this->spanScope = $span->activate();
        $this->span = $span;

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if ($response instanceof JsonResponse && !is_null($this->span)) {
            $this->parseResponseForSpan($this->span, $request, $response);
            $this->setSpanStatus($this->span, $response->getStatusCode());
        }

        $this->tracingService->stop();
        $this->spanScope?->detach();
        $this->span->end();

        $this->spanScope = null;
        $this->span = null;
    }

    /**
     * Parsing request data for span tags.
     *
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     * @param \Illuminate\Http\Request $request
     *
     * @return void
     *
     * @throws \JsonException
     */
    private function parseRequestForSpan(SpanInterface $span, Request $request): void
    {
        $span->setAttribute('request.http.method', $request->method());
        $span->setAttribute('request.http.host', $request->root());
        $span->setAttribute('request.http.target', '/'.$request->path());
        $span->setAttribute('request.http.uri', $request->getRequestUri());
        $span->setAttribute('request.http.scheme', $request->secure() ? 'https' : 'http');
        $span->setAttribute('request.http.flavor', $_SERVER['SERVER_PROTOCOL'] ?? 'not passed');
        $span->setAttribute('request.http.server_name', $request->server('SERVER_ADDR'));
        $span->setAttribute('request.http.user_agent', $request->userAgent() ?? 'not passed');
        $span->setAttribute('request.http.headers', $this->tracingService->transformedHeaders($this->tracingService->filterHeaders($request->headers->all())));
        $span->setAttribute('request.net.host.port', $request->server('SERVER_PORT') ?? 'not passed');
        $span->setAttribute('request.net.peer.ip', $request->ip() ?? 'not passed');
        $span->setAttribute('request.net.peer.port', isset($_SERVER['REMOTE_PORT']) ? ((string) $_SERVER['REMOTE_PORT']) : 'not passed');

        if (in_array($request->headers->get('Content_Type'), $this->config->get('tracing.middleware.payload.content_types'), true)) {
            $span->setAttribute('request.http.input', json_encode($this->tracingService->filterInput($request->input()), JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Parsing response data for span tags.
     *
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Http\JsonResponse $response
     *
     * @return void
     */
    private function parseResponseForSpan(SpanInterface $span, Request $request, JsonResponse $response): void
    {
        if (method_exists($request->route(), 'getActionName')) {
            $span->setAttribute('request.laravel.action', $request->route()?->getActionName());
        }
        $span->updateName(sprintf('%s %s', $request->method(), $request->route()?->uri()));

        $span->setAttribute('response.http.status_code', $response->getStatusCode());
        $span->setAttribute('response.http.headers', $this->tracingService->transformedHeaders($this->tracingService->filterHeaders($response->headers->all())));

        if (in_array($response->headers->get('Content_Type'), $this->config->get('tracing.middleware.payload.content_types'), true)) {
            $span->setAttribute('response.content', $response->content());
        }
    }

    /**
     * Setting span's status.
     *
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     * @param int $httpStatusCode
     *
     * @return void
     */
    private function setSpanStatus(SpanInterface $span, int $httpStatusCode): void
    {
        if ($httpStatusCode >= 400 && $httpStatusCode < 600) {
            $status = StatusData::error();
        } elseif ($httpStatusCode >= 200 && $httpStatusCode < 300) {
            $status = StatusData::ok();
        } else {
            $status = StatusData::unset();
        }

        $span->setStatus($status->getCode(), $status->getDescription());
    }
}