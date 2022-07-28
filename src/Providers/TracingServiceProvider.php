<?php

namespace Romanpravda\Laravel\Tracing\Providers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Jaeger\Config;
use Romanpravda\Laravel\Tracing\Interfaces\ClientTracingServiceInterface;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;
use Romanpravda\Laravel\Tracing\Propogators\TraceContextPropagator;
use Romanpravda\Laravel\Tracing\Services\ClientTracingService;
use Romanpravda\Laravel\Tracing\Services\NoopTracingService;
use Romanpravda\Laravel\Tracing\Services\TracingService;
use Romanpravda\Laravel\Tracing\Span\SpanKind;
use Romanpravda\Laravel\Tracing\Transports\JaegerTransport;

class TracingServiceProvider extends ServiceProvider
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
            $configRepository = $app->make(ConfigRepository::class);
            $tracingConfig = $configRepository->get('tracing');

            if (!Arr::get($tracingConfig, 'enabled', false)) {
                return new NoopTracingService();
            }

            $agentHost = Arr::get($tracingConfig, 'host');
            $agentPort = Arr::get($tracingConfig, 'port');
            $agentHostPort = sprintf('%s:%d', $agentHost, $agentPort);

            $serviceName = Arr::get($tracingConfig, 'service-name', 'jaeger');

            $config = Config::getInstance()->gen128bit();
            $config->setTransport(new JaegerTransport($agentHostPort));
            /** @var \Jaeger\Jaeger $tracer */
            $tracer = $config->initTracer($serviceName);
            $tracer->setPropagator(new TraceContextPropagator());

            return new TracingService($tracer, $tracingConfig);
        });

        $this->app->bind(ClientTracingServiceInterface::class, static function (Application $app) {
            /** @var \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface $tracingService */
            $tracingService = $app->make(TracingServiceInterface::class);

            return new ClientTracingService($tracingService);
        });

        $this->registerQueryListener();
    }

    public function registerQueryListener(): void
    {
        DB::listen(function (QueryExecuted $query) {
            /** @var \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface $tracer */
            $tracer = $this->app->make(TracingServiceInterface::class);

            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $this->app->make(ConfigRepository::class);

            $endTime = microtime(true);
            $duration = $query->time / 1000;

            $span = $tracer->startSpan($query->sql, SpanKind::KIND_CLIENT, null, $endTime - $duration);
            $span->getCurrent()->setTag('service.minor', $query->connectionName);
            $span->getCurrent()->setTag('query.connection', $query->connectionName);
            $span->getCurrent()->setTag('query.query', $query->sql);

            if ($query->bindings !== [] && $config->get('app.debug') === true) {
                $bindings = implode(',', $query->bindings);
                $span->getCurrent()->setTag('query.bindings', $bindings);
            }

            $tracer->endCurrentSpan($endTime);
        });
    }
}