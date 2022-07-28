<?php

namespace Romanpravda\Laravel\Tracing\Middlewares;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Romanpravda\Laravel\Tracing\Interfaces\SpanInterface;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;
use Romanpravda\Laravel\Tracing\Span\SpanKind;
use Romanpravda\Laravel\Tracing\Span\SpanStatus;

class TraceRequests
{
    /**
     * Tracing service.
     *
     * @var \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface
     */
    private $tracingService;

    /**
     * Config's repository.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    private $config;

    /**
     * TraceRequests constructor.
     *
     * @param \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface $tracingService
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    public function __construct(TracingServiceInterface $tracingService, Repository $config)
    {
        $this->tracingService = $tracingService;
        $this->config = $config;
    }

    /**
     * Handling request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $parentContext = $this->tracingService->extract(\OpenTracing\Formats\TEXT_MAP, $request->headers->all());
        $span = $this->tracingService->startSpan($request->url(), SpanKind::KIND_SERVER, $parentContext);
        $span->getCurrent()->setTag('service.minor', 'http');
        $this->parseRequestForSpan($span, $request);

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $this->parseResponseForSpan($span, $request, $response);
            $this->setSpanStatus($span, $response->getStatusCode());
        }

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
     */
    private function parseRequestForSpan(SpanInterface $span, Request $request): void
    {
        $span->getCurrent()->setTag('request.http.method', $request->method());
        $span->getCurrent()->setTag('request.http.host', $request->root());
        $span->getCurrent()->setTag('request.http.target', '/'.$request->path());
        $span->getCurrent()->setTag('request.http.uri', $request->getRequestUri());
        $span->getCurrent()->setTag('request.http.scheme', $request->secure() ? 'https' : 'http');
        $span->getCurrent()->setTag('request.http.flavor', $_SERVER['SERVER_PROTOCOL'] ?? 'not passed');
        $span->getCurrent()->setTag('request.http.server_name', $request->server('SERVER_ADDR'));
        $span->getCurrent()->setTag('request.http.user_agent', $request->userAgent() ?? 'not passed');
        $span->getCurrent()->setTag('request.http.headers', $this->tracingService->transformedHeaders($this->tracingService->filterHeaders($request->headers->all())));
        $span->getCurrent()->setTag('request.net.host.port', $request->server('SERVER_PORT') ?? 'not passed');
        $span->getCurrent()->setTag('request.net.peer.ip', $request->ip() ?? 'not passed');
        $span->getCurrent()->setTag('request.net.peer.port', isset($_SERVER['REMOTE_PORT']) ? ((string) $_SERVER['REMOTE_PORT']) : 'not passed');

        if (in_array($request->headers->get('Content_Type'), $this->config->get('tracing.middleware.payload.content_types'), true)) {
            $span->getCurrent()->setTag('request.http.input', json_encode($this->tracingService->filterInput($request->input())));
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
            $span->getCurrent()->setTag('request.laravel.action', $request->route()->getActionName());
        }
        $span->getCurrent()->overwriteOperationName(sprintf('%s %s', $request->method(), $request->route()->uri()));

        $span->getCurrent()->setTag('response.http.status_code', $response->getStatusCode());
        $span->getCurrent()->setTag('response.http.headers', $this->tracingService->transformedHeaders($this->tracingService->filterHeaders($response->headers->all())));

        if (in_array($response->headers->get('Content_Type'), $this->config->get('tracing.middleware.payload.content_types'), true)) {
            $span->getCurrent()->setTag('response.content', $response->content());
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
            $span->setStatus(SpanStatus::STATUS_ERROR);
        } elseif ($httpStatusCode >= 200 && $httpStatusCode < 300) {
            $span->setStatus(SpanStatus::STATUS_OK);
        } else {
            $span->setStatus(SpanStatus::STATUS_UNSET);
        }
    }
}