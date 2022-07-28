<?php

namespace Romanpravda\Laravel\Tracing\Span;

class SpanStatus
{
    public const STATUS_ERROR = 0;
    public const STATUS_OK = 1;
    public const STATUS_UNSET = 2;
}