<?php

namespace Romanpravda\Laravel\Tracing\Providers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Jaeger\Config;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\ProbabilisticSampler;
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

            if (Arr::get($tracingConfig, 'sampling.type', 'const') === 'probabilistic') {
                $rate = Arr::get($tracingConfig, 'sampling.rate', 0.5);
                $config->setSampler(new ProbabilisticSampler($rate));
            } else {
                $config->setSampler(new ConstSampler());
            }

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

    /**
     * Register DB Query listener.
     *
     * @return void
     */
    public function registerQueryListener(): void
    {
        DB::listen(function (QueryExecuted $query) {
            /** @var \Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface $tracer */
            $tracer = $this->app->make(TracingServiceInterface::class);

            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $this->app->make(ConfigRepository::class);

            $endTime = (int) (microtime(true) * 1000000);
            $duration = (int) ($query->time * 1000);

            $span = $tracer->startSpan($query->sql, SpanKind::KIND_CLIENT, null, $endTime - $duration);
            $span->getCurrent()->setTag('service.minor', $query->connectionName);
            $span->getCurrent()->setTag('query.connection', $query->connectionName);
            $span->getCurrent()->setTag('query.query', $query->sql);

            if ($query->bindings !== [] && $config->get('app.debug') === true) {
                $bindings = implode(',', array_map(static function ($binding) {
                    if ($binding instanceof \DateTimeInterface) {
                        return $binding->format('Y-m-d H:i:s');
                    }

                    if (is_bool($binding)) {
                        return (int) $binding;
                    }

                    return $binding;
                }, $query->bindings));
                $span->getCurrent()->setTag('query.bindings', $bindings);
            }

            $tracer->endCurrentSpan($endTime);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../dist/config/tracing.php' => config_path('tracing.php'),
        ]);
    }
}