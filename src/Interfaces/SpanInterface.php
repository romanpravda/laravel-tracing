<?php

namespace Romanpravda\Laravel\Tracing\Interfaces;

use OpenTracing\Span as BaseSpanInterface;

interface SpanInterface
{
    /**
     * Returns base span.
     *
     * @return \OpenTracing\Span
     */
    public function getCurrent(): BaseSpanInterface;

    /**
     * Returns parent span.
     *
     * @return \Romanpravda\Laravel\Tracing\Interfaces\SpanInterface|null
     */
    public function getParent(): ?SpanInterface;

    /**
     * Setting base span's status.
     *
     * @param int $spanStatus
     *
     * @return void
     */
    public function setStatus(int $spanStatus): void;

    /**
     * Setting base span's kind.
     *
     * @param int $spanKind
     *
     * @return void
     */
    public function setKind(int $spanKind): void;
}