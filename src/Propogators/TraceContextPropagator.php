<?php

namespace Romanpravda\Laravel\Tracing\Propogators;

use Jaeger\Propagator\Propagator as PropagatorInterface;
use Jaeger\SpanContext;
use OpenTracing\SpanContext as SpanContextInterface;

class TraceContextPropagator implements PropagatorInterface
{
    private const VERSION = '00'; // Currently, only '00' is supported

    // TRACEPARENT states for header name for parent trace
    private const TRACEPARENT = 'traceparent';

    /**
     * Writing context to given data.
     *
     * @param \OpenTracing\SpanContext $spanContext
     * @param string $format
     * @param mixed $carrier
     */
    public function inject(SpanContextInterface $spanContext, $format, &$carrier): void
    {
        if ($spanContext instanceof SpanContext) {
            $carrier[self::TRACEPARENT] = $this->buildTraceHeader($spanContext);
        }
    }

    /**
     * Retrieving context from given data.
     *
     * @param string $format
     * @param mixed $carrier
     *
     * @return SpanContext|null
     */
    public function extract($format, $carrier): ?SpanContext
    {
        $spanContext = null;

        $carrier = array_change_key_case($carrier, CASE_LOWER);

        foreach ($carrier as $k => $v) {
            if ($k !== self::TRACEPARENT) {
                continue;
            }

            if (null === $spanContext) {
                $spanContext = new SpanContext(0, 0, 0, null, 0);
            }

            if (is_array($v)) {
                $v = urldecode(current($v));
            } else {
                $v = urldecode($v);
            }

            [$version, $traceId, $spanId, $flags] = explode('-', $v);

            $spanContext->spanId = $spanContext->hexToSignedInt($spanId);
            $spanContext->flags = (int) $flags;
            $spanContext->traceIdToString($traceId);
        }

        return $spanContext;
    }

    /**
     * Building parent trace header's value.
     *
     * @param \Jaeger\SpanContext $spanContext
     *
     * @return string
     */
    private function buildTraceHeader(SpanContext $spanContext): string
    {
        if ($spanContext->traceIdHigh) {
            return sprintf('%s-%x%016x-%x-0%x', self::VERSION, $spanContext->traceIdHigh, $spanContext->traceIdLow,
                $spanContext->spanId, $spanContext->flags);
        }

        return sprintf('%s-%x-%x-0%x', self::VERSION, $spanContext->traceIdLow, $spanContext->spanId, $spanContext->flags);
    }
}