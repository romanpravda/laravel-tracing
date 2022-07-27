<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Romanpravda\Laravel\Tracing\Exporters\JaegerAgentExporter;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;
use Romanpravda\Laravel\Tracing\Services\TracingService;

final class TracingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../dist/config/tracing.php',
            'tracing'
        );

        $this->app->singleton(TracingServiceInterface::class, static function (Application $app) {
            /** @var \Illuminate\Contracts\Config\Repository $configRepository */
            $configRepository = $app->make(Repository::class);
            $tracingConfig = $configRepository->get('tracing');

            $agentHost = Arr::get($tracingConfig, 'host');
            $agentPort = Arr::get($tracingConfig, 'port');
            $agentHostPort = sprintf('%s:%d', $agentHost, $agentPort);

            $serviceName = Arr::get($tracingConfig, 'service-name', 'jaeger');

            $exporter = new JaegerAgentExporter($serviceName, $agentHostPort);
            $tracerProvider = (new TracerProvider(new SimpleSpanProcessor($exporter), new AlwaysOnSampler()));

            $propagator = TraceContextPropagator::getInstance();

            return new TracingService($tracerProvider, $propagator, $tracingConfig);
        });

        $this->registerQueryListener();
    }

    private function registerQueryListener(): void
    {
        DB::listen(function (QueryExecuted $query) {
            /** @var \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface $tracer */
            $tracer = $this->app->make(TracingServiceInterface::class);

            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $this->app->make(Repository::class);

            $endTime = ClockFactory::getDefault()->now();
            $duration = (int) round($query->time * 1000000);

            $rootSpanContext = $tracer->getRootSpan()?->storeInContext(Context::getCurrent());
            $span = $tracer->startSpan($query->sql, $rootSpanContext, SpanKind::KIND_INTERNAL, $endTime - $duration);
            $span->setAttribute('query.connection', $query->connectionName);
            $span->setAttribute('query.query', $query->sql);
            $span->setAttribute('service.minor', $query->connectionName);

            if ($query->bindings !== [] && $config->get('app.debug') === true) {
                $bindings = implode(',', $query->bindings);
                $span->setAttribute('query.bindings', $bindings);
            }

            $spanScope = $span->activate();

            $span->end($endTime);
            $spanScope->detach();
        });
    }
}