<?php

namespace Romanpravda\Laravel\Tracing\Span;

use OpenTracing\Span as BaseSpanInterface;
use Romanpravda\Laravel\Tracing\Interfaces\SpanInterface;

class Span implements SpanInterface
{
    /**
     * Base span.
     *
     * @var \OpenTracing\Span
     */
    private $current;

    /**
     * Parent span.
     *
     * @var \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    private $parent;

    /**
     * Span constructor.
     *
     * @param \OpenTracing\Span $current
     * @param \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null $parent
     */
    public function __construct(BaseSpanInterface $current, ?SpanInterface $parent = null)
    {
        $this->current = $current;
        $this->parent = $parent;
    }

    /**
     * Returns base span.
     *
     * @return \OpenTracing\Span
     */
    public function getCurrent(): BaseSpanInterface
    {
        return $this->current;
    }

    /**
     * Returns parent span.
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    public function getParent(): ?SpanInterface
    {
        return $this->parent;
    }

    /**
     * Setting base span's status.
     *
     * @param int $spanStatus
     *
     * @return void
     */
    public function setStatus(int $spanStatus): void
    {
        switch ($spanStatus) {
            case SpanStatus::STATUS_ERROR:
                $this->getCurrent()->setTag('otel.status_code', 'ERROR');
                $this->getCurrent()->setTag('otel.status_description', '');
                break;
            case SpanStatus::STATUS_OK:
                $this->getCurrent()->setTag('otel.status_code', 'OK');
                $this->getCurrent()->setTag('otel.status_description', '');
                break;
        }
    }

    /**
     * Setting base span's kind.
     *
     * @param int $spanKind
     *
     * @return void
     */
    public function setKind(int $spanKind): void
    {
        switch ($spanKind) {
            case SpanKind::KIND_CLIENT:
                $this->getCurrent()->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_RPC_CLIENT);
                break;
            case SpanKind::KIND_SERVER:
                $this->getCurrent()->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_RPC_SERVER);
                break;
            case SpanKind::KIND_CONSUMER:
                $this->getCurrent()->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_MESSAGE_BUS_CONSUMER);
                break;
            case SpanKind::KIND_PRODUCER:
                $this->getCurrent()->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_MESSAGE_BUS_PRODUCER);
                break;
        }
    }
}