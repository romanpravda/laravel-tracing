<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Interfaces;

use OpenTelemetry\API\Trace\SpanInterface as BaseSpanInterface;

interface SpanInterface
{
    /**
     * Returns base span.
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    public function getCurrent(): BaseSpanInterface;

    /**
     * Returns parent span.
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    public function getParent(): ?SpanInterface;
}
