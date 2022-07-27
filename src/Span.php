<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing;

use OpenTelemetry\API\Trace\SpanInterface as BaseSpanInterface;
use Romanpravda\Laravel\Tracing\Interfaces\SpanInterface;

final class Span implements SpanInterface
{
    /**
     * Span constructor.
     *
     * @param \OpenTelemetry\API\Trace\SpanInterface $current
     * @param \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null $parent
     */
    public function __construct(
        private readonly BaseSpanInterface $current,
        private readonly ?SpanInterface $parent = null,
    )
    {
    }

    /**
     * Returns base span.
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
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
}