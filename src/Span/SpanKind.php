<?php

namespace Romanpravda\Laravel\Tracing\Span;

class SpanKind
{
    public const KIND_CLIENT = 1;
    public const KIND_SERVER = 2;
    public const KIND_CONSUMER = 4;
    public const KIND_PRODUCER = 3;
    public const KIND_INTERNAL = 0;
}