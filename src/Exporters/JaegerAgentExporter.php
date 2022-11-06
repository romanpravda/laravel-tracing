<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Exporters;

use OpenTelemetry\Contrib\Jaeger\JaegerTransport;
use OpenTelemetry\Contrib\Jaeger\ParsedEndpointUrl;
use OpenTelemetry\Contrib\Jaeger\SpanConverter;
use OpenTelemetry\SDK\Trace\Behavior\SpanExporterTrait;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

final class JaegerAgentExporter implements SpanExporterInterface
{
    use SpanExporterTrait;

    /**
     * Default service name.
     *
     * @var string
     */
    private string $serviceName;

    /**
     * The span converter.
     *
     * @var \OpenTelemetry\Contrib\Jaeger\SpanConverter
     */
    private SpanConverter $spanConverter;

    /**
     * Transport to Jaeger.
     *
     * @var \OpenTelemetry\Contrib\Jaeger\JaegerTransport
     */
    private JaegerTransport $jaegerTransport;

    /**
     * JaegerAgentExporter constructor.
     *
     * @param string $name
     * @param string $endpointUrl
     */
    public function __construct(
        string $name,
        string $endpointUrl
    ) {
        $parsedEndpoint = (new ParsedEndpointUrl($endpointUrl))
            ->validateHost() //This is because the host is required downstream
            ->validatePort(); //This is because the port is required downstream

        $this->serviceName = $name;

        $this->spanConverter = new SpanConverter();
        $this->jaegerTransport = new JaegerTransport($parsedEndpoint);
    }

    /**
     * Creates exporter from connection string.
     *
     * @param string $endpointUrl
     * @param string $name
     * @param string $args
     *
     * @return static
     */
    public static function fromConnectionString(string $endpointUrl, string $name, string $args): self
    {
        return new self(
            $name,
            $endpointUrl
        );
    }

    /**
     * Appends spans to exporter.
     *
     * @param iterable<SpanDataInterface> $spans Batch of spans to export
     *
     * @return int
     *
     * @psalm-return SpanExporterInterface::STATUS_*
     */
    protected function doExport(iterable $spans): int
    {
        // UDP Transport begins here after converting to thrift format span
        foreach ($spans as $span) {
            if ($span->getAttributes()->has('service.major') && $span->getAttributes()->has('service.minor')) {
                $serviceName = sprintf('%s-%s', $span->getAttributes()->get('service.major'), $span->getAttributes()->get('service.minor'));
            } else {
                $serviceName = $this->serviceName;
            }
            $this->jaegerTransport->append(
                $this->spanConverter->convert([$span])[0],
                $serviceName,
            );
        }

        return SpanExporterInterface::STATUS_SUCCESS;
    }

    /**
     * Shuts down the exporter.
     *
     * @return bool
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/v1.7.0/specification/trace/sdk.md#shutdown-2
     */
    public function shutdown(): bool
    {
        $this->running = false;
        $this->forceFlush();

        return true;
    }

    /**
     * This is a hint to ensure that the export of any Spans the exporter has received prior to the call to ForceFlush
     * SHOULD be completed as soon as possible, preferably before returning from this method.
     *
     * @return bool
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/v1.7.0/specification/trace/sdk.md#forceflush-2
     */
    public function forceFlush(): bool
    {
        return (bool) $this->jaegerTransport->flush(true);
    }
}
