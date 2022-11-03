<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Middlewares;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\StatusData;
use Romanpravda\Laravel\Tracing\Interfaces\SpanInterface;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;

final class TraceRequests
{
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
        $context = $this->tracingService->extract($request->headers->all());
        $span = $this->tracingService->startSpan($request->url(), $context, SpanKind::KIND_SERVER);
        $span->getCurrent()->setAttribute('service.minor', 'http');
        $this->parseRequestForSpan($span, $request);
        $spanScope = $span->getCurrent()->activate();

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $this->parseResponseForSpan($span, $request, $response);
            $this->setSpanStatus($span, $response->getStatusCode());
        }

        $spanScope?->detach();
        $this->tracingService->stop();

        return $response;
    }

    /**
     * Parsing request data for span tags.
     *
     * @param \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface $span
     * @param \Illuminate\Http\Request $request
     *
     * @return void
     *
     * @throws \JsonException
     */
    private function parseRequestForSpan(SpanInterface $span, Request $request): void
    {
        $span->getCurrent()->setAttribute('request.http.method', $request->method());
        $span->getCurrent()->setAttribute('request.http.host', $request->root());
        $span->getCurrent()->setAttribute('request.http.target', '/'.$request->path());
        $span->getCurrent()->setAttribute('request.http.uri', $request->getRequestUri());
        $span->getCurrent()->setAttribute('request.http.scheme', $request->secure() ? 'https' : 'http');
        $span->getCurrent()->setAttribute('request.http.flavor', $_SERVER['SERVER_PROTOCOL'] ?? 'not passed');
        $span->getCurrent()->setAttribute('request.http.server_name', $request->server('SERVER_ADDR'));
        $span->getCurrent()->setAttribute('request.http.user_agent', $request->userAgent() ?? 'not passed');
        $span->getCurrent()->setAttribute('request.http.headers', $this->tracingService->transformedHeaders($this->tracingService->filterHeaders($request->headers->all())));
        $span->getCurrent()->setAttribute('request.net.host.port', $request->server('SERVER_PORT') ?? 'not passed');
        $span->getCurrent()->setAttribute('request.net.peer.ip', $request->ip() ?? 'not passed');
        $span->getCurrent()->setAttribute('request.net.peer.port', isset($_SERVER['REMOTE_PORT']) ? ((string) $_SERVER['REMOTE_PORT']) : 'not passed');

        if ($this->config->get('tracing.send-input', false) && in_array($request->headers->get('Content_Type'), $this->config->get('tracing.middleware.payload.content_types'), true)) {
            $span->getCurrent()->setAttribute('request.http.input', json_encode($this->tracingService->filterInput($request->input()), JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Parsing response data for span tags.
     *
     * @param \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface $span
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Http\JsonResponse $response
     *
     * @return void
     */
    private function parseResponseForSpan(SpanInterface $span, Request $request, JsonResponse $response): void
    {
        if (method_exists($request->route(), 'getActionName')) {
            $span->getCurrent()->setAttribute('request.laravel.action', $request->route()?->getActionName());
        }
        $span->getCurrent()->updateName(sprintf('%s %s', $request->method(), $request->route()?->uri()));

        $span->getCurrent()->setAttribute('response.http.status_code', $response->getStatusCode());
        $span->getCurrent()->setAttribute('response.http.headers', $this->tracingService->transformedHeaders($this->tracingService->filterHeaders($response->headers->all())));

        if ($this->config->get('tracing.send-response', false) && in_array($response->headers->get('Content_Type'), $this->config->get('tracing.middleware.payload.content_types'), true)) {
            $span->getCurrent()->setAttribute('response.content', $response->content());
        }
    }

    /**
     * Setting span's status.
     *
     * @param \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface $span
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

        $span->getCurrent()->setStatus($status->getCode(), $status->getDescription());
    }
}