<?php

namespace Romanpravda\Laravel\Tracing\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OpenTracing\SpanContext as SpanContextInterface;
use OpenTracing\Tracer as TracerInterface;
use Romanpravda\Laravel\Tracing\Interfaces\SpanInterface;
use Romanpravda\Laravel\Tracing\Interfaces\TracingServiceInterface;
use Romanpravda\Laravel\Tracing\Span\Span;

class TracingService implements TracingServiceInterface
{
    /**
     * Parent tracing service.
     *
     * @var \OpenTracing\Tracer
     */
    private $tracer;

    /**
     * The current span.
     *
     * @var \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    private $currentSpan;

    /**
     * Tracing config data.
     *
     * @var array
     */
    private $tracingConfig;

    /**
     * TracingService constructor.
     *
     * @param \OpenTracing\Tracer $tracer
     * @param array $tracingConfig
     */
    public function __construct(TracerInterface $tracer, array $tracingConfig = []) {
        $this->tracer = $tracer;
        $this->tracingConfig = $tracingConfig;
    }

    /**
     * Starting span for trace.
     *
     * @param string $spanName
     * @param int $spanKind
     * @param \OpenTracing\SpanContext|null $parent
     * @param int|null $timestamp
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface
     */
    public function startSpan(string $spanName, int $spanKind, ?SpanContextInterface $parent = null, ?int $timestamp = null): SpanInterface
    {
        if ($this->hasCurrentSpan()) {
            $parent = $this->getCurrentSpan()->getCurrent()->getContext();
        }

        $options = [];
        if (!is_null($parent)) {
            $options['child_of'] = $parent;
        }
        if (!is_null($timestamp)) {
            $options['start_time'] = $timestamp / 1000000;
        }

        $baseSpan = $this->tracer->startSpan($spanName, $options);
        $baseSpan->setTag('service.major', Arr::get($this->tracingConfig, 'service-name', 'jaeger'));

        $span = new Span($baseSpan, $this->getCurrentSpan());
        $span->setKind($spanKind);

        $this->currentSpan = $span;

        return $span;
    }

    /**
     * Check for current span existence.
     *
     * @return bool
     */
    public function hasCurrentSpan(): bool
    {
        return !is_null($this->currentSpan);
    }

    /**
     * Retrieving current span.
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    public function getCurrentSpan(): ?SpanInterface
    {
        return $this->currentSpan;
    }

    /**
     * Ending current span.
     *
     * @param int|null $timestamp
     *
     * @return void
     */
    public function endCurrentSpan(?int $timestamp = null): void
    {
        if (!$this->hasCurrentSpan()) {
            return;
        }

        $parentSpan = $this->getCurrentSpan()->getParent();
        $this->getCurrentSpan()->getCurrent()->finish($timestamp);
        $this->currentSpan = $parentSpan;
    }

    /**
     * Writing context to given data.
     *
     * @param \OpenTracing\SpanContext $spanContext
     * @param string $format
     * @param $carrier
     *
     * @return void
     */
    public function inject(SpanContextInterface $spanContext, string $format, &$carrier): void
    {
        $this->tracer->inject($spanContext, $format, $carrier);
    }

    /**
     * Retrieving context from given data.
     *
     * @param string $format
     *
     * @param $carrier
     *
     * @return \OpenTracing\SpanContext|null
     */
    public function extract(string $format, $carrier): ?SpanContextInterface
    {
        return $this->tracer->extract($format, $carrier);
    }

    /**
     * Stopping current trace.
     *
     * @return void
     */
    public function stop(): void
    {
        while ($this->hasCurrentSpan()) {
            $this->endCurrentSpan();
        }
        $this->tracer->flush();
    }

    /**
     * Filtering and hiding headers.
     *
     * @param array $headers
     *
     * @return array
     */
    public function filterHeaders(array $headers = []): array
    {
        return $this->hideSensitiveHeaders($this->filterAllowedHeaders(collect($headers)))->all();
    }

    /**
     * Filtering and hiding input data.
     *
     * @param array $input
     *
     * @return array
     */
    public function filterInput(array $input = []): array
    {
        return $this->hideSensitiveInput(collect($input))->all();
    }

    /**
     * Transforming headers.
     *
     * @param array $headers
     *
     * @return string
     */
    public function transformedHeaders(array $headers = []): string
    {
        if (!$headers) {
            return '';
        }

        ksort($headers);
        $max = max(array_map('strlen', array_keys($headers))) + 1;

        $content = '';
        foreach ($headers as $name => $values) {
            $name = implode('-', array_map('ucfirst', explode('-', $name)));

            if (is_array($values)) {
                foreach ($values as $value) {
                    $content .= sprintf("%-{$max}s %s\r\n", $name.':', $value);
                }
            } else {
                $content .= sprintf("%-{$max}s %s\r\n", $name.':', $values);
            }
        }

        return $content;
    }

    /**
     * Filtering away not allowed headers.
     *
     * @param \Illuminate\Support\Collection $headers
     *
     * @return \Illuminate\Support\Collection
     */
    private function filterAllowedHeaders(Collection $headers): Collection
    {
        $allowedHeaders = Arr::get($this->tracingConfig, 'middleware.allowed_headers');

        if (in_array('*', $allowedHeaders, true)) {
            return $headers;
        }

        $normalizedHeaders = array_map('strtolower', $allowedHeaders);

        return $headers->filter(function ($value, $name) use ($normalizedHeaders) {
            return in_array($name, $normalizedHeaders, true);
        });
    }

    /**
     * Hiding sensitive data in headers' values.
     *
     * @param \Illuminate\Support\Collection $headers
     *
     * @return \Illuminate\Support\Collection
     */
    private function hideSensitiveHeaders(Collection $headers): Collection
    {
        $sensitiveHeaders = Arr::get($this->tracingConfig, 'middleware.sensitive_headers');

        $normalizedHeaders = array_map('strtolower', $sensitiveHeaders);

        $headers->transform(function ($value, $name) use ($normalizedHeaders) {
            return in_array($name, $normalizedHeaders, true) ? ['This value is hidden because it contains sensitive info'] : $value;
        });

        return $headers;
    }

    /**
     * Hiding sensitive data in input data.
     *
     * @param \Illuminate\Support\Collection $input
     *
     * @return \Illuminate\Support\Collection
     */
    private function hideSensitiveInput(Collection $input): Collection
    {
        $sensitiveInput = Arr::get($this->tracingConfig, 'middleware.sensitive_input');

        $normalizedInput = array_map('strtolower', $sensitiveInput);

        $input->transform(function ($value, $name) use ($normalizedInput) {
            return in_array($name, $normalizedInput, true) ? ['This value is hidden because it contains sensitive info'] : $value;
        });

        return $input;
    }
}